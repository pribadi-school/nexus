"""Sound output: which devices exist, and playing a file on one device.

Windows lists every sound card once per "host API" (MME, DirectSound, WASAPI,
...). We use one host API only (WASAPI when present, else MME), so each physical
device is shown once. A zone remembers its device by NAME; the index is looked
up again every time, because indexes change when devices are plugged in.

Each play runs in its own thread with its own stream, so two floors play at the
same time and a long sound never holds up another zone or the next bell."""
from __future__ import annotations

import threading
from typing import Callable, Dict, List, Optional, Sequence, Tuple

PREFERRED_HOST_APIS = ("Windows WASAPI", "MMP", "MME")


class AudioError(Exception):
    """Something the person can understand (shown in the status banner)."""


def choose_devices(devices: Sequence[dict], hostapis: Sequence[dict]) -> List[Tuple[int, str]]:
    """(index, name) of output devices of the single best host API, one per name.

    Pure function (no sound library needed), so it is unit tested."""
    names = {i: str(h.get("name", "")) for i, h in enumerate(hostapis)}
    for wanted in PREFERRED_HOST_APIS:
        indexes = [i for i, n in names.items() if n == wanted]
        if not indexes:
            continue
        seen = set()
        result: List[Tuple[int, str]] = []
        for index, device in enumerate(devices):
            if device.get("hostapi") not in indexes or int(device.get("max_output_channels", 0)) < 1:
                continue
            name = str(device.get("name", "")).strip()
            if name and name.lower() not in seen:
                seen.add(name.lower())
                result.append((index, name))
        if result:
            return result
    return []


def match_device(wanted: str, available: Sequence[Tuple[int, str]]) -> Optional[int]:
    """Finds the saved device by name. MME shortens long names to 31 characters,
    so a prefix match is accepted as a fallback."""
    if not wanted:
        return None
    wanted_l = wanted.strip().lower()
    for index, name in available:
        if name.lower() == wanted_l:
            return index
    for index, name in available:
        low = name.lower()
        if low.startswith(wanted_l) or wanted_l.startswith(low):
            return index
    return None


def _sd():
    import sounddevice  # imported late: the engine and its tests never need it
    return sounddevice


def list_output_devices() -> List[Tuple[int, str]]:
    sd = _sd()
    return choose_devices(list(sd.query_devices()), list(sd.query_hostapis()))


def find_device(name: str) -> Optional[int]:
    return match_device(name, list_output_devices())


class Playback:
    """A sound that is playing; `stop()` ends it early."""

    def __init__(self) -> None:
        self._stop = threading.Event()
        self.thread: Optional[threading.Thread] = None

    def stop(self) -> None:
        self._stop.set()

    @property
    def stopped(self) -> bool:
        return self._stop.is_set()


class Player:
    """One current sound per zone. Starting a new one on the same zone cuts the
    old one, so the next bell is never late because a long sound is still going."""

    def __init__(self, on_error: Callable[[int, str], None]):
        self._on_error = on_error
        self._current: Dict[int, Playback] = {}
        self._lock = threading.Lock()

    def play(self, zone_id: int, device_name: str, path: str, volume: int,
             on_start: Optional[Callable[[], None]] = None,
             on_cut: Optional[Callable[[], None]] = None) -> Playback:
        playback = Playback()
        with self._lock:
            old = self._current.get(zone_id)
            if old is not None and old.thread is not None and old.thread.is_alive():
                old.stop()
                if on_cut:
                    on_cut()
            self._current[zone_id] = playback
        thread = threading.Thread(target=self._run, args=(zone_id, device_name, path, volume, playback, on_start),
                                  name="bel-zona-%d" % zone_id, daemon=True)
        playback.thread = thread
        thread.start()
        return playback

    def stop_all(self) -> None:
        with self._lock:
            for playback in self._current.values():
                playback.stop()

    def _run(self, zone_id: int, device_name: str, path: str, volume: int, playback: Playback,
             on_start: Optional[Callable[[], None]] = None) -> None:
        try:
            play_file(device_name, path, volume, playback, on_start)
        except AudioError as error:
            self._on_error(zone_id, str(error))
        except Exception as error:  # a bad file or driver must never crash the app
            self._on_error(zone_id, "%s: %s" % (type(error).__name__, error))


def play_file(device_name: str, path: str, volume: int, playback: Playback,
              on_start: Optional[Callable[[], None]] = None) -> None:
    import soundfile  # MP3 and WAV
    sd = _sd()

    index = find_device(device_name)
    if index is None:
        raise AudioError("perangkat \"%s\" tidak ditemukan" % device_name)
    try:
        data, rate = soundfile.read(path, dtype="float32", always_2d=True)
    except Exception as error:
        raise AudioError("file suara tidak bisa dibaca (%s)" % error)
    if data.shape[0] == 0:
        raise AudioError("file suara kosong")

    info = sd.query_devices(index)
    channels = max(1, min(int(info["max_output_channels"]), data.shape[1], 2))
    if data.shape[1] > channels:
        data = data[:, :channels]
    gain = max(0.0, min(1.0, volume / 100.0))
    if gain < 1.0:
        data = data * gain

    extra = None
    host = sd.query_hostapis(info["hostapi"])
    if host.get("name") == "Windows WASAPI":
        try:
            extra = sd.WasapiSettings(auto_convert=True)  # lets Windows convert rate/channels
        except Exception:
            extra = None

    block = 2048
    with sd.OutputStream(samplerate=rate, device=index, channels=channels, dtype="float32",
                         extra_settings=extra) as stream:
        if on_start:
            on_start()
        position = 0
        total = data.shape[0]
        while position < total and not playback.stopped:
            stream.write(data[position:position + block])
            position += block

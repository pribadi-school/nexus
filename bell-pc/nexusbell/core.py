"""The running app without any window: the one-second clock, the sync loop,
and the players. The window (ui.py) only reads from here and calls its
methods, so a window problem can never stop a bell."""
from __future__ import annotations

import os
import threading
import time as timelib
from datetime import datetime
from typing import Any, Callable, Dict, List, Optional

from . import audio, strings
from .engine import Entry, Ring, Scheduler, Zone, zones_from_payload
from .eventlog import EventLog
from .store import Store, bundled_sounds_dir
from .syncer import NexusClient, SyncError
from . import winutil

DEFAULT_SOUND = "builtin:bel-sekolah"


class BellCore:
    def __init__(self, store: Store, clock: Callable[[], datetime] = datetime.now,
                 player: Optional[audio.Player] = None,
                 client_factory: Callable[[str, str], NexusClient] = NexusClient):
        self.store = store
        self.clock = clock
        self.events = EventLog(store.log_dir, clock)
        self.settings = store.load_settings()
        self.scheduler = Scheduler(log=self._engine_log, fired=store.load_fired())
        self.player = player or audio.Player(self._play_error)
        self._client_factory = client_factory
        self._version = ""
        self._sounds: Dict[int, Dict[str, Any]] = {}
        self._banners: Dict[str, str] = {}
        self._device_ok: Dict[int, Optional[bool]] = {}
        self.sync_message = strings.SYNC_NEVER
        self._lock = threading.RLock()
        self._stop = threading.Event()
        self._sync_now = threading.Event()
        self._threads: List[threading.Thread] = []
        self._current_names: Dict[int, tuple] = {}
        self._load_cache()

    # -- starting and stopping --------------------------------------------------
    def start(self) -> None:
        winutil.prevent_sleep()
        for target, name in ((self._tick_loop, "jam"), (self._sync_loop, "sinkron")):
            thread = threading.Thread(target=target, name=name, daemon=True)
            thread.start()
            self._threads.append(thread)

    def stop(self) -> None:
        self._stop.set()
        self._sync_now.set()
        self.player.stop_all()
        self.save_fired()
        self.events.close()
        winutil.allow_sleep()

    # -- reading state (for the window) ----------------------------------------------
    def zones(self) -> Dict[int, Zone]:
        return self.scheduler.zones()

    def banner(self) -> str:
        with self._lock:
            return "  |  ".join(self._banners.values())

    def device_state(self, zone_id: int) -> str:
        """'ok', 'missing' or 'unset'."""
        if not self.settings["devices"].get(str(zone_id)):
            return "unset"
        with self._lock:
            ok = self._device_ok.get(zone_id)
        return "missing" if ok is False else "ok"

    def status_report(self) -> Dict[str, Any]:
        zones = {}
        for zone_id, zone in self.zones().items():
            name = self.settings["devices"].get(str(zone_id), "")
            zones[str(zone_id)] = {"device": name, "ok": self.device_state(zone_id) == "ok"}
        return {"zones": zones}

    # -- settings ----------------------------------------------------------------
    def set_device(self, zone_id: int, name: str) -> None:
        with self._lock:
            self.settings["devices"][str(zone_id)] = name
        self.store.save_settings(self.settings)
        self.check_devices()

    def set_connection(self, server_url: str, token: str) -> None:
        with self._lock:
            self.settings["server_url"] = server_url.strip()
            self.settings["token"] = token.strip()
        self.store.save_settings(self.settings)
        self.sync_soon()

    def set_option(self, key: str, value: Any) -> None:
        with self._lock:
            self.settings[key] = value
        self.store.save_settings(self.settings)

    def sync_soon(self) -> None:
        self._sync_now.set()

    # -- ringing -------------------------------------------------------------------
    def tick_once(self, now: Optional[datetime] = None) -> List[Ring]:
        """One clock tick. Each due bell is started in its own thread, so this
        returns at once and the next tick is never late."""
        rings = self.scheduler.tick(now or self.clock())
        for ring in rings:
            try:
                self._ring(ring)
            except Exception as error:  # one broken bell must not stop the others
                self.events.add("failed", ring.zone_id, strings.LOG_FAILED % (ring.zone_name, ring.entry.label or "bel", error), ring.zone_name)
        if rings:
            self.save_fired()
        return rings

    def ring_now(self, zone_id: int, sound: str = DEFAULT_SOUND) -> None:
        zone = self.zones().get(zone_id)
        name = zone.name if zone else str(zone_id)
        self.events.add("info", zone_id, strings.LOG_MANUAL % (name, self.sound_name(sound)), name)
        self._play(zone_id, name, sound, zone.volume if zone else 100, "manual")

    def test_entry(self, zone_id: int, entry: Entry) -> None:
        zone = self.zones().get(zone_id)
        name = zone.name if zone else str(zone_id)
        self.events.add("info", zone_id, strings.LOG_TEST % (name, entry.label or entry.at.strftime("%H:%M:%S")), name)
        self._play(zone_id, name, entry.sound, zone.volume if zone else 100, "test")

    def test_device(self, zone_id: int) -> None:
        zone = self.zones().get(zone_id)
        name = zone.name if zone else str(zone_id)
        self.events.add("info", zone_id, strings.LOG_TEST % (name, strings.ZONE_TEST_DEVICE.lower()), name)
        self._play(zone_id, name, "builtin:denting", zone.volume if zone else 100, "test")

    def resolve_sound(self, ref: str) -> Optional[str]:
        if ref.startswith("builtin:"):
            path = os.path.join(bundled_sounds_dir(), ref[8:] + ".wav")
            return path if os.path.exists(path) else None
        if ref.startswith("sound:"):
            try:
                meta = self._sounds.get(int(ref[6:]))
            except ValueError:
                return None
            if meta:
                path = self.store.sound_path(meta["id"], meta["sha256"], meta["ext"])
                return path if os.path.exists(path) else None
        return None

    def sound_name(self, ref: str) -> str:
        if ref.startswith("builtin:"):
            return strings.BUILTIN_NAMES.get(ref[8:], ref[8:])
        try:
            meta = self._sounds.get(int(ref[6:]))
        except ValueError:
            meta = None
        return meta["name"] if meta else strings.SOUND_MISSING

    def check_devices(self) -> None:
        """Looks up each zone's saved device by name. A missing device only
        warns; the other zone keeps running."""
        try:
            available = audio.list_output_devices()
        except Exception as error:
            with self._lock:
                self._banners["audio"] = strings.BANNER_AUDIO_LIB % error
            return
        with self._lock:
            self._banners.pop("audio", None)
            for zone_id, zone in self.zones().items():
                wanted = self.settings["devices"].get(str(zone_id), "")
                if not wanted:
                    self._device_ok[zone_id] = None
                    self._banners.pop("device-%d" % zone_id, None)
                    continue
                ok = audio.match_device(wanted, available) is not None
                self._device_ok[zone_id] = ok
                if ok:
                    self._banners.pop("device-%d" % zone_id, None)
                else:
                    self._banners["device-%d" % zone_id] = strings.BANNER_DEVICE_MISSING % (zone.name, wanted)

    def save_fired(self) -> None:
        try:
            self.store.save_fired(self.scheduler.fired_keys())
        except OSError:
            pass

    # -- internals -------------------------------------------------------------------
    def _ring(self, ring: Ring) -> None:
        label = ring.entry.label or "bel"
        self._play(ring.zone_id, ring.zone_name, ring.entry.sound, ring.volume, "scheduled", label,
                   ring.scheduled.strftime("%H:%M:%S"))

    def _play(self, zone_id: int, zone_name: str, sound: str, volume: int, why: str,
              label: str = "", at: str = "") -> None:
        device = self.settings["devices"].get(str(zone_id), "")
        shown = label or self.sound_name(sound)
        if not device:
            self.events.add("failed", zone_id, strings.LOG_FAILED % (zone_name, shown, strings.ZONE_DEVICE_UNSET.lower()), zone_name)
            return
        path = self.resolve_sound(sound)
        if path is None:
            self.events.add("warning", zone_id, strings.LOG_FALLBACK % zone_name, zone_name)
            path = self.resolve_sound(DEFAULT_SOUND)
            if path is None:
                self.events.add("failed", zone_id, strings.LOG_FAILED % (zone_name, shown, "tidak ada file suara"), zone_name)
                return

        def started() -> None:
            if why == "scheduled":
                self.events.add("played", zone_id, strings.LOG_PLAYED % (at, shown, self.sound_name(sound)), zone_name)

        def cut() -> None:
            self.events.add("info", zone_id, strings.LOG_CUT % zone_name, zone_name)

        self._current_names[zone_id] = (zone_name, shown)
        self.player.play(zone_id, device, path, volume, on_start=started, on_cut=cut)

    def _play_error(self, zone_id: int, message: str) -> None:
        name, shown = self._current_names.get(zone_id, (str(zone_id), "bel"))
        self.events.add("failed", zone_id, strings.LOG_FAILED % (name, shown, message), name)
        with self._lock:
            self._banners["play-%d" % zone_id] = strings.BANNER_PLAY_FAILED % (name, message)
        threading.Timer(60.0, lambda: self._clear_banner("play-%d" % zone_id)).start()

    def _clear_banner(self, key: str) -> None:
        with self._lock:
            self._banners.pop(key, None)

    def _engine_log(self, kind: str, zone_id: Optional[int], message: str) -> None:
        zone = self.zones().get(zone_id) if zone_id is not None else None
        self.events.add(kind, zone_id, message, zone.name if zone else "")

    def _tick_loop(self) -> None:
        while not self._stop.is_set():
            try:
                self.tick_once()
            except Exception as error:  # never let the clock thread die
                self.events.add("warning", None, "Kesalahan jam: %s" % error)
            now = self.clock()
            self._stop.wait(max(0.05, 1.0 - now.microsecond / 1e6))

    def _sync_loop(self) -> None:
        last_prevent = 0.0
        while not self._stop.is_set():
            try:
                self.check_devices()
                self._sync_once()
            except Exception as error:
                self.sync_message = strings.SYNC_FAILED % error
            if timelib.monotonic() - last_prevent > 60:
                winutil.prevent_sleep()
                last_prevent = timelib.monotonic()
            self._sync_now.wait(self.settings["sync_seconds"])
            self._sync_now.clear()

    def _sync_once(self) -> None:
        client = self._client_factory(self.settings["server_url"], self.settings["token"])
        if not client.configured():
            self.sync_message = strings.SYNC_NOT_SET
            return
        events = self.events.take_pending()
        try:
            result = client.sync(self._version, self.status_report(), events)
        except SyncError as error:
            self.sync_message = strings.SYNC_REJECTED if error.rejected else strings.SYNC_FAILED % error
            return
        self.events.confirm(len(events))
        if result.changed and result.data is not None:
            self._apply(result.data, result.version, client)
        self.sync_message = strings.SYNC_OK % self.clock().strftime("%H:%M:%S")

    def _apply(self, data: Dict[str, Any], version: str, client: Optional[NexusClient], announce: bool = True) -> None:
        zones, problems = zones_from_payload(data)
        sounds = {int(s["id"]): {"id": int(s["id"]), "name": str(s["name"]), "sha256": str(s["sha256"]), "ext": str(s["ext"])}
                  for s in data.get("sounds", []) if "id" in s}
        if client is not None:
            for meta in sounds.values():
                path = self.store.sound_path(meta["id"], meta["sha256"], meta["ext"])
                if not os.path.exists(path):
                    try:
                        client.download_sound(meta["id"], meta["sha256"], path)
                    except SyncError as error:
                        problems.append("suara \"%s\": %s" % (meta["name"], error))
        self._sounds = sounds
        self.scheduler.set_zones(zones)
        self._version = version
        try:
            self.store.save_cache(version, data)
        except OSError:
            pass
        with self._lock:
            if problems:
                self._banners["schedule"] = strings.SYNC_PROBLEMS % "; ".join(problems[:3])
            else:
                self._banners.pop("schedule", None)
        if announce:
            self.events.add("info", None, "Jadwal diperbarui dari Nexus (%d lantai)" % len(zones))

    def _load_cache(self) -> None:
        cache = self.store.load_cache()
        if cache:
            try:
                self._apply(cache["data"], "", None, announce=False)  # version "" so the next sync refreshes it
                self._version = ""
            except Exception as error:
                self.events.add("warning", None, "Jadwal tersimpan tidak bisa dipakai: %s" % error)

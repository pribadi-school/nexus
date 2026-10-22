"""Tests for device choice, saved files and the wiring in BellCore, with a fake
clock and fake sound output (no real sound card is needed)."""
import json
import os
import sys
import tempfile
import threading
import time as timelib
import unittest
from datetime import date, datetime, time

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from nexusbell import audio  # noqa: E402
from nexusbell.core import BellCore  # noqa: E402
from nexusbell.engine import Entry, Zone  # noqa: E402
from nexusbell.store import Store  # noqa: E402

FRIDAY = date(2026, 10, 23)


def at(text):
    h, m, s = (int(p) for p in text.split(":"))
    return datetime.combine(FRIDAY, time(h, m, s))


class DeviceTests(unittest.TestCase):
    DEVICES = [
        {"name": "Microsoft Sound Mapper - Output", "hostapi": 0, "max_output_channels": 2},
        {"name": "Speakers (Realtek High Definition Audio)", "hostapi": 0, "max_output_channels": 2},
        {"name": "Speakers (USB Audio Device)", "hostapi": 0, "max_output_channels": 2},
        {"name": "Microphone (Realtek)", "hostapi": 0, "max_input_channels": 2, "max_output_channels": 0},
        {"name": "Speakers (Realtek High Definition Audio)", "hostapi": 1, "max_output_channels": 2},
        {"name": "Speakers (USB Audio Device)", "hostapi": 1, "max_output_channels": 2},
        {"name": "Speakers (Realtek High Definition Audio)", "hostapi": 2, "max_output_channels": 2},
        {"name": "Speakers (USB Audio Device)", "hostapi": 2, "max_output_channels": 2},
    ]
    HOSTAPIS = [{"name": "MME"}, {"name": "Windows DirectSound"}, {"name": "Windows WASAPI"}]

    def test_each_physical_device_is_listed_once_from_a_single_host_api(self):
        names = [name for _i, name in audio.choose_devices(self.DEVICES, self.HOSTAPIS)]
        self.assertEqual(names, ["Speakers (Realtek High Definition Audio)", "Speakers (USB Audio Device)"])

    def test_falls_back_to_mme_when_wasapi_is_missing(self):
        names = [name for _i, name in audio.choose_devices(self.DEVICES[:4], [{"name": "MME"}])]
        self.assertEqual(len(names), 3)
        self.assertNotIn("Microphone (Realtek)", names)

    def test_device_is_found_by_name_not_by_a_saved_index(self):
        before = audio.choose_devices(self.DEVICES, self.HOSTAPIS)
        shuffled = list(reversed(self.DEVICES))  # indexes change when hardware changes
        after = audio.choose_devices(shuffled, self.HOSTAPIS)
        wanted = "Speakers (USB Audio Device)"
        old_index = audio.match_device(wanted, before)
        new_index = audio.match_device(wanted, after)
        self.assertNotEqual(old_index, new_index)
        self.assertEqual(shuffled[new_index]["name"], wanted)

    def test_missing_device_gives_none(self):
        available = audio.choose_devices(self.DEVICES, self.HOSTAPIS)
        self.assertIsNone(audio.match_device("Speakers (Unplugged Card)", available))
        self.assertIsNone(audio.match_device("", available))

    def test_shortened_mme_names_still_match(self):
        available = [(4, "Speakers (Realtek High Definition Audio)")]
        self.assertEqual(audio.match_device("Speakers (Realtek High Definiti", available), 4)


class StoreTests(unittest.TestCase):
    def test_damaged_settings_fall_back_to_defaults_and_are_kept_aside(self):
        with tempfile.TemporaryDirectory() as folder:
            problems = []
            store = Store(folder, on_problem=problems.append)
            with open(store.settings_path, "w", encoding="utf-8") as handle:
                handle.write("{ not json")
            settings = store.load_settings()
            self.assertEqual(settings["devices"], {})
            self.assertTrue(os.path.exists(store.settings_path + ".rusak"))
            self.assertEqual(len(problems), 1)

    def test_settings_are_saved_as_readable_json_and_devices_by_name(self):
        with tempfile.TemporaryDirectory() as folder:
            store = Store(folder)
            settings = store.load_settings()
            settings["devices"]["1"] = "Speakers (USB Audio Device)"
            store.save_settings(settings)
            text = open(store.settings_path, encoding="utf-8").read()
            self.assertIn("Speakers (USB Audio Device)", text)
            self.assertEqual(store.load_settings()["devices"], {"1": "Speakers (USB Audio Device)"})
            self.assertEqual(json.loads(text)["autostart"], True)

    def test_wrong_types_in_settings_are_ignored(self):
        with tempfile.TemporaryDirectory() as folder:
            store = Store(folder)
            with open(store.settings_path, "w", encoding="utf-8") as handle:
                json.dump({"sync_seconds": "fast", "devices": "oops", "night_mode": True}, handle)
            settings = store.load_settings()
            self.assertEqual(settings["sync_seconds"], 30)
            self.assertEqual(settings["devices"], {})
            self.assertTrue(settings["night_mode"])


class FakePlayer:
    """Records what would be played; can pretend a device is missing."""

    def __init__(self, missing=()):
        self.calls = []
        self.missing = set(missing)

    def play(self, zone_id, device, path, volume, on_start=None, on_cut=None):
        self.calls.append({"zone": zone_id, "device": device, "path": os.path.basename(path), "volume": volume})
        if device not in self.missing and on_start:
            on_start()

    def stop_all(self):
        pass


def payload(zones):
    return {"zones": zones, "sounds": [], "builtin": []}


def zone_payload(zone_id, name, entries, volume=100, holidays=()):
    return {"id": zone_id, "name": name, "color": "blue", "volume": volume, "profile": {"id": 1, "name": "Normal"},
            "holidays": [{"date": h, "label": ""} for h in holidays], "entries": entries}


def row(entry_id, time_text, sound="builtin:bel-sekolah", label="", days=(1, 2, 3, 4, 5)):
    return {"id": entry_id, "time": time_text, "days": list(days), "sound": sound, "label": label, "enabled": True, "shared_from": None}


class CoreTests(unittest.TestCase):
    def make(self, player=None, devices=None):
        self.folder = tempfile.TemporaryDirectory()
        self.addCleanup(self.folder.cleanup)
        store = Store(self.folder.name)
        settings = store.load_settings()
        settings["devices"] = devices if devices is not None else {"1": "Out A", "2": "Out B"}
        store.save_settings(settings)
        self.now = at("06:59:50")
        core = BellCore(store, clock=lambda: self.now, player=player or FakePlayer())
        self.addCleanup(core.events.close)  # runs before the folder is deleted
        core._apply(payload([
            zone_payload(1, "Lantai 2", [row(1, "07:00:00", label="Masuk")], volume=70),
            zone_payload(2, "Lantai 3", [row(1, "07:00:00", sound="builtin:denting", label="Masuk")], volume=40),
        ]), "v1", None)
        return core

    def test_two_floors_ring_at_the_same_second_each_on_its_own_device(self):
        player = FakePlayer()
        core = self.make(player)
        core.tick_once(at("06:59:59"))
        rings = core.tick_once(at("07:00:00"))
        self.assertEqual(len(rings), 2)
        by_zone = {c["zone"]: c for c in player.calls}
        self.assertEqual(by_zone[1], {"zone": 1, "device": "Out A", "path": "bel-sekolah.wav", "volume": 70})
        self.assertEqual(by_zone[2], {"zone": 2, "device": "Out B", "path": "denting.wav", "volume": 40})

    def test_log_lines_name_the_zone(self):
        core = self.make()
        core.tick_once(at("06:59:59"))
        core.tick_once(at("07:00:00"))
        text = "\n".join(core.events.recent())
        self.assertIn("Lantai 2", text)
        self.assertIn("Lantai 3", text)
        self.assertEqual([e["kind"] for e in core.events.take_pending() if e["kind"] == "played"], ["played", "played"])

    def test_a_missing_device_warns_but_the_other_floor_keeps_ringing(self):
        original = audio.list_output_devices
        audio.list_output_devices = lambda: [(0, "Out A")]  # Out B is unplugged
        self.addCleanup(setattr, audio, "list_output_devices", original)
        player = FakePlayer(missing={"Out B"})
        core = self.make(player)
        core.check_devices()
        self.assertIn("Lantai 3", core.banner())
        self.assertEqual(core.device_state(1), "ok")
        self.assertEqual(core.device_state(2), "missing")
        core.tick_once(at("06:59:59"))
        core.tick_once(at("07:00:00"))
        played = [e for e in core.events.take_pending() if e["kind"] == "played"]
        self.assertEqual([e["zone"] for e in played], [1])  # floor 2's own player reports the failure
        self.assertEqual({c["zone"] for c in player.calls}, {1, 2})

    def test_unset_device_is_logged_as_failed_and_does_not_crash(self):
        core = self.make(FakePlayer(), devices={"1": "Out A"})
        core.tick_once(at("06:59:59"))
        rings = core.tick_once(at("07:00:00"))
        self.assertEqual(len(rings), 2)
        kinds = [e["kind"] for e in core.events.take_pending()]
        self.assertIn("failed", kinds)

    def test_a_sound_missing_on_disk_falls_back_to_the_built_in_bell(self):
        player = FakePlayer()
        core = self.make(player)
        core._apply(payload([zone_payload(1, "Lantai 2", [row(1, "07:00:00", sound="sound:9")])]), "v2", None)
        core.tick_once(at("06:59:59"))
        core.tick_once(at("07:00:00"))
        self.assertEqual(player.calls[0]["path"], "bel-sekolah.wav")
        self.assertIn("warning", [e["kind"] for e in core.events.take_pending()])

    def test_bad_zone_player_error_never_raises_out_of_tick(self):
        class Exploding(FakePlayer):
            def play(self, *args, **kwargs):
                raise RuntimeError("driver crashed")

        core = self.make(Exploding())
        core.tick_once(at("06:59:59"))
        core.tick_once(at("07:00:00"))  # must not raise
        self.assertIn("failed", [e["kind"] for e in core.events.take_pending()])

    def test_schedule_is_kept_for_the_next_start_and_fired_bells_are_remembered(self):
        core = self.make()
        core.store.save_cache("v1", payload([zone_payload(1, "Lantai 2", [row(1, "07:00:00")])]))
        core.tick_once(at("06:59:59"))
        core.tick_once(at("07:00:00"))
        again = BellCore(core.store, clock=lambda: at("07:00:05"), player=FakePlayer())
        self.addCleanup(again.events.close)
        self.assertEqual(list(again.zones()), [1])  # loaded from the saved copy, with no network
        self.assertEqual(again.tick_once(at("07:00:05")), [])  # and it does not ring the same bell again


class ParallelPlaybackTests(unittest.TestCase):
    """A long sound on one floor must never delay the other floor or the next bell."""

    def test_zones_play_at_the_same_time_and_a_new_bell_cuts_the_old_one(self):
        started = {1: threading.Event(), 2: threading.Event(), "1b": threading.Event()}
        release = threading.Event()
        cut_flags = []

        def fake_play_file(device, path, volume, playback, on_start=None):
            key = {"Out A": 1, "Out B": 2}[device]
            if key == 1 and started[1].is_set():
                key = "1b"
            started[key].set()
            if on_start:
                on_start()
            if key == 1:  # the long sound: runs until it is cut
                while not playback.stopped and not release.is_set():
                    timelib.sleep(0.01)
                cut_flags.append(playback.stopped)

        original = audio.play_file
        audio.play_file = fake_play_file
        self.addCleanup(setattr, audio, "play_file", original)
        player = audio.Player(lambda zone, message: None)
        player.play(1, "Out A", "x", 100)
        self.assertTrue(started[1].wait(2))
        player.play(2, "Out B", "x", 100)  # floor 2 starts while floor 1 is still playing
        self.assertTrue(started[2].wait(2))
        player.play(1, "Out A", "x", 100)  # the next bell on floor 1 starts at once, cutting the long one
        self.assertTrue(started["1b"].wait(2))
        timelib.sleep(0.1)
        release.set()
        timelib.sleep(0.1)
        self.assertEqual(cut_flags[0], True)


if __name__ == "__main__":
    unittest.main()

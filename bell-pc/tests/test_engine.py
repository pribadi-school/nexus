"""Engine tests with a fake clock. No sound, network or window is involved."""
import os
import sys
import unittest
from datetime import date, datetime, time, timedelta

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from nexusbell.engine import GRACE_SECONDS, Entry, Scheduler, Zone, zones_from_payload  # noqa: E402

FRIDAY = date(2026, 10, 23)  # a Friday
MONDAY = date(2026, 10, 26)
WEEKDAYS = frozenset({1, 2, 3, 4, 5})


class FakeClock:
    def __init__(self, start: datetime):
        self.now = start

    def set(self, text: str, day: date = FRIDAY) -> datetime:
        h, m, s = (int(p) for p in text.split(":"))
        self.now = datetime.combine(day, time(h, m, s))
        return self.now

    def advance(self, seconds: float) -> datetime:
        self.now += timedelta(seconds=seconds)
        return self.now


def entry(entry_id, at, days=WEEKDAYS, sound="builtin:bel-sekolah", label="", enabled=True, shared_from=None):
    h, m, s = (int(p) for p in at.split(":"))
    return Entry(entry_id, time(h, m, s), frozenset(days), sound, label or at, enabled, shared_from)


def zone(zone_id, name, entries, holidays=(), volume=100, profile="Normal"):
    return Zone(zone_id, name, "blue", volume, profile, frozenset(holidays), tuple(entries))


class Log:
    def __init__(self):
        self.lines = []

    def __call__(self, kind, zone_id, message):
        self.lines.append((kind, zone_id, message))

    def kinds(self):
        return [k for k, _z, _m in self.lines]


def run(scheduler, clock, start, end):
    """Ticks once per second from start to end; returns every Ring."""
    rings = []
    clock.set(start)
    stop = datetime.combine(clock.now.date(), time(*[int(p) for p in end.split(":")]))
    while clock.now <= stop:
        rings.extend(scheduler.tick(clock.now))
        clock.advance(1)
    return rings


class RingingTests(unittest.TestCase):
    def setUp(self):
        self.clock = FakeClock(datetime.combine(FRIDAY, time(6, 0)))
        self.log = Log()
        self.scheduler = Scheduler(log=self.log)

    def test_bell_rings_once_at_its_time(self):
        self.scheduler.set_zones({1: zone(1, "Lantai 2", [entry(1, "07:00:00")])})
        rings = run(self.scheduler, self.clock, "06:59:50", "07:01:30")
        self.assertEqual(len(rings), 1)
        self.assertEqual(rings[0].scheduled, datetime.combine(FRIDAY, time(7, 0, 0)))

    def test_no_double_fire_across_many_ticks_and_repeated_second(self):
        self.scheduler.set_zones({1: zone(1, "Lantai 2", [entry(1, "07:00:00")])})
        self.clock.set("07:00:00")
        first = self.scheduler.tick(self.clock.now)
        again = self.scheduler.tick(self.clock.now)  # same second, e.g. a hiccup
        later = self.scheduler.tick(self.clock.advance(5))
        self.assertEqual((len(first), len(again), len(later)), (1, 0, 0))

    def test_no_double_fire_after_restart_when_fired_keys_are_kept(self):
        self.scheduler.set_zones({1: zone(1, "Lantai 2", [entry(1, "07:00:00")])})
        self.clock.set("07:00:01")
        self.assertEqual(len(self.scheduler.tick(self.clock.now)), 1)
        restarted = Scheduler(log=self.log, fired=self.scheduler.fired_keys())  # the app was restarted
        restarted.set_zones({1: zone(1, "Lantai 2", [entry(1, "07:00:00")])})
        self.clock.set("07:00:10")
        self.assertEqual(restarted.tick(self.clock.now), [])

    def test_two_zones_ring_in_the_same_second(self):
        self.scheduler.set_zones({
            1: zone(1, "Lantai 2", [entry(1, "07:00:00")]),
            2: zone(2, "Lantai 3", [entry(1, "07:00:00")]),  # same entry id on purpose: keys include the zone
        })
        self.clock.set("07:00:00")
        rings = self.scheduler.tick(self.clock.now)
        self.assertEqual([(r.zone_id, r.zone_name) for r in rings], [(1, "Lantai 2"), (2, "Lantai 3")])

    def test_disabled_rows_do_not_ring(self):
        self.scheduler.set_zones({1: zone(1, "Lantai 2", [entry(1, "07:00:00", enabled=False)])})
        self.assertEqual(run(self.scheduler, self.clock, "06:59:55", "07:00:10"), [])

    def test_rows_only_ring_on_their_days(self):
        self.scheduler.set_zones({1: zone(1, "Lantai 2", [entry(1, "07:00:00", days={1, 2, 3, 4})])})  # Mon-Thu
        self.clock.set("07:00:00", FRIDAY)
        self.assertEqual(self.scheduler.tick(self.clock.now), [])
        self.clock.set("07:00:00", MONDAY)
        self.assertEqual(len(self.scheduler.tick(self.clock.now)), 1)

    def test_volume_travels_with_the_ring(self):
        self.scheduler.set_zones({1: zone(1, "Lantai 2", [entry(1, "07:00:00")], volume=40)})
        self.clock.set("07:00:00")
        self.assertEqual(self.scheduler.tick(self.clock.now)[0].volume, 40)

    def test_bell_that_crosses_midnight_in_a_gap_is_handled(self):
        self.scheduler.set_zones({1: zone(1, "Lantai 2", [entry(1, "23:59:50", days=range(1, 8))])})
        self.clock.set("23:59:40")
        self.scheduler.tick(self.clock.now)
        self.clock.set("00:00:05", FRIDAY + timedelta(days=1))  # first tick after midnight
        rings = self.scheduler.tick(self.clock.now)
        self.assertEqual(len(rings), 1)  # 15 seconds late: inside the grace window


class GraceWindowTests(unittest.TestCase):
    def setUp(self):
        self.clock = FakeClock(datetime.combine(FRIDAY, time(6, 0)))
        self.log = Log()
        self.scheduler = Scheduler(log=self.log)
        self.scheduler.set_zones({1: zone(1, "Lantai 2", [entry(1, "07:00:00", label="Masuk")])})

    def test_late_but_inside_grace_still_rings(self):
        self.clock.set("06:59:59")
        self.scheduler.tick(self.clock.now)
        self.clock.set("07:00:%02d" % GRACE_SECONDS)  # exactly the limit
        self.assertEqual(len(self.scheduler.tick(self.clock.now)), 1)

    def test_late_beyond_grace_is_skipped_and_logged(self):
        self.clock.set("06:59:59")
        self.scheduler.tick(self.clock.now)
        self.clock.set("07:00:%02d" % (GRACE_SECONDS + 1))  # the app was blocked
        self.assertEqual(self.scheduler.tick(self.clock.now), [])
        self.assertEqual(self.log.kinds(), ["skipped"])
        self.assertIn("Masuk", self.log.lines[0][2])
        self.assertEqual(self.log.lines[0][1], 1)  # the log names the zone

    def test_skipped_bell_is_logged_once_and_never_rings_later(self):
        self.clock.set("06:59:59")
        self.scheduler.tick(self.clock.now)
        self.clock.set("07:05:00")
        self.scheduler.tick(self.clock.now)
        self.scheduler.tick(self.clock.advance(1))
        self.assertEqual(self.log.kinds(), ["skipped"])

    def test_starting_the_app_late_does_not_flood_the_log_with_old_bells(self):
        self.clock.set("12:00:00")  # first tick ever: older bells are history, not "skipped"
        self.assertEqual(self.scheduler.tick(self.clock.now), [])
        self.assertEqual(self.log.lines, [])

    def test_starting_inside_the_grace_window_rings(self):
        self.clock.set("07:00:12")
        self.assertEqual(len(self.scheduler.tick(self.clock.now)), 1)

    def test_a_pc_that_slept_through_several_bells_logs_each_skip(self):
        self.scheduler.set_zones({1: zone(1, "Lantai 2", [entry(1, "07:00:00"), entry(2, "08:00:00")])})
        self.clock.set("06:59:59")
        self.scheduler.tick(self.clock.now)
        self.clock.set("09:00:00")
        self.assertEqual(self.scheduler.tick(self.clock.now), [])
        self.assertEqual(self.log.kinds(), ["skipped", "skipped"])


class IndependentZonesTests(unittest.TestCase):
    """Each zone has its own profile, days, holidays and volume."""

    def setUp(self):
        self.clock = FakeClock(datetime.combine(FRIDAY, time(6, 0)))
        self.log = Log()
        self.scheduler = Scheduler(log=self.log)

    def test_each_zone_follows_its_own_profile_days(self):
        # Lantai 2 rings Mon-Fri; Lantai 3 uses a "Senin-Kamis" profile.
        self.scheduler.set_zones({
            1: zone(1, "Lantai 2", [entry(1, "07:00:00", days={1, 2, 3, 4, 5})], profile="Normal"),
            2: zone(2, "Lantai 3", [entry(1, "07:00:00", days={1, 2, 3, 4})], profile="Pendek"),
        })
        self.clock.set("07:00:00", FRIDAY)
        self.assertEqual([r.zone_id for r in self.scheduler.tick(self.clock.now)], [1])

    def test_a_holiday_on_one_zone_does_not_silence_the_other(self):
        self.scheduler.set_zones({
            1: zone(1, "Lantai 2", [entry(1, "07:00:00")], holidays=[FRIDAY]),
            2: zone(2, "Lantai 3", [entry(1, "07:00:00")]),
        })
        self.clock.set("07:00:00")
        self.assertEqual([r.zone_id for r in self.scheduler.tick(self.clock.now)], [2])
        self.assertIn("info", self.log.kinds())  # "hari libur" is written to the log

    def test_switching_the_profile_of_one_zone_never_changes_the_other(self):
        floor3 = zone(2, "Lantai 3", [entry(1, "07:00:00"), entry(2, "10:00:00")], profile="Normal")
        self.scheduler.set_zones({1: zone(1, "Lantai 2", [entry(1, "07:00:00")], profile="Normal"), 2: floor3})
        before = self.scheduler.zones()[2]
        # Nexus switches only Lantai 2 to the "Ujian" profile (different bells).
        self.scheduler.set_zones({1: zone(1, "Lantai 2", [entry(7, "07:30:00")], profile="Ujian"), 2: floor3})
        after = self.scheduler.zones()
        self.assertIs(after[2], before)
        self.assertEqual(after[1].profile, "Ujian")
        self.clock.set("07:00:00")
        self.assertEqual([r.zone_id for r in self.scheduler.tick(self.clock.now)], [2])  # Lantai 2 no longer rings at 07:00
        self.assertEqual(self.scheduler.next_for(2, self.clock.now)[1].id, 2)

    def test_editing_one_zones_schedule_leaves_the_other_untouched(self):
        floor2 = zone(1, "Lantai 2", [entry(1, "07:00:00")])
        floor3 = zone(2, "Lantai 3", [entry(1, "09:00:00")])
        self.scheduler.set_zones({1: floor2, 2: floor3})
        self.scheduler.set_zones({1: zone(1, "Lantai 2", []), 2: floor3})
        self.assertEqual(self.scheduler.zones()[2].entries, floor3.entries)
        self.clock.set("09:00:00")
        self.assertEqual([r.zone_id for r in self.scheduler.tick(self.clock.now)], [2])

    def test_each_zone_has_its_own_volume(self):
        self.scheduler.set_zones({
            1: zone(1, "Lantai 2", [entry(1, "07:00:00")], volume=100),
            2: zone(2, "Lantai 3", [entry(1, "07:00:00")], volume=30),
        })
        self.clock.set("07:00:00")
        self.assertEqual({r.zone_id: r.volume for r in self.scheduler.tick(self.clock.now)}, {1: 100, 2: 30})

    def test_school_wide_bell_rings_on_both_floors_once_each(self):
        # Nexus sends a "both floors" row to the other zone with shared_from set.
        self.scheduler.set_zones({
            1: zone(1, "Lantai 2", [entry(5, "09:30:00")]),
            2: zone(2, "Lantai 3", [entry(5, "09:30:00", shared_from="Lantai 2")]),
        })
        self.clock.set("09:30:00")
        rings = self.scheduler.tick(self.clock.now)
        self.assertEqual([r.zone_id for r in rings], [1, 2])
        self.assertEqual(self.scheduler.tick(self.clock.advance(1)), [])


class NextBellTests(unittest.TestCase):
    def test_next_bell_skips_switched_off_rows_weekends_and_holidays(self):
        scheduler = Scheduler()
        scheduler.set_zones({1: zone(1, "Lantai 2", [
            entry(1, "07:00:00"), entry(2, "08:00:00", enabled=False), entry(3, "09:00:00"),
        ], holidays=[MONDAY])})
        friday_noon = datetime.combine(FRIDAY, time(12, 0))
        when, row = scheduler.next_for(1, friday_noon)
        self.assertEqual(when, datetime.combine(MONDAY + timedelta(days=1), time(7, 0)))  # Monday is a holiday
        self.assertEqual(row.id, 1)
        self.assertEqual(scheduler.next_for(1, datetime.combine(FRIDAY, time(7, 30))), (datetime.combine(FRIDAY, time(9, 0)), scheduler.zones()[1].entries[2]))

    def test_no_zone_no_next(self):
        self.assertIsNone(Scheduler().next_for(9, datetime.combine(FRIDAY, time(7, 0))))


class PayloadTests(unittest.TestCase):
    def test_zones_are_read_and_bad_rows_are_skipped_not_fatal(self):
        payload = {"zones": [
            {"id": 1, "name": "Lantai 2", "color": "blue", "volume": 80, "profile": {"id": 3, "name": "Normal"},
             "holidays": [{"date": "2026-12-25", "label": "Natal"}, {"date": "oops"}],
             "entries": [
                 {"id": 1, "time": "07:00:00", "days": [1, 2, 3], "sound": "builtin:denting", "label": "A", "enabled": True, "shared_from": None},
                 {"id": 2, "time": "99:99", "days": [1], "sound": "x"},
                 {"id": 3, "time": "08:00:00", "days": [], "sound": "x"},
             ]},
            {"name": "broken zone without id"},
        ]}
        zones, problems = zones_from_payload(payload)
        self.assertEqual(list(zones), [1])
        self.assertEqual(len(zones[1].entries), 1)
        self.assertEqual(zones[1].holidays, frozenset({date(2026, 12, 25)}))
        self.assertEqual(zones[1].volume, 80)
        self.assertEqual(zones[1].profile, "Normal")
        self.assertEqual(len(problems), 4)

    def test_volume_is_clamped(self):
        zones, _ = zones_from_payload({"zones": [{"id": 1, "name": "A", "volume": 500, "entries": []}]})
        self.assertEqual(zones[1].volume, 100)


if __name__ == "__main__":
    unittest.main()

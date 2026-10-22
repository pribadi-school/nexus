"""The bell engine: decides which bell rings when. Pure logic (no sound, no
network, no screen), so it can be tested with a fake clock.

Rules it guarantees:
* Each zone (floor) has its own entries, holidays and volume. A change to one
  zone's data never changes another's.
* Every tick (once a second) returns the bells that are due. Two zones that
  are due in the same second both come back from the same tick, and the
  caller plays them in parallel.
* A bell rings at most once: its key (zone, entry, scheduled time) goes into a
  "fired" set.
* Late bells: within GRACE_SECONDS of the scheduled time it still rings;
  later than that it is skipped and written to the log.
"""
from __future__ import annotations

import threading
from dataclasses import dataclass
from datetime import date, datetime, time, timedelta
from typing import Callable, Dict, Iterable, List, Optional, Tuple

GRACE_SECONDS = 30

LogFn = Callable[[str, Optional[int], str], None]


@dataclass(frozen=True)
class Entry:
    id: int
    at: time
    days: frozenset  # ISO weekdays, 1 = Monday ... 7 = Sunday
    sound: str  # "builtin:<key>" or "sound:<id>"
    label: str = ""
    enabled: bool = True
    shared_from: Optional[str] = None  # name of the floor a school-wide bell comes from


@dataclass(frozen=True)
class Zone:
    id: int
    name: str
    color: str = "blue"
    volume: int = 100
    profile: Optional[str] = None
    holidays: frozenset = frozenset()  # of datetime.date
    entries: Tuple[Entry, ...] = ()


@dataclass(frozen=True)
class Ring:
    zone_id: int
    zone_name: str
    entry: Entry
    scheduled: datetime
    volume: int


def parse_time(text: str) -> time:
    parts = [int(p) for p in str(text).split(":")]
    while len(parts) < 3:
        parts.append(0)
    return time(parts[0], parts[1], parts[2])


def zones_from_payload(data: dict) -> Tuple[Dict[int, Zone], List[str]]:
    """Reads the schedule sent by Nexus. A bad row is skipped (and reported in
    the returned problems) instead of breaking the whole schedule."""
    zones: Dict[int, Zone] = {}
    problems: List[str] = []
    for raw in data.get("zones", []):
        try:
            zone_id = int(raw["id"])
            entries = []
            for row in raw.get("entries", []):
                try:
                    days = frozenset(int(d) for d in row["days"] if 1 <= int(d) <= 7)
                    if not days:
                        raise ValueError("no days")
                    entries.append(Entry(
                        id=int(row["id"]), at=parse_time(row["time"]), days=days, sound=str(row["sound"]),
                        label=str(row.get("label", "")), enabled=bool(row.get("enabled", True)),
                        shared_from=row.get("shared_from") or None,
                    ))
                except (KeyError, ValueError, TypeError) as error:
                    problems.append("%s: baris bel tidak terbaca (%s)" % (raw.get("name", zone_id), error))
            holidays = set()
            for h in raw.get("holidays", []):
                try:
                    holidays.add(date.fromisoformat(str(h["date"])))
                except (KeyError, ValueError, TypeError):
                    problems.append("%s: tanggal libur tidak terbaca" % raw.get("name", zone_id))
            profile = raw.get("profile") or {}
            zones[zone_id] = Zone(
                id=zone_id, name=str(raw["name"]), color=str(raw.get("color", "blue")),
                volume=max(0, min(100, int(raw.get("volume", 100)))), profile=profile.get("name"),
                holidays=frozenset(holidays), entries=tuple(sorted(entries, key=lambda e: (e.at, e.id))),
            )
        except (KeyError, ValueError, TypeError) as error:
            problems.append("Satu lantai tidak terbaca (%s)" % error)
    return zones, problems


def _key(zone_id: int, entry_id: int, scheduled: datetime) -> Tuple[int, int, str]:
    return (zone_id, entry_id, scheduled.strftime("%Y-%m-%d %H:%M:%S"))


class Scheduler:
    def __init__(self, log: Optional[LogFn] = None, grace: int = GRACE_SECONDS,
                 fired: Optional[Iterable[str]] = None):
        self._log = log or (lambda kind, zone_id, message: None)
        self._grace = grace
        self._zones: Dict[int, Zone] = {}
        self._fired = set()
        for text in fired or ():
            try:
                zone_id, entry_id, when = str(text).split("|", 2)
                self._fired.add((int(zone_id), int(entry_id), when))
            except ValueError:
                continue
        self._last: Optional[datetime] = None
        self._holiday_logged = set()
        self._lock = threading.Lock()

    # -- data -----------------------------------------------------------------
    def set_zones(self, zones: Dict[int, Zone]) -> None:
        with self._lock:
            self._zones = dict(zones)

    def zones(self) -> Dict[int, Zone]:
        with self._lock:
            return dict(self._zones)

    def fired_keys(self) -> List[str]:
        with self._lock:
            return sorted("%d|%d|%s" % key for key in self._fired)

    # -- the clock ------------------------------------------------------------
    def tick(self, now: datetime) -> List[Ring]:
        """Bells that are due at `now`. Call it about once a second."""
        with self._lock:
            last = self._last
            self._last = now
            if last is not None and now < last:
                last = None  # the PC clock was set back; do not guess about the gap

            first_day = (min(last, now - timedelta(seconds=self._grace)) if last else now - timedelta(seconds=self._grace)).date()
            first_day = max(first_day, now.date() - timedelta(days=3))
            days: List[date] = []
            day = first_day
            while day <= now.date():
                days.append(day)
                day += timedelta(days=1)

            rings: List[Ring] = []
            for zone in sorted(self._zones.values(), key=lambda z: z.id):
                for day in days:
                    if day in zone.holidays:
                        self._note_holiday(zone, day, now)
                        continue
                    weekday = day.isoweekday()
                    for entry in zone.entries:
                        if not entry.enabled or weekday not in entry.days:
                            continue
                        scheduled = datetime.combine(day, entry.at)
                        age = (now - scheduled).total_seconds()
                        if age < 0:
                            continue
                        key = _key(zone.id, entry.id, scheduled)
                        if key in self._fired:
                            continue
                        if age <= self._grace:
                            self._fired.add(key)
                            rings.append(Ring(zone.id, zone.name, entry, scheduled, zone.volume))
                        elif last is not None and scheduled > last:
                            # It came due while nothing was ticking (the app was blocked or the PC slept).
                            self._fired.add(key)
                            self._log("skipped", zone.id, "%s %s dilewati: terlambat %d detik (batas %d detik)" % (
                                scheduled.strftime("%H:%M:%S"), entry.label or "bel", int(age), self._grace))
            self._prune(now)
            rings.sort(key=lambda r: (r.scheduled, r.zone_id))
            return rings

    def next_for(self, zone_id: int, now: datetime) -> Optional[Tuple[datetime, Entry]]:
        """The next bell of a zone after `now` (skips holidays and switched-off rows)."""
        with self._lock:
            zone = self._zones.get(zone_id)
            if zone is None:
                return None
            best: Optional[Tuple[datetime, Entry]] = None
            for ahead in range(0, 9):
                day = now.date() + timedelta(days=ahead)
                if day in zone.holidays:
                    continue
                for entry in zone.entries:
                    if not entry.enabled or day.isoweekday() not in entry.days:
                        continue
                    scheduled = datetime.combine(day, entry.at)
                    if scheduled > now and (best is None or scheduled < best[0]):
                        best = (scheduled, entry)
                if best is not None:
                    break
            return best

    # -- internals ------------------------------------------------------------
    def _note_holiday(self, zone: Zone, day: date, now: datetime) -> None:
        if day == now.date() and (zone.id, day) not in self._holiday_logged:
            self._holiday_logged.add((zone.id, day))
            self._log("info", zone.id, "Hari libur (%s): tidak ada bel hari ini" % day.isoformat())

    def _prune(self, now: datetime) -> None:
        limit = (now - timedelta(days=4)).strftime("%Y-%m-%d")
        self._fired = {key for key in self._fired if key[2][:10] >= limit}
        self._holiday_logged = {item for item in self._holiday_logged if item[1] >= now.date() - timedelta(days=1)}

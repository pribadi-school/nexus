"""The bell diary: a daily rotating log file (one line per bell played, skipped
or failed, with the zone name), plus a short queue of the same events that the
syncer reports to Nexus."""
from __future__ import annotations

import logging
import logging.handlers
import os
import threading
from collections import deque
from datetime import datetime
from typing import Callable, Deque, Dict, List, Optional

KINDS = ("played", "skipped", "failed", "warning", "info")


class EventLog:
    def __init__(self, folder: str, clock: Callable[[], datetime] = datetime.now):
        self._clock = clock
        self._lock = threading.Lock()
        self._pending: Deque[Dict] = deque(maxlen=500)
        self._recent: Deque[str] = deque(maxlen=300)
        self._listeners: List[Callable[[str], None]] = []
        self._logger = logging.getLogger("nexusbell")
        self._logger.setLevel(logging.INFO)
        self._logger.propagate = False
        self.close()  # a new log replaces the previous one (tests, restarts)
        try:
            os.makedirs(folder, exist_ok=True)
            handler = logging.handlers.TimedRotatingFileHandler(
                os.path.join(folder, "bell.log"), when="midnight", backupCount=60, encoding="utf-8", delay=True)
            handler.setFormatter(logging.Formatter("%(asctime)s %(message)s", "%Y-%m-%d %H:%M:%S"))
            self._logger.handlers = [handler]
        except OSError:
            self._logger.handlers = [logging.NullHandler()]  # a read-only folder must not stop the bells

    def close(self) -> None:
        """Releases the log file (Windows will not delete or move an open file)."""
        for handler in list(self._logger.handlers):
            try:
                handler.close()
            except Exception:
                pass
            self._logger.removeHandler(handler)

    def add_listener(self, callback: Callable[[str], None]) -> None:
        self._listeners.append(callback)

    def add(self, kind: str, zone_id: Optional[int], message: str, zone_name: str = "") -> None:
        """Never raises: logging a bell must not be able to stop the next one."""
        try:
            kind = kind if kind in KINDS else "info"
            now = self._clock()
            line = "[%s] %s%s" % (kind.upper(), ("%s: " % zone_name) if zone_name else "", message)
            self._logger.info(line)
            with self._lock:
                self._pending.append({"at": now.strftime("%Y-%m-%d %H:%M:%S"), "kind": kind, "zone": zone_id, "msg": message[:290]})
                self._recent.append("%s  %s" % (now.strftime("%H:%M:%S"), line))
            for callback in list(self._listeners):
                try:
                    callback(line)
                except Exception:
                    pass
        except Exception:
            pass

    def recent(self) -> List[str]:
        with self._lock:
            return list(self._recent)

    def take_pending(self, limit: int = 100) -> List[Dict]:
        """Events not reported yet (they stay queued until `confirm` is called)."""
        with self._lock:
            return list(self._pending)[:limit]

    def confirm(self, count: int) -> None:
        with self._lock:
            for _ in range(min(count, len(self._pending))):
                self._pending.popleft()

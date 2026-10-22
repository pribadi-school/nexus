"""Files next to the program: readable settings (pengaturan.json), the last
schedule downloaded from Nexus (jadwal-cache.json), downloaded sounds, and a
small state file that remembers which bells already rang today.

Every read tolerates a missing or damaged file: the app starts with defaults,
keeps the damaged file as *.rusak and carries on."""
from __future__ import annotations

import json
import os
import sys
import tempfile
from typing import Any, Dict, Optional


def app_dir() -> str:
    """The folder of the .exe (or of the project when run from source)."""
    if getattr(sys, "frozen", False):
        return os.path.dirname(os.path.abspath(sys.executable))
    return os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def bundled_sounds_dir() -> str:
    """Built-in sounds travel inside the exe (PyInstaller) or sit in the project."""
    base = getattr(sys, "_MEIPASS", None)
    return os.path.join(base, "sounds") if base else os.path.join(app_dir(), "sounds")


DEFAULT_SETTINGS: Dict[str, Any] = {
    "server_url": "",
    "token": "",
    "devices": {},  # zone id (as text) -> sound device NAME (never an index)
    "autostart": True,
    "night_mode": False,
    "sync_seconds": 30,
}


def write_json(path: str, data: Any) -> None:
    """Writes atomically so a power cut never leaves a half-written file."""
    folder = os.path.dirname(path) or "."
    os.makedirs(folder, exist_ok=True)
    handle, temp = tempfile.mkstemp(dir=folder, suffix=".tmp")
    try:
        with os.fdopen(handle, "w", encoding="utf-8") as out:
            json.dump(data, out, ensure_ascii=False, indent=2, sort_keys=True)
        os.replace(temp, path)
    except Exception:
        try:
            os.unlink(temp)
        except OSError:
            pass
        raise


def read_json(path: str, default: Any, on_damaged=None) -> Any:
    if not os.path.exists(path):
        return default
    try:
        with open(path, "r", encoding="utf-8") as source:
            return json.load(source)
    except (OSError, ValueError) as error:
        try:
            os.replace(path, path + ".rusak")
        except OSError:
            pass
        if on_damaged:
            on_damaged("%s tidak bisa dibaca (%s); dipindah ke %s.rusak" % (os.path.basename(path), error, os.path.basename(path)))
        return default


class Store:
    def __init__(self, folder: Optional[str] = None, on_problem=None):
        self.folder = folder or app_dir()
        self._on_problem = on_problem
        self.settings_path = os.path.join(self.folder, "pengaturan.json")
        self.cache_path = os.path.join(self.folder, "jadwal-cache.json")
        self.state_path = os.path.join(self.folder, "status-bel.json")
        self.sounds_dir = os.path.join(self.folder, "suara")
        self.log_dir = os.path.join(self.folder, "log")

    # -- settings -------------------------------------------------------------
    def load_settings(self) -> Dict[str, Any]:
        data = read_json(self.settings_path, {}, self._on_problem)
        merged = dict(DEFAULT_SETTINGS)
        if isinstance(data, dict):
            for key in DEFAULT_SETTINGS:
                if key in data and isinstance(data[key], type(DEFAULT_SETTINGS[key])):
                    merged[key] = data[key]
        merged["devices"] = {str(k): str(v) for k, v in dict(merged["devices"]).items()}
        merged["sync_seconds"] = max(10, min(600, int(merged["sync_seconds"])))
        return merged

    def save_settings(self, settings: Dict[str, Any]) -> None:
        write_json(self.settings_path, settings)

    # -- schedule cache -------------------------------------------------------
    def load_cache(self) -> Optional[Dict[str, Any]]:
        data = read_json(self.cache_path, None, self._on_problem)
        return data if isinstance(data, dict) and "data" in data else None

    def save_cache(self, version: str, data: Dict[str, Any]) -> None:
        write_json(self.cache_path, {"version": version, "data": data})

    # -- which bells already rang ----------------------------------------------
    def load_fired(self) -> list:
        data = read_json(self.state_path, {}, self._on_problem)
        fired = data.get("fired", []) if isinstance(data, dict) else []
        return [str(x) for x in fired] if isinstance(fired, list) else []

    def save_fired(self, fired: list) -> None:
        write_json(self.state_path, {"fired": fired})

    # -- downloaded sounds ------------------------------------------------------
    def sound_path(self, sound_id: int, sha256: str, ext: str) -> str:
        return os.path.join(self.sounds_dir, "%d-%s.%s" % (sound_id, sha256[:12], ext))

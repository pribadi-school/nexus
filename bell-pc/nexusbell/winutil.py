"""Small Windows helpers: keep the PC awake, start with Windows, run only once.
Every function is safe to call on any system (they do nothing off Windows)."""
from __future__ import annotations

import os
import sys

RUN_KEY = r"Software\Microsoft\Windows\CurrentVersion\Run"
RUN_NAME = "NexusBell"
ES_CONTINUOUS = 0x80000000
ES_SYSTEM_REQUIRED = 0x00000001

_mutex_handle = None


def prevent_sleep() -> bool:
    """Keeps Windows from sleeping while the app runs. Call again now and then:
    the setting belongs to the thread that set it."""
    if os.name != "nt":
        return False
    try:
        import ctypes
        return bool(ctypes.windll.kernel32.SetThreadExecutionState(ES_CONTINUOUS | ES_SYSTEM_REQUIRED))
    except Exception:
        return False


def allow_sleep() -> None:
    if os.name != "nt":
        return
    try:
        import ctypes
        ctypes.windll.kernel32.SetThreadExecutionState(ES_CONTINUOUS)
    except Exception:
        pass


def autostart_command() -> str:
    return '"%s" --minimized' % os.path.abspath(sys.executable)


def autostart_enabled() -> bool:
    if os.name != "nt":
        return False
    try:
        import winreg
        with winreg.OpenKey(winreg.HKEY_CURRENT_USER, RUN_KEY) as key:
            winreg.QueryValueEx(key, RUN_NAME)
            return True
    except OSError:
        return False


def set_autostart(enabled: bool) -> bool:
    """Adds or removes the current-user Run entry. Only the packaged .exe is
    registered (running from source would register the Python interpreter)."""
    if os.name != "nt":
        return False
    try:
        import winreg
        with winreg.OpenKey(winreg.HKEY_CURRENT_USER, RUN_KEY, 0, winreg.KEY_SET_VALUE) as key:
            if enabled:
                if not getattr(sys, "frozen", False):
                    return False
                winreg.SetValueEx(key, RUN_NAME, 0, winreg.REG_SZ, autostart_command())
            else:
                try:
                    winreg.DeleteValue(key, RUN_NAME)
                except FileNotFoundError:
                    pass
        return True
    except OSError:
        return False


def acquire_single_instance() -> bool:
    """False when another copy is already running (two copies would ring every bell twice)."""
    global _mutex_handle
    if os.name != "nt":
        return True
    try:
        import ctypes
        handle = ctypes.windll.kernel32.CreateMutexW(None, False, "Local\\NexusBellSingleInstance")
        if ctypes.windll.kernel32.GetLastError() == 183:  # ERROR_ALREADY_EXISTS
            return False
        _mutex_handle = handle
        return True
    except Exception:
        return True


def open_folder(path: str) -> None:
    try:
        if os.name == "nt":
            os.startfile(path)  # type: ignore[attr-defined]
    except Exception:
        pass

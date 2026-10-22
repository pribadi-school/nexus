"""Nexus Bell: the school bell player for the bell PC.

    python main.py             open the window
    python main.py --minimized start in the system tray (what auto-start uses)
"""
import sys

from nexusbell import strings, winutil
from nexusbell.core import BellCore
from nexusbell.store import Store
from nexusbell.tray import Tray
from nexusbell.ui import App


def main(argv) -> int:
    if not winutil.acquire_single_instance():
        try:
            import tkinter
            from tkinter import messagebox
            root = tkinter.Tk()
            root.withdraw()
            messagebox.showinfo(strings.APP_TITLE, strings.BANNER_ALREADY_RUNNING)
        except Exception:
            print(strings.BANNER_ALREADY_RUNNING)
        return 1

    minimized = "--minimized" in argv
    core = BellCore(Store())
    app = App(core, start_hidden=minimized)

    def quit_app() -> None:
        core.stop()
        tray.stop()
        app.root.destroy()

    tray = Tray(on_show=lambda: app.request(app.show), on_quit=lambda: app.request(quit_app))
    if not tray.start():
        app.show()  # no tray available: never leave the app invisible

    core.start()
    core.events.add("info", None, "Nexus Bell dimulai")
    try:
        app.run()
    finally:
        core.stop()
        tray.stop()
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))

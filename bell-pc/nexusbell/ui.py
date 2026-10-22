"""The window (tkinter): two side-by-side floor panels, each with its own
colour, device, schedule and buttons, plus a connection box and today's log.
Laid out like a simple alarm-clock list. It only reads from BellCore."""
from __future__ import annotations

import queue
import tkinter as tk
from datetime import datetime
from tkinter import messagebox, ttk
from typing import Callable, Dict, List, Optional

from . import audio, strings, winutil
from .core import BellCore
from .engine import Entry, Zone

ZONE_COLORS = {"blue": "#1F4E9F", "orange": "#B45309", "green": "#15803D", "purple": "#6D28D9"}
LIGHT = {"bg": "#F2F5F9", "card": "#FFFFFF", "fg": "#12233C", "muted": "#5A6472", "line": "#C9D2DF", "off": "#8A94A3",
         "banner_bg": "#FEF3C7", "banner_fg": "#7C2D12"}
NIGHT = {"bg": "#14181F", "card": "#1D232D", "fg": "#E6EAF0", "muted": "#9AA5B5", "line": "#2E3745", "off": "#6B7686",
         "banner_bg": "#4A3410", "banner_fg": "#FCD9A0"}


def days_text(days) -> str:
    if len(days) == 7:
        return strings.EVERY_DAY
    return ",".join(strings.DAYS_SHORT[d] for d in sorted(days))


def date_text(now: datetime) -> str:
    return "%s, %d %s %d" % (strings.DAYS_LONG[now.isoweekday()], now.day, strings.MONTHS[now.month - 1], now.year)


class ZonePanel:
    """One floor: title bar in the floor's colour, device, buttons and schedule."""

    def __init__(self, parent: tk.Widget, core: BellCore, zone_id: int, theme: Dict[str, str], devices: Callable[[], List[str]]):
        self.core = core
        self.zone_id = zone_id
        self.theme = theme
        self._devices = devices
        self._entries: Dict[str, Entry] = {}
        self.frame = tk.Frame(parent, bg=theme["card"], highlightthickness=1, highlightbackground=theme["line"])

        self.header = tk.Frame(self.frame)
        self.header.pack(fill="x")
        self.title = tk.Label(self.header, font=("Segoe UI", 15, "bold"), anchor="w", padx=12, pady=8)
        self.title.pack(side="left", fill="x", expand=True)
        self.meta = tk.Label(self.header, font=("Segoe UI", 10), padx=12)
        self.meta.pack(side="right")

        body = tk.Frame(self.frame, bg=theme["card"], padx=12, pady=8)
        body.pack(fill="both", expand=True)
        self.next_label = tk.Label(body, bg=theme["card"], fg=theme["fg"], font=("Segoe UI", 11, "bold"), anchor="w")
        self.next_label.pack(fill="x")
        self.state_label = tk.Label(body, bg=theme["card"], fg=theme["muted"], font=("Segoe UI", 9), anchor="w")
        self.state_label.pack(fill="x", pady=(0, 6))

        row = tk.Frame(body, bg=theme["card"])
        row.pack(fill="x", pady=(0, 6))
        tk.Label(row, text=strings.ZONE_DEVICE + ":", bg=theme["card"], fg=theme["muted"], font=("Segoe UI", 9)).pack(side="left")
        self.device_var = tk.StringVar()
        self.device_box = ttk.Combobox(row, textvariable=self.device_var, state="readonly", width=34, postcommand=self._fill_devices)
        self.device_box.pack(side="left", padx=6, fill="x", expand=True)
        self.device_box.bind("<<ComboboxSelected>>", self._device_chosen)

        buttons = tk.Frame(body, bg=theme["card"])
        buttons.pack(fill="x", pady=(0, 6))
        ttk.Button(buttons, text=strings.ZONE_TEST_DEVICE, command=lambda: core.test_device(zone_id)).pack(side="left")
        ttk.Button(buttons, text=strings.ZONE_RING_NOW, command=lambda: core.ring_now(zone_id)).pack(side="left", padx=6)
        ttk.Button(buttons, text=strings.ZONE_TEST_ROW, command=self._test_row).pack(side="left")

        columns = ("time", "days", "label", "sound", "on")
        self.tree = ttk.Treeview(body, columns=columns, show="headings", height=12, selectmode="browse")
        widths = {"time": 80, "days": 120, "label": 150, "sound": 110, "on": 50}
        for column in columns:
            self.tree.heading(column, text=strings.COLUMNS[column])
            self.tree.column(column, width=widths[column], anchor="center" if column in ("time", "on") else "w")
        scroll = ttk.Scrollbar(body, orient="vertical", command=self.tree.yview)
        self.tree.configure(yscrollcommand=scroll.set)
        self.tree.pack(side="left", fill="both", expand=True)
        scroll.pack(side="right", fill="y")
        self.tree.tag_configure("off", foreground=theme["off"])
        self.tree.bind("<Double-1>", lambda _e: self._test_row())
        self._signature = None

    def _fill_devices(self) -> None:
        self.device_box["values"] = self._devices()

    def _device_chosen(self, _event=None) -> None:
        self.core.set_device(self.zone_id, self.device_var.get())

    def _test_row(self) -> None:
        selected = self.tree.selection()
        if selected and selected[0] in self._entries:
            self.core.test_entry(self.zone_id, self._entries[selected[0]])

    def refresh(self, zone: Optional[Zone], now: datetime) -> None:
        color = ZONE_COLORS.get(zone.color if zone else "blue", ZONE_COLORS["blue"])
        for widget in (self.header, self.title, self.meta):
            widget.configure(bg=color)
        self.title.configure(fg="#FFFFFF", text=zone.name if zone else "Lantai %d" % self.zone_id)
        self.meta.configure(fg="#FFFFFF", text=("%s: %s   %s: %d%%" % (strings.ZONE_PROFILE, zone.profile or "-", strings.ZONE_VOLUME, zone.volume)) if zone else "")

        saved = self.core.settings["devices"].get(str(self.zone_id), "")
        if self.device_box.focus_get() is not self.device_box:
            self.device_var.set(saved or strings.ZONE_NO_DEVICE)
        state = self.core.device_state(self.zone_id)
        text, fg = {"ok": (strings.ZONE_DEVICE_OK, "#15803D"), "missing": (strings.ZONE_DEVICE_MISSING, "#B91C1C"),
                    "unset": (strings.ZONE_DEVICE_UNSET, self.theme["muted"])}[state]
        if zone and now.date() in zone.holidays:
            text, fg = strings.ZONE_HOLIDAY_TODAY, "#B45309"
        self.state_label.configure(text=text, fg=fg)

        upcoming = self.core.scheduler.next_for(self.zone_id, now) if zone else None
        if upcoming:
            when, entry = upcoming
            day = "" if when.date() == now.date() else " (%s)" % strings.DAYS_LONG[when.isoweekday()]
            self.next_label.configure(text="%s: %s%s  %s" % (strings.ZONE_NEXT, when.strftime("%H:%M:%S"), day, entry.label))
        else:
            self.next_label.configure(text="%s: %s" % (strings.ZONE_NEXT, strings.ZONE_NEXT_NONE))

        entries = zone.entries if zone else ()
        signature = (zone.id if zone else None, tuple((e.id, e.at, e.days, e.sound, e.label, e.enabled) for e in entries))
        if signature != self._signature:
            self._signature = signature
            self.tree.delete(*self.tree.get_children())
            self._entries = {}
            for entry in entries:
                label = entry.label + (" (%s)" % (strings.SHARED_FROM % entry.shared_from) if entry.shared_from else "")
                item = self.tree.insert("", "end", values=(entry.at.strftime("%H:%M:%S"), days_text(entry.days), label,
                                                           self.core.sound_name(entry.sound), "✓" if entry.enabled else ""),
                                        tags=() if entry.enabled else ("off",))
                self._entries[item] = entry


class App:
    def __init__(self, core: BellCore, start_hidden: bool = False):
        self.core = core
        self.theme = NIGHT if core.settings["night_mode"] else LIGHT
        self.root = tk.Tk()
        self.root.title(strings.APP_TITLE)
        self.root.geometry("1180x680")
        self.root.minsize(900, 520)
        self.root.configure(bg=self.theme["bg"])
        self.root.protocol("WM_DELETE_WINDOW", self.hide)
        self._device_cache: List[str] = []
        self._requests: "queue.Queue[Callable[[], None]]" = queue.Queue()
        self._build()
        self.panels: Dict[int, ZonePanel] = {}
        self._tick()
        if start_hidden:
            self.root.withdraw()

    # -- layout --------------------------------------------------------------------
    def _build(self) -> None:
        theme = self.theme
        top = tk.Frame(self.root, bg=theme["bg"], padx=16, pady=10)
        top.pack(fill="x")
        self.clock = tk.Label(top, bg=theme["bg"], fg=theme["fg"], font=("Segoe UI", 34, "bold"))
        self.clock.pack(side="left")
        info = tk.Frame(top, bg=theme["bg"], padx=16)
        info.pack(side="left", fill="x", expand=True)
        self.date = tk.Label(info, bg=theme["bg"], fg=theme["fg"], font=("Segoe UI", 13), anchor="w")
        self.date.pack(fill="x")
        self.sync = tk.Label(info, bg=theme["bg"], fg=theme["muted"], font=("Segoe UI", 9), anchor="w")
        self.sync.pack(fill="x")
        buttons = tk.Frame(top, bg=theme["bg"])
        buttons.pack(side="right")
        ttk.Button(buttons, text=strings.CONNECTION, command=self.open_connection).pack(side="left", padx=4)
        ttk.Button(buttons, text=strings.SYNC_NOW, command=self.core.sync_soon).pack(side="left", padx=4)
        self.options_button = ttk.Menubutton(buttons, text=strings.MENU_OPTIONS)
        self.options_button.pack(side="left", padx=4)
        self._build_options_menu()

        self.banner = tk.Label(self.root, bg=theme["banner_bg"], fg=theme["banner_fg"], font=("Segoe UI", 10, "bold"),
                               anchor="w", padx=16, pady=6, wraplength=1100, justify="left")

        self.zone_area = tk.Frame(self.root, bg=theme["bg"], padx=12)
        self.zone_area.pack(fill="both", expand=True)
        self.zone_area.columnconfigure((0, 1), weight=1, uniform="zona")
        self.zone_area.rowconfigure(0, weight=1)

        log_frame = tk.Frame(self.root, bg=theme["bg"], padx=12, pady=8)
        log_frame.pack(fill="x")
        tk.Label(log_frame, text=strings.LOG_TITLE, bg=theme["bg"], fg=theme["muted"], font=("Segoe UI", 9, "bold"), anchor="w").pack(fill="x")
        self.log = tk.Text(log_frame, height=6, bg=theme["card"], fg=theme["fg"], font=("Consolas", 9), relief="flat",
                           highlightthickness=1, highlightbackground=theme["line"], state="disabled", wrap="none")
        self.log.pack(fill="x")
        self._log_len = 0

    def _build_options_menu(self) -> None:
        menu = tk.Menu(self.options_button, tearoff=0)
        self.autostart_var = tk.BooleanVar(value=winutil.autostart_enabled())
        self.night_var = tk.BooleanVar(value=self.core.settings["night_mode"])
        menu.add_checkbutton(label=strings.MENU_AUTOSTART, variable=self.autostart_var, command=self._toggle_autostart)
        menu.add_checkbutton(label=strings.MENU_NIGHT, variable=self.night_var, command=self._toggle_night)
        menu.add_command(label=strings.MENU_OPEN_LOG, command=lambda: winutil.open_folder(self.core.store.log_dir))
        self.options_button["menu"] = menu

    # -- actions ---------------------------------------------------------------------
    def _toggle_autostart(self) -> None:
        wanted = self.autostart_var.get()
        ok = winutil.set_autostart(wanted)
        if not ok and wanted:
            messagebox.showinfo(strings.APP_TITLE, "Mulai bersama Windows hanya bisa diatur dari file NexusBell.exe (bukan dari kode sumber).")
            self.autostart_var.set(False)
        self.core.set_option("autostart", self.autostart_var.get())

    def _toggle_night(self) -> None:
        self.core.set_option("night_mode", self.night_var.get())
        messagebox.showinfo(strings.APP_TITLE, "Mode malam dipakai setelah aplikasi dibuka ulang.")

    def open_connection(self) -> None:
        dialog = tk.Toplevel(self.root)
        dialog.title(strings.CONNECTION_TITLE)
        dialog.transient(self.root)
        dialog.resizable(False, False)
        frame = ttk.Frame(dialog, padding=16)
        frame.pack(fill="both", expand=True)
        ttk.Label(frame, text=strings.KEY_HELP, wraplength=420, justify="left").grid(row=0, column=0, columnspan=2, sticky="w", pady=(0, 10))
        ttk.Label(frame, text=strings.SERVER_ADDRESS).grid(row=1, column=0, sticky="w")
        url = tk.StringVar(value=self.core.settings["server_url"] or "https://nexus.pribadidepok.sch.id")
        ttk.Entry(frame, textvariable=url, width=46).grid(row=1, column=1, pady=4, padx=(8, 0))
        ttk.Label(frame, text=strings.CONNECTION_KEY).grid(row=2, column=0, sticky="w")
        key = tk.StringVar(value=self.core.settings["token"])
        ttk.Entry(frame, textvariable=key, width=46, show="•").grid(row=2, column=1, pady=4, padx=(8, 0))

        def save() -> None:
            self.core.set_connection(url.get(), key.get())
            dialog.destroy()

        ttk.Button(frame, text=strings.SAVE_CONNECT, command=save).grid(row=3, column=1, sticky="e", pady=(10, 0))
        dialog.grab_set()

    def request(self, action: Callable[[], None]) -> None:
        """Other threads (the tray icon) ask the window to do something; tkinter
        itself is only ever touched from the main thread."""
        self._requests.put(action)

    def hide(self) -> None:
        self.root.withdraw()

    def show(self) -> None:
        self.root.deiconify()
        self.root.lift()
        self.root.focus_force()

    def devices(self) -> List[str]:
        try:
            self._device_cache = [name for _index, name in audio.list_output_devices()]
        except Exception:
            pass
        return self._device_cache

    # -- refresh (once a second) ---------------------------------------------------------
    def _tick(self) -> None:
        try:
            while True:
                self._requests.get_nowait()()
        except queue.Empty:
            pass
        except Exception:
            pass
        try:
            self._refresh()
        except Exception:
            pass  # a drawing problem must never stop the clock
        self.root.after(250, self._tick)

    def _refresh(self) -> None:
        now = self.core.clock()
        self.clock.configure(text=now.strftime("%H:%M:%S"))
        self.date.configure(text=date_text(now))
        self.sync.configure(text=self.core.sync_message)

        banner = self.core.banner()
        if banner:
            self.banner.configure(text=banner)
            if not self.banner.winfo_ismapped():
                self.banner.pack(fill="x", before=self.zone_area)
        elif self.banner.winfo_ismapped():
            self.banner.pack_forget()

        zones = self.core.zones()
        wanted = sorted(zones) or [1, 2]
        for column, zone_id in enumerate(wanted[:2]):
            panel = self.panels.get(zone_id)
            if panel is None:
                panel = ZonePanel(self.zone_area, self.core, zone_id, self.theme, self.devices)
                panel.frame.grid(row=0, column=column, sticky="nsew", padx=6, pady=4)
                self.panels[zone_id] = panel
            panel.refresh(zones.get(zone_id), now)

        lines = self.core.events.recent()
        if len(lines) != self._log_len:
            self._log_len = len(lines)
            self.log.configure(state="normal")
            self.log.delete("1.0", "end")
            self.log.insert("end", "\n".join(lines[-60:]))
            self.log.see("end")
            self.log.configure(state="disabled")

    def run(self) -> None:
        self.root.mainloop()

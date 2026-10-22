"""System tray icon (pystray). The app starts minimised here and keeps ringing
bells while the window is closed."""
from __future__ import annotations

from typing import Callable, Optional

from . import strings


def make_icon_image(size: int = 64):
    from PIL import Image, ImageDraw
    image = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    draw = ImageDraw.Draw(image)
    s = size / 64.0
    blue, red, white = (31, 78, 159, 255), (185, 28, 28, 255), (255, 255, 255, 255)
    draw.ellipse([4 * s, 4 * s, 60 * s, 60 * s], fill=blue)
    draw.pieslice([18 * s, 16 * s, 46 * s, 44 * s], 180, 360, fill=white)  # bell dome
    draw.rectangle([18 * s, 30 * s, 46 * s, 42 * s], fill=white)
    draw.rectangle([14 * s, 40 * s, 50 * s, 45 * s], fill=white)
    draw.ellipse([28 * s, 45 * s, 36 * s, 53 * s], fill=white)  # clapper
    draw.ellipse([27 * s, 8 * s, 37 * s, 18 * s], fill=red)
    return image


class Tray:
    def __init__(self, on_show: Callable[[], None], on_quit: Callable[[], None]):
        self._icon = None
        self._on_show = on_show
        self._on_quit = on_quit

    def start(self) -> bool:
        try:
            import pystray
            menu = pystray.Menu(
                pystray.MenuItem(strings.MENU_SHOW, lambda icon, item: self._on_show(), default=True),
                pystray.MenuItem(strings.MENU_QUIT, lambda icon, item: self._on_quit()),
            )
            self._icon = pystray.Icon("NexusBell", make_icon_image(), strings.TRAY_TITLE, menu)
            self._icon.run_detached()
            return True
        except Exception:
            self._icon = None
            return False

    def stop(self) -> None:
        if self._icon is not None:
            try:
                self._icon.stop()
            except Exception:
                pass

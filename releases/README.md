# Ready-to-install files

Download these from GitHub (open the file, then the download button) and copy them to a USB drive.

| File | Install on | Guide |
|---|---|---|
| **`NexusBell-Setup.exe`** (installer, recommended) | the bell PC (Windows) | `bell-pc/README.md` (Bahasa Indonesia) |
| `NexusBell.exe` (single file, no installer) | the bell PC, if you prefer to copy it by hand | same guide |
| **`nexus-signage-landscape-wifi-1.4.4.apk`** | the landscape TVs (Xiaomi / Android 10, SD, Lantai 3, Kantin) | `docs/android-tv.md` |
| **`nexus-signage-t960x-portrait-1.4.4.apk`** | the vertical T960X display (Android 7.1.2) | `docs/t960x-setup.md` |

They were built from this repository (the installer on 23 Oct 2026, the two Android players, version 1.4.4, on 24 Oct 2026).
Install an Android player **over** the existing app (`adb install -r`, or choose Update); never uninstall, or the TV
loses its pairing and downloaded videos. The old `nexus-signage-tv.apk` here was a wrong 1.0.0 build and was removed. The `.sha256` files let you check
that a copy was not damaged. `NexusBell-Setup.exe` and `NexusBell.exe` are not code-signed, so Windows may show a
"Windows protected your PC" warning: choose **More info > Run anyway**.

Before the bell PC works, the server needs migration `017_bell_scheduler.sql` imported in
phpMyAdmin (and 009 to 016 if not done yet), then `bash ~/repositories/nexus/update.sh`.

# Ready-to-install files

Download these from GitHub (open the file, then the download button) and copy them to a USB drive.

| File | Install on | Guide |
|---|---|---|
| **`NexusBell-Setup.exe`** (installer, recommended) | the bell PC (Windows) | `bell-pc/README.md` (Bahasa Indonesia) |
| `NexusBell.exe` (single file, no installer) | the bell PC, if you prefer to copy it by hand | same guide |
| `nexus-signage-tv.apk` | each Android TV (Xiaomi etc.) | `docs/android-tv.md` |

They were built from this repository (the installer on 23 Oct 2026). The `.sha256` files let you check
that a copy was not damaged. `NexusBell-Setup.exe` and `NexusBell.exe` are not code-signed, so Windows may show a
"Windows protected your PC" warning: choose **More info > Run anyway**.

Before the bell PC works, the server needs migration `017_bell_scheduler.sql` imported in
phpMyAdmin (and 009 to 016 if not done yet), then `bash ~/repositories/nexus/update.sh`.

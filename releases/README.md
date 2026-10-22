# Ready-to-install files

Download these from GitHub (open the file, then the download button) and copy them to a USB drive.

| File | Install on | Guide |
|---|---|---|
| `NexusBell.exe` | the bell PC (Windows) | `bell-pc/README.md` (Bahasa Indonesia) |
| `nexus-signage-tv.apk` | each Android TV (Xiaomi etc.) | `docs/android-tv.md` |

Both were built on 22 Oct 2026 from this repository. The `.sha256` files let you check
that a copy was not damaged. `NexusBell.exe` is not code-signed, so Windows may show a
"Windows protected your PC" warning: choose **More info > Run anyway**.

Before the bell PC works, the server needs migration `017_bell_scheduler.sql` imported in
phpMyAdmin (and 009 to 016 if not done yet), then `bash ~/repositories/nexus/update.sh`.

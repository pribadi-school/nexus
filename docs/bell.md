# Bell (school bell scheduler)

Two independent areas ("zones", seeded as **Lantai 2** and **Lantai 3**), each
with its own profiles (Normal, Jumat, Ujian, Pendek), schedule, holidays,
volume and active profile. The schedule is edited in Nexus (menu **Bell**,
below Signage). A Windows program on the bell PC (`bell-pc/`) downloads it and
rings from its own saved copy, so bells keep working when the internet is down.

Why the split: Nexus runs on shared hosting and cannot play sound on the school
PC, and a bell must not depend on the internet.

## Nexus side (`app/Modules/Bell`)

| Page | What it does |
|---|---|
| `/bell` → `/bell/zones/{id}` | One area: alarm-clock style list (switch, big time, days, sound, per-row Listen/Edit/Copy/Delete), live school clock + next bell, profile chips with **Use this profile now**, CSV export/import |
| `/bell/zones/{id}/settings` | Name, colour, volume, profiles (rename/duplicate/copy to the other area/delete), holidays, JSON backup |
| `/bell/sounds` | Built-in sounds and MP3/WAV uploads (10 MB, checked by file header) |
| `/bell/pc` | Connection key, online state, device status reported by the PC, recent bells |

- Tables (migration 017): `bell_zones`, `bell_profiles`, `bell_entries`,
  `bell_holidays`, `bell_sounds`, `bell_pc` (one row), `bell_events`.
- Times are school wall-clock (Asia/Jakarta) and are **not** converted through UTC.
- `bell_entries.days` is a string of ISO weekday digits (`12345` = Mon-Fri).
  `sound_ref` is `builtin:<key>` or `sound:<id>`.
- A row's **both areas** flag sends it to the other area too: the sync payload adds
  other areas' enabled school-wide rows to each zone with `shared_from`.
- Copying a row or profile to the other area never carries the school-wide flag.
- CSV: UTF-8 with BOM, `;` separators, columns `Waktu;Hari;File suara;Label;Aktif;Dua lantai`
  (days as `Sen,Sel,...`). Import is all-or-nothing and lists every bad line.
- The JSON backup (`/bell/backup.json`) has everything except the sound files (names only).
  There is no restore screen yet; use phpMyAdmin exports for full restores.
- Sound files are in `storage/bell/sounds/` (excluded from Git, like signage media; copy
  the folder when moving servers). The three built-in sounds are
  `bell-pc/sounds/*.wav`, made by `php tools/make-bell-sounds.php`.

### API used by the bell PC

Public routes (no CSRF) protected by the **connection key**, sent as header
`X-Nexus-Bell-Token` (a custom header because some cPanel PHP setups drop
`Authorization`). Only the SHA-256 of the key is stored; making a new key stops
the old one; **Remove key** disables the API.

- `POST /bell/api/sync` with `version`, `pc_name`, `app_version`, `status` (JSON),
  `events` (JSON). Replies `{ok, version, changed, data?}`; `data` is sent only
  when the PC's `version` differs.
- `GET /bell/api/sounds/{id}` returns one uploaded sound (the PC checks its SHA-256).

Both fail with 403 without a valid key. The PC calls sync about every 30 seconds;
"online" means a check-in in the last 2 minutes.

## Windows program (`bell-pc/`)

Python 3.11+, tkinter window, pystray tray icon, `sounddevice` + `soundfile`
(MP3 and WAV, a specific output device per area, two devices at once). Packaged
with PyInstaller (`bell-pc/build.ps1` → `dist/NexusBell.exe`, about 31 MB). UI is
Bahasa Indonesia (`nexusbell/strings.py`); setup instructions are in `bell-pc/README.md`.

- `engine.py`: pure scheduling (fake-clock tests): per-zone entries, holidays and
  volume; fired-set so a bell never rings twice (also kept across restarts); 30 s
  grace window, else *skipped* + logged; two zones in the same second both ring.
- `audio.py`: one host API only (WASAPI, else MME) so each physical device is listed
  once; devices saved **by name**; one play thread and stream per play; a new bell on
  a zone cuts that zone's previous sound.
- `core.py`: 1-second tick thread, sync thread, device checks; a missing device only
  warns (other area keeps ringing); a sound not downloaded yet falls back to the
  built-in bell.
- `store.py`: `pengaturan.json` (readable), `jadwal-cache.json`, `status-bel.json`,
  `suara/`, `log/` next to the exe; damaged files are moved to `*.rusak`.
- `winutil.py`: no sleep (`SetThreadExecutionState`), current-user auto-start (Run key,
  `--minimized`), single instance mutex.
- Daily rotating log `log/bell.log` (60 days) with the zone name on every line.

## Tests

```
php -c .dev\php.ini tests\bell.php          # Nexus side, rolled back (51 checks)
cd bell-pc && python -m unittest discover -s tests -v   # engine, devices, store, core (40 tests)
```

## Server setup

1. Import `database/migrations/017_bell_scheduler.sql` in phpMyAdmin.
2. Pull and publish (`bash ~/repositories/nexus/update.sh`).
3. Open **Bell**, add each area's bells (or import CSV), then **Bell PC → Make connection key**.
4. Install `releases/NexusBell-Setup.exe` on the bell PC (per-user installer: Start menu entry,
   optional Start with Windows, uninstaller in Apps; settings, sounds and logs stay across upgrades) and follow
   `bell-pc/README.md`. Build it with `bell-pc/build-installer.ps1` (Inno Setup 6, script in `bell-pc/installer/`).

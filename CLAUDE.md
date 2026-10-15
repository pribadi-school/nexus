# Nexus: project guide for Claude

Nexus is the IT infrastructure dashboard for Pribadi School (Sekolah Pribadi
Depok). Tagline: "Connecting to Pribadi School". It tracks school devices
(StudyTouch interactive boards, CCTV cameras, student Chromebooks) and their
condition. It runs on its own subdomain, `nexus.pribadidepok.sch.id`,
completely separate from the school's WordPress site. UI language: English.

## Status and build order

1. **Done:** logo assets, layout (sidebar / bottom nav), sign-in, Dashboard
   (category tiles, "Needs attention", recent-activity feed), sample data.
2. **Done: StudyTouch** (`app/Modules/StudyTouch`): list (table ≥900px,
   cards below) with live search + status/room filters, instant status
   switch, problem rows highlighted, detail page (status, notes log,
   details), add/edit/delete, editable rooms list at `/studytouch/rooms`.
   Use it as the template for the next two modules.
3. **Next: CCTV:** cameras grouped by area (list grouped under area headings),
   editable areas (`cctv_areas`, a `LookupList`), label, exact location,
   model, IP (optional, `ip` rule), SD card GB, last checked ("Mark as
   checked" should set `last_checked_at`), filters by area and status.
4. **Then Chromebooks:** serial, student, class, status ("OK"); editable
   class list; search by serial or student; filters; reassign with history
   (`chromebook_assignments`, write an `assignment` note); CSV import
   (upload → preview → confirm) and CSV export. May need paging (hundreds).
5. **Then** `docs/deploy-cpanel.md`: subdomain, MySQL database + user,
   importing the migrations, `.env`, cPanel Git "Update from Remote".

CCTV and Chromebooks still route to `App\Devices\ComingSoonController`;
replace that in the category's `registerRoutes()` when their pages are built.

### Shared device building blocks (reuse them, don't copy them)

- `App\Devices\DeviceService`: create / update / changeStatus / addNote /
  delete for the shared `devices` row. `changeStatus` writes the status note
  in the same transaction. A duplicate serial throws `DeviceServiceError`
  (field `serial_number`) for the form to show.
- `App\Devices\DeviceController`: `POST /devices/{id}/status` (JSON for
  fetch, redirect to `return` otherwise) and `POST /devices/{id}/notes`
  (kind, body, optional `set_status`).
- `views/partials/status-toggle.php`: the switch. `instant => true` (lists)
  saves via fetch + toast; `false` (detail page) posts and reloads.
- `views/partials/notes-log.php`: add-note form (kinds from
  `Category::noteKinds()`, "Also mark as …" checkbox) + entries
  (`partials/note-entry.php`, also used by the dashboard feed).
- `App\Devices\LookupList` + `LookupRepository` + `LookupController` +
  `views/lists/index.php`: editable lists. Declare them in
  `Category::lists()`; routes are added automatically under the list's path.
- `App\Core\Validator`: form rules (`required`, `max:N`, `int`, `exists:table`,
  `in:a,b`, `ip`, `min_value:N`, `max_value:N`) with friendly messages.
- Front-end: `filters.js` (`form[data-live-filter]` + `[data-results]`;
  the controller returns `{html}` for fetch), `status-toggle.js`,
  `toast.js`, `note-form.js`, `confirm.js`. Styles in `devices.css`.
- A list page's controller returns the `_results` partial as JSON when
  `$request->isFetch()`; rows carry `data-device-row="{id}"` so the switch
  can re-highlight them.

## Stack and decisions

- **Plain PHP 8.2+, no framework, no Composer.** Laravel was rejected
  because cPanel's "Update from Remote" only runs `git pull` (no composer
  install or artisan), and the owner wants SQL files imported in phpMyAdmin.
  The small core in `app/Core` covers routing, views, PDO, sessions, CSRF.
- **MySQL 8.0+ or MariaDB 10.6+** (production is Hostinger shared hosting,
  cPanel). SQL must run on both.
- **No build step at runtime.** Hand-written CSS with tokens, plain ES-module
  JavaScript. Node is only used on the dev machine for `npm run build:assets`
  (fonts, icon sprite, PNG icons); its output in `public/assets` is committed.
- **Sign-in: one shared password + your name** (owner decision, Oct 2026;
  Google OAuth was planned and then dropped). `APP_PASSWORD` lives in `.env`.
  The name typed at sign-in becomes a row in `users` and is the author of
  notes and status changes. Changing `APP_PASSWORD` signs everyone out (the
  session stores an HMAC of it). Wrong passwords are limited per IP
  (`login_attempts`, `LOGIN_MAX_ATTEMPTS` / `LOGIN_LOCKOUT_MINUTES`). There
  are no roles.
- **Secrets only in `.env`** (never committed). `.env.example` documents every key.

## Running it locally

```
powershell -ExecutionPolicy Bypass -File bin\dev.ps1          # http://localhost:8000
powershell -ExecutionPolicy Bypass -File bin\dev.ps1 -Reset   # wipe local DB, re-import, re-seed
powershell -ExecutionPolicy Bypass -File bin\dev.ps1 -Stop    # stop the local DB
```

- Uses LocalWP's bundled PHP 8.2 and MariaDB 10.11 (`E:\LocalWP`; override
  with `NEXUS_LOCALWP`). Database files live in `.dev/mariadb` (ignored), on
  port 3307. The first run writes a local `.env` (the local sign-in password
  is `APP_PASSWORD` there), applies migrations and loads the sample data.
- The Claude preview config is `.claude/launch.json` → `nexus`.
- `php bin/migrate.php [--seed|--status]` applies pending migrations (needs
  the same `-n -d extension=...` flags dev.ps1 passes, so run it through
  dev.ps1 or copy those flags).
- Dev machine notes: no standalone PHP/Composer/MySQL/Python on PATH. From
  the Bash tool, curl cannot reach the local server; use PowerShell
  (`Invoke-WebRequest`) or the browser preview.

## Layout of the code

```
public/              document root: index.php (front controller), .htaccess,
                     assets/{css,js,fonts,brand,icons}, favicon.ico, manifest
app/bootstrap.php    autoloader (App\ -> app/), .env, config, error handler, session
app/routes.php       all routes; categories add theirs via registerRoutes()
app/helpers.php      e(), url(), asset(), icon(), csrf_field(), partial(), safe_path(), request_uri(),
                     time helpers (format_datetime, time_ago, day_label), plural()
app/Core/            App, Router, Request, Response, View, Database, Session, Validator,
                     Csrf, Env, Config, ErrorHandler, Migrations, Navigation
app/Auth/            Auth (session user), AuthController, UserRepository, LoginThrottle
app/Devices/         shared device layer: Category (abstract), CategoryRegistry, DeviceService,
                     Status, NoteKind, DeviceRepository, NoteRepository, Lookup* (editable lists),
                     DeviceController (/devices/{id}, status switch, notes)
app/Modules/<Name>/  one folder per area: Dashboard, StudyTouch, Cctv, Chromebooks
                     (Category class, controllers, views/)
views/               layouts/{app,auth}.php, lists/, partials/ (lockup, avatar, flashes, status-toggle, notes-log,
                     empty-state, note-entry, confirm-dialog), auth/, errors/, devices/
config/              app.php, auth.php, database.php, modules.php (category list)
database/migrations/ NNN_name.sql, imported in order (phpMyAdmin or bin/migrate.php)
database/seeds/      sample_data.sql, clear_sample_data.sql
storage/             sessions/, logs/app.log (not web-served, contents ignored)
bin/                 dev.ps1, migrate.php
tools/               build-assets.mjs (dev only)
design-system/       MASTER.md (shared Pribadi copy) + pages/nexus-app.md
```

## Conventions

- **Controllers** are `final` classes with methods taking `Request` and
  returning `Response` (`Response::html(View::render(...))`,
  `Response::redirect()`, `Response::json()`).
- **Views** are plain PHP. Escape everything with `e()`. Module views are
  addressed `'Module::name'`; shared ones by path (`'partials/note-entry'`).
- **Forms POST with `csrf_field()`**; the router rejects POSTs without a valid
  token (419). After a POST, redirect (303) and report with
  `Session::flash('success'|'error'|'info', ...)`.
- **fetch() calls** use `request()` from `public/assets/js/util.js`: it sends
  `X-Requested-With: fetch` and the CSRF header; the server answers JSON
  (`$request->isFetch()`). Every page must still work without JavaScript.
- **Content-Security-Policy is strict** (`'self'` only): no inline
  `<script>`, no `style="..."` attributes, no third-party assets. Dynamic
  sizes go through SVG attributes (see the dashboard meter) or classes.
- **Risky actions** use `data-confirm="Question?"` on the form (plus
  `data-confirm-text`, `data-confirm-button`, `data-confirm-tone="danger"`);
  `confirm.js` shows the shared dialog.
- **Loading states:** `data-busy-on-submit` on a form spins its button;
  `setBusy()` for fetch buttons; skeleton rows (`.skeleton`) while loading lists.
- **Times:** DB stores UTC (`Database::now()`, PDO session `time_zone = +00:00`);
  display only through `format_datetime()`, `time_ago()`, `iso_time()` (Asia/Jakarta).
- **Code-like values** (category, status, note kind) are VARCHARs validated
  against PHP constants (`Status`, `NoteKind`), not ENUMs.
- **Devices are soft-deleted** (`devices.deleted_at`); every query on devices
  must filter `deleted_at IS NULL`. Notes are append-only.
- **Every status change, firmware change and reassignment writes a
  `device_notes` row** (kind `status` / `firmware` / `assignment`) in the same
  transaction, authored by the signed-in person. Status notes store
  `meta = {"from": "...", "to": "..."}`; the label is rendered per category.
- Code comments explain *why*, briefly. Match the surrounding style.

## Adding a device category

1. `app/Modules/<Name>/<Name>Category.php` extending `App\Devices\Category`
   (key, name, icon, noun, statusLabel, registerRoutes, describe).
2. A new migration with its detail table (`device_id` PK + FK to `devices`
   with `ON DELETE CASCADE`).
3. Add the class to `config/modules.php`. Navigation, dashboard tiles, the
   feed and "Needs attention" pick it up automatically.

## Database

Tables (see `database/migrations/001_initial_schema.sql` for details):

- `devices`: one row per device of any category: category, serial_number,
  model, status (`ok`/`problem`), status_changed_at, last_checked_at,
  created_by/updated_by → users, timestamps, deleted_at. `active_serial` is a
  generated column so serials are unique per category among non-deleted devices.
- `studytouch_boards` (room_id → rooms, firmware_version),
  `cctv_cameras` (area_id → cctv_areas, label, location, ip_address, sd_card_gb),
  `chromebooks` (student_name, class_id → classes, assigned_at),
  `chromebook_assignments` (history; class_name copied as text).
- `device_notes`: the shared notes log (kind, body, meta JSON, user_id, created_at).
- `rooms`, `cctv_areas`, `classes`: editable lists (name unique, sort_order).
  `classes` starts with 7A–7C, 8A–8B, 9A–9B, 10A–10B, 11A–11B, 12A–12B.
- `users`: names of people who signed in. `login_attempts`: failed sign-ins.
- `schema_migrations`: applied migration versions.

Migration rules: once a migration has been imported on the server, never edit
it; add `NNN_description.sql` instead. Each file ends with
`INSERT INTO schema_migrations (version) VALUES ('NNN_description');` so a
phpMyAdmin import records itself; the dashboard warns when files are pending.
Use `utf8mb4_unicode_ci` (never MySQL-only `utf8mb4_0900_*`), InnoDB, and
test on both engines (LocalWP has MySQL 8.4 too). Migration 001 has not been
deployed yet.

## Design and branding

- Read `DESIGN.md` first: it points to `design-system/sekolah-pribadi-depok/MASTER.md`
  (shared Pribadi system) and `pages/nexus-app.md` (Nexus overrides; it wins).
  Tokens: `public/assets/css/tokens.css`. No raw hex values elsewhere.
- Brand: blue `#1F4E9F` (action colour), red `#B91C1C` (logo point, "Has problem",
  destructive), navy `#0F2A5C` (login backdrop). Neutrals from MASTER
  (page `#f2f5f9`, white cards, 1px `rgba(18,35,60,.16)` borders).
- Flat app style: no shadows (except `--shadow-e4` on menus/dialogs), no
  gradients, 8px buttons, 12px cards, no icon tiles, 150ms colour-only
  transitions, ≥44px touch targets, 16px inputs on phones.
- Status always shows a word: "Running well" / "Has problem" (Chromebooks:
  "OK" / "Has problem"). Problem rows: `problem-tint` + 3px red left border.
  Meter "running well" uses `--color-success-track` (light green); solid green
  next to red fails red–green colour blindness (checked with the dataviz validator).
- Fonts (self-hosted): Inter (UI), Space Grotesk 700 (wordmark only),
  IBM Plex Sans 500 (tagline only).
- Logo "Rise" (replaced the first "Pentagon Hub" mark, Oct 2026): raised
  arms drawn as lines meeting at one point, red point above. One drawing per
  size in `public/assets/brand/`: `logo-mark.svg` (≥56px, full),
  `logo-mark-48.svg` (sidebar/top bar), `favicon.svg` (≤32px),
  `logo-mark-white.svg` (navy backgrounds), `app-icon.svg` (inside app icons).
  PNGs: `app-icon-512.png`, `app-icon-192.png`, `apple-touch-icon.png`,
  `favicon-32.png`, `/favicon.ico`; rebuild with `npm run build:assets`.
  Size table in nexus-app.md §6. Mockup: `B · Rise-html/` (not committed).
- Wordmark: "nexus", always lowercase, Space Grotesk 700, letter-spacing
  −0.04em, `#1F4E9F`. Lockup (`partials/lockup.php`) = mark at 1.4× the
  wordmark size; `large => true` uses the full mark (login), otherwise the
  48 variant. The login page adds the tagline (IBM Plex Sans 500, `#4B5568`).
- Icons: Lucide, via `icon('name')` from `public/assets/icons/sprite.svg`.
  To add one, put its name in `ICONS` in `tools/build-assets.mjs` and rebuild.

## Deployment (summary; full guide comes in docs/deploy-cpanel.md)

- cPanel Git Version Control clones the repo (e.g. `~/nexus`); the subdomain's
  document root points at its `public/` folder. If a host cannot do that, the
  root `.htaccess` forwards everything into `public/`.
- Deploy = `git push`, then cPanel → Git → "Update from Remote". Nothing to build.
- On the server: create `.env` from `.env.example` (APP_ENV=production,
  APP_DEBUG=false, a long APP_PASSWORD, DB credentials); `storage/` must be
  writable; PHP 8.1+ with pdo_mysql and mbstring.
- New migrations are imported by hand in phpMyAdmin, in order.

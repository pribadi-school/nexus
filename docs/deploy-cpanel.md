# Deploy Nexus to cPanel

Target address: `https://nexus.pribadidepok.sch.id`

| Setting | Value |
| --- | --- |
| GitHub repository | `https://github.com/pribadi-school/nexus.git` |
| Branch | `main` |
| Repository folder | `/home/pribadisch/repositories/nexus` |
| Website document root | `/home/pribadisch/public_html/nexus` |

The repository stays under `/home/pribadisch/repositories`. This hosting account
requires website roots inside `public_html`. A publishing command copies only
public assets there and creates an entry point loading the app from the
repository. Settings, school data and uploaded videos stay outside the website
root. The school's main website is separate.

## 1. Clone the code

In **cPanel → Terminal**, run:

```bash
mkdir -p /home/pribadisch/repositories
git clone https://github.com/pribadi-school/nexus.git /home/pribadisch/repositories/nexus
```

The repository is public, so the HTTPS clone URL needs no GitHub password. If
already cloned, use `git -C /home/pribadisch/repositories/nexus pull --ff-only origin main`
instead. Terminal clones do not automatically appear in cPanel's Git interface.

## 2. Create the subdomain

Open **cPanel → Domains → Create a New Domain**.

- Domain: `nexus.pribadidepok.sch.id`.
- Disable **Share document root** with the primary domain.
- Document root: `nexus` after the fixed `/public_html/` prefix.

Confirm the resulting absolute path is `/home/pribadisch/public_html/nexus`.
Keep the Git repository in the requested folder.

If the subdomain already exists, manage it rather than creating a duplicate.

## 3. Set DNS and HTTPS

At the authoritative DNS provider, ensure the `nexus` record points to this
hosting account's server IP (shown in cPanel). Use cPanel's Zone Editor only
if this server manages the domain's DNS. Do not change the main domain's records.

In **cPanel → SSL/TLS Status**, issue/run AutoSSL for the subdomain once DNS
resolves. Then enable **Force HTTPS Redirect** for this domain in **Domains**.

## 4. Select PHP

Use **MultiPHP Manager** (or the host's PHP selector) to select PHP 8.2 or newer
for this subdomain. Required extensions: `pdo_mysql`, `mbstring`, `fileinfo`;
enable `zip` and `SimpleXML` for XLSX imports. No Composer or Node is needed.

The terminal CLI must also use PHP 8.2+. Run `php -v`, then publish:

```bash
cd /home/pribadisch/repositories/nexus
php bin/publish-cpanel.php
```

If `php -v` is older, use the host's PHP 8.2+ CLI path instead. The publishing
script stops on an older PHP version. Its default target is exactly
`/home/pribadisch/public_html/nexus`. It preserves host-managed `.htaccess`
settings and `.well-known`, copies public assets, and writes the app entry
point. It does not copy `.env`, private code, media, sessions or database files.
It can publish before the database is configured; the site is ready only after
the remaining steps are complete.

For 500 MB video uploads, set the following for the subdomain in the PHP INI
editor, subject to your hosting limits:

```ini
upload_max_filesize = 500M
post_max_size = 512M
max_input_time = 600
max_execution_time = 600
```

## 5. Create a database

Open **Database Wizard / MySQL Database Wizard**. Create a database and a
dedicated user, then grant that user **ALL PRIVILEGES** on this database only.
Record the full names cPanel shows, including its account prefix.

For example, both may be `pribadisch_nexus`, but use the actual generated names.
Keep the database password private.

## 6. Create the production .env

In **File Manager**, enable **Show Hidden Files**. Inside
`/home/pribadisch/repositories/nexus`, copy `.env.example` to `.env`.
Edit these values, filling in the database credentials privately:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://nexus.pribadidepok.sch.id
APP_TIMEZONE=Asia/Jakarta
APP_PASSWORD="a-long-private-password-you-choose"
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=pribadisch_nexus
DB_USERNAME=pribadisch_nexus
DB_PASSWORD="your-database-password"
SIGNAGE_MAX_UPLOAD_MB=500
```

Do not copy the local development `.env`. Restrict the production file to the
account/PHP process (usually permission `600`; host configuration may vary).
Do not post credentials in GitHub or screenshots.

## 7. Transfer the current database and videos

Git transfers code only. The edited students, serial numbers, camera locations,
notes, playlists and screens live in the local database. Uploaded MP4s live in
`storage/signage/media/`. Transfer both separately to preserve the current setup.

- Export the current local database as an SQL file, kept outside Git.
- In cPanel phpMyAdmin, select the new empty database and import that export.
- Upload the existing MP4s, keeping their generated filenames, into
  `/home/pribadisch/repositories/nexus/storage/signage/media/`.
- Do not transfer local session files or logs.

A full database export already includes the schema and migration history. Do
not import the migrations again or load sample data after restoring it. If the
destination already has real data, back it up and plan the import before making
changes; do not import a replacement dump over it blindly.

For a deliberately empty installation instead, import migrations `001` through
`007` in order, or run `php bin/migrate.php` with a PHP 8.2+ CLI. This creates
the schema and the inventory supplied in migrations, but does not transfer the
current edited school data. Do not use `--seed` for production.

## 8. Check storage and test

PHP needs write access to `storage/sessions`, `storage/logs`, `storage/cache`,
and `storage/signage/media`. Use account-owned directories; `755` normally
works when PHP runs as the account user. Use `775` only when the host's group
configuration requires it. Do not use `777`.

Visit the HTTPS address and sign in with the production shared password.
Confirm the student counts/serials, CCTV locations and Signage content match
the local app. Test video upload and seeking, thumbnails, playlist preview,
and a screen player. Test a website item separately, since some websites
block embedding. Copy production player addresses again; old `localhost`
addresses do not work on the TVs. Pair Android TVs to the production URL.

## Later updates

After pushing code to GitHub, run these in cPanel Terminal:

```bash
cd /home/pribadisch/repositories/nexus
git pull --ff-only origin main && php bin/publish-cpanel.php
```

No **Deploy HEAD Commit** step or `.cpanel.yml` is needed. Avoid editing tracked
source files in cPanel. `.env` and media are ignored by Git and remain on the
server. Apply only pending migrations after backing up the database. Back up
the database and media regularly, separately from Git.

Reference: [cPanel Git Version Control](https://docs.cpanel.net/cpanel/files/git-version-control/)
and [domain document roots](https://docs.cpanel.net/cpanel/domains/domains/manage-the-domain/).

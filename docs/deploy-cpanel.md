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

The terminal CLI must also use PHP 8.2+. On this account, the verified CLI is
`/opt/alt/php82/usr/bin/php` (8.2.34). The default `php` and the `ea-php82` wrapper
resolved to the account's PHP 8.1, so use this explicit executable:

```bash
cd /home/pribadisch/repositories/nexus
/opt/alt/php82/usr/bin/php bin/publish-cpanel.php
```

If `php -v` is older, use the host's PHP 8.2+ CLI path instead. The publishing
script stops on an older PHP version. Its default target is exactly
`/home/pribadisch/public_html/nexus`. It preserves host-managed `.htaccess`
settings and `.well-known`, copies public assets, and writes the app entry
point. It does not copy `.env`, private code, media, sessions or database files.
It can publish before the database is configured; the site is ready only after
the remaining steps are complete.

On this hosting account MultiPHP Manager is unavailable. The working Nexus-only
web override is the following block in `/home/pribadisch/public_html/nexus/.htaccess`:

```apache
<FilesMatch "\.php$">
  SetHandler application/x-httpd-alt-php82___lsphp
</FilesMatch>
```

The publisher preserves this block. The account-wide PHP setting stays 8.1.
Confirm web PHP separately from terminal PHP. If diagnostic scripts are used,
remove them after verifying the version and extensions; do not publish phpinfo.

The publisher now applies 500 MB upload settings to the Nexus subdomain through
its managed `.htaccess` block (LiteSpeed/LSAPI) and `.user.ini` (CGI/FastCGI).
It preserves the host's PHP handler and unrelated settings. If your provider
locks these values, set them for the subdomain in the PHP INI editor or ask hosting support:

```ini
upload_max_filesize = 500M
post_max_size = 512M
max_input_time = 1800
max_execution_time = 600
```

Reload Add content and check that it says **Up to 500 MB per upload**.
CGI/FastCGI may cache `.user.ini` for five minutes. A smaller displayed limit
means PHP is still enforcing that lower cap. An upstream web-server/proxy cap
may also need the provider's help. No database migration or APK update is needed.

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

After pushing code to GitHub, run this one command in cPanel Terminal:

```bash
bash ~/repositories/nexus/update.sh
```

It pulls the code, publishes the CSS, JS and icons into `public_html/nexus`
with a PHP 8.2+ CLI it finds itself (`/opt/alt/php82/usr/bin/php` on this
account; set `NEXUS_PHP` to override), and lists migrations, where `[ ]`
means not yet imported. Both steps matter: the PHP code is read straight from
the repository, but the browser loads CSS and JS from `public_html/nexus`. A
pull without publishing shows new pages with old styles. That happened with the
signage screen editor in October 2026.

The manual equivalent is:

```bash
cd /home/pribadisch/repositories/nexus
git pull --ff-only origin main && /opt/alt/php82/usr/bin/php bin/publish-cpanel.php
```

No **Deploy HEAD Commit** step or `.cpanel.yml` is needed. Avoid editing tracked
source files in cPanel. `.env` and media are ignored by Git and remain on the
server. Apply only pending migrations after backing up the database. Back up
the database and media regularly, separately from Git.

Reference: [cPanel Git Version Control](https://docs.cpanel.net/cpanel/files/git-version-control/)
and [domain document roots](https://docs.cpanel.net/cpanel/domains/domains/manage-the-domain/).

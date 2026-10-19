# Google Admin connection for Chromebooks

Nexus matches a Chromebook's complete serial number to one enrolled Google
ChromeOS device. Reports appear inside Details → Google Admin. Local student
assignments, repair status, and model information are never overwritten.

## Easy setup: six steps

Have your school's Google Workspace super administrator and cPanel access ready.
Keep Google Cloud, Google Admin, and cPanel open in separate browser tabs. Start
with reading device details; OU changes can be enabled afterwards.

### 1. Turn on Google's APIs

Open [Google Cloud](https://console.cloud.google.com/). Select your school's
project, or create one called **Nexus**.

Go to **APIs & Services → Library**. Search for **Admin SDK API**, then click
**Enable**. Repeat for **Chrome Management API**.

### 2. Download the connection file

In Google Cloud, open **APIs & Services → Credentials → Create credentials →
Service account**. Name it **nexus** and finish creating it.

Open the service account → **Keys → Add key → Create new key → JSON → Create**.
Rename the downloaded file to `google-service-account.json`.

Open the file and find `client_id`. Keep that long number for step 3. This is
a private key file: keep it out of public_html, Git, and chat.

### 3. Allow Nexus to read your devices

Sign into [Google Admin](https://admin.google.com/) as a super administrator.
Open **Security → Access and data control → API controls → Manage domain-wide
delegation → Add new**.

Paste the numeric `client_id` from step 2 into **Client ID**. Paste this entire
line into **OAuth scopes**, then click **Authorize**:

```text
https://www.googleapis.com/auth/admin.directory.device.chromeos.readonly,https://www.googleapis.com/auth/chrome.management.telemetry.readonly
```

Use the client ID number, not the service account's email address.

### 4. Upload the file in cPanel

Open **cPanel → File Manager**. Upload `google-service-account.json` here:

```text
/home/pribadisch/repositories/nexus/storage/private
```

Set the uploaded file's permissions to **600** (only your hosting account can
read it). The private folder is ignored by Git.

### 5. Enter the connection values

In Google Admin, open **Account → Account settings → Profile**. Copy the
**Customer ID**. Use that actual ID, not `my_customer`.

In cPanel File Manager, enable **Settings → Show Hidden Files**. Edit
`/home/pribadisch/repositories/nexus/.env` and add or update:

```dotenv
GOOGLE_SERVICE_ACCOUNT_FILE=/home/pribadisch/repositories/nexus/storage/private/google-service-account.json
GOOGLE_ADMIN_EMAIL=your-admin@pribadidepok.sch.id
GOOGLE_CUSTOMER_ID=YOUR_CUSTOMER_ID
GOOGLE_TELEMETRY_ENABLED=true
```

Replace the example email with your real school administrator email. Replace
`YOUR_CUSTOMER_ID` with the ID you copied, then save.

The email must belong to an administrator who can view ChromeOS devices and
has **Chrome Management → Telemetry API → Devices / Network report** privileges.

### 6. Finish and test

After the latest Nexus code has been deployed, open **cPanel → Terminal** and run:

```bash
cd ~/repositories/nexus
/opt/alt/php82/usr/bin/php bin/migrate.php
```

In Nexus, open **Chromebooks → a Chromebook → Details → Google Admin → Refresh**.
Its serial number must match the enrolled Google device. The setup page is at
`/chromebooks/google`.

The cPanel update script only lists pending migrations; the command above
applies them. You can also import `database/migrations/009_chromebook_google.sql`
in phpMyAdmin.

### Testing on your computer instead?

Complete Google steps 1–3. Place the JSON file in
`F:\Nexus Project\storage\private` and edit `F:\Nexus Project\.env`.
Use the same email and Customer ID values, with this file path:

```dotenv
GOOGLE_SERVICE_ACCOUNT_FILE=F:/Nexus Project/storage/private/google-service-account.json
```

Restart the local Nexus server with `bin/dev.ps1`; it applies the migration.
Open a Chromebook and click **Refresh**.

### If something does not work

- **Google denied access:** check the client ID, scopes, administrator email,
  and that administrator's ChromeOS device and telemetry privileges under
  **Google Admin → Admin roles**.
- **No matching device:** compare the complete serial number in Nexus and
  Google Admin.
- **Network details missing:** enable device network reporting
  (`ReportNetworkStatus`) for the device's OU. Availability also depends on
  device eligibility and affiliated users. Set `GOOGLE_TELEMETRY_ENABLED=false`
  if you only want Directory reports.
- **cURL or OpenSSL error:** enable these PHP extensions in cPanel, or ask
  hosting support. Outbound HTTPS and valid CA certificates are also required;
  keep certificate verification enabled.

## Enable OU changes

OU changes are disabled while `GOOGLE_OU_PASSWORD_HASH` is empty. Nexus uses one
shared login password plus self-entered names, so names alone cannot authorize
remote device changes. Set a **different management password** known only to staff
who may move devices. Each move requires this password and an explicit checkbox
acknowledging that different policies can apply. Use HTTPS on the live site.

Authorize these additional domain-wide delegation scopes and give the delegated
administrator permission to move ChromeOS devices between the relevant OUs:

```text
https://www.googleapis.com/auth/admin.directory.orgunit.readonly
https://www.googleapis.com/auth/admin.directory.device.chromeos
```

Generate a hash in cPanel Terminal without putting the password in command history
(replace `php` with your PHP 8.2+ executable if necessary):

```bash
read -rs -p "New OU management password: " nexus_ou_password
printf '\n'
printf '%s' "$nexus_ou_password" | php -r 'echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT), PHP_EOL;'
unset nexus_ou_password
```

Copy the resulting hash into `GOOGLE_OU_PASSWORD_HASH` in `.env`. Never use the
hash as the password. Keep both the hash and key out of Git.

Nexus loads the OU list when Change organizational unit opens. It verifies the
serial/device identity and current OU again before writing, rejects stale forms,
and records the previous OU, target OU, signed-in name, request time, and outcome
in `chromebook_google_moves`. The name is an attribution from the existing shared
login, not a verified individual Google identity. Five wrong management passwords
from an IP block further attempts for 15 minutes. The management password is never
stored in a session or audit record.

An accepted move means Google accepted the OU assignment; device policies apply
on the device's next synchronization. Timeouts and server failures are marked
unconfirmed. Nexus never automatically retries an OU write: refresh and check the
actual OU first. A pending audit entry may indicate the request was interrupted;
verify it in Google Admin before retrying.

## Freshness and performance

- Page rendering only reads the local report cache. It does not wait for Google.
- With JavaScript enabled, an open Chromebook page refreshes in the background on
  entry and every five minutes while visible. Refresh manually checks sooner,
  with a 30-second minimum interval to limit duplicate requests. Failed requests
  retain the saved report and show an error; automatic retries wait at least a
  minute. Per-device database locks prevent overlapping refreshes and moves.
- Leaving the page cancels the browser request and polling timer. PHP releases
  its session lock before contacting Google, so navigating elsewhere stays fast.
- Last policy sync is Google's policy synchronization timestamp, not proof of
  current connectivity. Network state is explicitly labeled as reported state.
- Google documents default network collection every 60 minutes and reporting
  every three hours, controlled by policy. Nexus cannot force a new device report.
- LAN/connection/gateway/signal come from the latest telemetry report. When LAN
  telemetry is missing, Directory's last-known LAN IP is labeled separately.
  WAN IP comes from Directory's last-known network; its timestamp is not inferred
  from the telemetry report or Nexus's refresh time.
- Location is the administrator's annotation, not GPS. Missing reports remain
  “Not reported”; they never become a fabricated online/offline status.
- No Google credentials, access tokens, full user history, or raw API errors are
  returned to browsers. The cache stores only the fields used by the Details UI.
- All signed-in Nexus staff can read the Google device reports, like the existing
  inventory details. Delete the connection values to disable access; remove the
  delegation grant in Google Admin to revoke the service account's authority.

## Validation

Offline client/normalization checks: `php tests/google-admin.php`.
Local database integration checks (rolled back): `php tests/google-admin-db.php`.
These use an ephemeral test key and fake Google responses. They never contact
Google or move a real device. Live authorization and report availability must be
verified after configuring the school's credentials.

References: [authorization setup](https://developers.google.com/chrome/management/guides/setup),
[service account JWT authorization](https://developers.google.com/identity/protocols/oauth2/service-account),
[Directory device fields](https://developers.google.com/workspace/admin/directory/reference/rest/v1/chromeosdevices),
[telemetry reports](https://developers.google.com/chrome/management/reference/rest/v1/customers.telemetry.devices),
[OU moves](https://developers.google.com/workspace/admin/directory/reference/rest/v1/chromeosdevices/moveDevicesToOu).

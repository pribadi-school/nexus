<a class="back-link" href="/chromebooks"><?= icon('chevron-left', 'icon--sm') ?>Chromebooks</a>
<header class="page-header"><div><h1 class="page-title">Connect Google Admin</h1><p class="page-subtitle">Follow these six steps once. Start with viewing device details; enable OU changes afterwards.</p></div></header>
<section class="card form-card">
  <div class="card__header"><h2 class="card__title"><?= $googleConfigured ? 'Settings saved — test the connection in step 6' : 'Before you start' ?></h2></div>
  <div class="card__body google-setup">
    <p>You need your school’s Google Workspace super administrator and access to cPanel. Keep Google Cloud, Google Admin, and cPanel open in separate tabs.</p>
    <section class="google-setup__step" aria-labelledby="google-step-1">
      <h2 class="card__title" id="google-step-1">1. Turn on Google’s APIs</h2>
      <p>Open <a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">Google Cloud</a>. Select your school’s project, or create one named <strong>Nexus</strong>.</p>
      <p>Go to <strong>APIs &amp; Services → Library</strong>. Search for each name below and click <strong>Enable</strong>:</p>
      <ul><li>Admin SDK API</li><li>Chrome Management API</li></ul>
    </section>
    <section class="google-setup__step" aria-labelledby="google-step-2">
      <h2 class="card__title" id="google-step-2">2. Download the connection file</h2>
      <p>In Google Cloud, go to <strong>APIs &amp; Services → Credentials → Create credentials → Service account</strong>. Name it <strong>nexus</strong> and finish creating it.</p>
      <p>Open that service account. Under <strong>Keys</strong>, choose <strong>Add key → Create new key → JSON → Create</strong>.</p>
      <p>Rename the downloaded file to <code>google-service-account.json</code>. Find its <code>client_id</code> value—you need that long number in step 3.</p>
      <p class="field__hint">This file is a private key. Keep it out of public_html and Git.</p>
    </section>
    <section class="google-setup__step" aria-labelledby="google-step-3">
      <h2 class="card__title" id="google-step-3">3. Give Nexus permission to read devices</h2>
      <p>Open <a href="https://admin.google.com/" target="_blank" rel="noopener noreferrer">Google Admin</a> with your super administrator account.</p>
      <p>Go to <strong>Security → Access and data control → API controls → Manage domain-wide delegation → Add new</strong>.</p>
      <p>For <strong>Client ID</strong>, paste the number from step 2. For <strong>OAuth scopes</strong>, paste this entire line, then click <strong>Authorize</strong>:</p>
      <pre>https://www.googleapis.com/auth/admin.directory.device.chromeos.readonly,https://www.googleapis.com/auth/chrome.management.telemetry.readonly</pre>
      <p class="field__hint">Use the numeric client ID, not the service account’s email address. Nexus will read devices using the administrator email you enter in step 5.</p>
    </section>
    <section class="google-setup__step" aria-labelledby="google-step-4">
      <h2 class="card__title" id="google-step-4">4. Put the file on your cPanel server</h2>
      <p>In <strong>cPanel → File Manager</strong>, open this folder and upload <code>google-service-account.json</code>:</p>
      <pre>/home/pribadisch/repositories/nexus/storage/private</pre>
      <p>Set the file’s permissions to <strong>600</strong> so only your hosting account can read it.</p>
    </section>
    <section class="google-setup__step" aria-labelledby="google-step-5">
      <h2 class="card__title" id="google-step-5">5. Fill in three connection values</h2>
      <p>In Google Admin, open <strong>Account → Account settings → Profile</strong> and copy your <strong>Customer ID</strong>.</p>
      <p>In cPanel File Manager, enable <strong>Settings → Show Hidden Files</strong>. Open <code>/home/pribadisch/repositories/nexus/.env</code> and add or update these lines:</p>
      <pre>GOOGLE_SERVICE_ACCOUNT_FILE=/home/pribadisch/repositories/nexus/storage/private/google-service-account.json
GOOGLE_ADMIN_EMAIL=your-admin@pribadidepok.sch.id
GOOGLE_CUSTOMER_ID=YOUR_CUSTOMER_ID
GOOGLE_TELEMETRY_ENABLED=true</pre>
      <p>Replace <code>your-admin@pribadidepok.sch.id</code> with your real school administrator email, and <code>YOUR_CUSTOMER_ID</code> with the ID you copied. Save the file.</p>
      <p class="field__hint">The email must belong to an administrator who can view ChromeOS devices and network telemetry reports.</p>
    </section>
    <section class="google-setup__step" aria-labelledby="google-step-6">
      <h2 class="card__title" id="google-step-6">6. Finish and test</h2>
      <p>After the latest Nexus code is deployed, open <strong>cPanel → Terminal</strong> and run:</p>
      <pre>cd ~/repositories/nexus
/opt/alt/php82/usr/bin/php bin/migrate.php</pre>
      <p>In Nexus, open <strong>Chromebooks → a Chromebook → Google Admin (below Notes) → Refresh</strong>.</p>
      <p>You should see its Google details. The serial number in Nexus must match the enrolled device in Google Admin.</p>
    </section>
    <details class="google-setup__extra">
      <summary>Testing on this computer instead?</summary>
      <div class="google-setup__content">
        <p>Complete Google steps 1–3 above. For steps 4–5, place the JSON file in <code>F:\Nexus Project\storage\private</code> and edit <code>F:\Nexus Project\.env</code>.</p>
        <p>Use this file path, plus your real administrator email and Customer ID:</p>
        <pre>GOOGLE_SERVICE_ACCOUNT_FILE=F:/Nexus Project/storage/private/google-service-account.json</pre>
        <p>Restart your local Nexus server, open a Chromebook, and click <strong>Refresh</strong>. The local startup script applies the database migration.</p>
      </div>
    </details>
    <details class="google-setup__extra">
      <summary>Optional: enable changing OU through Nexus (<?= $googleCanMove ? 'enabled' : 'disabled' ?>)</summary>
      <div class="google-setup__content">
        <p>An OU is an organizational unit in Google Admin. Moving a device can change the policies it receives.</p>
        <p><strong>A. Add write permission.</strong> Return to the domain-wide delegation entry from step 3. Edit its scopes, keep the existing two, and add these two:</p>
        <pre>https://www.googleapis.com/auth/admin.directory.orgunit.readonly,https://www.googleapis.com/auth/admin.directory.device.chromeos</pre>
        <p>The administrator email from step 5 also needs permission to move ChromeOS devices.</p>
        <p><strong>B. Set a separate management password.</strong> In cPanel Terminal, run this block. Type your new password when prompted; the characters stay hidden.</p>
        <pre>read -rs -p "New OU management password: " nexus_ou_password
printf '\n'
printf '%s' "$nexus_ou_password" | /opt/alt/php82/usr/bin/php -r 'echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT), PHP_EOL;'
unset nexus_ou_password</pre>
        <p>Copy the long result and add it to <code>.env</code> like this, replacing <code>PASTE_THE_RESULT_HERE</code>:</p>
        <pre>GOOGLE_OU_PASSWORD_HASH=PASTE_THE_RESULT_HERE</pre>
        <p><strong>C. Move a device.</strong> Refresh its Google details in Nexus, open <strong>Change organizational unit</strong>, choose an OU, enter the management password, confirm, and click <strong>Move Chromebook</strong>.</p>
      </div>
    </details>
    <details class="google-setup__extra">
      <summary>If something does not work</summary>
      <div class="google-setup__content">
        <ul>
          <li><strong>Google denied access:</strong> check the client ID, scopes, and administrator email. In Google Admin → Admin roles, check that this administrator has ChromeOS device access and Telemetry API device/network report privileges.</li>
          <li><strong>No matching device:</strong> compare the complete serial number in Nexus and Google Admin.</li>
          <li><strong>Details appear but network fields are missing:</strong> enable network status reporting for the device’s OU in Google Admin. Reports depend on Google’s reporting policies and device eligibility.</li>
          <li><strong>cURL or OpenSSL error:</strong> enable those PHP extensions in cPanel, or ask your hosting support to enable them.</li>
        </ul>
        <p>Google reports can be delayed. Nexus checks every five minutes while the device page is open; it displays Google’s report time separately. Location is the administrator’s label.</p>
        <p><a href="https://developers.google.com/chrome/management/guides/setup" target="_blank" rel="noopener noreferrer">Open Google’s official setup guide</a></p>
      </div>
    </details>
  </div>
</section>

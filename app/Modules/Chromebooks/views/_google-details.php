<?php
/** Google reports supplement the local inventory; they never overwrite assignments or repair status. */
$googleId = (int) $chromebook['id'];
$report = $googleCache['report'] ?? null;
$googleReady = $googleConfigured && trim((string) $chromebook['serial_number']) !== '';
$googleTime = static function (?string $value): string {
    if (!$value || strtotime($value) === false) return 'Not reported';
    return format_datetime($value);
};
$googleLabel = static fn (?string $value): string => $value ? ucwords(strtolower(str_replace('_', ' ', $value))) : 'Not reported';
?>
<section class="card google-device" id="google-admin" aria-labelledby="google-admin-title" data-google-device data-google-refresh="/chromebooks/<?= $googleId ?>/google/refresh"<?= $googleReady ? ' data-google-auto-refresh' : '' ?>>
  <div class="google-device__header">
    <h2 class="card__title" id="google-admin-title">Google Admin</h2>
    <?php if ($googleReady): ?>
      <form method="post" action="/chromebooks/<?= $googleId ?>/google/refresh" data-google-refresh-form>
        <?= csrf_field() ?><input type="hidden" name="force" value="1">
        <button class="btn btn--secondary btn--sm" type="submit"><?= icon('refresh-cw', 'icon--sm') ?>Refresh</button>
      </form>
    <?php endif ?>
  </div>
  <p class="google-device__message" data-google-message role="status" aria-live="polite"></p>
  <?php if (!$googleConfigured): ?>
    <p class="google-device__hint">Connect your school’s Google Admin account to view device reports here.</p>
    <p class="google-device__hint"><a href="/chromebooks/google">Set up Google Admin</a></p>
  <?php elseif (!$googleReady): ?>
    <p class="google-device__hint">Add the serial number to match this Chromebook with its enrolled Google device.</p>
  <?php else: ?>
    <?php if (!empty($googleCache['error'])): ?>
      <p class="google-device__warning"><?= e($googleCache['error']) ?><?= $report ? ' The saved report below may be out of date.' : '' ?></p>
    <?php endif ?>
    <?php if (!$report): ?>
      <p class="google-device__hint">No Google report saved yet. Refresh to retrieve the device details.</p>
    <?php else: ?>
      <dl class="details google-device__details">
        <div><dt>Google status</dt><dd><?= e($googleLabel($report['status'])) ?></dd></div>
        <div><dt>Last policy sync</dt><dd><?= e($googleTime($report['last_sync'])) ?></dd></div>
        <div><dt>ChromeOS</dt><dd><?= e($report['os_version'] ?: 'Not reported') ?></dd></div>
        <div><dt>Google model</dt><dd><?= e($report['model'] ?: 'Not reported') ?></dd></div>
        <div><dt>Location</dt><dd><?= e($report['location'] ?: 'Not set') ?><span class="google-device__sub">Administrator’s location label</span></dd></div>
        <div><dt>Organizational unit</dt><dd><?= e($report['ou'] ?: 'Not reported') ?></dd></div>
        <div><dt>Reported network</dt><dd><?= e($googleLabel($report['network_state'])) ?></dd></div>
        <div><dt>Connection type</dt><dd><?= e($googleLabel($report['network_type'])) ?></dd></div>
        <div><dt>LAN IP</dt><dd><?= e($report['lan_ip'] ?: 'Not reported') ?><?php if ($report['lan_ip'] && $report['lan_source'] === 'directory'): ?><span class="google-device__sub">Last known IP from Google Directory</span><?php endif ?></dd></div>
        <div><dt>WAN IP</dt><dd><?= e($report['wan_ip'] ?: 'Not reported') ?><span class="google-device__sub">Last known IP from Google Directory</span></dd></div>
        <div><dt>Gateway</dt><dd><?= e($report['gateway'] ?: 'Not reported') ?></dd></div>
        <div><dt>Wi-Fi signal</dt><dd><?= $report['signal_dbm'] !== null ? e($report['signal_dbm']) . ' dBm' : 'Not reported' ?></dd></div>
        <div><dt>Network reported</dt><dd><?= e($googleTime($report['network_reported_at'])) ?></dd></div>
        <div><dt>Nexus refreshed</dt><dd><?= !empty($googleCache['refreshed_at']) ? e($googleTime($googleCache['refreshed_at'])) : 'Refresh needed to verify OU' ?></dd></div>
      </dl>
      <?php if (!empty($report['telemetry_warning'])): ?><p class="google-device__warning"><?= e($report['telemetry_warning']) ?></p><?php endif ?>
      <p class="google-device__hint">Google reports can be delayed. Last policy sync and reported network state do not confirm that the Chromebook is online now.</p>
      <?php if ($googleCanMove && $report['ou']): ?>
        <details class="google-device__ou" data-google-ou data-units-url="/chromebooks/<?= $googleId ?>/google/ou">
          <summary>Change organizational unit</summary>
          <p class="field__hint" data-google-ou-status role="status"></p>
          <?= partial('Chromebooks::_google-ou-form', ['chromebook' => $chromebook, 'currentOu' => $report['ou'], 'units' => null]) ?>
          <noscript><a href="/chromebooks/<?= $googleId ?>/google/ou">Open the OU change form</a></noscript>
        </details>
      <?php elseif (!$googleCanMove): ?>
        <p class="google-device__hint">OU changes are disabled. <a href="/chromebooks/google">Connection settings</a></p>
      <?php endif ?>
    <?php endif ?>
    <?php if ($googleMoves !== []): ?>
      <details class="google-device__ou">
        <summary>Recent OU changes</summary>
        <ol class="google-device__moves">
          <?php foreach ($googleMoves as $move): ?>
            <li><strong><?= e($move['from_ou']) ?> → <?= e($move['to_ou']) ?></strong><span class="google-device__sub"><?= e($googleLabel($move['state'])) ?> · <?= e($googleTime($move['requested_at'])) ?> · <?= e($move['requested_by_name']) ?></span></li>
          <?php endforeach ?>
        </ol>
      </details>
    <?php endif ?>
  <?php endif ?>
</section>

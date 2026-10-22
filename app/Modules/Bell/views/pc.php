<?php
/**
 * @var array<string,mixed> $pc
 * @var array<string,mixed> $status
 * @var bool $online
 * @var list<array<string,mixed>> $events
 * @var string $serverUrl
 * @var list<array<string,mixed>> $zones
 */
$hasKey = ($pc['token_hash'] ?? '') !== '' && $pc['token_hash'] !== null;
$kinds = ['played' => ['Rang', 'success'], 'skipped' => ['Skipped', 'warning'], 'failed' => ['Failed', 'problem'], 'warning' => ['Warning', 'warning'], 'info' => ['Info', 'quiet']];
$deviceStatus = is_array($status['zones'] ?? null) ? $status['zones'] : [];
?>
<header class="page-header"><div><h1 class="page-title">Bell</h1><p class="page-subtitle">The Windows PC that plays the bells through the speakers.</p></div></header>
<?= partial('Bell::_tabs', ['tab' => 'pc']) ?>

<section class="card form-card">
  <div class="card__header">
    <h2 class="card__title">Bell PC</h2>
    <?php if (!$hasKey): ?><span class="chip chip--quiet">Not connected</span>
    <?php elseif ($online): ?><span class="chip chip--success">Online</span>
    <?php else: ?><span class="chip chip--problem">Offline</span><?php endif ?>
  </div>
  <?php if ($hasKey): ?>
    <dl class="bell-facts">
      <div><dt>Last check-in</dt><dd><?= ($pc['last_seen_at'] ?? null) ? e(time_ago($pc['last_seen_at'])) . ' (' . e(format_datetime($pc['last_seen_at'])) . ')' : 'Never' ?></dd></div>
      <div><dt>PC</dt><dd><?= e($pc['pc_name'] ?? '-') ?: '-' ?></dd></div>
      <div><dt>App version</dt><dd><?= e($pc['app_version'] ?? '-') ?: '-' ?></dd></div>
    </dl>
    <?php if (!$online && ($pc['last_seen_at'] ?? null) !== null): ?>
      <div class="notice notice--warning" role="status"><?= icon('triangle-alert') ?><div>The bell PC has not checked in for a while. Bells still ring from its last copy of the schedule, but new changes will not reach it until it is back online.</div></div>
    <?php endif ?>
  <?php endif ?>

  <?php if ($deviceStatus !== []): ?>
    <h3 class="bell-subtitle">Sound devices (as reported by the PC)</h3>
    <ul class="bell-settings-list">
      <?php foreach ($zones as $zone): $s = $deviceStatus[(string) $zone['id']] ?? null; if (!is_array($s)) continue; ?>
        <li class="bell-settings-row bell-zone bell-zone--<?= e($zone['color']) ?>">
          <span class="bell-settings-row__rename"><span class="bell-dot" aria-hidden="true"></span> <strong><?= e($zone['name']) ?></strong> · <?= e((string) ($s['device'] ?? 'no device chosen')) ?></span>
          <span class="chip chip--<?= !empty($s['ok']) ? 'success' : 'problem' ?>"><?= !empty($s['ok']) ? 'Ready' : 'Not available' ?></span>
        </li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>

  <h3 class="bell-subtitle">Connect the PC</h3>
  <ol class="bell-steps">
    <li>Install the Nexus Bell app on the bell PC (see <code>bell-pc/README.md</code>).</li>
    <li>Make a connection key here and copy it. It is shown only once.</li>
    <li>In the app's <strong>Koneksi</strong> box, enter the server address <code><?= e($serverUrl) ?></code> and the key, then press <strong>Simpan &amp; sambungkan</strong>.</li>
    <li>Pick the sound device of each floor in the app and press <strong>Tes perangkat</strong>.</li>
  </ol>
  <div class="form-actions">
    <?php if ($hasKey): ?>
      <form method="post" action="/bell/pc/key/revoke" data-confirm="Remove the connection key?" data-confirm-text="The bell PC keeps ringing from its last copy, but it can no longer download changes." data-confirm-button="Remove key" data-confirm-tone="danger"><?= csrf_field() ?><button class="btn btn--secondary" type="submit">Remove key</button></form>
    <?php endif ?>
    <form method="post" action="/bell/pc/key"<?= $hasKey ? ' data-confirm="Make a new key?" data-confirm-text="The old key stops working. Enter the new key in the bell app." data-confirm-button="Make new key"' : '' ?> data-busy-on-submit><?= csrf_field() ?>
      <button class="btn btn--primary" type="submit"><?= icon('key-round', 'icon--sm') ?><?= $hasKey ? 'Make a new key' : 'Make connection key' ?></button>
    </form>
  </div>
</section>

<section class="card">
  <div class="card__header"><h2 class="card__title">Recent bells</h2><span class="chip chip--quiet num"><?= count($events) ?></span></div>
  <?php if ($events === []): ?>
    <?= partial('partials/empty-state', ['icon' => 'history', 'title' => 'Nothing reported yet', 'text' => 'Every bell the PC rings, skips or fails will be listed here.', 'compact' => true]) ?>
  <?php else: ?>
    <ul class="bell-events">
      <?php foreach ($events as $event): [$label, $tone] = $kinds[$event['kind']] ?? [$event['kind'], 'quiet']; ?>
        <li class="bell-event">
          <span class="chip chip--<?= e($tone) ?>"><?= e($label) ?></span>
          <time class="bell-event__time num" datetime="<?= e(str_replace(' ', 'T', (string) $event['occurred_at'])) ?>"><?= e(date('D j M, H:i:s', strtotime((string) $event['occurred_at']))) ?></time>
          <span class="bell-event__zone"><?= e($event['zone_name'] ?? '') ?></span>
          <span class="bell-event__msg"><?= e($event['message']) ?></span>
        </li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>
</section>

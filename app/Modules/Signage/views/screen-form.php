<?php
$adding = $screen === null;
$base = $adding ? '/signage/screens' : '/signage/screens/' . (int) $screen['id'];
$field = static fn (string $name): string => $errors[$name] ?? '';
$invalid = static fn (string $name): string => $field($name) ? ' aria-invalid="true" aria-describedby="' . $name . '-error"' : '';
$error = static fn (string $name): string => $field($name) ? '<p class="field__error" id="' . $name . '-error">' . e($field($name)) . '</p>' : '';
$playerUrl = !$adding ? rtrim((string) config('app.url', ''), '/') . '/signage/player/' . $screen['token'] : '';
?>
<a class="back-link" href="/signage/screens"><?= icon('chevron-left', 'icon--sm') ?>Screens</a>
<header class="page-header"><div><h1 class="page-title"><?= $adding ? 'Add a screen' : e($screen['name']) ?></h1><p class="page-subtitle">Choose what plays and how it fits the display.</p></div></header>
<?= partial('Signage::_tabs', ['tab' => 'screens']) ?>

<?php if (!$adding): ?>
<p class="signage-help"><a href="/signage/pair?screen=<?= (int) $screen['id'] ?>">Pair the Nexus Android TV app with this screen</a> to download videos for offline playback.</p>
<?php endif ?>

<?php if (!$adding): ?>
  <section class="card signage-player-address">
    <div class="signage-section-head"><h2 class="card__title">Player address</h2><?= partial('Signage::_screen-status', ['screen' => $screen]) ?></div>
    <p class="signage-help">Open this address in the TV browser, or add it as a website in AbleSign. Keep the address private: it gives access to this screen's playlist.</p>
    <div class="signage-copy">
      <label class="visually-hidden" for="player-address">Player address</label>
      <input class="input" type="text" id="player-address" value="<?= e($playerUrl) ?>" readonly spellcheck="false" data-player-address>
      <button class="btn btn--secondary" type="button" data-copy-player hidden>Copy address</button>
      <a class="btn btn--secondary" href="<?= e($playerUrl) ?>" target="_blank" rel="noopener">Open player</a>
    </div>
    <p class="field__hint">The player checks for playlist changes automatically. Videos and websites require a network connection.</p>
    <?php if (!empty($screen['last_error'])): ?><p class="signage-row__error">Player reported: <?= e($screen['last_error']) ?></p><?php endif ?>
  </section>
<?php endif ?>

<form class="card form-card" method="post" action="<?= e($base) ?>" data-busy-on-submit novalidate>
  <?= csrf_field() ?>
  <?php if ($errors !== []): ?><div class="notice notice--error" role="alert"><?= icon('circle-alert') ?><div>Check the highlighted <?= count($errors) === 1 ? 'field' : 'fields' ?>.</div></div><?php endif ?>
  <div class="form-grid">
    <div class="field field--full<?= $field('name') ? ' has-error' : '' ?>">
      <label class="field__label" for="name">Screen name <span class="field__required">required</span></label>
      <input class="input" id="name" name="name" value="<?= e($values['name'] ?? '') ?>" maxlength="160" required autocomplete="off" placeholder="e.g. Main corridor TV"<?= $invalid('name') ?>><?= $error('name') ?>
    </div>
    <div class="field field--full<?= $field('location') ? ' has-error' : '' ?>">
      <label class="field__label" for="location">Location</label>
      <input class="input" id="location" name="location" value="<?= e($values['location'] ?? '') ?>" maxlength="160" autocomplete="off" placeholder="e.g. 2nd floor"<?= $invalid('location') ?>><?= $error('location') ?>
    </div>
    <div class="field field--full<?= $field('playlist_id') ? ' has-error' : '' ?>">
      <label class="field__label" for="playlist_id">Playlist</label>
      <select class="select" id="playlist_id" name="playlist_id"<?= $invalid('playlist_id') ?>>
        <option value="">Not assigned</option>
        <?php foreach ($playlists as $option): ?><option value="<?= (int) $option['id'] ?>"<?= (int) ($values['playlist_id'] ?? 0) === (int) $option['id'] ? ' selected' : '' ?>><?= e($option['name']) ?></option><?php endforeach ?>
      </select><?= $error('playlist_id') ?><p class="field__hint"><a href="/signage/playlists/create">Create a playlist</a> to arrange videos and websites.</p>
    </div>
    <div class="field<?= $field('orientation') ? ' has-error' : '' ?>">
      <label class="field__label" for="orientation">Orientation</label>
      <select class="select" id="orientation" name="orientation"<?= $invalid('orientation') ?>>
        <option value="landscape"<?= ($values['orientation'] ?? 'landscape') === 'landscape' ? ' selected' : '' ?>>Landscape</option>
        <option value="portrait"<?= ($values['orientation'] ?? '') === 'portrait' ? ' selected' : '' ?>>Portrait (rotate clockwise)</option>
      </select><?= $error('orientation') ?>
    </div>
    <div class="field<?= $field('fit') ? ' has-error' : '' ?>">
      <label class="field__label" for="fit">Video fit</label>
      <select class="select" id="fit" name="fit"<?= $invalid('fit') ?>>
        <option value="contain"<?= ($values['fit'] ?? 'contain') === 'contain' ? ' selected' : '' ?>>Fit entire video</option>
        <option value="cover"<?= ($values['fit'] ?? '') === 'cover' ? ' selected' : '' ?>>Fill screen (crop edges)</option>
      </select><?= $error('fit') ?>
    </div>
    <div class="field field--full signage-checkboxes">
      <label class="signage-check"><input type="checkbox" name="muted" value="1"<?= !empty($values['muted']) ? ' checked' : '' ?><?= $invalid('muted') ?>><span>Mute sound<span class="signage-row__sub">Muted playback starts automatically in most TV browsers.</span></span></label><?= $error('muted') ?>
      <label class="signage-check"><input type="checkbox" name="enabled" value="1"<?= !empty($values['enabled']) ? ' checked' : '' ?><?= $invalid('enabled') ?>><span>Enable playback<span class="signage-row__sub">Turn this off to pause the screen at its next check.</span></span></label><?= $error('enabled') ?>
    </div>
  </div>
  <div class="form-actions"><a class="btn btn--secondary" href="/signage/screens">Cancel</a><button class="btn btn--primary" type="submit"><?= $adding ? 'Add screen' : 'Save changes' ?></button></div>
</form>
<?php if (!$adding): ?>
  <div class="signage-danger-actions">
    <form method="post" action="<?= e($base) ?>/rotate-token" data-confirm="Replace this screen's player address?" data-confirm-text="The current address will stop working. Open the new address on the TV after replacing it." data-confirm-button="Replace address"><?= csrf_field() ?><button class="btn btn--secondary" type="submit"><?= icon('rotate-ccw', 'icon--sm') ?>Replace player address</button></form>
    <form method="post" action="<?= e($base) ?>/delete" data-confirm="Delete this screen?" data-confirm-text="Its player address will stop working. Your playlists and content will remain available." data-confirm-button="Delete screen" data-confirm-tone="danger"><?= csrf_field() ?><button class="btn btn--danger-quiet" type="submit"><?= icon('trash-2', 'icon--sm') ?>Delete screen</button></form>
  </div>
<?php endif ?>

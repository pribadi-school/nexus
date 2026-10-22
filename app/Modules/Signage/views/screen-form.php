<?php
$adding = $screen === null;
$section ??= 'playlist';
$base = $adding ? '/signage/screens' : '/signage/screens/' . (int) $screen['id'];
$field = static fn (string $name): string => $errors[$name] ?? '';
$invalid = static fn (string $name): string => $field($name) ? ' aria-invalid="true" aria-describedby="' . $name . '-error"' : '';
$error = static fn (string $name): string => $field($name) ? '<p class="field__error" id="' . $name . '-error">' . e($field($name)) . '</p>' : '';
$playerUrl = !$adding ? rtrim((string) config('app.url', ''), '/') . '/signage/player/' . $screen['token'] : '';
$tvControl = !$adding && \App\Modules\Signage\SignageTvControl::enabled();
// Playlist, TV control and settings are separate areas of one screen.
$sections = ['playlist' => ['Playlist', $base . '/edit']];
if ($tvControl) $sections['control'] = ['TV control', $base . '/control'];
$sections['settings'] = ['Settings', $base . '/settings'];
?>
<a class="back-link" href="/signage/screens"><?= icon('chevron-left', 'icon--sm') ?>Screens</a>
<header class="page-header"><div><h1 class="page-title"><?= $adding ? 'Add a screen' : e($screen['name']) ?></h1><?php if ($adding): ?><p class="page-subtitle">Add a TV with its own playlist of videos, images and websites.</p><?php endif ?></div><?php if (!$adding): ?><div class="page-actions"><?= partial('Signage::_screen-status', ['screen' => $screen]) ?></div><?php endif ?></header>
<?= partial('Signage::_tabs', ['tab' => 'screens']) ?>

<?php if (!$adding): ?>
<nav class="signage-tabs signage-tabs--screen" aria-label="<?= e($screen['name']) ?> sections">
  <?php foreach ($sections as $key => [$label, $href]): ?>
    <a class="signage-tabs__link<?= $section === $key ? ' is-active' : '' ?>" href="<?= e($href) ?>"<?= $section === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach ?>
</nav>
<?php endif ?>

<?php if (!$adding && $section === 'playlist'): ?>
<?= partial('Signage::_playlist-source', ['screen' => $screen, 'currentPlaylist' => $currentPlaylist, 'sharedPlaylists' => $sharedPlaylists]) ?>
<?= partial('Signage::_screen-editor', ['screen' => $screen, 'items' => $items, 'content' => $content, 'revision' => $revision]) ?>
<?php endif ?>

<?php if ($tvControl && $section === 'control'): ?>
<?= partial('Signage::_tv-control', ['screen' => $screen]) ?>
<?php endif ?>

<?php if ($adding || $section === 'settings'): ?>
<h2 class="signage-settings-title"><?= $adding ? 'Screen details' : 'Screen settings' ?></h2>
<form class="card form-card" method="post" action="<?= e($base) ?>" data-busy-on-submit novalidate>
  <?= csrf_field() ?>
  <?php if ($errors !== []): ?><div class="notice notice--error" role="alert"><?= icon('circle-alert') ?><div>Check the highlighted <?= count($errors) === 1 ? 'field' : 'fields' ?>.</div></div><?php endif ?>
  <div class="form-grid">
    <div class="field field--full<?= $field('name') ? ' has-error' : '' ?>">
      <label class="field__label" for="name">Screen name <span class="field__required">required</span></label>
      <input class="input" id="name" name="name" value="<?= e($values['name'] ?? '') ?>" maxlength="160" required autocomplete="off" placeholder="e.g. Main corridor TV"<?= $invalid('name') ?>><?= $error('name') ?>
    </div>
    <div class="field<?= $field('orientation') ? ' has-error' : '' ?>">
      <label class="field__label" for="orientation">Orientation</label>
      <select class="select" id="orientation" name="orientation"<?= $invalid('orientation') ?>>
        <option value="landscape"<?= ($values['orientation'] ?? 'landscape') === 'landscape' ? ' selected' : '' ?>>Landscape</option>
        <option value="portrait"<?= ($values['orientation'] ?? '') === 'portrait' ? ' selected' : '' ?>>Portrait (rotate clockwise)</option>
      </select><?= $error('orientation') ?>
    </div>
    <div class="field<?= $field('fit') ? ' has-error' : '' ?>">
      <label class="field__label" for="fit">Media fit</label>
      <select class="select" id="fit" name="fit"<?= $invalid('fit') ?>>
        <option value="contain"<?= ($values['fit'] ?? 'contain') === 'contain' ? ' selected' : '' ?>>Fit entire video or image</option>
        <option value="cover"<?= ($values['fit'] ?? '') === 'cover' ? ' selected' : '' ?>>Fill screen (crop edges)</option>
      </select><?= $error('fit') ?>
    </div>
    <div class="field field--full signage-checkboxes">
      <label class="signage-check"><input type="checkbox" name="muted" value="1"<?= !empty($values['muted']) ? ' checked' : '' ?><?= $invalid('muted') ?>><span>Mute sound<span class="signage-row__sub">Muted playback starts automatically in most TV browsers.</span></span></label><?= $error('muted') ?>
      <label class="signage-check"><input type="checkbox" name="enabled" value="1"<?= !empty($values['enabled']) ? ' checked' : '' ?><?= $invalid('enabled') ?>><span>Enable playback<span class="signage-row__sub">Turn this off to pause the screen at its next check.</span></span></label><?= $error('enabled') ?>
    </div>
  </div>
  <div class="form-actions"><a class="btn btn--secondary" href="<?= $adding ? '/signage/screens' : e($base . '/edit') ?>">Cancel</a><button class="btn btn--primary" type="submit"><?= $adding ? 'Add screen' : 'Save changes' ?></button></div>
</form>
<?php endif ?>

<?php if (!$adding && $section === 'settings'): $photo = (string) ($screen['photo_filename'] ?? ''); ?>
  <section class="card signage-screen-photo" id="screen-photo" aria-labelledby="screen-photo-title">
    <div class="signage-screen-photo__image">
      <?php if ($photo !== ''): ?>
        <img src="<?= e($base . '/photo?v=' . $photo) ?>" alt="Photo of <?= e($screen['name']) ?>">
      <?php else: ?>
        <span class="signage-screen-photo__empty"><?= icon('tv-minimal', 'icon--xl') ?>No photo yet</span>
      <?php endif ?>
    </div>
    <div class="signage-screen-photo__body">
      <h2 class="card__title" id="screen-photo-title">Screen photo</h2>
      <p class="signage-help">A photo of this TV where it hangs, so everyone can tell the screens apart. JPG, PNG or WebP, up to 8 MB.</p>
      <form class="signage-screen-photo__upload" method="post" action="<?= e($base) ?>/photo" enctype="multipart/form-data" data-busy-on-submit>
        <?= csrf_field() ?>
        <label class="visually-hidden" for="screen-photo-file">Choose a photo</label>
        <input class="signage-file" id="screen-photo-file" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
        <button class="btn btn--primary btn--sm" type="submit"><?= icon('upload', 'icon--sm') ?><?= $photo !== '' ? 'Replace photo' : 'Upload photo' ?></button>
      </form>
      <?php if ($photo !== ''): ?>
        <form method="post" action="<?= e($base) ?>/photo/delete" data-confirm="Remove this screen's photo?" data-confirm-button="Remove photo" data-confirm-tone="danger">
          <?= csrf_field() ?><button class="btn btn--danger-quiet btn--sm" type="submit"><?= icon('trash-2', 'icon--sm') ?>Remove photo</button>
        </form>
      <?php endif ?>
    </div>
  </section>

  <section class="card signage-player-address">
    <div class="signage-section-head"><h2 class="card__title">Player address</h2></div>
    <p class="signage-help"><a href="/signage/pair?screen=<?= (int) $screen['id'] ?>">Pair the Nexus Android TV app with this screen</a> to download videos and images for offline playback. Or open this address in the TV browser. Keep it private: it gives access to this screen's playlist.</p>
    <div class="signage-copy">
      <label class="visually-hidden" for="player-address">Player address</label>
      <input class="input" type="text" id="player-address" value="<?= e($playerUrl) ?>" readonly spellcheck="false" data-player-address>
      <button class="btn btn--secondary" type="button" data-copy-player hidden>Copy address</button>
      <a class="btn btn--secondary" href="<?= e($playerUrl) ?>" target="_blank" rel="noopener">Open player</a>
    </div>
    <p class="field__hint">The player checks for playlist changes automatically. This browser player requires a network connection.</p>
    <?php if (!empty($screen['last_error'])): ?><p class="signage-row__error">Player reported: <?= e($screen['last_error']) ?></p><?php endif ?>
  </section>

  <div class="signage-danger-actions">
    <form method="post" action="<?= e($base) ?>/rotate-token" data-confirm="Replace this screen's player address?" data-confirm-text="The current address will stop working. Open the new address on the TV after replacing it." data-confirm-button="Replace address"><?= csrf_field() ?><button class="btn btn--secondary" type="submit"><?= icon('rotate-ccw', 'icon--sm') ?>Replace player address</button></form>
    <form method="post" action="<?= e($base) ?>/delete" data-confirm="Delete this screen?" data-confirm-text="Its player address and playlist will be removed. Content remains in the content library." data-confirm-button="Delete screen" data-confirm-tone="danger"><?= csrf_field() ?><button class="btn btn--danger-quiet" type="submit"><?= icon('trash-2', 'icon--sm') ?>Delete screen</button></form>
  </div>
<?php endif ?>

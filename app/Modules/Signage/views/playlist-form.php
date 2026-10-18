<?php
$adding = $playlist === null;
$base = $adding ? '/signage/playlists' : '/signage/playlists/' . (int) $playlist['id'];
$field = static fn (string $name): string => $errors[$name] ?? '';
$invalid = static fn (string $name): string => $field($name) ? ' aria-invalid="true" aria-describedby="' . $name . '-error"' : '';
$error = static fn (string $name): string => $field($name) ? '<p class="field__error" id="' . $name . '-error">' . e($field($name)) . '</p>' : '';
?>
<a class="back-link" href="/signage/playlists"><?= icon('chevron-left', 'icon--sm') ?>Playlists</a>
<header class="page-header"><div><h1 class="page-title"><?= $adding ? 'Create a playlist' : e($playlist['name']) ?></h1><p class="page-subtitle"><?= $adding ? 'Give the playlist a name, then add content in playback order.' : 'Videos play to the end. Websites display for their selected time.' ?></p></div>
  <?php if (!$adding): ?><div class="page-actions"><a class="btn btn--secondary" href="<?= e($base) ?>/preview" target="_blank" rel="noopener">Preview playlist</a></div><?php endif ?>
</header>
<?= partial('Signage::_tabs', ['tab' => 'playlists']) ?>
<form class="card form-card" method="post" action="<?= e($base) ?>" data-busy-on-submit novalidate>
  <?= csrf_field() ?>
  <?php if ($errors !== []): ?><div class="notice notice--error" role="alert"><?= icon('circle-alert') ?><div>Check the highlighted <?= count($errors) === 1 ? 'field' : 'fields' ?>.</div></div><?php endif ?>
  <div class="form-grid">
    <div class="field field--full<?= $field('name') ? ' has-error' : '' ?>"><label class="field__label" for="name">Playlist name <span class="field__required">required</span></label><input class="input" id="name" name="name" value="<?= e($values['name'] ?? '') ?>" maxlength="160" required autocomplete="off" placeholder="e.g. School announcements"<?= $invalid('name') ?>><?= $error('name') ?></div>
    <div class="field field--full<?= $field('description') ? ' has-error' : '' ?>"><label class="field__label" for="description">Description</label><textarea class="input" id="description" name="description" rows="2" maxlength="500"<?= $invalid('description') ?>><?= e($values['description'] ?? '') ?></textarea><?= $error('description') ?></div>
  </div>
  <div class="form-actions"><a class="btn btn--secondary" href="/signage/playlists">Cancel</a><button class="btn btn--primary" type="submit"><?= $adding ? 'Create playlist' : 'Save details' ?></button></div>
</form>

<?php if (!$adding): ?>
  <section class="card signage-playlist-content">
    <div class="card__header"><h2 class="card__title">Playback order</h2><span class="signage-muted"><?= e(plural(count($items), 'item')) ?></span></div>
    <?php if ($items === []): ?>
      <div class="card__body"><p class="signage-help">This playlist is empty. Add content below, then assign the playlist to a screen.</p></div>
    <?php else: ?>
      <ol class="signage-playlist-items">
        <?php foreach ($items as $index => $entry): $itemBase = $base . '/items/' . (int) $entry['item_id']; ?>
          <li class="signage-playlist-item">
            <span class="signage-playlist-item__position" aria-label="Position <?= $index + 1 ?>"><?= $index + 1 ?></span>
            <div class="signage-playlist-item__main"><a class="signage-row__title" href="/signage/content/<?= (int) ($entry['content_id'] ?? $entry['id']) ?>/edit"><?= e($entry['name']) ?></a><span class="signage-row__sub signage-break"><?= $entry['type'] === 'video' ? 'MP4 video · plays to the end' : e($entry['source_url']) ?></span></div>
            <?php if ($entry['type'] === 'website'): ?>
              <form class="signage-duration" method="post" action="<?= e($itemBase) ?>"><?= csrf_field() ?><label class="visually-hidden" for="duration-<?= (int) $entry['item_id'] ?>">Display seconds for <?= e($entry['name']) ?></label><input class="input signage-duration__input" type="number" name="duration_seconds" id="duration-<?= (int) $entry['item_id'] ?>" min="5" max="86400" step="1" value="<?= (int) $entry['duration_seconds'] ?>" required><span class="signage-muted">sec</span><button class="btn btn--secondary btn--sm" type="submit">Save time</button></form>
            <?php else: ?><span class="signage-playlist-item__duration signage-muted">Full video</span><?php endif ?>
            <div class="signage-playlist-item__actions">
              <form method="post" action="<?= e($itemBase) ?>/move"><?= csrf_field() ?><input type="hidden" name="direction" value="up"><button class="icon-btn" type="submit" aria-label="Move <?= e($entry['name']) ?> up"<?= $index === 0 ? ' disabled' : '' ?>><?= icon('chevron-up') ?></button></form>
              <form method="post" action="<?= e($itemBase) ?>/move"><?= csrf_field() ?><input type="hidden" name="direction" value="down"><button class="icon-btn" type="submit" aria-label="Move <?= e($entry['name']) ?> down"<?= $index === count($items) - 1 ? ' disabled' : '' ?>><?= icon('chevron-down') ?></button></form>
              <form method="post" action="<?= e($itemBase) ?>/delete"><?= csrf_field() ?><button class="icon-btn icon-btn--danger" type="submit" aria-label="Remove <?= e($entry['name']) ?> from playlist"><?= icon('x', 'icon--sm') ?></button></form>
            </div>
          </li>
        <?php endforeach ?>
      </ol>
    <?php endif ?>
    <div class="signage-add-content">
      <?php if ($content === []): ?>
        <p class="signage-help">Add an MP4 video or website to your content library first.</p><a class="btn btn--secondary" href="/signage/content/create"><?= icon('plus', 'icon--sm') ?>Add content</a>
      <?php else: ?>
        <form class="signage-add-item" method="post" action="<?= e($base) ?>/items" data-signage-add-item data-busy-on-submit>
          <?= csrf_field() ?>
          <div class="field"><label class="field__label" for="content_id">Add content to this playlist</label><select class="select" id="content_id" name="content_id" required data-playlist-content><option value="">Choose content</option><?php foreach ($content as $option): ?><option value="<?= (int) $option['id'] ?>" data-type="<?= e($option['type']) ?>"><?= e($option['name']) ?> · <?= $option['type'] === 'video' ? 'MP4 video' : 'Website' ?></option><?php endforeach ?></select></div>
          <div class="field" data-website-duration><label class="field__label" for="duration_seconds">Website display time (seconds)</label><input class="input" id="duration_seconds" type="number" name="duration_seconds" value="30" min="5" max="86400" step="1"><span class="field__hint">Videos play to the end.</span></div>
          <button class="btn btn--primary" type="submit"><?= icon('plus', 'icon--sm') ?>Add to playlist</button>
        </form>
      <?php endif ?>
    </div>
  </section>
  <div class="signage-danger-actions"><form method="post" action="<?= e($base) ?>/delete" data-confirm="Delete this playlist?" data-confirm-text="Screens using it will have no playlist assigned. Your content library will remain available." data-confirm-button="Delete playlist" data-confirm-tone="danger"><?= csrf_field() ?><button class="btn btn--danger-quiet" type="submit"><?= icon('trash-2', 'icon--sm') ?>Delete playlist</button></form></div>
<?php endif ?>

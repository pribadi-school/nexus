<?php
$base = '/signage/playlists/' . (int) $playlist['id'];
$tvs = $playlist['screens'];
$names = array_column($tvs, 'name');
?>
<a class="back-link" href="/signage/playlists"><?= icon('chevron-left', 'icon--sm') ?>Playlists</a>
<header class="page-header"><div><h1 class="page-title"><?= e($playlist['name']) ?></h1>
  <p class="page-subtitle"><?= $tvs === [] ? 'Not used by any TV yet.' : 'Plays on ' . e(implode(', ', $names)) . '. Changes apply to all of these TVs.' ?></p></div></header>
<?= partial('Signage::_tabs', ['tab' => 'playlists']) ?>

<nav class="signage-tabs signage-tabs--screen" aria-label="<?= e($playlist['name']) ?> sections">
  <?php foreach (['playlist' => ['Playlist', $base . '/edit'], 'settings' => ['Settings', $base . '/settings']] as $key => [$label, $href]): ?>
    <a class="signage-tabs__link<?= $section === $key ? ' is-active' : '' ?>" href="<?= e($href) ?>"<?= $section === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach ?>
</nav>

<?php if ($section === 'playlist'): ?>
<?= partial('Signage::_screen-editor', ['base' => $base, 'owner' => 'this playlist', 'items' => $items, 'content' => $content, 'revision' => $revision]) ?>
<?php else: ?>
<h2 class="signage-settings-title">Playlist settings</h2>
<form class="card form-card" method="post" action="<?= e($base) ?>" data-busy-on-submit novalidate>
  <?= csrf_field() ?>
  <div class="form-grid">
    <div class="field field--full">
      <label class="field__label" for="playlist-name">Playlist name <span class="field__required">required</span></label>
      <input class="input" id="playlist-name" name="name" value="<?= e($playlist['name']) ?>" maxlength="160" required autocomplete="off">
    </div>
  </div>
  <div class="form-actions"><a class="btn btn--secondary" href="<?= e($base) ?>/edit">Cancel</a><button class="btn btn--primary" type="submit">Save name</button></div>
</form>

<section class="card form-card">
  <h2 class="card__title">Copy this playlist</h2>
  <p class="signage-help">Makes a new shared playlist with the same content and timings. No TV plays the copy until you choose it on a TV.</p>
  <form class="signage-playlist-new" method="post" action="<?= e($base) ?>/duplicate" data-busy-on-submit>
    <?= csrf_field() ?>
    <label class="visually-hidden" for="copy-name">Name of the copy</label>
    <input class="input" id="copy-name" name="name" value="<?= e($playlist['name']) ?> copy" maxlength="160" required autocomplete="off">
    <button class="btn btn--secondary" type="submit"><?= icon('plus', 'icon--sm') ?>Copy playlist</button>
  </form>
</section>

<div class="signage-danger-actions">
  <?php if ($tvs !== []): ?>
    <p class="signage-help">This playlist is used by <?= e(implode(', ', $names)) ?>. Choose another playlist on those TVs before deleting it.</p>
  <?php else: ?>
    <form method="post" action="<?= e($base) ?>/delete" data-confirm="Delete this playlist?" data-confirm-text="Its content stays in the content library." data-confirm-button="Delete playlist" data-confirm-tone="danger"><?= csrf_field() ?><button class="btn btn--danger-quiet" type="submit"><?= icon('trash-2', 'icon--sm') ?>Delete playlist</button></form>
  <?php endif ?>
</div>
<?php endif ?>

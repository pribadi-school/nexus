<header class="page-header"><div><h1 class="page-title">Signage</h1><p class="page-subtitle">Screens and content for school displays.</p></div></header>
<?= partial('Signage::_tabs', ['tab' => 'playlists']) ?>

<div class="signage-library-heading">
  <h2 class="card__title">Shared playlists</h2>
  <p class="signage-help">Make a playlist once and play it on several TVs. Editing it updates every TV that plays it. A TV can also keep its own playlist.</p>
</div>

<form class="card signage-playlist-new" method="post" action="/signage/playlists" data-busy-on-submit>
  <?= csrf_field() ?>
  <label class="visually-hidden" for="new-playlist-name">Playlist name</label>
  <input class="input" id="new-playlist-name" name="name" maxlength="160" required autocomplete="off" placeholder="New playlist name, e.g. Morning announcements">
  <button class="btn btn--primary" type="submit"><?= icon('plus', 'icon--sm') ?>Create playlist</button>
</form>

<?php if ($playlists === []): ?>
  <section class="card"><?= partial('partials/empty-state', [
      'icon' => 'presentation', 'title' => 'No shared playlists yet',
      'text' => 'Create one above, or open a TV and choose “Save as shared playlist” to share what it already plays.',
  ]) ?></section>
<?php else: ?>
  <ul class="signage-content-grid signage-playlist-grid" aria-label="Shared playlists">
    <?php foreach ($playlists as $playlist): $href = '/signage/playlists/' . (int) $playlist['id'] . '/edit'; ?>
      <li class="card signage-content-card">
        <?= partial('Signage::_playlist-thumbnail', ['cover' => $playlist['cover'], 'href' => $href, 'label' => 'Edit ' . $playlist['name'], 'emptyText' => 'Empty playlist']) ?>
        <div class="signage-content-card__body">
          <a class="signage-content-card__name" href="<?= e($href) ?>"><?= e($playlist['name']) ?></a>
          <p class="signage-content-card__meta"><?= e(plural((int) $playlist['item_count'], 'item')) ?></p>
          <p class="signage-content-card__details signage-playlist-tvs">
            <?php if ($playlist['screens'] === []): ?>Not used by any TV yet
            <?php else: ?>Plays on <?= e(implode(', ', array_column($playlist['screens'], 'name'))) ?><?php endif ?>
          </p>
        </div>
      </li>
    <?php endforeach ?>
  </ul>
<?php endif ?>

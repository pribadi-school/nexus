<header class="page-header">
  <div>
    <h1 class="page-title">Signage</h1>
    <p class="page-subtitle">Screens and content for school displays.</p>
  </div>
  <div class="page-actions"><a class="btn btn--secondary" href="/signage/pair"><?= icon('tv-minimal', 'icon--sm') ?>Pair Android TV</a><a class="btn btn--primary" href="/signage/screens/create"><?= icon('plus', 'icon--sm') ?>Add screen</a></div>
</header>
<?= partial('Signage::_tabs', ['tab' => 'screens']) ?>

<?php if ($screens === []): ?>
  <section class="card">
    <?= partial('partials/empty-state', [
        'icon' => 'presentation', 'title' => 'Set up your first screen',
        'text' => 'Add content, arrange it in a playlist, then assign the playlist to a screen. Open its player address on the TV.',
        'actionHref' => '/signage/content/create', 'actionLabel' => 'Add content',
    ]) ?>
  </section>
<?php else: ?>
  <p class="results__summary"><?= e(plural(count($screens), 'screen')) ?> · Open a screen to copy its player address or change its playlist.</p>
  <ul class="signage-content-grid" aria-label="Screens">
    <?php foreach ($screens as $screen):
      $editUrl = '/signage/screens/' . (int) $screen['id'] . '/edit';
      $cover = $covers[(int) $screen['playlist_id']] ?? null;
    ?>
      <li class="card signage-content-card">
        <?= partial('Signage::_playlist-thumbnail', ['cover' => $cover, 'href' => $editUrl, 'label' => 'Manage ' . $screen['name'], 'emptyText' => $screen['playlist_id'] ? 'Playlist is empty' : 'No playlist assigned']) ?>
        <div class="signage-content-card__body">
          <div class="signage-content-card__heading"><a class="signage-content-card__name" href="<?= e($editUrl) ?>"><?= e($screen['name']) ?></a><a class="icon-btn" href="<?= e($editUrl) ?>" aria-label="Manage <?= e($screen['name']) ?>"><?= icon('pencil', 'icon--sm') ?></a></div>
          <p class="signage-content-card__meta"><?= e($screen['location'] ?: 'Location not set') ?></p>
          <p class="signage-content-card__details">
            <?php if ($screen['playlist_id']): ?><a href="/signage/playlists/<?= (int) $screen['playlist_id'] ?>/edit"><?= e($screen['playlist_name']) ?></a> · <?= e(plural((int) $screen['item_count'], 'item')) ?><?php else: ?>No playlist assigned<?php endif ?>
          </p>
          <?php if ($cover !== null): ?><p class="signage-content-card__details">Playlist preview · <?= e($cover['name']) ?></p><?php endif ?>
          <div class="signage-card-status"><?= partial('Signage::_screen-status', ['screen' => $screen]) ?><span class="signage-muted">Last connected <?php if ($screen['last_seen_at']): ?><time datetime="<?= e(iso_time($screen['last_seen_at'])) ?>" title="<?= e(format_datetime($screen['last_seen_at'])) ?>"><?= e(time_ago($screen['last_seen_at'])) ?></time><?php else: ?>never<?php endif ?></span></div>
          <?php if (!empty($screen['last_error'])): ?><p class="signage-row__error"><?= e($screen['last_error']) ?></p><?php endif ?>
        </div>
      </li>
    <?php endforeach ?>
  </ul>
<?php endif ?>

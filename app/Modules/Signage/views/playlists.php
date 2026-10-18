<header class="page-header"><div><h1 class="page-title">Signage</h1><p class="page-subtitle">Screens and content for school displays.</p></div><div class="page-actions"><a class="btn btn--primary" href="/signage/playlists/create"><?= icon('plus', 'icon--sm') ?>Create playlist</a></div></header>
<?= partial('Signage::_tabs', ['tab' => 'playlists']) ?>
<?php if ($playlists === []): ?>
  <section class="card"><?= partial('partials/empty-state', ['icon' => 'presentation', 'title' => 'Arrange content in a playlist', 'text' => 'Mix videos and websites in the order you want. A playlist loops continuously on every screen assigned to it.', 'actionHref' => '/signage/playlists/create', 'actionLabel' => 'Create a playlist']) ?></section>
<?php else: ?>
  <p class="results__summary"><?= e(plural(count($playlists), 'playlist')) ?> · Changes are picked up automatically by assigned screens.</p>
  <ul class="signage-content-grid" aria-label="Playlists">
    <?php foreach ($playlists as $entry):
      $editUrl = '/signage/playlists/' . (int) $entry['id'] . '/edit';
      $cover = $covers[(int) $entry['id']] ?? null;
    ?>
      <li class="card signage-content-card">
        <?= partial('Signage::_playlist-thumbnail', ['cover' => $cover, 'href' => $editUrl, 'label' => 'Edit ' . $entry['name'], 'emptyText' => 'Playlist is empty']) ?>
        <div class="signage-content-card__body">
          <div class="signage-content-card__heading"><a class="signage-content-card__name" href="<?= e($editUrl) ?>"><?= e($entry['name']) ?></a><a class="icon-btn" href="<?= e($editUrl) ?>" aria-label="Edit <?= e($entry['name']) ?>"><?= icon('pencil', 'icon--sm') ?></a></div>
          <p class="signage-content-card__meta"><?= e(plural((int) $entry['item_count'], 'item')) ?> · <?= e(plural((int) $entry['screen_count'], 'screen')) ?></p>
          <?php if ($entry['description']): ?><p class="signage-content-card__details"><?= e($entry['description']) ?></p><?php endif ?>
          <?php if ($cover !== null): ?><p class="signage-content-card__details">First item · <?= e($cover['name']) ?></p><?php endif ?>
          <p class="signage-content-card__details">Updated <time datetime="<?= e(iso_time($entry['updated_at'])) ?>" title="<?= e(format_datetime($entry['updated_at'])) ?>"><?= e(time_ago($entry['updated_at'])) ?></time></p>
        </div>
      </li>
    <?php endforeach ?>
  </ul>
<?php endif ?>

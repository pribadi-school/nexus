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
        'text' => 'Add a screen, then arrange its own playlist of videos and websites.',
        'actionHref' => '/signage/screens/create', 'actionLabel' => 'Add screen',
    ]) ?>
  </section>
<?php else: ?>
  <p class="results__summary"><?= e(plural(count($screens), 'screen')) ?></p>
  <ul class="signage-content-grid" aria-label="Screens">
    <?php foreach ($screens as $screen):
      $editUrl = '/signage/screens/' . (int) $screen['id'] . '/edit';
      $photo = (string) ($screen['photo_filename'] ?? '');
    ?>
      <li class="card signage-content-card">
        <?php /* A photo of the TV itself (Settings > Screen photo), not of its content. */ ?>
        <a class="signage-screen-card__photo" href="<?= e($editUrl) ?>" tabindex="-1" aria-hidden="true">
          <?php if ($photo !== ''): ?>
            <img src="<?= e('/signage/screens/' . (int) $screen['id'] . '/photo?v=' . $photo) ?>" alt="" loading="lazy">
          <?php else: ?>
            <span class="signage-screen-photo__empty"><?= icon('tv-minimal', 'icon--xl') ?>Add a photo of this TV</span>
          <?php endif ?>
        </a>
        <div class="signage-content-card__body">
          <div class="signage-content-card__heading"><a class="signage-content-card__name" href="<?= e($editUrl) ?>"><?= e($screen['name']) ?></a><a class="icon-btn" href="<?= e($editUrl) ?>" aria-label="Manage <?= e($screen['name']) ?>"><?= icon('pencil', 'icon--sm') ?></a></div>
          <div class="signage-card-status"><?= partial('Signage::_screen-status', ['screen' => $screen]) ?><span class="signage-muted">Last seen <?php if ($screen['last_seen_at']): ?><time datetime="<?= e(iso_time($screen['last_seen_at'])) ?>" title="<?= e(format_datetime($screen['last_seen_at'])) ?>"><?= e(time_ago($screen['last_seen_at'])) ?></time><?php else: ?>never<?php endif ?></span></div>
        </div>
      </li>
    <?php endforeach ?>
  </ul>
<?php endif ?>

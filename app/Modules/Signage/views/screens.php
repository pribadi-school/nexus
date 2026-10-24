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
        'text' => 'Add a screen, then arrange its own playlist of videos, images and websites.',
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
            <img src="<?= e('/signage/screens/' . (int) $screen['id'] . '/photo?v=' . $photo) ?>" alt="" loading="lazy" decoding="async">
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

<?php /* Installable player apps: pick the one that matches the TV, then pair it with "Pair Android TV". */ ?>
<section class="card signage-apps" aria-labelledby="signage-apps-title">
  <div>
    <h2 class="signage-apps__title" id="signage-apps-title">TV player app</h2>
    <p class="signage-help">Install the Nexus Signage app on a TV, then pair it. Install over the existing app to update; never uninstall, or the TV loses its pairing and videos.</p>
  </div>
  <div class="signage-apps__buttons">
    <?php foreach ($apps as $kind => $app): ?>
      <?php if ($app['file'] !== null): ?>
        <a class="btn btn--secondary signage-apps__button" href="<?= e('/signage/app/download/' . $kind) ?>" download>
          <?= icon('download', 'icon--sm') ?>
          <span><strong><?= e($app['label']) ?></strong><small>Version <?= e($app['file']['version']) ?> · <?= e(number_format($app['file']['size'] / 1048576, 1)) ?> MB</small></span>
        </a>
      <?php else: ?>
        <span class="btn btn--secondary signage-apps__button" aria-disabled="true"><span><strong><?= e($app['label']) ?></strong><small>Not available yet</small></span></span>
      <?php endif ?>
    <?php endforeach ?>
  </div>
  <ul class="signage-apps__hints">
    <?php foreach ($apps as $app): ?><li><strong><?= e($app['label']) ?>:</strong> <?= e($app['hint']) ?></li><?php endforeach ?>
  </ul>
</section>

<?php $bytes = static fn (int $value): string => $value >= 1073741824 ? round($value / 1073741824, 2) . ' GB' : ($value >= 1048576 ? round($value / 1048576, 1) . ' MB' : round($value / 1024, 1) . ' KB'); ?>
<header class="page-header"><div><h1 class="page-title">Signage</h1><p class="page-subtitle">Screens and content for school displays.</p></div><div class="page-actions"><a class="btn btn--primary" href="/signage/content/create"><?= icon('upload', 'icon--sm') ?>Add content</a></div></header>
<?= partial('Signage::_tabs', ['tab' => 'content']) ?>
<div class="signage-library-heading"><h2 class="card__title">Content library</h2><p class="signage-muted" data-content-summary data-count="<?= count($content) ?>" data-bytes="<?= (int) $usageBytes ?>"><?= e(plural(count($content), 'item')) ?><?= $content !== [] ? ' · ' . e($bytes((int) $usageBytes)) . ' stored' : '' ?></p></div>
<?php if ($content === []): ?>
  <section class="card" data-content-empty><?= partial('partials/empty-state', ['icon' => 'upload', 'title' => 'Build your content library', 'text' => 'Upload videos or images to your server, or add website addresses. Then add them to a playlist.', 'actionHref' => '/signage/content/create', 'actionLabel' => 'Add content']) ?></section>
<?php endif ?>
  <ul data-content-grid class="signage-content-grid" aria-label="Content library">
    <?php foreach ($content as $entry): ?>
      <?= partial('Signage::_content-card', ['entry' => $entry, 'screens' => $screens]) ?>
    <?php endforeach ?>
  </ul>
<?= partial('Signage::_content-dialog', ['uploadLimitBytes' => $uploadLimitBytes]) ?>

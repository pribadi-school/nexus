<?php
/**
 * @var \App\Devices\Category $category
 * @var array{total:int, ok:int, problem:int} $counts
 */
?>
<header class="page-header">
  <div>
    <h1 class="page-title"><?= e($category->name()) ?></h1>
    <p class="page-subtitle"><?= e($category->count($counts['total'])) ?> in the database</p>
  </div>
</header>

<section class="card">
  <?= partial('partials/empty-state', [
      'icon' => $category->icon(),
      'title' => 'This page is next on the build list',
      'text' => sprintf(
          'The %s list, detail view and notes log are being built. Right now: %d %s, %d with a problem.',
          $category->name(),
          $counts['ok'],
          $category->statusLabel('ok') === 'OK' ? 'OK' : 'running well',
          $counts['problem'],
      ),
      'actionHref' => '/',
      'actionLabel' => 'Back to the dashboard',
  ]) ?>
</section>

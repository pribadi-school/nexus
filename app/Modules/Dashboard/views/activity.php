<?php
/**
 * Older activity, for browsers without JavaScript ("Show older activity").
 *
 * @var list<array<string, mixed>> $feed
 * @var int|null $nextCursor
 */
?>
<header class="page-header">
  <div>
    <h1 class="page-title">Activity</h1>
    <p class="page-subtitle">Notes and status changes from every device, newest first.</p>
  </div>
  <a class="btn btn--secondary" href="/"><?= icon('chevron-left', 'icon--sm') ?>Dashboard</a>
</header>

<section class="card">
  <?php if ($feed === []): ?>
    <?= partial('partials/empty-state', ['icon' => 'history', 'title' => 'Nothing older', 'text' => 'You have reached the first note.', 'compact' => true]) ?>
  <?php else: ?>
    <ol class="feed" data-feed>
      <?= partial('Dashboard::_feed-items', ['feed' => $feed]) ?>
    </ol>
    <?php if ($nextCursor !== null): ?>
      <div class="feed__more">
        <a class="btn btn--secondary" href="<?= e(url('/activity', ['before' => $nextCursor])) ?>" data-feed-more>Show older activity</a>
      </div>
    <?php endif ?>
  <?php endif ?>
</section>

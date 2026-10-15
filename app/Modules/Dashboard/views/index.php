<?php
/**
 * @var list<array{category:\App\Devices\Category, total:int, ok:int, problem:int}> $summaries
 * @var list<array<string, mixed>> $attention  devices with a problem, newest first
 * @var int $problemTotal
 * @var list<array<string, mixed>> $feed
 * @var int|null $nextCursor
 * @var list<string> $pendingMigrations
 */
$deviceTotal = array_sum(array_column($summaries, 'total'));
?>
<header class="page-header">
  <div>
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle"><?= e((new DateTimeImmutable('now', local_tz()))->format('l, j F Y')) ?></p>
  </div>
</header>

<?php if ($pendingMigrations !== []): ?>
  <div class="flashes">
    <div class="notice notice--warning" role="status">
      <?= icon('triangle-alert') ?>
      <div>
        The database is behind the app. In phpMyAdmin, import
        <?php foreach ($pendingMigrations as $i => $version): ?><?= $i > 0 ? ', then ' : '' ?><code>database/migrations/<?= e($version) ?>.sql</code><?php endforeach ?>.
      </div>
    </div>
  </div>
<?php endif ?>

<div class="dashboard">
  <section class="dashboard__tiles" aria-label="Devices by category">
    <div class="tiles">
      <?php foreach ($summaries as $summary): ?>
        <?= partial('Dashboard::_tile', $summary) ?>
      <?php endforeach ?>
    </div>
  </section>

  <section class="dashboard__attention card" aria-labelledby="attention-title">
    <div class="card__header">
      <h2 class="card__title" id="attention-title">Needs attention</h2>
    </div>

    <?php if ($deviceTotal === 0): ?>
      <?= partial('partials/empty-state', [
          'icon' => 'inbox',
          'title' => 'No devices yet',
          'text' => 'Add StudyTouch boards, cameras or Chromebooks, and any with a problem will be listed here.',
          'compact' => true,
      ]) ?>
    <?php elseif ($problemTotal === 0): ?>
      <p class="hero hero--clear">
        <span class="hero__value">0</span>
        <span class="hero__label">devices have a problem</span>
        <span class="hero__sub">All <?= e(plural($deviceTotal, 'device')) ?> are running well.</span>
      </p>
    <?php else: ?>
      <p class="hero">
        <span class="hero__value"><?= $problemTotal ?></span>
        <span class="hero__label"><?= $problemTotal === 1 ? 'device has a problem' : 'devices have a problem' ?></span>
        <span class="hero__sub">out of <?= e(plural($deviceTotal, 'device')) ?></span>
      </p>
      <ul class="attention">
        <?php foreach ($attention as $device): ?>
          <li class="attention__item">
            <a class="attention__link" href="<?= e($device['url']) ?>">
              <span class="attention__text">
                <span class="attention__title"><?= e($device['title']) ?></span>
                <span class="attention__context"><?= e($device['context']) ?></span>
              </span>
              <?php if ($device['status_changed_at']): ?>
                <time class="attention__since" datetime="<?= e(iso_time($device['status_changed_at'])) ?>" title="Has had a problem since <?= e(format_datetime($device['status_changed_at'])) ?>">
                  <?= e(time_ago($device['status_changed_at'])) ?>
                </time>
              <?php endif ?>
              <?= icon('chevron-right', 'icon--sm') ?>
            </a>
          </li>
        <?php endforeach ?>
      </ul>
      <?php if ($problemTotal > count($attention)): ?>
        <p class="attention__more">and <?= $problemTotal - count($attention) ?> more. Filter each list by "Has problem" to see them all.</p>
      <?php endif ?>
    <?php endif ?>
  </section>

  <section class="dashboard__feed card" aria-labelledby="feed-title">
    <div class="card__header">
      <h2 class="card__title" id="feed-title">Recent activity</h2>
    </div>
    <?php if ($feed === []): ?>
      <?= partial('partials/empty-state', [
          'icon' => 'history',
          'title' => 'No notes yet',
          'text' => 'Reports, problems, fixes and status changes from every device will show up here, newest first.',
          'compact' => true,
      ]) ?>
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
</div>

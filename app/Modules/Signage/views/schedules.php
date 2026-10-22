<?php
use App\Modules\Signage\SignageSchedules;

$states = ['active' => ['Playing now', 'success'], 'upcoming' => ['Upcoming', 'action'], 'ended' => ['Ended', 'quiet'], 'off' => ['Off', 'quiet']];
?>
<header class="page-header"><div><h1 class="page-title">Signage</h1><p class="page-subtitle">Screens and content for school displays.</p></div>
  <?php if ($hasPlaylists): ?><div class="page-actions"><a class="btn btn--primary" href="/signage/schedules/create"><?= icon('plus', 'icon--sm') ?>New schedule</a></div><?php endif ?>
</header>
<?= partial('Signage::_tabs', ['tab' => 'schedules']) ?>

<div class="signage-library-heading">
  <h2 class="card__title">Schedules</h2>
  <p class="signage-help">Optional. During its time, a schedule plays a shared playlist on the TVs you choose, instead of what they normally play. Afterwards each TV goes back to its own playlist.</p>
</div>

<?php if (!$hasPlaylists): ?>
  <section class="card"><?= partial('partials/empty-state', [
      'icon' => 'clock', 'title' => 'Make a shared playlist first',
      'text' => 'A schedule plays a shared playlist. Create one in the Playlists tab, then come back here.',
      'actionHref' => '/signage/playlists', 'actionLabel' => 'Go to Playlists',
  ]) ?></section>
<?php elseif ($schedules === []): ?>
  <section class="card"><?= partial('partials/empty-state', [
      'icon' => 'clock', 'title' => 'No schedules yet',
      'text' => 'For example: the flag ceremony playlist on Mondays 07:00–07:30. TVs without a schedule keep playing their normal playlist.',
      'actionHref' => '/signage/schedules/create', 'actionLabel' => 'New schedule',
  ]) ?></section>
<?php else: ?>
  <ul class="signage-schedule-list" aria-label="Schedules">
    <?php foreach ($schedules as $schedule): [$label, $tone] = $states[$schedule['state']]; $href = '/signage/schedules/' . (int) $schedule['id']; ?>
      <li class="card signage-schedule<?= $schedule['state'] === 'active' ? ' is-active' : '' ?>">
        <div class="signage-schedule__main">
          <div class="signage-schedule__title"><a href="<?= e($href) ?>/edit"><?= e($schedule['name']) ?></a> <span class="chip chip--<?= e($tone) ?>"><?= e($label) ?></span></div>
          <p class="signage-schedule__when"><?= e($schedule['summary']) ?></p>
          <p class="signage-row__sub">Plays <strong><?= e($schedule['playlist_name']) ?></strong> on <?= e(implode(', ', array_column($schedule['screens'], 'name')) ?: 'no TVs') ?></p>
        </div>
        <div class="signage-schedule__actions">
          <a class="btn btn--secondary btn--sm" href="<?= e($href) ?>/edit"><?= icon('pencil', 'icon--sm') ?>Edit</a>
          <form method="post" action="<?= e($href) ?>/toggle"><?= csrf_field() ?><button class="btn btn--secondary btn--sm" type="submit"><?= $schedule['state'] === 'off' ? 'Turn on' : 'Turn off' ?></button></form>
        </div>
      </li>
    <?php endforeach ?>
  </ul>
<?php endif ?>

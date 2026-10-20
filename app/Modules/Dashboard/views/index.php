<?php
/**
 * Dashboard: header, search across every device, one stat strip (a cell per
 * category + "Needs attention"), a problem alert, then the devices with a
 * problem (left) and recent activity (right). Same layout as the other
 * Pribadi apps.
 *
 * @var list<array{category:\App\Devices\Category, total:int, ok:int, problem:int}> $summaries
 * @var list<array<string, mixed>> $attention  devices with a problem, newest first
 * @var int $problemTotal
 * @var int $deviceTotal
 * @var list<array<string, mixed>> $feed       latest few notes
 * @var list<string> $pendingMigrations
 */
use App\Devices\NoteKind;
use App\Devices\Status;
?>
<header class="page-header">
  <div>
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">StudyTouch boards, CCTV cameras and Chromebooks in one place.</p>
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

<div class="dash">
  <form class="dash-search" method="get" action="/search" role="search">
    <div class="filters__search">
      <?= icon('search', 'icon--sm') ?>
      <label class="visually-hidden" for="dash-q">Search all devices</label>
      <input class="input" id="dash-q" type="search" name="q" autocomplete="off" enterkeyhint="search"
             placeholder="Search devices, people, notes or problems…">
    </div>
    <button type="submit" class="btn btn--secondary">Search<?= icon('arrow-right', 'icon--sm') ?></button>
  </form>

  <section class="card stat-strip" aria-label="Devices by category">
    <?php foreach ($summaries as $s): $category = $s['category']; ?>
      <a class="stat" href="<?= e($category->path()) ?>">
        <span class="stat__label"><?= e($category->name()) ?></span>
        <span class="stat__value"><?= $s['total'] ?> <span class="stat__unit"><?= e($category->noun()[$s['total'] === 1 ? 0 : 1]) ?></span></span>
        <span class="stat__sub">
          <?php if ($category->key() === 'chromebook'): ?>
            <?= $s['reported'] ?> reported · <?= $s['problem'] ?> problem · <?= $s['repaired'] ?> repaired · <?= $s['replaced'] ?> replaced<?php if ($s['ok'] > 0): ?> · <?= $s['ok'] ?> legacy OK<?php endif ?>
          <?php else: ?>
          <?= $s['ok'] ?> <?= e(strtolower($category->statusLabel(Status::OK)) === 'ok' ? 'OK' : 'running well') ?><?php if ($s['problem'] > 0): ?> · <span class="status-word status-word--problem"><?= $s['problem'] ?> <?= $s['problem'] === 1 ? 'has' : 'have' ?> a problem</span><?php endif ?>
          <?php endif ?>
        </span>
      </a>
    <?php endforeach ?>
    <a class="stat stat--attention<?= $problemTotal > 0 ? ' has-problems' : '' ?>" href="#attention">
      <span class="stat__label">Needs attention <?= icon('arrow-right', 'icon--sm stat__arrow') ?></span>
      <span class="stat__value"><?= $problemTotal ?> <span class="stat__unit"><?= $problemTotal === 1 ? 'device' : 'devices' ?></span></span>
      <span class="stat__sub">out of <?= e(plural($deviceTotal, 'device')) ?></span>
    </a>
  </section>

  <?php if ($problemTotal > 0): ?>
    <div class="dash-alert" role="status">
      <?= icon('triangle-alert', 'icon--lg') ?>
      <div>
        <p class="dash-alert__title"><?= $problemTotal ?> <?= $problemTotal === 1 ? 'device has a problem' : 'devices have a problem' ?></p>
        <p class="dash-alert__text">Check them, and add a note on the device's page when they're fixed.</p>
      </div>
      <a class="link-quiet dash-alert__link" href="#attention">View list<?= icon('arrow-right', 'icon--sm') ?></a>
    </div>
  <?php endif ?>

  <div class="dash-columns">
    <section class="card" id="attention" aria-labelledby="attention-title">
      <div class="card__header card__header--stack">
        <div>
          <h2 class="card__title" id="attention-title">Needs attention</h2>
          <p class="card__subtitle">Devices with a problem, newest first.</p>
        </div>
      </div>

      <?php if ($deviceTotal === 0): ?>
        <?= partial('partials/empty-state', ['icon' => 'inbox', 'title' => 'No devices yet', 'text' => 'Add StudyTouch boards, cameras or Chromebooks, and any with a problem will be listed here.', 'compact' => true]) ?>
      <?php elseif ($attention === []): ?>
        <?= partial('partials/empty-state', ['icon' => 'circle-check', 'title' => 'Everything is running well', 'text' => 'No device has a problem right now.', 'compact' => true]) ?>
      <?php else: ?>
        <div class="table-scroll">
          <table class="data-table data-table--compact">
            <thead>
              <tr>
                <th scope="col">Device</th>
                <th scope="col">Page</th>
                <th scope="col">Latest note</th>
                <th scope="col" class="align-right">Since</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($attention as $d): $category = categories()->find((string) $d['category']); ?>
                <tr class="is-problem">
                  <th scope="row">
                    <a class="data-table__link" href="<?= e($d['url']) ?>"><?= e($d['title']) ?></a>
                    <span class="data-table__sub"><?= e($d['context']) ?></span>
                  </th>
                  <td><span class="chip"><?= e($category?->name() ?? '') ?></span></td>
                  <td class="dash-note"><?= $d['latest_note'] ? e($d['latest_note']) : '<span class="data-table__muted">No note yet</span>' ?></td>
                  <td class="data-table__muted align-right">
                    <?php if ($d['status_changed_at']): ?>
                      <time datetime="<?= e(iso_time($d['status_changed_at'])) ?>" title="<?= e(format_datetime($d['status_changed_at'])) ?>"><?= e(time_ago($d['status_changed_at'])) ?></time>
                    <?php endif ?>
                  </td>
                </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
        <?php if ($problemTotal > count($attention)): ?>
          <p class="attention__more">and <?= $problemTotal - count($attention) ?> more. Filter each page by "Has problem" to see them all.</p>
        <?php endif ?>
      <?php endif ?>
    </section>

    <section class="card" aria-labelledby="activity-title">
      <div class="card__header">
        <h2 class="card__title" id="activity-title">Recent activity</h2>
        <a class="icon-btn" href="/activity" title="All activity"><?= icon('arrow-right', '', 'All activity') ?></a>
      </div>
      <?php if ($feed === []): ?>
        <?= partial('partials/empty-state', ['icon' => 'history', 'title' => 'No notes yet', 'text' => 'Reports, problems, fixes and status changes will show up here.', 'compact' => true]) ?>
      <?php else: ?>
        <ol class="mini-feed">
          <?php foreach ($feed as $note):
              $kind = NoteKind::meta((string) $note['kind']);
              $cat = categories()->find((string) $note['category']);
              $meta = is_array($note['meta']) ? $note['meta'] : [];
              $text = $note['kind'] === NoteKind::STATUS && isset($meta['to']) && Status::isValid((string) $meta['to'])
                  ? 'Status changed to ' . ($cat?->statusLabel((string) $meta['to']) ?? $meta['to'])
                  : (string) $note['body'];
          ?>
            <li class="mini-feed__item">
              <span class="chip chip--<?= e(['problem' => 'problem', 'success' => 'success', 'action' => 'action'][$kind['tone']] ?? 'quiet') ?>"><?= e($kind['label']) ?></span>
              <a class="mini-feed__device" href="<?= e($note['url']) ?>"><?= e($note['title']) ?></a>
              <p class="mini-feed__text"><?= e($text) ?></p>
              <p class="mini-feed__meta"><?= e($note['author_name']) ?> · <time datetime="<?= e(iso_time($note['created_at'])) ?>" title="<?= e(format_datetime($note['created_at'])) ?>"><?= e(time_ago($note['created_at'])) ?></time></p>
            </li>
          <?php endforeach ?>
        </ol>
        <div class="mini-feed__more">
          <a class="btn btn--secondary btn--block" href="/activity">View all activity<?= icon('chevron-right', 'icon--sm') ?></a>
        </div>
      <?php endif ?>
    </section>
  </div>
</div>

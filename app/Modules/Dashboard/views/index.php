<?php
/**
 * Dashboard: header, search across every device, one stat strip (a cell per
 * category + "Needs attention"), an attention alert, then devices needing
 * attention (left) and recent activity (right). Same layout as the other
 * Pribadi apps.
 *
 * @var list<array{category:\App\Devices\Category, total:int, ok:int, problem:int}> $summaries
 * @var list<array<string, mixed>> $attention  problem or borrowed devices, newest first
 * @var array{problem:int, borrowed:int} $attentionCounts
 * @var int $attentionTotal
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
    <a class="stat stat--attention<?= $attentionTotal > 0 ? ' has-items' : '' ?>" href="#attention">
      <span class="stat__label">Needs attention <?= icon('arrow-right', 'icon--sm stat__arrow') ?></span>
      <span class="stat__value"><?= $attentionTotal ?> <span class="stat__unit"><?= $attentionTotal === 1 ? 'device' : 'devices' ?></span></span>
      <span class="stat__sub"><span class="status-word status-word--problem"><?= $attentionCounts['problem'] ?> problem</span> · <span class="status-word status-word--borrowed"><?= $attentionCounts['borrowed'] ?> borrowed</span></span>
    </a>
  </section>

  <?php if ($attentionTotal > 0): ?>
    <div class="dash-alert dash-alert--attention" role="status">
      <?= icon('circle-alert', 'icon--lg') ?>
      <div>
        <p class="dash-alert__title"><?= e(plural($attentionTotal, 'device')) ?> <?= $attentionTotal === 1 ? 'needs' : 'need' ?> attention</p>
        <p class="dash-alert__text">Review problems and borrowed devices, then add a note when the situation changes.</p>
      </div>
      <a class="link-quiet dash-alert__link" href="#attention">View list<?= icon('arrow-right', 'icon--sm') ?></a>
    </div>
  <?php endif ?>

  <div class="dash-columns">
    <section class="card" id="attention" aria-labelledby="attention-title">
      <div class="card__header card__header--stack">
        <div>
          <h2 class="card__title" id="attention-title">Needs attention</h2>
          <p class="card__subtitle">Problem and borrowed devices, newest first.</p>
        </div>
      </div>

      <?php if ($deviceTotal === 0): ?>
        <?= partial('partials/empty-state', ['icon' => 'inbox', 'title' => 'No devices yet', 'text' => 'Add devices to track problems and borrowed Chromebooks here.', 'compact' => true]) ?>
      <?php elseif ($attention === []): ?>
        <?= partial('partials/empty-state', ['icon' => 'circle-check', 'title' => 'Nothing needs attention', 'text' => 'No problems or borrowed devices to review right now.', 'compact' => true]) ?>
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
                <?php $borrowed = $d['attention_kind'] === NoteKind::BORROWED; ?>
                <tr class="attention-row attention-row--<?= $borrowed ? 'borrowed' : 'problem' ?>">
                  <th scope="row">
                    <a class="data-table__link" href="<?= e($d['url']) ?>"><?= e($d['title']) ?></a>
                    <span class="data-table__sub"><?= e($d['context']) ?></span>
                    <span class="chip chip--<?= $borrowed ? 'action' : 'problem' ?> attention-kind"><?= icon($borrowed ? 'user-round-check' : 'triangle-alert', 'icon--sm') ?><?= $borrowed ? 'Borrowed' : 'Problem' ?></span>
                  </th>
                  <td><span class="chip"><?= e($category?->name() ?? '') ?></span></td>
                  <td class="dash-note"><?= $d['latest_note'] ? e($d['latest_note']) : '<span class="data-table__muted">No note yet</span>' ?></td>
                  <td class="data-table__muted align-right">
                    <?php if ($d['attention_since']): ?>
                      <time datetime="<?= e(iso_time($d['attention_since'])) ?>" title="<?= e(format_datetime($d['attention_since'])) ?>"><?= e(time_ago($d['attention_since'])) ?></time>
                    <?php endif ?>
                  </td>
                </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
        <?php if ($attentionTotal > count($attention)): ?>
          <p class="attention__more">and <?= $attentionTotal - count($attention) ?> more. Open the device pages to review their latest notes.</p>
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

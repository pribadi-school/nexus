<?php
/**
 * The board list for the current filters. Also returned on its own to
 * filters.js when filters change.
 *
 * @var \App\Modules\StudyTouch\StudyTouchCategory $category
 * @var list<array<string, mixed>> $boards
 * @var array{total:int, ok:int, problem:int} $counts
 * @var bool $filtered
 */
use App\Devices\Status;


?>
<?php if ($counts['total'] === 0): ?>
  <section class="card">
    <?= partial('partials/empty-state', [
        'icon' => 'presentation',
        'title' => 'No boards yet',
        'text' => 'Add the StudyTouch boards one by one: room, model and firmware.',
        'actionHref' => '/studytouch/new',
        'actionLabel' => 'Add the first board',
    ]) ?>
  </section>
<?php elseif ($boards === []): ?>
  <section class="card">
    <div class="empty-state">
      <?= icon('search', 'icon--xl') ?>
      <p class="empty-state__title">No boards match</p>
      <p class="empty-state__text">Try another model or status, or check the spelling in the search.</p>
      <div class="empty-state__actions"><a class="btn btn--secondary" href="/studytouch" data-clear-filters>Clear filters</a></div>
    </div>
  </section>
<?php else: ?>
  <p class="results__summary">
    <?php if ($filtered): ?>
      Showing <?= count($boards) ?> of <?= e($category->count($counts['total'])) ?> · <a href="/studytouch" data-clear-filters>Clear filters</a>
    <?php else: ?>
      <?= e($category->count($counts['total'])) ?>, sorted by room
    <?php endif ?>
  </p>

  <?php /* Desktop: table */ ?>
  <div class="card table-card">
    <table class="data-table">
      <thead>
        <tr>
          <th scope="col">Room</th>
          <th scope="col">Model</th>
          <th scope="col">Firmware</th>
          <th scope="col">Latest note</th>
          <th scope="col">Last updated</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($boards as $board): $problem = $board['status'] === Status::PROBLEM; ?>
          <tr class="<?= $problem ? 'is-problem' : '' ?>" data-device-row="<?= (int) $board['id'] ?>">
            <th scope="row">
              <a class="data-table__link" href="/studytouch/<?= (int) $board['id'] ?>"><?= e($board['room_name']) ?></a>
              <?php if ($problem): ?><?= partial('partials/problem-flag', ['category' => $category]) ?><?php endif ?>
            </th>
            <td><?= e($board['model'] ?? '–') ?></td>
            <td class="num"><?= e($board['firmware_version'] ?? '–') ?></td>
            <td class="data-table__note"><?= partial('partials/latest-note', ['device' => $board, 'href' => '/studytouch/' . (int) $board['id']]) ?></td>
            <td class="data-table__muted">
              <time datetime="<?= e(iso_time($board['updated_at'])) ?>" title="<?= e(format_datetime($board['updated_at'])) ?><?= $board['updated_by_name'] ? ' by ' . e($board['updated_by_name']) : '' ?>"><?= e(time_ago($board['updated_at'])) ?></time>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>

  <?php /* Phones: cards */ ?>
  <ul class="device-cards">
    <?php foreach ($boards as $board): $problem = $board['status'] === Status::PROBLEM; ?>
      <li class="device-card<?= $problem ? ' is-problem' : '' ?>" data-device-row="<?= (int) $board['id'] ?>">
        <a class="device-card__main" href="/studytouch/<?= (int) $board['id'] ?>">
          <span class="device-card__title"><?= e($board['room_name']) ?></span>
          <span class="device-card__meta"><?= e($board['model'] ?? 'No model yet') ?></span>
          <?php if ($problem): ?><?= partial('partials/problem-flag', ['category' => $category]) ?><?php endif ?>
          <span class="device-card__note-label">Latest note</span>
          <?= partial('partials/latest-note', ['device' => $board]) ?>
          <span class="device-card__meta">
            <?= $board['firmware_version'] ? 'Firmware ' . e($board['firmware_version']) . ' · ' : '' ?>Updated <?= e(time_ago($board['updated_at'])) ?>
          </span>
        </a>
        <?= icon('chevron-right', 'device-card__chevron') ?>
      </li>
    <?php endforeach ?>
  </ul>
<?php endif ?>

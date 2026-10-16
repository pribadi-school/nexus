<?php
/**
 * One page of Chromebooks for the current filters. Also returned on its own
 * to filters.js when filters change.
 *
 * @var \App\Modules\Chromebooks\ChromebookCategory $category
 * @var list<array<string, mixed>> $chromebooks
 * @var int $matching  rows matching the filters, all pages
 * @var int $page
 * @var int $pages
 * @var array{total:int, ok:int, problem:int, unassigned:int} $counts
 * @var array{status:?string, class:?int, q:?string} $filters
 * @var bool $filtered
 */
use App\Devices\Status;
use App\Modules\Chromebooks\ChromebookRepository;

$query = array_filter([
    'q' => $filters['q'],
    'status' => $filters['status'],
    'class' => $filters['class'] === 0 ? 'none' : $filters['class'],
], static fn ($v) => $v !== null);
$pageUrl = static fn (int $p): string => '/chromebooks?' . http_build_query($query + ($p > 1 ? ['page' => $p] : []));
$exportUrl = '/chromebooks/export' . ($query ? '?' . http_build_query($query) : '');
$return = request_uri();
$first = ($page - 1) * ChromebookRepository::PER_PAGE + 1;
$student = static fn (array $c): string => $c['student_name'] ?? 'Not assigned';
?>
<?php if ($counts['total'] === 0): ?>
  <section class="card">
    <?= partial('partials/empty-state', [
        'icon' => 'laptop',
        'title' => 'No Chromebooks yet',
        'text' => 'Add them one by one, or import a CSV file with serial number, student and class for each one.',
        'actionHref' => '/chromebooks/import',
        'actionLabel' => 'Import a CSV file',
    ]) ?>
  </section>
<?php elseif ($chromebooks === []): ?>
  <section class="card">
    <div class="empty-state">
      <?= icon('search', 'icon--xl') ?>
      <p class="empty-state__title">No Chromebooks match</p>
      <p class="empty-state__text">Try another class or status, or check the serial or student name.</p>
      <div class="empty-state__actions"><a class="btn btn--secondary" href="/chromebooks" data-clear-filters>Clear filters</a></div>
    </div>
  </section>
<?php else: ?>
  <div class="results__bar">
    <p class="results__summary">
      <?php if ($pages > 1): ?>
        <?= $first ?>–<?= $first + count($chromebooks) - 1 ?> of <?= $filtered ? $matching . ' matching' : e($category->count($counts['total'])) ?>
      <?php elseif ($filtered): ?>
        Showing <?= $matching ?> of <?= e($category->count($counts['total'])) ?>
      <?php else: ?>
        <?= e($category->count($counts['total'])) ?>, sorted by class and student
      <?php endif ?>
      <?php if ($filtered): ?> · <a href="/chromebooks" data-clear-filters>Clear filters</a><?php endif ?>
    </p>
    <a class="btn btn--ghost btn--sm" href="<?= e($exportUrl) ?>" download><?= icon('download', 'icon--sm') ?>Export CSV<?= $filtered ? ' (' . $matching . ')' : '' ?></a>
  </div>

  <?php /* Desktop: table */ ?>
  <div class="card table-card">
    <table class="data-table">
      <thead>
        <tr>
          <th scope="col">Student</th>
          <th scope="col">Class</th>
          <th scope="col">Serial number</th>
          <th scope="col">Model</th>
          <th scope="col">Status</th>
          <th scope="col">Last updated</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($chromebooks as $c): $problem = $c['status'] === Status::PROBLEM; ?>
          <tr class="<?= $problem ? 'is-problem' : '' ?>" data-device-row="<?= (int) $c['id'] ?>">
            <th scope="row"><a class="data-table__link<?= $c['student_name'] === null ? ' is-unassigned' : '' ?>" href="/chromebooks/<?= (int) $c['id'] ?>"><?= e($student($c)) ?></a></th>
            <td><?= e($c['class_name'] ?? '–') ?></td>
            <td class="num"><?= e($c['serial_number'] ?? '–') ?></td>
            <td><?= e($c['model'] ?? '–') ?></td>
            <td><?= partial('partials/status-toggle', ['category' => $category, 'device' => $c, 'title' => $c['serial_number'] ?? $student($c), 'return' => $return]) ?></td>
            <td class="data-table__muted">
              <time datetime="<?= e(iso_time($c['updated_at'])) ?>" title="<?= e(format_datetime($c['updated_at'])) ?><?= $c['updated_by_name'] ? ' by ' . e($c['updated_by_name']) : '' ?>"><?= e(time_ago($c['updated_at'])) ?></time>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>

  <?php /* Phones: cards */ ?>
  <ul class="device-cards">
    <?php foreach ($chromebooks as $c): $problem = $c['status'] === Status::PROBLEM; ?>
      <li class="device-card<?= $problem ? ' is-problem' : '' ?>" data-device-row="<?= (int) $c['id'] ?>">
        <a class="device-card__main" href="/chromebooks/<?= (int) $c['id'] ?>">
          <span class="device-card__title<?= $c['student_name'] === null ? ' is-unassigned' : '' ?>"><?= e($student($c)) ?><?= $c['class_name'] ? ' · ' . e($c['class_name']) : '' ?></span>
          <span class="device-card__meta num"><?= e($c['serial_number'] ?? '–') ?></span>
          <span class="device-card__meta"><?= e($c['model'] ?? '') ?></span>
        </a>
        <?= partial('partials/status-toggle', ['category' => $category, 'device' => $c, 'title' => $c['serial_number'] ?? $student($c), 'return' => $return]) ?>
      </li>
    <?php endforeach ?>
  </ul>

  <?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Pages">
      <?php if ($page > 1): ?>
        <a class="btn btn--secondary" href="<?= e($pageUrl($page - 1)) ?>" rel="prev"><?= icon('chevron-left', 'icon--sm') ?>Previous</a>
      <?php else: ?>
        <span class="btn btn--secondary is-disabled" aria-disabled="true"><?= icon('chevron-left', 'icon--sm') ?>Previous</span>
      <?php endif ?>
      <span class="pager__info">Page <?= $page ?> of <?= $pages ?></span>
      <?php if ($page < $pages): ?>
        <a class="btn btn--secondary" href="<?= e($pageUrl($page + 1)) ?>" rel="next">Next<?= icon('chevron-right', 'icon--sm') ?></a>
      <?php else: ?>
        <span class="btn btn--secondary is-disabled" aria-disabled="true">Next<?= icon('chevron-right', 'icon--sm') ?></span>
      <?php endif ?>
    </nav>
  <?php endif ?>
<?php endif ?>

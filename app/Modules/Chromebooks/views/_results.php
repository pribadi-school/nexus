<?php
/**
 * One page of Chromebooks (every year, or one) for the current filters. Also
 * returned on its own to filters.js when filters change.
 *
 * @var \App\Modules\Chromebooks\ChromebookCategory $category
 * @var list<array<string, mixed>> $chromebooks
 * @var int $matching  rows matching the filters, all pages
 * @var int $page
 * @var int $pages
 * @var array{total:int, ok:int, problem:int, unassigned:int} $counts
 * @var array{year:?int, status:?string, q:?string} $filters  year null = every year
 * @var bool $filtered
 */
use App\Devices\Status;
use App\Modules\Chromebooks\ChromebookRepository;

$year = $filters['year'];
$query = array_filter([
    'year' => $year,
    'q' => $filters['q'],
    'status' => $filters['status'],
], static fn ($v) => $v !== null);
$pageUrl = static fn (int $p): string => '/chromebooks?' . http_build_query($query + ($p > 1 ? ['page' => $p] : []));
$exportUrl = '/chromebooks/export?' . http_build_query($query);
$clearUrl = '/chromebooks';
$importYear = $year ?? categories()->get('chromebook')->years()[0];
$first = ($page - 1) * ChromebookRepository::PER_PAGE + 1;
$student = static fn (array $c): string => $c['student_name'] ?? 'Not assigned';
?>
<?php if ($counts['total'] === 0): ?>
  <section class="card">
    <?= partial('partials/empty-state', [
        'icon' => 'laptop',
        'title' => $year !== null ? "No Chromebooks for $year yet" : 'No Chromebooks yet',
        'text' => "Import a year's list (Excel or CSV, with asset ID, serial number, student and email), or add them one by one.",
        'actionHref' => "/chromebooks/import?year=$importYear",
        'actionLabel' => "Import the $importYear list",
    ]) ?>
  </section>
<?php elseif ($chromebooks === []): ?>
  <section class="card">
    <div class="empty-state">
      <?= icon('search', 'icon--xl') ?>
      <p class="empty-state__title">No Chromebooks match</p>
      <p class="empty-state__text">Try another year or status, or check the asset ID, serial or student name.</p>
      <div class="empty-state__actions"><a class="btn btn--secondary" href="<?= e($clearUrl) ?>" data-clear-filters>Clear filters</a></div>
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
        <?= e($category->count($counts['total'])) ?>, newest year first
      <?php endif ?>
      <?php if ($filtered): ?> · <a href="<?= e($clearUrl) ?>" data-clear-filters>Clear filters</a><?php endif ?>
    </p>
    <a class="btn btn--ghost btn--sm" href="<?= e($exportUrl) ?>" download><?= icon('download', 'icon--sm') ?>Export CSV<?= $filtered ? ' (' . $matching . ')' : '' ?></a>
  </div>

  <?php /* Desktop: table */ ?>
  <div class="card table-card">
    <table class="data-table">
      <thead>
        <tr>
          <th scope="col">Asset ID</th>
          <th scope="col">Student</th>
          <th scope="col">Serial number</th>
          <th scope="col">Last updated</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($chromebooks as $c): $problem = $c['status'] === Status::PROBLEM; ?>
          <tr class="<?= $problem ? 'is-problem' : '' ?>" data-device-row="<?= (int) $c['id'] ?>">
            <th scope="row" class="num nowrap"><a class="data-table__link" href="/chromebooks/<?= (int) $c['id'] ?>"><?= e($c['asset_id'] ?? '–') ?></a></th>
            <td>
              <a class="data-table__link<?= $c['student_name'] === null ? ' is-unassigned' : '' ?>" href="/chromebooks/<?= (int) $c['id'] ?>"><?= e($student($c)) ?></a>
              <?php if ($c['student_email']): ?><span class="data-table__sub"><?= e($c['student_email']) ?></span><?php endif ?>
              <?php if ($problem): ?><?= partial('partials/problem-flag', ['category' => $category]) ?><?php endif ?>
            </td>
            <td class="num"><?= e($c['serial_number'] ?? '–') ?></td>
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
          <span class="device-card__title<?= $c['student_name'] === null ? ' is-unassigned' : '' ?>"><?= e($student($c)) ?></span>
          <span class="device-card__meta num"><?= e(implode(' · ', array_filter([$c['asset_id'], $c['serial_number'] ?? 'No serial yet']))) ?></span>
          <?php if ($problem): ?><?= partial('partials/problem-flag', ['category' => $category]) ?><?php endif ?>
        </a>
        <?= icon('chevron-right', 'device-card__chevron') ?>
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

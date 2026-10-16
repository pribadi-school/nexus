<?php
/**
 * @var \App\Modules\Chromebooks\ChromebookCategory $category
 * @var array{total:int, ok:int, problem:int, unassigned:int} $counts  for the selected year
 * @var array{year:int, status:?string, q:?string} $filters
 * @var list<int> $years  newest first
 * @var array<int, array{total:int, problem:int}> $yearCounts
 */
$year = $filters['year'];
?>
<header class="page-header">
  <div>
    <h1 class="page-title">Chromebooks</h1>
    <p class="page-subtitle">
      Academic year <?= $year ?> · <?= e($category->count($counts['total'])) ?>
      <?php if ($counts['problem'] > 0): ?>
        · <span class="status-word status-word--problem"><?= $counts['problem'] ?> <?= $counts['problem'] === 1 ? 'has a problem' : 'have a problem' ?></span>
      <?php endif ?>
      <?php if ($counts['unassigned'] > 0): ?> · <?= $counts['unassigned'] ?> not assigned<?php endif ?>
    </p>
  </div>
  <div class="page-actions">
    <a class="btn btn--secondary" href="/chromebooks/import?year=<?= $year ?>"><?= icon('upload', 'icon--sm') ?>Import</a>
    <a class="btn btn--primary" href="/chromebooks/new?year=<?= $year ?>"><?= icon('plus', 'icon--sm') ?>Add Chromebook</a>
  </div>
</header>

<nav class="year-tabs" aria-label="Academic year">
  <?php foreach ($years as $y): $n = $yearCounts[$y] ?? ['total' => 0, 'problem' => 0]; ?>
    <a class="year-tab" href="/chromebooks?year=<?= $y ?>"<?= $y === $year ? ' aria-current="page"' : '' ?>>
      <?= $y ?>
      <span class="year-tab__count"><?= $n['total'] ?><?php if ($n['problem'] > 0): ?> · <span class="year-tab__problem"><?= $n['problem'] ?> problem<?= $n['problem'] === 1 ? '' : 's' ?></span><?php endif ?></span>
    </a>
  <?php endforeach ?>
</nav>

<form class="filters card" method="get" action="/chromebooks" role="search" aria-label="Filter Chromebooks" data-live-filter>
  <input type="hidden" name="year" value="<?= $year ?>">
  <div class="filters__search">
    <?= icon('search', 'icon--sm') ?>
    <label class="visually-hidden" for="filter-q">Search by asset ID, serial, student or email</label>
    <input class="input" id="filter-q" type="search" name="q" value="<?= e($filters['q'] ?? '') ?>"
           placeholder="Search asset ID, serial, student" autocomplete="off" enterkeyhint="search">
  </div>
  <div class="filters__field">
    <label class="visually-hidden" for="filter-status">Status</label>
    <select class="select" id="filter-status" name="status">
      <option value="">All statuses</option>
      <option value="ok"<?= $filters['status'] === 'ok' ? ' selected' : '' ?>>OK (<?= $counts['ok'] ?>)</option>
      <option value="problem"<?= $filters['status'] === 'problem' ? ' selected' : '' ?>>Has problem (<?= $counts['problem'] ?>)</option>
    </select>
  </div>
  <button type="submit" class="btn btn--secondary filters__apply">Apply</button>
</form>

<div class="results" data-results aria-live="polite">
  <?= partial('Chromebooks::_results', get_defined_vars()) ?>
</div>

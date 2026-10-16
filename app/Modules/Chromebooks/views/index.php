<?php
/**
 * @var \App\Modules\Chromebooks\ChromebookCategory $category
 * @var array{total:int, ok:int, problem:int, unassigned:int} $counts
 * @var array{status:?string, class:?int, q:?string} $filters
 * @var list<array{id:int, name:string}> $classes
 */
?>
<header class="page-header">
  <div>
    <h1 class="page-title">Chromebooks</h1>
    <p class="page-subtitle">
      <?= e($category->count($counts['total'])) ?>
      <?php if ($counts['problem'] > 0): ?>
        · <span class="status-word status-word--problem"><?= $counts['problem'] ?> <?= $counts['problem'] === 1 ? 'has a problem' : 'have a problem' ?></span>
      <?php endif ?>
      <?php if ($counts['unassigned'] > 0): ?> · <?= $counts['unassigned'] ?> not assigned<?php endif ?>
    </p>
  </div>
  <div class="page-actions">
    <a class="btn btn--secondary" href="/chromebooks/classes"><?= icon('users', 'icon--sm') ?>Classes</a>
    <a class="btn btn--secondary" href="/chromebooks/import"><?= icon('upload', 'icon--sm') ?>Import</a>
    <a class="btn btn--primary" href="/chromebooks/new"><?= icon('plus', 'icon--sm') ?>Add Chromebook</a>
  </div>
</header>

<form class="filters card" method="get" action="/chromebooks" role="search" aria-label="Filter Chromebooks" data-live-filter>
  <div class="filters__search">
    <?= icon('search', 'icon--sm') ?>
    <label class="visually-hidden" for="filter-q">Search by serial or student</label>
    <input class="input" id="filter-q" type="search" name="q" value="<?= e($filters['q'] ?? '') ?>"
           placeholder="Search serial or student" autocomplete="off" enterkeyhint="search">
  </div>
  <div class="filters__field">
    <label class="visually-hidden" for="filter-status">Status</label>
    <select class="select" id="filter-status" name="status">
      <option value="">All statuses</option>
      <option value="ok"<?= $filters['status'] === 'ok' ? ' selected' : '' ?>>OK (<?= $counts['ok'] ?>)</option>
      <option value="problem"<?= $filters['status'] === 'problem' ? ' selected' : '' ?>>Has problem (<?= $counts['problem'] ?>)</option>
    </select>
  </div>
  <div class="filters__field">
    <label class="visually-hidden" for="filter-class">Class</label>
    <select class="select" id="filter-class" name="class">
      <option value="">All classes</option>
      <?php foreach ($classes as $class): ?>
        <option value="<?= (int) $class['id'] ?>"<?= $filters['class'] === (int) $class['id'] ? ' selected' : '' ?>><?= e($class['name']) ?></option>
      <?php endforeach ?>
      <option value="none"<?= $filters['class'] === 0 ? ' selected' : '' ?>>No class</option>
    </select>
  </div>
  <button type="submit" class="btn btn--secondary filters__apply">Apply</button>
</form>

<div class="results" data-results aria-live="polite">
  <?= partial('Chromebooks::_results', get_defined_vars()) ?>
</div>

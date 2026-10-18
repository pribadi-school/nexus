<?php
/**
 * @var \App\Modules\Cctv\CctvCategory $category
 * @var list<array<string, mixed>> $cameras
 * @var array{total:int, ok:int, problem:int} $counts
 * @var array{status:?string, type:?string, location:?string, q:?string} $filters
 * @var list<array{id:int, name:string}> $locations
 * @var bool $filtered
 */
use App\Modules\Cctv\CctvCategory;
?>
<header class="page-header">
  <div>
    <h1 class="page-title">CCTV</h1>
    <p class="page-subtitle">
      <?= e($category->count($counts['total'])) ?>
      <?php if ($counts['problem'] > 0): ?>
        · <span class="status-word status-word--problem"><?= $counts['problem'] ?> <?= $counts['problem'] === 1 ? 'has a problem' : 'have a problem' ?></span>
      <?php endif ?>
    </p>
  </div>
  <div class="page-actions">
    <a class="btn btn--secondary" href="/cctv/locations"><?= icon('map-pin', 'icon--sm') ?>Locations</a>
    <a class="btn btn--primary" href="/cctv/new"><?= icon('plus', 'icon--sm') ?>Add camera</a>
  </div>
</header>

<form class="filters filters--cctv card" method="get" action="/cctv" role="search" aria-label="Filter cameras" data-live-filter>
  <div class="filters__search">
    <?= icon('search', 'icon--sm') ?>
    <label class="visually-hidden" for="filter-q">Search spots</label>
    <input class="input" id="filter-q" type="search" name="q" value="<?= e($filters['q'] ?? '') ?>"
           placeholder="Search spot or location" autocomplete="off" enterkeyhint="search">
  </div>
  <div class="filters__field filters__field--location">
    <label class="visually-hidden" for="filter-location">Location</label>
    <select class="select" id="filter-location" name="location">
      <option value="">All locations</option>
      <?php foreach ($locations as $location): ?>
        <option value="<?= (int) $location['id'] ?>"<?= $filters['location'] === (string) $location['id'] ? ' selected' : '' ?>><?= e($location['name']) ?></option>
      <?php endforeach ?>
      <option value="unassigned"<?= $filters['location'] === 'unassigned' ? ' selected' : '' ?>>Not assigned</option>
    </select>
  </div>
  <div class="filters__field">
    <label class="visually-hidden" for="filter-type">Type</label>
    <select class="select" id="filter-type" name="type">
      <option value="">All types</option>
      <?php foreach (CctvCategory::TYPES as $type): ?>
        <option value="<?= e($type) ?>"<?= $filters['type'] === $type ? ' selected' : '' ?>><?= e($type) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="filters__field">
    <label class="visually-hidden" for="filter-status">Status</label>
    <select class="select" id="filter-status" name="status">
      <option value="">All statuses</option>
      <option value="ok"<?= $filters['status'] === 'ok' ? ' selected' : '' ?>>Running well (<?= $counts['ok'] ?>)</option>
      <option value="problem"<?= $filters['status'] === 'problem' ? ' selected' : '' ?>>Has problem (<?= $counts['problem'] ?>)</option>
    </select>
  </div>
  <button type="submit" class="btn btn--secondary filters__apply">Apply</button>
</form>

<div class="results" data-results aria-live="polite">
  <?= partial('Cctv::_results', get_defined_vars()) ?>
</div>

<?php
/**
 * @var \App\Modules\Cctv\CctvCategory $category
 * @var list<array<string, mixed>> $cameras
 * @var list<array{area:string, cameras:list<array<string, mixed>>}> $groups
 * @var array{total:int, ok:int, problem:int} $counts
 * @var array{status:?string, area:?int, q:?string} $filters
 * @var bool $filtered
 * @var list<array{id:int, name:string}> $areas
 */
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
    <a class="btn btn--secondary" href="/cctv/areas"><?= icon('map-pin', 'icon--sm') ?>Areas</a>
    <a class="btn btn--primary" href="/cctv/new"><?= icon('plus', 'icon--sm') ?>Add camera</a>
  </div>
</header>

<form class="filters card" method="get" action="/cctv" role="search" aria-label="Filter cameras" data-live-filter>
  <div class="filters__search">
    <?= icon('search', 'icon--sm') ?>
    <label class="visually-hidden" for="filter-q">Search cameras</label>
    <input class="input" id="filter-q" type="search" name="q" value="<?= e($filters['q'] ?? '') ?>"
           placeholder="Search label, location, IP, model" autocomplete="off" enterkeyhint="search">
  </div>
  <div class="filters__field">
    <label class="visually-hidden" for="filter-status">Status</label>
    <select class="select" id="filter-status" name="status">
      <option value="">All statuses</option>
      <option value="ok"<?= $filters['status'] === 'ok' ? ' selected' : '' ?>>Running well (<?= $counts['ok'] ?>)</option>
      <option value="problem"<?= $filters['status'] === 'problem' ? ' selected' : '' ?>>Has problem (<?= $counts['problem'] ?>)</option>
    </select>
  </div>
  <div class="filters__field">
    <label class="visually-hidden" for="filter-area">Area</label>
    <select class="select" id="filter-area" name="area">
      <option value="">All areas</option>
      <?php foreach ($areas as $area): ?>
        <option value="<?= (int) $area['id'] ?>"<?= $filters['area'] === (int) $area['id'] ? ' selected' : '' ?>><?= e($area['name']) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <button type="submit" class="btn btn--secondary filters__apply">Apply</button>
</form>

<div class="results" data-results aria-live="polite">
  <?= partial('Cctv::_results', get_defined_vars()) ?>
</div>

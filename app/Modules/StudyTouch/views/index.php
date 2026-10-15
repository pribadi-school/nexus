<?php
/**
 * @var \App\Modules\StudyTouch\StudyTouchCategory $category
 * @var list<array<string, mixed>> $boards
 * @var array{total:int, ok:int, problem:int} $counts
 * @var array{status:?string, room:?int, q:?string} $filters
 * @var bool $filtered
 * @var list<array{id:int, name:string}> $rooms
 */
?>
<header class="page-header">
  <div>
    <h1 class="page-title">StudyTouch</h1>
    <p class="page-subtitle">
      <?= e($category->count($counts['total'])) ?>
      <?php if ($counts['problem'] > 0): ?>
        · <span class="status-word status-word--problem"><?= $counts['problem'] ?> <?= $counts['problem'] === 1 ? 'has a problem' : 'have a problem' ?></span>
      <?php endif ?>
    </p>
  </div>
  <div class="page-actions">
    <a class="btn btn--secondary" href="/studytouch/rooms"><?= icon('map-pin', 'icon--sm') ?>Rooms</a>
    <a class="btn btn--primary" href="/studytouch/new"><?= icon('plus', 'icon--sm') ?>Add board</a>
  </div>
</header>

<form class="filters card" method="get" action="/studytouch" role="search" aria-label="Filter boards" data-live-filter>
  <div class="filters__search">
    <?= icon('search', 'icon--sm') ?>
    <label class="visually-hidden" for="filter-q">Search boards</label>
    <input class="input" id="filter-q" type="search" name="q" value="<?= e($filters['q'] ?? '') ?>"
           placeholder="Search room, model, serial, firmware" autocomplete="off" enterkeyhint="search">
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
    <label class="visually-hidden" for="filter-room">Room</label>
    <select class="select" id="filter-room" name="room">
      <option value="">All rooms</option>
      <?php foreach ($rooms as $room): ?>
        <option value="<?= (int) $room['id'] ?>"<?= $filters['room'] === (int) $room['id'] ? ' selected' : '' ?>><?= e($room['name']) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <button type="submit" class="btn btn--secondary filters__apply">Apply</button>
</form>

<div class="results" data-results aria-live="polite">
  <?= partial('StudyTouch::_results', get_defined_vars()) ?>
</div>

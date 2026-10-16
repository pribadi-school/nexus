<?php
/**
 * @var \App\Modules\StudyTouch\StudyTouchCategory $category
 * @var array<string, mixed> $board
 * @var list<array<string, mixed>> $notes
 * @var int $noteCount
 */
use App\Devices\Status;

$id = (int) $board['id'];
$problem = $board['status'] === Status::PROBLEM;
?>
<a class="back-link" href="/studytouch"><?= icon('chevron-left', 'icon--sm') ?>StudyTouch</a>

<header class="page-header">
  <div>
    <h1 class="page-title"><?= e($board['room_name']) ?></h1>
    <p class="page-subtitle"><?= e($board['model'] === 'Merah Putih' ? 'Merah Putih board' : 'StudyTouch board') ?></p>
  </div>
  <div class="page-actions">
    <a class="btn btn--secondary" href="/studytouch/<?= $id ?>/edit"><?= icon('pencil', 'icon--sm') ?>Edit</a>
    <form method="post" action="/studytouch/<?= $id ?>/delete"
          data-confirm="Delete the board in <?= e($board['room_name']) ?>?"
          data-confirm-text="It disappears from every list. Its notes are kept in the database."
          data-confirm-button="Delete board" data-confirm-tone="danger">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn--danger-quiet"><?= icon('trash-2', 'icon--sm') ?>Delete</button>
    </form>
  </div>
</header>

<?php /* Order: status, notes, details (phones read top to bottom); on desktop
   the notes move to a right-hand column. */ ?>
<div class="detail">
    <section class="card status-card detail__status<?= $problem ? ' is-problem' : '' ?>" aria-labelledby="status-title" data-device-row="<?= $id ?>">
      <h2 class="status-card__title" id="status-title">Status</h2>
      <?= partial('partials/status-toggle', ['category' => $category, 'device' => $board, 'title' => $board['room_name'], 'return' => request_uri(), 'large' => true, 'instant' => false]) ?>
      <p class="status-card__since">
        <?php if ($board['status_changed_at']): ?>
          Since <time datetime="<?= e(iso_time($board['status_changed_at'])) ?>" title="<?= e(format_datetime($board['status_changed_at'])) ?>"><?= e(time_ago($board['status_changed_at'])) ?></time>
        <?php endif ?>
      </p>
    </section>

    <div class="detail__notes">
      <?= partial('partials/notes-log', ['category' => $category, 'device' => $board, 'notes' => $notes, 'noteCount' => $noteCount]) ?>
    </div>

    <section class="card detail__info" aria-labelledby="details-title">
      <div class="card__header"><h2 class="card__title" id="details-title">Details</h2></div>
      <dl class="details">
        <div><dt>Room</dt><dd><?= e($board['room_name']) ?></dd></div>
        <div><dt>Model</dt><dd><?= e($board['model'] ?? '–') ?></dd></div>
        <div><dt>Firmware</dt><dd class="num"><?= e($board['firmware_version'] ?? '–') ?></dd></div>
        <div>
          <dt>Last updated</dt>
          <dd><time datetime="<?= e(iso_time($board['updated_at'])) ?>"><?= e(format_datetime($board['updated_at'])) ?></time><?= $board['updated_by_name'] ? ' by ' . e($board['updated_by_name']) : '' ?></dd>
        </div>
        <div>
          <dt>Added</dt>
          <dd><time datetime="<?= e(iso_time($board['created_at'])) ?>"><?= e(format_datetime($board['created_at'])) ?></time><?= $board['created_by_name'] ? ' by ' . e($board['created_by_name']) : '' ?></dd>
        </div>
      </dl>
    </section>
</div>

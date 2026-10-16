<?php
/**
 * @var \App\Modules\Cctv\CctvCategory $category
 * @var array<string, mixed> $camera
 * @var list<array<string, mixed>> $notes
 * @var int $noteCount
 */
use App\Devices\Status;

$id = (int) $camera['id'];
$problem = $camera['status'] === Status::PROBLEM;
?>
<a class="back-link" href="/cctv"><?= icon('chevron-left', 'icon--sm') ?>CCTV</a>

<header class="page-header">
  <div>
    <h1 class="page-title"><?= e($camera['label']) ?></h1>
    <p class="page-subtitle"><?= e(implode(' · ', array_filter([$camera['model'], $camera['sd_card_gb'] !== null ? (int) $camera['sd_card_gb'] . ' GB SD card' : null]))) ?></p>
  </div>
  <div class="page-actions">
    <form method="post" action="/cctv/<?= $id ?>/checked" data-busy-on-submit>
      <?= csrf_field() ?>
      <button type="submit" class="btn btn--secondary"><?= icon('check', 'icon--sm') ?>Mark as checked</button>
    </form>
    <a class="btn btn--secondary" href="/cctv/<?= $id ?>/edit"><?= icon('pencil', 'icon--sm') ?>Edit</a>
    <form method="post" action="/cctv/<?= $id ?>/delete"
          data-confirm="Delete camera <?= e($camera['label']) ?>?"
          data-confirm-text="It disappears from every list. Its notes are kept in the database."
          data-confirm-button="Delete camera" data-confirm-tone="danger">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn--danger-quiet"><?= icon('trash-2', 'icon--sm') ?>Delete</button>
    </form>
  </div>
</header>

<div class="detail">
    <section class="card status-card detail__status<?= $problem ? ' is-problem' : '' ?>" aria-labelledby="status-title" data-device-row="<?= $id ?>">
      <h2 class="status-card__title" id="status-title">Status</h2>
      <?= partial('partials/status-toggle', ['category' => $category, 'device' => $camera, 'title' => $camera['label'], 'return' => request_uri(), 'large' => true, 'instant' => false]) ?>
      <p class="status-card__since">
        <?php if ($camera['status_changed_at']): ?>
          Since <time datetime="<?= e(iso_time($camera['status_changed_at'])) ?>" title="<?= e(format_datetime($camera['status_changed_at'])) ?>"><?= e(time_ago($camera['status_changed_at'])) ?></time>
        <?php endif ?>
      </p>
    </section>

    <div class="detail__notes">
      <?= partial('partials/notes-log', ['category' => $category, 'device' => $camera, 'notes' => $notes, 'noteCount' => $noteCount]) ?>
    </div>

    <section class="card detail__info" aria-labelledby="details-title">
      <div class="card__header"><h2 class="card__title" id="details-title">Details</h2></div>
      <dl class="details">
        <div><dt>Spot</dt><dd><?= e($camera['label']) ?></dd></div>
        <div><dt>Type</dt><dd><?= e($camera['model'] ?? '–') ?></dd></div>
        <div><dt>SD card</dt><dd class="num"><?= $camera['sd_card_gb'] !== null ? (int) $camera['sd_card_gb'] . ' GB' : '–' ?></dd></div>
        <div>
          <dt>Last checked</dt>
          <dd><?php if ($camera['last_checked_at']): ?><time datetime="<?= e(iso_time($camera['last_checked_at'])) ?>"><?= e(format_datetime($camera['last_checked_at'])) ?></time> (<?= e(time_ago($camera['last_checked_at'])) ?>)<?php else: ?>Never<?php endif ?></dd>
        </div>
        <div>
          <dt>Last updated</dt>
          <dd><time datetime="<?= e(iso_time($camera['updated_at'])) ?>"><?= e(format_datetime($camera['updated_at'])) ?></time><?= $camera['updated_by_name'] ? ' by ' . e($camera['updated_by_name']) : '' ?></dd>
        </div>
        <div>
          <dt>Added</dt>
          <dd><time datetime="<?= e(iso_time($camera['created_at'])) ?>"><?= e(format_datetime($camera['created_at'])) ?></time><?= $camera['created_by_name'] ? ' by ' . e($camera['created_by_name']) : '' ?></dd>
        </div>
      </dl>
    </section>
</div>

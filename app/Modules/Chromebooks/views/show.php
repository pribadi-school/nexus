<?php
/**
 * @var \App\Modules\Chromebooks\ChromebookCategory $category
 * @var array<string, mixed> $chromebook
 * @var list<array<string, mixed>> $notes
 * @var int $noteCount
 */
use App\Devices\Status;

$id = (int) $chromebook['id'];
$problem = $chromebook['status'] === Status::PROBLEM;
$label = $chromebook['asset_id'] ?? $chromebook['serial_number'] ?? '#' . $id;
$title = $chromebook['student_name'] ?? 'Chromebook ' . $label;
$back = '/chromebooks?year=' . (int) $chromebook['academic_year'];
?>
<a class="back-link" href="<?= e($back) ?>"><?= icon('chevron-left', 'icon--sm') ?>Chromebooks <?= e($category->yearLabel((int) $chromebook['academic_year'])) ?></a>

<header class="page-header">
  <div>
    <h1 class="page-title"><?= e($title) ?></h1>
    <p class="page-subtitle">
      <?= e(implode(' · ', array_filter([
          $chromebook['student_name'] !== null ? $chromebook['asset_id'] : 'Not assigned',
          $category->yearHeading((int) $chromebook['academic_year']),
          $chromebook['model'],
      ]))) ?>
    </p>
  </div>
  <div class="page-actions">
    <a class="btn btn--secondary" href="/chromebooks/<?= $id ?>/edit"><?= icon('pencil', 'icon--sm') ?>Edit</a>
    <form method="post" action="/chromebooks/<?= $id ?>/delete"
          data-confirm="Delete Chromebook <?= e($label) ?>?"
          data-confirm-text="It disappears from every list. Its notes and assignment history are kept in the database."
          data-confirm-button="Delete Chromebook" data-confirm-tone="danger">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn--danger-quiet"><?= icon('trash-2', 'icon--sm') ?>Delete</button>
    </form>
  </div>
</header>

<div class="detail detail--with-assign">
    <section class="card status-card detail__status<?= $problem ? ' is-problem' : '' ?>" aria-labelledby="status-title" data-device-row="<?= $id ?>">
      <h2 class="status-card__title" id="status-title">Status</h2>
      <?= partial('Chromebooks::_status', ['category' => $category, 'status' => $chromebook['status']]) ?>
      <form class="chromebook-status-form" method="post" action="/devices/<?= $id ?>/status" data-busy-on-submit>
        <?= csrf_field() ?>
        <input type="hidden" name="return" value="<?= e(request_uri()) ?>">
        <label class="visually-hidden" for="device-status">Change Chromebook status</label>
        <select class="select" id="device-status" name="status" required>
          <?php if (!$category->acceptsStatus($chromebook['status'])): ?>
            <option value="" selected disabled>Choose status</option>
          <?php endif ?>
          <?php foreach ($category->statuses() as $status): ?>
            <option value="<?= e($status) ?>"<?= $chromebook['status'] === $status ? ' selected' : '' ?>><?= e($category->statusLabel($status)) ?></option>
          <?php endforeach ?>
        </select>
        <button class="btn btn--primary" type="submit">Save status</button>
      </form>
      <p class="status-card__since">
        <?php if ($chromebook['status_changed_at']): ?>
          Since <time datetime="<?= e(iso_time($chromebook['status_changed_at'])) ?>" title="<?= e(format_datetime($chromebook['status_changed_at'])) ?>"><?= e(time_ago($chromebook['status_changed_at'])) ?></time>
        <?php endif ?>
      </p>
    </section>

    <section class="card detail__assign chromebook-info-placeholder" aria-label="Additional information">
      <p>-</p>
    </section>

    <div class="detail__notes">
      <?= partial('partials/notes-log', ['category' => $category, 'device' => $chromebook, 'notes' => $notes, 'noteCount' => $noteCount]) ?>
      <?= partial('Chromebooks::_google-details', $google) ?>
    </div>

    <section class="card detail__info" aria-labelledby="details-title">
      <div class="card__header"><h2 class="card__title" id="details-title">Details</h2></div>
      <dl class="details">
        <div><dt>Asset ID</dt><dd class="num"><?= e($chromebook['asset_id'] ?? '–') ?></dd></div>
        <div><dt>Year / category</dt><dd><?= e($category->yearLabel((int) $chromebook['academic_year'])) ?></dd></div>
        <div><dt>Serial number</dt><dd class="num"><?= e($chromebook['serial_number'] ?? 'Not recorded yet') ?></dd></div>
        <div><dt>Model</dt><dd><?= e($chromebook['model'] ?? '–') ?></dd></div>
        <div>
          <dt>Last updated</dt>
          <dd><time datetime="<?= e(iso_time($chromebook['updated_at'])) ?>"><?= e(format_datetime($chromebook['updated_at'])) ?></time><?= $chromebook['updated_by_name'] ? ' by ' . e($chromebook['updated_by_name']) : '' ?></dd>
        </div>
        <div>
          <dt>Added</dt>
          <dd><time datetime="<?= e(iso_time($chromebook['created_at'])) ?>"><?= e(format_datetime($chromebook['created_at'])) ?></time><?= $chromebook['created_by_name'] ? ' by ' . e($chromebook['created_by_name']) : '' ?></dd>
        </div>
      </dl>
    </section>
</div>

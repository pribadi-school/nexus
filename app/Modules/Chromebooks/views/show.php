<?php
/**
 * @var \App\Modules\Chromebooks\ChromebookCategory $category
 * @var array<string, mixed> $chromebook
 * @var list<array<string, mixed>> $history  newest first
 * @var list<array<string, mixed>> $notes
 * @var int $noteCount
 */
use App\Devices\Status;

$id = (int) $chromebook['id'];
$problem = $chromebook['status'] === Status::PROBLEM;
$label = $chromebook['asset_id'] ?? $chromebook['serial_number'] ?? '#' . $id;
$title = $chromebook['student_name'] ?? 'Chromebook ' . $label;
$back = '/chromebooks?year=' . (int) $chromebook['academic_year'];
$day = static fn (string $utc): string => local_time($utc)?->format('j M Y') ?? '';
?>
<a class="back-link" href="<?= e($back) ?>"><?= icon('chevron-left', 'icon--sm') ?>Chromebooks <?= (int) $chromebook['academic_year'] ?></a>

<header class="page-header">
  <div>
    <h1 class="page-title"><?= e($title) ?></h1>
    <p class="page-subtitle">
      <?= e(implode(' · ', array_filter([
          $chromebook['student_name'] !== null ? $chromebook['asset_id'] : 'Not assigned',
          'Academic year ' . $chromebook['academic_year'],
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
      <?= partial('partials/status-toggle', ['category' => $category, 'device' => $chromebook, 'title' => $label, 'return' => request_uri(), 'large' => true, 'instant' => false]) ?>
      <p class="status-card__since">
        <?php if ($chromebook['status_changed_at']): ?>
          Since <time datetime="<?= e(iso_time($chromebook['status_changed_at'])) ?>" title="<?= e(format_datetime($chromebook['status_changed_at'])) ?>"><?= e(time_ago($chromebook['status_changed_at'])) ?></time>
        <?php endif ?>
      </p>
    </section>

    <section class="card detail__assign" id="assign" aria-labelledby="assign-title">
      <div class="card__header"><h2 class="card__title" id="assign-title">Assigned to</h2></div>
      <form class="assign-form" method="post" action="/chromebooks/<?= $id ?>/assign" data-busy-on-submit>
        <?= csrf_field() ?>
        <div class="field">
          <label class="field__label" for="student_name">Student</label>
          <input class="input" id="student_name" name="student_name" type="text" maxlength="120" autocomplete="off"
                 value="<?= e($chromebook['student_name'] ?? '') ?>" placeholder="Full name">
        </div>
        <div class="field">
          <label class="field__label" for="student_email">Email</label>
          <input class="input" id="student_email" name="student_email" type="email" maxlength="160" autocomplete="off" spellcheck="false"
                 value="<?= e($chromebook['student_email'] ?? '') ?>" placeholder="name@pribadidepok.sch.id">
        </div>
        <p class="field__hint assign-form__hint">Change the name and save to give it to another student. Empty the name to mark it as returned. The change is kept in the history below.</p>
        <div class="assign-form__actions">
          <button type="submit" class="btn btn--primary"><?= icon('arrow-left-right', 'icon--sm') ?>Save assignment</button>
        </div>
      </form>

      <h3 class="history__title">History</h3>
      <?php if ($history === []): ?>
        <p class="history__empty">Never assigned yet.</p>
      <?php else: ?>
        <ol class="history">
          <?php foreach ($history as $h): ?>
            <li class="history__item<?= $h['ended_at'] === null ? ' is-current' : '' ?>">
              <span class="history__who"><?= e($h['student_name']) ?><?= $h['class_name'] ? ' · ' . e($h['class_name']) : '' ?></span>
              <span class="history__when">
                <time datetime="<?= e(iso_time($h['assigned_at'])) ?>"><?= e($day($h['assigned_at'])) ?></time>
                –
                <?php if ($h['ended_at'] === null): ?>now<?php else: ?><time datetime="<?= e(iso_time($h['ended_at'])) ?>"><?= e($day($h['ended_at'])) ?></time><?php endif ?>
                <?= $h['assigned_by_name'] ? ' · by ' . e($h['assigned_by_name']) : '' ?>
              </span>
            </li>
          <?php endforeach ?>
        </ol>
      <?php endif ?>
    </section>

    <div class="detail__notes">
      <?= partial('partials/notes-log', ['category' => $category, 'device' => $chromebook, 'notes' => $notes, 'noteCount' => $noteCount]) ?>
    </div>

    <section class="card detail__info" aria-labelledby="details-title">
      <div class="card__header"><h2 class="card__title" id="details-title">Details</h2></div>
      <dl class="details">
        <div><dt>Asset ID</dt><dd class="num"><?= e($chromebook['asset_id'] ?? '–') ?></dd></div>
        <div><dt>Academic year</dt><dd><?= (int) $chromebook['academic_year'] ?></dd></div>
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

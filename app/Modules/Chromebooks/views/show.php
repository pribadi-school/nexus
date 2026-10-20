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
$formId = 'chromebook-edit';
$fieldError = static fn (string $name): string => isset($errors[$name]) ? '<p class="field__error" id="' . $name . '-error">' . e($errors[$name]) . '</p>' : '';
?>
<a class="back-link" href="<?= e($back) ?>"><?= icon('chevron-left', 'icon--sm') ?>Chromebooks <?= e($category->yearLabel((int) $chromebook['academic_year'])) ?></a>

<form id="<?= $formId ?>" method="post" action="/chromebooks/<?= $id ?>" data-busy-on-submit novalidate>
  <?= csrf_field() ?>
</form>
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
    <button class="btn btn--primary" type="submit" form="<?= $formId ?>">Save changes</button>
    <form method="post" action="/chromebooks/<?= $id ?>/delete"
          data-confirm="Delete Chromebook <?= e($label) ?>?"
          data-confirm-text="It disappears from every list. Its notes and assignment history are kept in the database."
          data-confirm-button="Delete Chromebook" data-confirm-tone="danger">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn--danger-quiet"><?= icon('trash-2', 'icon--sm') ?>Delete</button>
    </form>
  </div>
</header>

<?php if ($errors !== []): ?>
  <div class="notice notice--error" role="alert"><?= icon('circle-alert') ?><div>Check the highlighted fields. Your changes have not been saved.</div></div>
<?php endif ?>

<div class="detail detail--with-assign chromebook-detail">
    <?= partial('Chromebooks::_student-details', ['chromebook' => $chromebook, 'values' => $values, 'errors' => $errors, 'formId' => $formId]) ?>
    <section class="card status-card detail__status<?= $problem ? ' is-problem' : '' ?>" aria-labelledby="status-title" data-device-row="<?= $id ?>">
      <h2 class="status-card__title" id="status-title">Status</h2>
      <?= partial('Chromebooks::_status', ['category' => $category, 'status' => $chromebook['status']]) ?>
      <div class="chromebook-status-form">
        <fieldset class="choice-group">
          <legend class="visually-hidden">Change Chromebook status</legend>
          <div class="choice-chips">
          <?php foreach ($category->statuses() as $status): ?>
            <?php $statusMeta = $category->statusMeta($status); ?>
            <label class="choice-chip choice-chip--<?= e($statusMeta['tone']) ?>">
              <input type="radio" name="status" value="<?= e($status) ?>" form="<?= $formId ?>" required<?= $values['status'] === $status ? ' checked' : '' ?>>
              <span><?= icon($statusMeta['icon'], 'icon--sm') ?><?= e($statusMeta['label']) ?></span>
            </label>
          <?php endforeach ?>
          </div>
        </fieldset>
      </div>
      <?= $fieldError('status') ?>
      <p class="status-card__since">
        <?php if ($chromebook['status_changed_at']): ?>
          Since <time datetime="<?= e(iso_time($chromebook['status_changed_at'])) ?>" title="<?= e(format_datetime($chromebook['status_changed_at'])) ?>"><?= e(time_ago($chromebook['status_changed_at'])) ?></time>
        <?php endif ?>
      </p>
    </section>

    <?= partial('Chromebooks::_device-details', ['category' => $category, 'chromebook' => $chromebook, 'values' => $values, 'errors' => $errors, 'years' => $years, 'models' => $models, 'formId' => $formId]) ?>

    <div class="detail__notes">
      <?= partial('Chromebooks::_notes', ['category' => $category, 'notes' => $notes, 'noteCount' => $noteCount, 'values' => $values, 'errors' => $errors, 'formId' => $formId]) ?>
      <section class="card" aria-labelledby="status-history-title">
        <div class="card__header"><h2 class="card__title" id="status-history-title">History of changes</h2></div>
        <?php if ($statusHistory === []): ?>
          <p class="notes-placeholder">-</p>
        <?php else: ?>
          <ol class="notes-list">
            <?php foreach ($statusHistory as $note): ?><?= partial('partials/note-entry', ['note' => $note]) ?><?php endforeach ?>
          </ol>
        <?php endif ?>
      </section>
      <?= partial('Chromebooks::_google-details', $google) ?>
    </div>

</div>

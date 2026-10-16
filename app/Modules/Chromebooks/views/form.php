<?php
/**
 * Add / edit a Chromebook. Student and class are set here only when adding;
 * after that they change through "Reassign" on the Chromebook's page, so the
 * history stays complete.
 *
 * @var \App\Modules\Chromebooks\ChromebookCategory $category
 * @var array<string, mixed>|null $chromebook  null when adding
 * @var array<string, mixed> $values
 * @var array<string, string> $errors
 * @var list<array{id:int, name:string}> $classes
 * @var list<string> $models
 */
use App\Devices\Status;

$adding = $chromebook === null;
$action = $adding ? '/chromebooks' : '/chromebooks/' . (int) $chromebook['id'];
$cancel = $adding ? '/chromebooks' : '/chromebooks/' . (int) $chromebook['id'];
$field = static fn (string $name): string => $errors[$name] ?? '';
$invalid = static fn (string $name): string => $field($name) ? ' aria-invalid="true" aria-describedby="' . $name . '-error"' : '';
$error = static fn (string $name): string => $field($name) ? '<p class="field__error" id="' . $name . '-error">' . e($field($name)) . '</p>' : '';
?>
<a class="back-link" href="<?= e($cancel) ?>"><?= icon('chevron-left', 'icon--sm') ?><?= $adding ? 'Chromebooks' : e($chromebook['serial_number'] ?? 'Chromebook') ?></a>

<header class="page-header">
  <div>
    <h1 class="page-title"><?= $adding ? 'Add a Chromebook' : 'Edit Chromebook' ?></h1>
    <p class="page-subtitle">
      <?= $adding
          ? 'Adding many? <a href="/chromebooks/import">Import a CSV file</a> instead.'
          : 'Student, class, status and notes are changed on the Chromebook\'s page.' ?>
    </p>
  </div>
</header>

<form class="card form-card" method="post" action="<?= e($action) ?>" data-busy-on-submit novalidate>
  <?= csrf_field() ?>

  <?php if ($errors !== []): ?>
    <div class="notice notice--error" role="alert">
      <?= icon('circle-alert') ?>
      <div>Check the highlighted <?= count($errors) === 1 ? 'field' : 'fields' ?>.</div>
    </div>
  <?php endif ?>

  <div class="form-grid">
    <div class="field<?= $field('serial_number') ? ' has-error' : '' ?>">
      <label class="field__label" for="serial_number">Serial number <span class="field__required">required</span></label>
      <input class="input num" id="serial_number" name="serial_number" type="text" maxlength="64" required autocomplete="off"
             autocapitalize="characters" spellcheck="false" value="<?= e($values['serial_number'] ?? '') ?>"<?= $invalid('serial_number') ?>>
      <?= $error('serial_number') ?>
      <p class="field__hint">On the sticker under the Chromebook.</p>
    </div>

    <div class="field<?= $field('model') ? ' has-error' : '' ?>">
      <label class="field__label" for="model">Model</label>
      <input class="input" id="model" name="model" type="text" maxlength="100" list="model-options" autocomplete="off"
             value="<?= e($values['model'] ?? '') ?>" placeholder="e.g. Acer Chromebook 311"<?= $invalid('model') ?>>
      <datalist id="model-options"><?php foreach ($models as $m): ?><option value="<?= e($m) ?>"><?php endforeach ?></datalist>
      <?= $error('model') ?>
    </div>

    <?php if ($adding): ?>
      <div class="field<?= $field('student_name') ? ' has-error' : '' ?>">
        <label class="field__label" for="student_name">Student</label>
        <input class="input" id="student_name" name="student_name" type="text" maxlength="120" autocomplete="off"
               value="<?= e($values['student_name'] ?? '') ?>" placeholder="Full name"<?= $invalid('student_name') ?>>
        <?= $error('student_name') ?>
        <p class="field__hint">Leave empty if it is not given to anyone yet.</p>
      </div>

      <div class="field<?= $field('class_id') ? ' has-error' : '' ?>">
        <label class="field__label" for="class_id">Class</label>
        <select class="select" id="class_id" name="class_id"<?= $invalid('class_id') ?>>
          <option value="">No class</option>
          <?php foreach ($classes as $class): ?>
            <option value="<?= (int) $class['id'] ?>"<?= (int) ($values['class_id'] ?? 0) === (int) $class['id'] ? ' selected' : '' ?>><?= e($class['name']) ?></option>
          <?php endforeach ?>
        </select>
        <?= $error('class_id') ?>
        <p class="field__hint">Missing a class? <a href="/chromebooks/classes">Edit the class list</a>.</p>
      </div>

      <fieldset class="choice-group field--full">
        <legend class="field__label">Status</legend>
        <div class="choice-chips">
          <?php foreach ([Status::OK, Status::PROBLEM] as $status): ?>
            <label class="choice-chip choice-chip--<?= $status === Status::OK ? 'success' : 'problem' ?>">
              <input type="radio" name="status" value="<?= $status ?>"<?= ($values['status'] ?? Status::OK) === $status ? ' checked' : '' ?>>
              <span><?= icon($status === Status::OK ? 'circle-check' : 'triangle-alert', 'icon--sm') ?><?= e($category->statusLabel($status)) ?></span>
            </label>
          <?php endforeach ?>
        </div>
      </fieldset>
    <?php endif ?>
  </div>

  <div class="form-actions">
    <a class="btn btn--secondary" href="<?= e($cancel) ?>">Cancel</a>
    <button type="submit" class="btn btn--primary"><?= $adding ? 'Add Chromebook' : 'Save changes' ?></button>
  </div>
</form>

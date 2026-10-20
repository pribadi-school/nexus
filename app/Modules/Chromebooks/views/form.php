<?php
/**
 * Add / edit a Chromebook. Student and email are set here only when adding;
 * later imports maintain the assignment history.
 *
 * @var \App\Modules\Chromebooks\ChromebookCategory $category
 * @var array<string, mixed>|null $chromebook  null when adding
 * @var array<string, mixed> $values
 * @var array<string, string> $errors
 * @var list<int> $years  newest first
 * @var list<string> $models
 */
$adding = $chromebook === null;
$action = $adding ? '/chromebooks' : '/chromebooks/' . (int) $chromebook['id'];
$cancel = $adding ? '/chromebooks?year=' . (int) $values['academic_year'] : '/chromebooks/' . (int) $chromebook['id'];
$field = static fn (string $name): string => $errors[$name] ?? '';
$invalid = static fn (string $name): string => $field($name) ? ' aria-invalid="true" aria-describedby="' . $name . '-error"' : '';
$error = static fn (string $name): string => $field($name) ? '<p class="field__error" id="' . $name . '-error">' . e($field($name)) . '</p>' : '';
?>
<a class="back-link" href="<?= e($cancel) ?>"><?= icon('chevron-left', 'icon--sm') ?><?= $adding ? 'Chromebooks' : e($chromebook['asset_id'] ?? $chromebook['serial_number'] ?? 'Chromebook') ?></a>

<header class="page-header">
  <div>
    <h1 class="page-title"><?= $adding ? 'Add a Chromebook' : 'Edit Chromebook' ?></h1>
    <p class="page-subtitle">
      <?= $adding
          ? 'Adding several Chromebooks? <a href="/chromebooks/import?year=' . (int) $values['academic_year'] . '">Import the list</a> instead.'
          : 'Status and notes are changed on the Chromebook\'s page.' ?>
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
    <div class="field<?= $field('academic_year') ? ' has-error' : '' ?>">
      <label class="field__label" for="academic_year">Year / category <span class="field__required">required</span></label>
      <select class="select" id="academic_year" name="academic_year" required<?= $invalid('academic_year') ?>>
        <?php foreach ($years as $y): ?>
          <option value="<?= $y ?>"<?= (int) ($values['academic_year'] ?? $years[0]) === $y ? ' selected' : '' ?>><?= e($category->yearLabel($y)) ?></option>
        <?php endforeach ?>
      </select>
      <?= $error('academic_year') ?>
      <p class="field__hint">Choose its academic year, or School for school-owned devices.</p>
    </div>

    <div class="field<?= $field('asset_id') ? ' has-error' : '' ?>">
      <label class="field__label" for="asset_id">Asset ID</label>
      <input class="input num" id="asset_id" name="asset_id" type="text" maxlength="20" autocomplete="off" spellcheck="false"
             value="<?= e($values['asset_id'] ?? '') ?>" placeholder="e.g. <?= e($category->yearLabel((int) ($values['academic_year'] ?? $years[0]))) ?>-01"<?= $invalid('asset_id') ?>>
      <?= $error('asset_id') ?>
    </div>

    <div class="field<?= $field('serial_number') ? ' has-error' : '' ?>">
      <label class="field__label" for="serial_number">Serial number</label>
      <input class="input num" id="serial_number" name="serial_number" type="text" maxlength="64" autocomplete="off"
             autocapitalize="characters" spellcheck="false" value="<?= e($values['serial_number'] ?? '') ?>"<?= $invalid('serial_number') ?>>
      <?= $error('serial_number') ?>
      <p class="field__hint">On the sticker under the Chromebook. Can be added later.</p>
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

      <div class="field<?= $field('student_email') ? ' has-error' : '' ?>">
        <label class="field__label" for="student_email">Student email</label>
        <input class="input" id="student_email" name="student_email" type="email" maxlength="160" autocomplete="off" spellcheck="false"
               value="<?= e($values['student_email'] ?? '') ?>" placeholder="name@pribadidepok.sch.id"<?= $invalid('student_email') ?>>
        <?= $error('student_email') ?>
      </div>

      <div class="field<?= $field('status') ? ' has-error' : '' ?>">
        <label class="field__label" for="status">Status</label>
        <select class="select" id="status" name="status"<?= $invalid('status') ?>>
          <option value="">Choose status</option>
          <?php foreach ($category->statuses() as $status): ?>
            <option value="<?= e($status) ?>"<?= ($values['status'] ?? null) === $status ? ' selected' : '' ?>><?= e($category->statusLabel($status)) ?></option>
          <?php endforeach ?>
        </select>
        <?= $error('status') ?>
      </div>
    <?php endif ?>
  </div>

  <div class="form-actions">
    <a class="btn btn--secondary" href="<?= e($cancel) ?>">Cancel</a>
    <button type="submit" class="btn btn--primary"><?= $adding ? 'Add Chromebook' : 'Save changes' ?></button>
  </div>
</form>

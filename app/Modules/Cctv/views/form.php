<?php
/**
 * Add / edit a camera: its spot, type and SD card size.
 *
 * @var \App\Modules\Cctv\CctvCategory $category
 * @var array<string, mixed>|null $camera  null when adding
 * @var array<string, mixed> $values
 * @var array<string, string> $errors
 */
use App\Devices\Status;
use App\Modules\Cctv\CctvCategory;

$adding = $camera === null;
$action = $adding ? '/cctv' : '/cctv/' . (int) $camera['id'];
$cancel = $adding ? '/cctv' : '/cctv/' . (int) $camera['id'];
$field = static fn (string $name): string => $errors[$name] ?? '';
$invalid = static fn (string $name): string => $field($name) ? ' aria-invalid="true" aria-describedby="' . $name . '-error"' : '';
$error = static fn (string $name): string => $field($name) ? '<p class="field__error" id="' . $name . '-error">' . e($field($name)) . '</p>' : '';
?>
<a class="back-link" href="<?= e($cancel) ?>"><?= icon('chevron-left', 'icon--sm') ?><?= $adding ? 'CCTV' : e($camera['label']) ?></a>

<header class="page-header">
  <div>
    <h1 class="page-title"><?= $adding ? 'Add a camera' : 'Edit camera' ?></h1>
    <?php if (!$adding): ?><p class="page-subtitle">Status and notes are changed on the camera's page.</p><?php endif ?>
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
    <div class="field field--full<?= $field('label') ? ' has-error' : '' ?>">
      <label class="field__label" for="label">Spot <span class="field__required">required</span></label>
      <input class="input" id="label" name="label" type="text" maxlength="100" required autocomplete="off"
             value="<?= e($values['label'] ?? '') ?>" placeholder="e.g. Gedung B - Tangga"<?= $invalid('label') ?>>
      <?= $error('label') ?>
    </div>

    <div class="field<?= $field('model') ? ' has-error' : '' ?>">
      <label class="field__label" for="model">Type <span class="field__required">required</span></label>
      <select class="select" id="model" name="model" required<?= $invalid('model') ?>>
        <option value="">Choose a type</option>
        <?php foreach (CctvCategory::TYPES as $type): ?>
          <option value="<?= e($type) ?>"<?= ($values['model'] ?? null) === $type ? ' selected' : '' ?>><?= e($type) ?></option>
        <?php endforeach ?>
      </select>
      <?= $error('model') ?>
    </div>

    <div class="field<?= $field('sd_card_gb') ? ' has-error' : '' ?>">
      <label class="field__label" for="sd_card_gb">SD card size</label>
      <select class="select" id="sd_card_gb" name="sd_card_gb"<?= $invalid('sd_card_gb') ?>>
        <option value="">No card / unknown</option>
        <?php foreach (CctvCategory::SD_SIZES as $gb): ?>
          <option value="<?= $gb ?>"<?= (int) ($values['sd_card_gb'] ?? 0) === $gb ? ' selected' : '' ?>><?= $gb ?> GB</option>
        <?php endforeach ?>
      </select>
      <?= $error('sd_card_gb') ?>
    </div>

    <?php if ($adding): ?>
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
    <button type="submit" class="btn btn--primary"><?= $adding ? 'Add camera' : 'Save changes' ?></button>
  </div>
</form>

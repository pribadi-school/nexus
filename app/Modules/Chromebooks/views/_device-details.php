<?php
$error = static fn (string $name): string => isset($errors[$name]) ? '<p class="field__error" id="' . $name . '-error">' . e($errors[$name]) . '</p>' : '';
$invalid = static fn (string $name): string => isset($errors[$name]) ? ' aria-invalid="true" aria-describedby="' . $name . '-error"' : '';
?>
<section class="card detail__info" aria-labelledby="details-title">
  <div class="card__header"><h2 class="card__title" id="details-title">Details</h2></div>
  <div class="device-details-fields">
    <div class="field">
      <label class="field__label" for="academic_year">Year / category <span class="field__required">required</span></label>
      <select class="select" id="academic_year" name="academic_year" form="<?= e($formId) ?>" required<?= $invalid('academic_year') ?>>
        <?php if (!in_array((int) ($values['academic_year'] ?? -1), $years, true)): ?><option value="" selected disabled>Choose year / category</option><?php endif ?>
        <?php foreach ($years as $year): ?>
          <option value="<?= $year ?>"<?= (int) ($values['academic_year'] ?? -1) === $year ? ' selected' : '' ?>><?= e($category->yearLabel($year)) ?></option>
        <?php endforeach ?>
      </select>
      <?= $error('academic_year') ?>
    </div>
    <div class="field">
      <label class="field__label" for="asset_id">Asset ID</label>
      <input class="input num" id="asset_id" name="asset_id" type="text" maxlength="20" autocomplete="off" spellcheck="false"
             form="<?= e($formId) ?>" value="<?= e($values['asset_id'] ?? '') ?>"<?= $invalid('asset_id') ?>>
      <?= $error('asset_id') ?>
    </div>
    <div class="field">
      <label class="field__label" for="serial_number">Serial number</label>
      <input class="input num" id="serial_number" name="serial_number" type="text" maxlength="64" autocomplete="off" spellcheck="false"
             form="<?= e($formId) ?>" value="<?= e($values['serial_number'] ?? '') ?>"<?= $invalid('serial_number') ?>>
      <?= $error('serial_number') ?>
    </div>
    <div class="field">
      <label class="field__label" for="model">Model</label>
      <input class="input" id="model" name="model" type="text" maxlength="100" list="model-options" autocomplete="off"
             form="<?= e($formId) ?>" value="<?= e($values['model'] ?? '') ?>"<?= $invalid('model') ?>>
      <datalist id="model-options"><?php foreach ($models as $model): ?><option value="<?= e($model) ?>"><?php endforeach ?></datalist>
      <?= $error('model') ?>
    </div>
  </div>
  <dl class="details chromebook-timestamps">
    <div><dt>Last updated</dt><dd><time datetime="<?= e(iso_time($chromebook['updated_at'])) ?>"><?= e(format_datetime($chromebook['updated_at'])) ?></time><?= $chromebook['updated_by_name'] ? ' by ' . e($chromebook['updated_by_name']) : '' ?></dd></div>
    <div><dt>Added</dt><dd><time datetime="<?= e(iso_time($chromebook['created_at'])) ?>"><?= e(format_datetime($chromebook['created_at'])) ?></time><?= $chromebook['created_by_name'] ? ' by ' . e($chromebook['created_by_name']) : '' ?></dd></div>
  </dl>
</section>

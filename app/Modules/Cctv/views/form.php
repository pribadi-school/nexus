<?php
/**
 * Add / edit a camera.
 *
 * @var \App\Modules\Cctv\CctvCategory $category
 * @var array<string, mixed>|null $camera  null when adding
 * @var array<string, mixed> $values
 * @var array<string, string> $errors
 * @var list<array{id:int, name:string}> $areas
 * @var list<string> $models
 */
use App\Devices\Status;

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

<?php if ($areas === []): ?>
  <div class="flashes">
    <div class="notice notice--info">
      <?= icon('info') ?>
      <div>Every camera belongs to an area, and there are no areas yet. <a href="/cctv/areas">Add the areas first</a>.</div>
    </div>
  </div>
<?php endif ?>

<form class="card form-card" method="post" action="<?= e($action) ?>" data-busy-on-submit novalidate>
  <?= csrf_field() ?>

  <?php if ($errors !== []): ?>
    <div class="notice notice--error" role="alert">
      <?= icon('circle-alert') ?>
      <div>Check the highlighted <?= count($errors) === 1 ? 'field' : 'fields' ?>.</div>
    </div>
  <?php endif ?>

  <div class="form-grid">
    <div class="field<?= $field('area_id') ? ' has-error' : '' ?>">
      <label class="field__label" for="area_id">Area <span class="field__required">required</span></label>
      <select class="select" id="area_id" name="area_id" required<?= $invalid('area_id') ?>>
        <option value="">Choose an area</option>
        <?php foreach ($areas as $area): ?>
          <option value="<?= (int) $area['id'] ?>"<?= (int) ($values['area_id'] ?? 0) === (int) $area['id'] ? ' selected' : '' ?>><?= e($area['name']) ?></option>
        <?php endforeach ?>
      </select>
      <?= $error('area_id') ?>
      <p class="field__hint">Missing an area? <a href="/cctv/areas">Edit the areas list</a>.</p>
    </div>

    <div class="field<?= $field('label') ? ' has-error' : '' ?>">
      <label class="field__label" for="label">Label <span class="field__required">required</span></label>
      <input class="input" id="label" name="label" type="text" maxlength="100" required autocomplete="off"
             value="<?= e($values['label'] ?? '') ?>" placeholder="e.g. CAM-07 Library door"<?= $invalid('label') ?>>
      <?= $error('label') ?>
      <p class="field__hint">The name used on the recorder.</p>
    </div>

    <div class="field field--full<?= $field('location') ? ' has-error' : '' ?>">
      <label class="field__label" for="location">Exact location</label>
      <input class="input" id="location" name="location" type="text" maxlength="160" autocomplete="off"
             value="<?= e($values['location'] ?? '') ?>" placeholder="e.g. Above the library door, facing the corridor"<?= $invalid('location') ?>>
      <?= $error('location') ?>
    </div>

    <div class="field<?= $field('model') ? ' has-error' : '' ?>">
      <label class="field__label" for="model">Model</label>
      <input class="input" id="model" name="model" type="text" maxlength="100" list="model-options" autocomplete="off"
             value="<?= e($values['model'] ?? '') ?>" placeholder="e.g. Hikvision DS-2CD1043G2-I"<?= $invalid('model') ?>>
      <datalist id="model-options"><?php foreach ($models as $m): ?><option value="<?= e($m) ?>"><?php endforeach ?></datalist>
      <?= $error('model') ?>
    </div>

    <div class="field<?= $field('serial_number') ? ' has-error' : '' ?>">
      <label class="field__label" for="serial_number">Serial number</label>
      <input class="input num" id="serial_number" name="serial_number" type="text" maxlength="64" autocomplete="off"
             autocapitalize="characters" spellcheck="false" value="<?= e($values['serial_number'] ?? '') ?>"<?= $invalid('serial_number') ?>>
      <?= $error('serial_number') ?>
    </div>

    <div class="field<?= $field('ip_address') ? ' has-error' : '' ?>">
      <label class="field__label" for="ip_address">IP address</label>
      <input class="input num" id="ip_address" name="ip_address" type="text" maxlength="45" inputmode="decimal" autocomplete="off"
             spellcheck="false" value="<?= e($values['ip_address'] ?? '') ?>" placeholder="e.g. 192.168.10.27"<?= $invalid('ip_address') ?>>
      <?= $error('ip_address') ?>
    </div>

    <div class="field<?= $field('sd_card_gb') ? ' has-error' : '' ?>">
      <label class="field__label" for="sd_card_gb">SD card size (GB)</label>
      <input class="input num" id="sd_card_gb" name="sd_card_gb" type="text" inputmode="numeric" maxlength="5" autocomplete="off"
             value="<?= e((string) ($values['sd_card_gb'] ?? '')) ?>" placeholder="e.g. 128"<?= $invalid('sd_card_gb') ?>>
      <?= $error('sd_card_gb') ?>
      <p class="field__hint">Leave empty if the camera has no card.</p>
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

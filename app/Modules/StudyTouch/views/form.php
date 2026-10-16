<?php
/**
 * Add / edit a board.
 *
 * @var \App\Modules\StudyTouch\StudyTouchCategory $category
 * @var array<string, mixed>|null $board  null when adding
 * @var array<string, mixed> $values
 * @var array<string, string> $errors
 * @var list<array{id:int, name:string}> $rooms
 * @var list<string> $models  StudyTouchCategory::MODELS
 * @var list<string> $firmwares
 */
use App\Devices\Status;

$adding = $board === null;
$action = $adding ? '/studytouch' : '/studytouch/' . (int) $board['id'];
$cancel = $adding ? '/studytouch' : '/studytouch/' . (int) $board['id'];
$field = static fn (string $name): string => $errors[$name] ?? '';
?>
<a class="back-link" href="<?= e($cancel) ?>"><?= icon('chevron-left', 'icon--sm') ?><?= $adding ? 'StudyTouch' : e($board['room_name']) ?></a>

<header class="page-header">
  <div>
    <h1 class="page-title"><?= $adding ? 'Add a board' : 'Edit board' ?></h1>
    <?php if (!$adding): ?><p class="page-subtitle">Status and notes are changed on the board's page.</p><?php endif ?>
  </div>
</header>

<?php if ($rooms === []): ?>
  <div class="flashes">
    <div class="notice notice--info">
      <?= icon('info') ?>
      <div>Every board belongs to a room, and there are no rooms yet. <a href="/studytouch/rooms">Add the rooms first</a>.</div>
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
    <div class="field<?= $field('room_id') ? ' has-error' : '' ?>">
      <label class="field__label" for="room_id">Room <span class="field__required">required</span></label>
      <select class="select" id="room_id" name="room_id" required<?= $field('room_id') ? ' aria-invalid="true" aria-describedby="room_id-error"' : '' ?>>
        <option value="">Choose a room</option>
        <?php foreach ($rooms as $room): ?>
          <option value="<?= (int) $room['id'] ?>"<?= (int) ($values['room_id'] ?? 0) === (int) $room['id'] ? ' selected' : '' ?>><?= e($room['name']) ?></option>
        <?php endforeach ?>
      </select>
      <?php if ($field('room_id')): ?><p class="field__error" id="room_id-error"><?= e($field('room_id')) ?></p><?php endif ?>
      <p class="field__hint">Missing a room? <a href="/studytouch/rooms">Edit the rooms list</a>.</p>
    </div>

    <div class="field<?= $field('model') ? ' has-error' : '' ?>">
      <label class="field__label" for="model">Model <span class="field__required">required</span></label>
      <select class="select" id="model" name="model" required<?= $field('model') ? ' aria-invalid="true" aria-describedby="model-error"' : '' ?>>
        <option value="">Choose a model</option>
        <?php foreach ($models as $m): ?>
          <option value="<?= e($m) ?>"<?= ($values['model'] ?? null) === $m ? ' selected' : '' ?>><?= e($m) ?></option>
        <?php endforeach ?>
      </select>
      <?php if ($field('model')): ?><p class="field__error" id="model-error"><?= e($field('model')) ?></p><?php endif ?>
    </div>

    <div class="field<?= $field('firmware_version') ? ' has-error' : '' ?>">
      <label class="field__label" for="firmware_version">Firmware version</label>
      <input class="input num" id="firmware_version" name="firmware_version" type="text" maxlength="40" list="firmware-options"
             autocomplete="off" spellcheck="false" value="<?= e($values['firmware_version'] ?? '') ?>" placeholder="e.g. <?= e(\App\Modules\StudyTouch\StudyTouchCategory::CURRENT_FIRMWARE) ?>"<?= $field('firmware_version') ? ' aria-invalid="true" aria-describedby="firmware_version-error"' : '' ?>>
      <datalist id="firmware-options"><?php foreach ($firmwares as $f): ?><option value="<?= e($f) ?>"><?php endforeach ?></datalist>
      <?php if ($field('firmware_version')): ?><p class="field__error" id="firmware_version-error"><?= e($field('firmware_version')) ?></p><?php endif ?>
      <p class="field__hint">All boards run <?= e(\App\Modules\StudyTouch\StudyTouchCategory::CURRENT_FIRMWARE) ?> (29 September 2025).<?= $adding ? '' : ' A new version is recorded in the notes automatically.' ?></p>
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
    <button type="submit" class="btn btn--primary"><?= $adding ? 'Add board' : 'Save changes' ?></button>
  </div>
</form>

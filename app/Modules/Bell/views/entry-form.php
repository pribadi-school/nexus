<?php
/**
 * @var array<string,mixed> $zone
 * @var array<string,mixed> $profile
 * @var array<string,mixed>|null $entry
 * @var array<string,mixed> $values
 * @var array<string,string> $errors
 * @var array<string,string> $soundNames
 */
use App\Modules\Bell\BellData;

$field = static fn (string $name): string => $errors[$name] ?? '';
$error = static fn (string $name): string => $field($name) ? '<p class="field__error" id="' . $name . '-error">' . e($field($name)) . '</p>' : '';
$action = $entry === null ? '/bell/profiles/' . (int) $profile['id'] . '/entries' : '/bell/entries/' . (int) $entry['id'];
$back = '/bell/zones/' . (int) $zone['id'] . '?profile=' . (int) $profile['id'];
$days = array_map('intval', (array) $values['days']);
$time = substr((string) $values['ring_time'], 0, 8);
?>
<a class="back-link" href="<?= e($back) ?>"><?= icon('chevron-left', 'icon--sm') ?><?= e($zone['name']) ?></a>
<header class="page-header"><div>
  <h1 class="page-title"><?= $entry === null ? 'Add bell' : 'Edit bell' ?></h1>
  <p class="page-subtitle"><?= e($zone['name']) ?> · profile <?= e($profile['name']) ?></p>
</div></header>
<?= partial('Bell::_tabs', ['tab' => 'zone-' . (int) $zone['id']]) ?>

<form class="card bell-card bell-zone bell-zone--<?= e($zone['color']) ?>" method="post" action="<?= e($action) ?>" data-busy-on-submit novalidate>
  <?= csrf_field() ?>
  <?php if ($errors !== []): ?><div class="notice notice--error" role="alert"><?= icon('circle-alert') ?><div>Check the highlighted <?= count($errors) === 1 ? 'field' : 'fields' ?>.</div></div><?php endif ?>
  <div class="form-grid">
    <div class="field<?= $field('ring_time') ? ' has-error' : '' ?>">
      <label class="field__label" for="ring_time">Time <span class="field__required">required</span></label>
      <input class="input input--time num" type="time" step="1" id="ring_time" name="ring_time" value="<?= e($time) ?>" required<?= $field('ring_time') ? ' aria-invalid="true" aria-describedby="ring_time-error"' : '' ?>>
      <?= $error('ring_time') ?><p class="field__hint">Hours, minutes and seconds (24-hour clock).</p>
    </div>

    <fieldset class="choice-group field--full<?= $field('days') ? ' has-error' : '' ?>">
      <legend class="field__label">Repeat on <span class="field__required">required</span></legend>
      <div class="choice-chips">
        <?php foreach (BellData::DAYS as $n => $names): ?>
          <label class="choice-chip"><input type="checkbox" name="days[]" value="<?= $n ?>"<?= in_array($n, $days, true) ? ' checked' : '' ?>><span><?= e($names[2]) ?></span></label>
        <?php endforeach ?>
      </div>
      <?= $error('days') ?>
    </fieldset>

    <div class="field field--full<?= $field('sound_ref') ? ' has-error' : '' ?>">
      <label class="field__label" for="sound_ref">Sound <span class="field__required">required</span></label>
      <div class="bell-sound-pick">
        <select class="select" id="sound_ref" name="sound_ref" data-bell-sound-select required<?= $field('sound_ref') ? ' aria-invalid="true" aria-describedby="sound_ref-error"' : '' ?>>
          <optgroup label="Built-in">
            <?php foreach ($soundNames as $ref => $name): if (!str_starts_with($ref, 'builtin:')) continue; ?>
              <option value="<?= e($ref) ?>" data-src="/bell/sounds/builtin/<?= e(substr($ref, 8)) ?>"<?= $values['sound_ref'] === $ref ? ' selected' : '' ?>><?= e($name) ?></option>
            <?php endforeach ?>
          </optgroup>
          <?php if (count(array_filter(array_keys($soundNames), static fn (string $r): bool => str_starts_with($r, 'sound:'))) > 0): ?>
          <optgroup label="Uploaded">
            <?php foreach ($soundNames as $ref => $name): if (!str_starts_with($ref, 'sound:')) continue; ?>
              <option value="<?= e($ref) ?>" data-src="/bell/sounds/<?= (int) substr($ref, 6) ?>/file"<?= $values['sound_ref'] === $ref ? ' selected' : '' ?>><?= e($name) ?></option>
            <?php endforeach ?>
          </optgroup>
          <?php endif ?>
        </select>
        <button class="btn btn--secondary" type="button" data-bell-preview-selected><?= icon('play', 'icon--sm') ?>Listen</button>
      </div>
      <?= $error('sound_ref') ?><p class="field__hint">Need another sound? Upload an MP3 or WAV in <a href="/bell/sounds">Sounds</a>.</p>
    </div>

    <div class="field field--full<?= $field('label') ? ' has-error' : '' ?>">
      <label class="field__label" for="label">Label</label>
      <input class="input" id="label" name="label" maxlength="120" value="<?= e($values['label']) ?>" placeholder="e.g. Start of lesson 1">
      <?= $error('label') ?>
    </div>

    <div class="field field--full bell-checks">
      <label class="checkbox"><input type="checkbox" name="enabled" value="1"<?= $values['enabled'] ? ' checked' : '' ?>><span>Turned on</span></label>
      <label class="checkbox"><input type="checkbox" name="both_floors" value="1"<?= $values['both_floors'] ? ' checked' : '' ?>><span>Also ring on the other floor <small>(a school-wide bell; it follows the other floor's volume and holidays)</small></span></label>
    </div>
  </div>
  <div class="form-actions"><a class="btn btn--secondary" href="<?= e($back) ?>">Cancel</a><button class="btn btn--primary" type="submit"><?= $entry === null ? 'Add bell' : 'Save bell' ?></button></div>
</form>

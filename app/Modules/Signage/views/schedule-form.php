<?php
use App\Modules\Signage\SignageSchedules;

$adding = $schedule === null;
$base = $adding ? '/signage/schedules' : '/signage/schedules/' . (int) $schedule['id'];
$field = static fn (string $name): string => $errors[$name] ?? '';
$invalid = static fn (string $name): string => $field($name) ? ' aria-invalid="true" aria-describedby="' . $name . '-error"' : '';
$error = static fn (string $name): string => $field($name) ? '<p class="field__error" id="' . $name . '-error">' . e($field($name)) . '</p>' : '';
$mode = ($values['mode'] ?? 'weekly') === 'once' ? 'once' : 'weekly';
$days = array_map('intval', (array) ($values['days'] ?? []));
$picked = array_map('intval', (array) ($values['screen_ids'] ?? []));
?>
<a class="back-link" href="/signage/schedules"><?= icon('chevron-left', 'icon--sm') ?>Schedules</a>
<header class="page-header"><div><h1 class="page-title"><?= $adding ? 'New schedule' : e($schedule['name']) ?></h1>
  <p class="page-subtitle">During this time the chosen TVs play the playlist below instead of their normal one, then go back.</p></div></header>
<?= partial('Signage::_tabs', ['tab' => 'schedules']) ?>

<form class="card form-card signage-schedule-form" method="post" action="<?= e($base) ?>" data-busy-on-submit novalidate>
  <?= csrf_field() ?>
  <?php if ($errors !== []): ?><div class="notice notice--error" role="alert"><?= icon('circle-alert') ?><div>Check the highlighted <?= count($errors) === 1 ? 'field' : 'fields' ?>.</div></div><?php endif ?>
  <div class="form-grid">
    <div class="field field--full<?= $field('name') ? ' has-error' : '' ?>">
      <label class="field__label" for="name">Name <span class="field__required">required</span></label>
      <input class="input" id="name" name="name" value="<?= e($values['name'] ?? '') ?>" maxlength="160" required autocomplete="off" placeholder="e.g. Monday flag ceremony"<?= $invalid('name') ?>><?= $error('name') ?>
    </div>
    <div class="field field--full<?= $field('playlist_id') ? ' has-error' : '' ?>">
      <label class="field__label" for="playlist_id">Playlist to play <span class="field__required">required</span></label>
      <select class="select" id="playlist_id" name="playlist_id"<?= $invalid('playlist_id') ?>>
        <option value="">Choose a shared playlist</option>
        <?php foreach ($playlists as $playlist): ?>
          <option value="<?= (int) $playlist['id'] ?>"<?= (string) ($values['playlist_id'] ?? '') === (string) $playlist['id'] ? ' selected' : '' ?>><?= e($playlist['name']) ?> · <?= e(plural((int) $playlist['item_count'], 'item')) ?></option>
        <?php endforeach ?>
      </select><?= $error('playlist_id') ?>
    </div>
    <fieldset class="field field--full signage-schedule-form__tvs<?= $field('screen_ids') ? ' has-error' : '' ?>">
      <legend class="field__label">TVs <span class="field__required">required</span></legend>
      <?php foreach ($screens as $screen): ?>
        <label class="signage-check"><input type="checkbox" name="screen_ids[]" value="<?= (int) $screen['id'] ?>"<?= in_array((int) $screen['id'], $picked, true) ? ' checked' : '' ?>><span><?= e($screen['name']) ?></span></label>
      <?php endforeach ?>
      <?= $error('screen_ids') ?>
    </fieldset>

    <fieldset class="field field--full signage-schedule-form__mode">
      <legend class="field__label">When</legend>
      <label class="signage-check"><input type="radio" name="mode" value="weekly"<?= $mode === 'weekly' ? ' checked' : '' ?>><span>Repeats every week<span class="signage-row__sub">For example every Monday, or Monday to Friday.</span></span></label>
      <label class="signage-check"><input type="radio" name="mode" value="once"<?= $mode === 'once' ? ' checked' : '' ?>><span>One day only<span class="signage-row__sub">A single event on a chosen date.</span></span></label>
    </fieldset>

    <fieldset class="field field--full signage-schedule-form__weekly<?= $field('days') ? ' has-error' : '' ?>" data-mode="weekly">
      <legend class="field__label">Days</legend>
      <div class="signage-schedule-form__days">
        <?php foreach (SignageSchedules::DAYS as $n => $label): ?>
          <label class="signage-day"><input type="checkbox" name="days[]" value="<?= $n ?>"<?= in_array($n, $days, true) ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
        <?php endforeach ?>
      </div>
      <?= $error('days') ?>
    </fieldset>
    <div class="field<?= $field('start_date') ? ' has-error' : '' ?>" data-mode="weekly">
      <label class="field__label" for="start_date">First date <span class="field__optional">optional</span></label>
      <input class="input" id="start_date" name="start_date" type="date" value="<?= e($values['start_date'] ?? '') ?>"<?= $invalid('start_date') ?>><?= $error('start_date') ?>
    </div>
    <div class="field<?= $field('end_date') ? ' has-error' : '' ?>" data-mode="weekly">
      <label class="field__label" for="end_date">Last date <span class="field__optional">optional</span></label>
      <input class="input" id="end_date" name="end_date" type="date" value="<?= e($values['end_date'] ?? '') ?>"<?= $invalid('end_date') ?>><?= $error('end_date') ?>
    </div>
    <div class="field field--full<?= $field('date') ? ' has-error' : '' ?>" data-mode="once">
      <label class="field__label" for="date">Date <span class="field__required">required</span></label>
      <input class="input" id="date" name="date" type="date" value="<?= e($values['date'] ?? '') ?>"<?= $invalid('date') ?>><?= $error('date') ?>
    </div>

    <div class="field<?= $field('start_time') ? ' has-error' : '' ?>">
      <label class="field__label" for="start_time">Starts at</label>
      <input class="input" id="start_time" name="start_time" type="time" value="<?= e($values['start_time'] ?? '') ?>" required<?= $invalid('start_time') ?>><?= $error('start_time') ?>
    </div>
    <div class="field<?= $field('end_time') ? ' has-error' : '' ?>">
      <label class="field__label" for="end_time">Ends at</label>
      <input class="input" id="end_time" name="end_time" type="time" value="<?= e($values['end_time'] ?? '') ?>" required<?= $invalid('end_time') ?>><?= $error('end_time') ?>
      <p class="field__hint">School time (Jakarta). Within one day.</p>
    </div>
    <div class="field field--full signage-checkboxes">
      <label class="signage-check"><input type="checkbox" name="enabled" value="1"<?= !empty($values['enabled']) ? ' checked' : '' ?>><span>Turn on<span class="signage-row__sub">Turn off to keep the schedule but stop using it.</span></span></label>
    </div>
  </div>
  <div class="form-actions"><a class="btn btn--secondary" href="/signage/schedules">Cancel</a><button class="btn btn--primary" type="submit"><?= $adding ? 'Create schedule' : 'Save changes' ?></button></div>
</form>

<?php if (!$adding): ?>
  <div class="signage-danger-actions">
    <form method="post" action="<?= e($base) ?>/delete" data-confirm="Delete this schedule?" data-confirm-text="Its TVs go back to their normal playlists." data-confirm-button="Delete schedule" data-confirm-tone="danger"><?= csrf_field() ?><button class="btn btn--danger-quiet" type="submit"><?= icon('trash-2', 'icon--sm') ?>Delete schedule</button></form>
  </div>
<?php endif ?>

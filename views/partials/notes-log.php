<?php
/**
 * The notes log (shared by every category): add-a-note form + the device's
 * notes, newest first. Every entry shows its kind, who wrote it and when.
 *
 * @var \App\Devices\Category $category
 * @var array<string, mixed> $device  needs id, status
 * @var list<array<string, mixed>> $notes
 * @var int $noteCount
 */
use App\Core\Session;
use App\Devices\DeviceController;
use App\Devices\NoteKind;
use App\Devices\Status;

$id = (int) $device['id'];
$draft = Session::get('note.draft');
if (is_array($draft) && ($draft['device'] ?? null) === $id) {
    Session::forget('note.draft');
} else {
    $draft = null;
}
$kinds = $category->noteKinds();
$selectedKind = in_array($draft['kind'] ?? null, $kinds, true) ? $draft['kind'] : $kinds[0];
$isOk = $device['status'] !== Status::PROBLEM;
$other = $isOk ? Status::PROBLEM : Status::OK;
?>
<section class="card notes" id="notes" aria-labelledby="notes-title">
  <div class="card__header">
    <h2 class="card__title" id="notes-title">Notes</h2>
    <span class="chip chip--quiet num"><?= $noteCount ?></span>
  </div>

  <form class="note-form" id="add-note" method="post" action="/devices/<?= $id ?>/notes"
        data-note-form data-busy-on-submit data-current-status="<?= e($device['status']) ?>">
    <?= csrf_field() ?>
    <fieldset class="choice-group">
      <legend class="field__label">Kind of note</legend>
      <div class="choice-chips">
        <?php foreach ($kinds as $kind): $meta = NoteKind::meta($kind); ?>
          <label class="choice-chip choice-chip--<?= e($meta['tone']) ?>">
            <input type="radio" name="kind" value="<?= e($kind) ?>"<?= $kind === $selectedKind ? ' checked' : '' ?>>
            <span><?= icon($meta['icon'], 'icon--sm') ?><?= e($meta['label']) ?></span>
          </label>
        <?php endforeach ?>
      </div>
    </fieldset>

    <div class="field">
      <label class="field__label" for="note-body">What happened?</label>
      <textarea class="input textarea" id="note-body" name="body" rows="3" maxlength="<?= DeviceController::NOTE_MAX ?>" required
                placeholder="<?= e($category->noteExample()) ?>"><?= e($draft['body'] ?? '') ?></textarea>
    </div>

    <div class="note-form__footer">
      <?php if ($category->key() === 'chromebook'): ?>
      <div class="field">
        <label class="field__label" for="note-status">Status after this note</label>
        <select class="select" id="note-status" name="set_status">
          <option value="">Keep current status</option>
          <?php foreach ($category->statuses() as $status): ?>
            <option value="<?= e($status) ?>"<?= ($draft['set_status'] ?? null) === $status ? ' selected' : '' ?>><?= e($category->statusLabel($status)) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <?php else: ?>
      <label class="checkbox">
        <input type="checkbox" name="set_status" value="<?= e($other) ?>" data-set-status<?= ($draft['set_status'] ?? null) === $other ? ' checked' : '' ?>>
        <span>Also mark as <strong class="status-word status-word--<?= e($other) ?>"><?= e($category->statusLabel($other)) ?></strong></span>
      </label>
      <?php endif ?>
      <button type="submit" class="btn btn--primary"><?= icon('plus', 'icon--sm') ?>Add note</button>
    </div>
  </form>

  <?php if ($notes === []): ?>
    <?php if ($category->key() === 'chromebook'): ?>
      <p class="notes-placeholder">-</p>
    <?php else: ?>
    <?= partial('partials/empty-state', [
        'icon' => 'message-square-text',
        'title' => 'No notes yet',
        'text' => 'Reports, problems, fixes and status changes for this ' . $category->noun()[0] . ' will be listed here.',
        'compact' => true,
    ]) ?>
    <?php endif ?>
  <?php else: ?>
    <ol class="notes-list">
      <?php foreach ($notes as $note): ?>
        <?= partial('partials/note-entry', ['note' => $note]) ?>
      <?php endforeach ?>
    </ol>
    <?php if ($noteCount > count($notes)): ?>
      <p class="attention__more">Showing the newest <?= count($notes) ?> of <?= $noteCount ?> notes.</p>
    <?php endif ?>
  <?php endif ?>
</section>

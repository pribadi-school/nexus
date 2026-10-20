<?php use App\Devices\DeviceController; ?>
<section class="card notes" id="notes" aria-labelledby="notes-title">
  <div class="card__header">
    <h2 class="card__title" id="notes-title">Notes</h2>
    <span class="chip chip--quiet num"><?= $noteCount ?></span>
  </div>
  <div class="note-form" id="add-note">
    <div class="field">
      <label class="field__label" for="note-body">New note</label>
      <textarea class="input textarea" id="note-body" name="note" form="<?= e($formId) ?>" rows="3" maxlength="<?= DeviceController::NOTE_MAX ?>"
                placeholder="<?= e($category->noteExample()) ?>"<?= isset($errors['note']) ? ' aria-invalid="true" aria-describedby="note-error"' : '' ?>><?= e($values['note'] ?? '') ?></textarea>
      <?php if (isset($errors['note'])): ?><p class="field__error" id="note-error"><?= e($errors['note']) ?></p><?php endif ?>
      <p class="field__hint">A new note is included when you save changes.</p>
    </div>
  </div>
  <?php if ($notes === []): ?>
    <p class="notes-placeholder">-</p>
  <?php else: ?>
    <ol class="notes-list">
      <?php foreach ($notes as $note): ?><?= partial('partials/note-entry', ['note' => $note]) ?><?php endforeach ?>
    </ol>
    <?php if ($noteCount > count($notes)): ?><p class="attention__more">Showing the newest <?= count($notes) ?> of <?= $noteCount ?> notes.</p><?php endif ?>
  <?php endif ?>
</section>

<?php
use App\Core\Session;
use App\Devices\DeviceController;
use App\Devices\HistoryService;
use App\Devices\NoteKind;

$noteId = (int) $note['id'];
$isStatus = $note['kind'] === NoteKind::STATUS;
$draft = Session::get('history.draft');
if (($draft['id'] ?? null) === $noteId) Session::forget('history.draft'); else $draft = null;
$meta = is_array($note['meta']) ? $note['meta'] : [];
$selected = $draft['status'] ?? $meta['to'] ?? '';
?>
<details class="history-edit"<?= $draft !== null ? ' open' : '' ?>>
  <summary>Edit <?= $isStatus ? 'status change' : 'note' ?></summary>
  <form method="post" action="/devices/<?= (int) $note['device_id'] ?>/notes/<?= $noteId ?>" data-busy-on-submit>
    <?= csrf_field() ?>
    <input type="hidden" name="version" value="<?= e(HistoryService::version($note)) ?>">
    <div class="field">
      <?php if ($isStatus): ?>
        <label class="field__label" for="history-status-<?= $noteId ?>">Correct status</label>
        <select class="select" id="history-status-<?= $noteId ?>" name="status" required>
          <?php if (!$category->acceptsStatus($selected)): ?><option value="" selected disabled>Choose status</option><?php endif ?>
          <?php foreach ($category->statuses() as $status): ?>
            <option value="<?= e($status) ?>"<?= $selected === $status ? ' selected' : '' ?>><?= e($category->statusLabel($status)) ?></option>
          <?php endforeach ?>
        </select>
        <p class="field__hint">Correcting the latest change also updates the current status.</p>
      <?php else: ?>
        <label class="field__label" for="history-body-<?= $noteId ?>">Note</label>
        <textarea class="input textarea" id="history-body-<?= $noteId ?>" name="body" rows="3" maxlength="<?= DeviceController::NOTE_MAX ?>" required><?= e($draft['body'] ?? $note['body']) ?></textarea>
      <?php endif ?>
    </div>
    <button class="btn btn--secondary btn--sm" type="submit">Save correction</button>
  </form>
</details>

<?php
/**
 * Shown under a device's name in a list when it has a problem: the status
 * word (colour is never the only cue) and, if given, the latest note.
 * Lists no longer have a status column; status is changed on the device page.
 *
 * @var \App\Devices\Category $category
 * @var string|null $note    latest note text
 * @var string|null $noteAt  its UTC time
 */
use App\Devices\Status;

$note ??= null;
$noteAt ??= null;
?>
<span class="problem-flag">
  <span class="chip chip--problem"><?= icon('triangle-alert', 'icon--sm') ?><?= e($category->statusLabel(Status::PROBLEM)) ?></span>
  <?php if ($note): ?>
    <span class="status-note" title="<?= e($note) ?>"><?= e($note) ?><?php if ($noteAt): ?> <span class="status-note__time">· <?= e(time_ago($noteAt)) ?></span><?php endif ?></span>
  <?php endif ?>
</span>

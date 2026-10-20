<?php
/** Latest non-status note, shared by device tables and mobile cards. */
$body = $device['latest_note'] ?? null;
$at = $device['latest_note_at'] ?? null;
$href ??= null;
$emptyLabel ??= 'No note yet';
?>
<span class="latest-note">
  <?php if ($body !== null && $body !== ''): ?>
    <?php if ($href !== null): ?><a class="latest-note__text" href="<?= e($href) ?>#notes" title="<?= e($body) ?>"><?= e($body) ?></a><?php else: ?><span class="latest-note__text" title="<?= e($body) ?>"><?= e($body) ?></span><?php endif ?>
    <?php if ($at): ?><time class="latest-note__time" datetime="<?= e(iso_time($at)) ?>" title="<?= e(format_datetime($at)) ?>"><?= e(time_ago($at)) ?></time><?php endif ?>
  <?php else: ?>
    <span class="latest-note__time"><?= e($emptyLabel) ?></span>
  <?php endif ?>
</span>

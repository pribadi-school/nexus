<?php
/**
 * The status switch (shared by every category). On = OK / Running well,
 * off = Has problem. Works as a plain form. With $instant (lists),
 * status-toggle.js saves it in place without a reload; without it (the
 * detail page) the page reloads so the new status note shows in the log.
 *
 * @var \App\Devices\Category $category
 * @var array<string, mixed> $device  needs id, status
 * @var string $title   the device's name, for screen readers and messages
 * @var string $return  page to come back to after a plain-form change
 * @var bool $large
 * @var bool $instant
 */
use App\Devices\Status;

$large ??= false;
$instant ??= true;
$isOk = $device['status'] !== Status::PROBLEM;
$next = $isOk ? Status::PROBLEM : Status::OK;
$okLabel = $category->statusLabel(Status::OK);
$problemLabel = $category->statusLabel(Status::PROBLEM);
?>
<form class="status-toggle" method="post" action="/devices/<?= (int) $device['id'] ?>/status" <?= $instant ? 'data-status-toggle' : 'data-busy-on-submit' ?>>
  <?= csrf_field() ?>
  <input type="hidden" name="status" value="<?= e($next) ?>">
  <input type="hidden" name="return" value="<?= e($return) ?>">
  <button type="submit" class="switch<?= $large ? ' switch--lg' : '' ?> <?= $isOk ? 'is-ok' : 'is-problem' ?>"
          data-label-ok="<?= e($okLabel) ?>" data-label-problem="<?= e($problemLabel) ?>" data-title="<?= e($title) ?>">
    <span class="switch__track" aria-hidden="true"><span class="switch__thumb"></span></span>
    <span class="switch__label"><?= e($isOk ? $okLabel : $problemLabel) ?></span>
    <span class="visually-hidden" data-switch-hint>: <?= e($title) ?>. Change to <?= e($isOk ? $problemLabel : $okLabel) ?></span>
  </button>
</form>

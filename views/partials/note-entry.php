<?php
/**
 * One entry of a notes log: the shared component for the dashboard feed and
 * every device's notes log.
 *
 * @var array<string, mixed> $note  device_notes row + category, author_name
 *                                  (+ title, context, url when $showDevice)
 * @var bool $showDevice  name the device (feeds that mix devices)
 */
use App\Devices\NoteKind;
use App\Devices\Status;

$showDevice ??= false;
$kind = NoteKind::meta((string) $note['kind']);
$category = categories()->find((string) $note['category']);
$plainNote = $category?->key() === 'chromebook' && !in_array($note['kind'], [NoteKind::STATUS, NoteKind::ASSIGNMENT], true);
if ($plainNote) $kind = NoteKind::meta(NoteKind::NOTE);
$author = (string) $note['author_name'];
$meta = is_array($note['meta']) ? $note['meta'] : [];
?>
<li class="note note--<?= e($kind['tone']) ?>" id="note-<?= (int) $note['id'] ?>">
  <div class="note__top">
    <span class="note__kind"><?= icon($kind['icon']) ?><?= e($kind['label']) ?></span>
    <time class="note__time" datetime="<?= e(iso_time($note['created_at'])) ?>" title="<?= e(format_datetime($note['created_at'])) ?>"><?= e(time_ago($note['created_at'])) ?></time>
  </div>

  <?php if ($showDevice): ?>
    <p>
      <a class="note__device" href="<?= e($note['url']) ?>"><?= e($note['title']) ?></a>
      <span class="note__context">· <?= e($note['context']) ?></span>
    </p>
  <?php endif ?>

  <?php if ($note['kind'] === NoteKind::STATUS && isset($meta['to']) && Status::isValid((string) $meta['to'])): ?>
    <?php $to = (string) $meta['to']; ?>
    <p class="note__text">
      Status changed to
      <span class="status-word status-word--<?= e($to) ?>"><?= e($category?->statusLabel($to) ?? $to) ?></span>
      by <?= e($author) ?>
    </p>
  <?php else: ?>
    <p class="note__text"><?= nl2br(e($note['body'])) ?></p>
    <p class="note__author">by <?= e($author) ?></p>
  <?php endif ?>
  <?php if (!empty($note['edited_at'])): ?>
    <p class="note__author">Edited <?= e(time_ago($note['edited_at'])) ?><?= !empty($note['editor_name']) ? ' by ' . e($note['editor_name']) : '' ?></p>
  <?php endif ?>
  <?php if (!$showDevice && $category?->key() === 'chromebook' && $note['kind'] !== NoteKind::ASSIGNMENT): ?>
    <?= partial('partials/history-edit', ['note' => $note, 'category' => $category]) ?>
  <?php endif ?>
</li>

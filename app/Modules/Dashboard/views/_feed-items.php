<?php
/**
 * Feed entries grouped by day. Also returned by GET /activity for "Show
 * older activity"; app.js drops a repeated day heading at the seam.
 *
 * @var list<array<string, mixed>> $feed
 */
$day = null;
foreach ($feed as $note):
    $noteDay = local_time($note['created_at'])?->format('Y-m-d');
    if ($noteDay !== $day):
        $day = $noteDay; ?>
  <li class="feed__day" data-day="<?= e($noteDay) ?>"><?= e(day_label($note['created_at'])) ?></li>
<?php endif ?>
  <?= partial('partials/note-entry', ['note' => $note, 'showDevice' => true]) ?>
<?php endforeach;

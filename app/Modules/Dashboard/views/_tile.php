<?php
/**
 * One category's stat tile: total, a status meter, and labelled counts.
 *
 * @var \App\Devices\Category $category
 * @var int $total
 * @var int $ok
 * @var int $problem
 */
$okLabel = $category->statusLabel('ok');
$problemLabel = $category->statusLabel('problem');

// Meter geometry in %. A problem segment never shrinks below 3% so a single
// broken device out of hundreds is still visible.
$problemPct = $total > 0 && $problem > 0 ? max(3.0, round($problem / $total * 100, 2)) : 0.0;
$okPct = $total > 0 ? 100.0 - $problemPct : 0.0;
$titleId = 'tile-' . e($category->key());
?>
<article class="card tile" aria-labelledby="<?= $titleId ?>">
  <div class="tile__head">
    <h2 class="tile__title" id="<?= $titleId ?>"><?= e($category->name()) ?></h2>
    <a class="link-quiet" href="<?= e($category->path()) ?>">View all<?= icon('chevron-right', 'icon--sm') ?></a>
  </div>

  <p class="tile__value">
    <?= $total ?>
    <span class="tile__unit"><?= e($category->noun()[$total === 1 ? 0 : 1]) ?></span>
  </p>

  <svg class="meter" width="100%" height="8" role="img"
       aria-label="<?= e(sprintf('%d %s, %d %s', $ok, strtolower($okLabel), $problem, strtolower($problemLabel))) ?>">
    <?php if ($total === 0): ?>
      <rect class="meter__empty" x="0" y="0" width="100%" height="8"/>
    <?php else: ?>
      <?php if ($okPct > 0): ?>
        <rect class="meter__ok" x="0" y="0" width="<?= $okPct ?>%" height="8"><title><?= e("$okLabel: $ok") ?></title></rect>
      <?php endif ?>
      <?php if ($problemPct > 0): ?>
        <rect class="meter__problem" x="<?= $okPct ?>%" y="0" width="<?= $problemPct ?>%" height="8"><title><?= e("$problemLabel: $problem") ?></title></rect>
        <?php if ($okPct > 0): ?>
          <rect class="meter__gap" x="<?= $okPct ?>%" y="0" width="2" height="8" transform="translate(-1 0)"/>
        <?php endif ?>
      <?php endif ?>
    <?php endif ?>
  </svg>

  <ul class="tile__breakdown">
    <li class="tile__row">
      <span class="swatch swatch--ok" aria-hidden="true"></span>
      <span><?= e($okLabel) ?></span>
      <span class="tile__count"><?= $ok ?></span>
    </li>
    <li class="tile__row tile__row--problem<?= $problem > 0 ? ' has-items' : '' ?>">
      <span class="swatch <?= $problem > 0 ? 'swatch--problem' : 'swatch--empty' ?>" aria-hidden="true"></span>
      <?php if ($problem > 0): ?>
        <a href="<?= e(url($category->path(), ['status' => 'problem'])) ?>"><?= e($problemLabel) ?></a>
      <?php else: ?>
        <span><?= e($problemLabel) ?></span>
      <?php endif ?>
      <span class="tile__count"><?= $problem ?></span>
    </li>
  </ul>
</article>

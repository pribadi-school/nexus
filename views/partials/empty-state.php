<?php
/**
 * Shared empty state: muted icon, title, one supporting line, optional action.
 *
 * @var string $icon
 * @var string $title
 * @var string $text
 * @var string|null $actionHref
 * @var string|null $actionLabel
 * @var bool $compact
 */
$actionHref ??= null;
$actionLabel ??= null;
$compact ??= false;
?>
<div class="empty-state<?= $compact ? ' empty-state--compact' : '' ?>">
  <?= icon($icon, 'icon--xl') ?>
  <p class="empty-state__title"><?= e($title) ?></p>
  <p class="empty-state__text"><?= e($text) ?></p>
  <?php if ($actionHref !== null && $actionLabel !== null): ?>
    <div class="empty-state__actions">
      <a class="btn btn--primary" href="<?= e($actionHref) ?>"><?= e($actionLabel) ?></a>
    </div>
  <?php endif ?>
</div>

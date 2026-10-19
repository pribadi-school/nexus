<?php
$itemBase = $base . '/items/' . (int) ($entry['item_id'] ?? 0);
$rowMenuId = 'playlist-actions-' . ($saved ? 'item-' . (int) $entry['item_id'] : 'library-' . (int) $entry['id']);
?>
<li class="card signage-editor-item" data-editor-item data-content-id="<?= (int) $entry['id'] ?>" data-item-id="<?= $saved ? (int) $entry['item_id'] : '' ?>" data-content-type="<?= e($entry['type']) ?>" data-duration="<?= $saved ? (int) $entry['duration_seconds'] : 30 ?>" data-content-name="<?= e($entry['name']) ?>">
  <span class="visually-hidden" data-item-position<?= !$saved ? ' hidden' : '' ?>><?= $position ?></span>
  <div class="signage-editor-item__thumbnail"><?= partial('Signage::_playlist-thumbnail', ['cover' => $entry, 'href' => '/signage/content/' . (int) $entry['id'] . '/edit', 'label' => 'Edit ' . $entry['name'], 'emptyText' => 'Preview']) ?></div>
  <div class="signage-editor-item__info"><span class="signage-row__title" title="<?= e($entry['name']) ?>"><?= e($entry['name']) ?></span>
    <span class="signage-row__sub"<?= $entry['type'] === 'video' ? ' data-video-minutes' : '' ?>><?= $entry['type'] === 'video' ? 'Video' : ($entry['type'] === 'image' ? 'Image' : 'Website') ?></span>
  </div>
  <?php if ($entry['type'] !== 'video'): ?>
  <form class="signage-editor-item__timing" method="post" action="<?= e($itemBase) ?>" data-duration-form<?= !$saved ? ' hidden' : '' ?>><?= csrf_field() ?><label><span>Duration</span><span class="signage-editor-item__duration-value"><input class="input" name="duration_minutes" type="number" min="0.083333" max="1440" step="any" value="<?= $saved ? e(round((int) $entry['duration_seconds'] / 60, 6)) : '0.5' ?>" aria-label="Duration in minutes for <?= e($entry['name']) ?>" data-item-duration><span>min</span></span></label><noscript><button class="btn btn--secondary btn--sm" type="submit">Save duration</button></noscript></form>
  <?php endif ?>
  <div class="signage-editor-item__actions" data-item-actions<?= !$saved ? ' hidden' : '' ?>>
    <button class="icon-btn" type="button" popovertarget="<?= e($rowMenuId) ?>" data-row-menu-button aria-label="Playlist actions for <?= e($entry['name']) ?>"><?= icon('ellipsis-vertical', 'icon--sm') ?></button>
    <div class="signage-content-menu signage-editor-item__menu" id="<?= e($rowMenuId) ?>" popover>
      <form method="post" action="<?= e($itemBase) ?>/move" data-item-move="up"><?= csrf_field() ?><input type="hidden" name="direction" value="up"><button class="menu-item" type="submit" aria-label="Move <?= e($entry['name']) ?> up"><?= icon('chevron-up', 'icon--sm') ?>Move up</button></form>
      <form method="post" action="<?= e($itemBase) ?>/move" data-item-move="down"><?= csrf_field() ?><input type="hidden" name="direction" value="down"><button class="menu-item" type="submit" aria-label="Move <?= e($entry['name']) ?> down"><?= icon('chevron-down', 'icon--sm') ?>Move down</button></form>
      <form method="post" action="<?= e($itemBase) ?>/delete" data-item-remove><?= csrf_field() ?><button class="menu-item btn--danger-quiet" type="submit" aria-label="Remove <?= e($entry['name']) ?> from this screen"><?= icon('x', 'icon--sm') ?>Remove from playlist</button></form>
    </div>
  </div>
  <form method="post" action="<?= e($base) ?>/items" data-library-add<?= $saved ? ' hidden' : '' ?>><?= csrf_field() ?><input type="hidden" name="content_id" value="<?= (int) $entry['id'] ?>"><input type="hidden" name="duration_seconds" value="30"><button class="icon-btn" type="submit" aria-label="Add <?= e($entry['name']) ?> to this screen"><?= icon('plus', 'icon--sm') ?></button></form>
</li>

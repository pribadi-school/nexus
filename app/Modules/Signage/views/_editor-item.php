<?php $itemBase = $base . '/items/' . (int) ($entry['item_id'] ?? 0); ?>
<li class="card signage-editor-item" data-editor-item data-content-id="<?= (int) $entry['id'] ?>" data-item-id="<?= $saved ? (int) $entry['item_id'] : '' ?>" data-content-type="<?= e($entry['type']) ?>" data-duration="<?= $saved ? (int) $entry['duration_seconds'] : 30 ?>" data-content-name="<?= e($entry['name']) ?>">
  <span class="signage-editor-item__position" data-item-position<?= !$saved ? ' hidden' : '' ?>><?= $position ?></span>
  <div class="signage-editor-item__thumbnail"><?= partial('Signage::_playlist-thumbnail', ['cover' => $entry, 'href' => '/signage/content/' . (int) $entry['id'] . '/edit', 'label' => 'Edit ' . $entry['name'], 'emptyText' => 'Preview']) ?></div>
  <div class="signage-editor-item__info"><span class="signage-row__title"><?= e($entry['name']) ?></span><span class="signage-row__sub"><?= $entry['type'] === 'video' ? 'MP4 video · full video' : 'Website' ?></span>
    <?php if ($entry['type'] === 'website'): ?><label class="signage-editor-item__timing"<?= !$saved ? ' hidden' : '' ?>><span>Display seconds</span><input class="input" type="number" min="5" max="86400" value="<?= $saved ? (int) $entry['duration_seconds'] : 30 ?>" aria-label="Display seconds for <?= e($entry['name']) ?>" data-item-duration></label><?php endif ?>
  </div>
  <div class="signage-editor-item__actions" data-item-actions<?= !$saved ? ' hidden' : '' ?>>
    <form method="post" action="<?= e($itemBase) ?>/move" data-item-move="up"><?= csrf_field() ?><input type="hidden" name="direction" value="up"><button class="icon-btn" type="submit" aria-label="Move <?= e($entry['name']) ?> up"><?= icon('chevron-up', 'icon--sm') ?></button></form>
    <form method="post" action="<?= e($itemBase) ?>/move" data-item-move="down"><?= csrf_field() ?><input type="hidden" name="direction" value="down"><button class="icon-btn" type="submit" aria-label="Move <?= e($entry['name']) ?> down"><?= icon('chevron-down', 'icon--sm') ?></button></form>
    <form method="post" action="<?= e($itemBase) ?>/delete" data-item-remove><?= csrf_field() ?><button class="icon-btn icon-btn--danger" type="submit" aria-label="Remove <?= e($entry['name']) ?> from this screen"><?= icon('x', 'icon--sm') ?></button></form>
  </div>
  <form method="post" action="<?= e($base) ?>/items" data-library-add<?= $saved ? ' hidden' : '' ?>><?= csrf_field() ?><input type="hidden" name="content_id" value="<?= (int) $entry['id'] ?>"><input type="hidden" name="duration_seconds" value="30"><button class="icon-btn" type="submit" aria-label="Add <?= e($entry['name']) ?> to this screen"><?= icon('plus', 'icon--sm') ?></button></form>
</li>

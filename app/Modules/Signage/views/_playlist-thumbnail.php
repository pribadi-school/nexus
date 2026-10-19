<?php
$video = $cover !== null && $cover['type'] === 'video';
$image = $cover !== null && $cover['type'] === 'image';
$previewUrl = $video || $image ? url('/signage/preview/media/' . (int) $cover['id'], ['v' => substr(hash('sha256', (string) $cover['stored_filename']), 0, 16)]) : '';
?>
<a class="signage-thumbnail" href="<?= e($href) ?>" aria-label="<?= e($label) ?>"<?= $video ? ' data-signage-thumbnail data-video-duration data-video-src="' . e($previewUrl) . '"' : '' ?>>
  <?php if ($video): ?>
    <img class="signage-thumbnail__image" alt="" data-thumbnail-image hidden>
    <span class="signage-thumbnail__fallback" data-thumbnail-fallback><?= icon('presentation', 'icon--xl') ?><span data-thumbnail-message>Video preview</span></span>
    <span class="signage-thumbnail__duration" data-thumbnail-duration hidden></span>
    <span class="visually-hidden" data-thumbnail-orientation></span>
  <?php elseif ($image): ?>
    <img class="signage-thumbnail__image" src="<?= e($previewUrl) ?>" alt="" loading="lazy">
    <span class="signage-thumbnail__kind">Image</span>
  <?php elseif ($cover !== null): ?>
    <span class="signage-thumbnail__website"><?= icon('tv-minimal', 'icon--xl') ?><span><?= e((string) parse_url((string) $cover['source_url'], PHP_URL_HOST)) ?></span></span>
    <span class="signage-thumbnail__kind">Website</span>
  <?php else: ?>
    <span class="signage-thumbnail__fallback"><?= icon('presentation', 'icon--xl') ?><span><?= e($emptyText) ?></span></span>
  <?php endif ?>
</a>

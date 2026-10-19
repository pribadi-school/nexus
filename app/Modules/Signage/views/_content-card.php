<?php
$bytes = static fn (int $value): string => $value >= 1073741824 ? round($value / 1073741824, 2) . ' GB' : ($value >= 1048576 ? round($value / 1048576, 1) . ' MB' : round($value / 1024, 1) . ' KB');
$video = $entry['type'] === 'video';
$image = $entry['type'] === 'image';
$editUrl = '/signage/content/' . (int) $entry['id'] . '/edit';
$host = $video || $image ? '' : (string) parse_url((string) $entry['source_url'], PHP_URL_HOST);
$previewUrl = $video || $image ? url('/signage/preview/media/' . (int) $entry['id'], ['v' => substr(hash('sha256', (string) $entry['stored_filename']), 0, 16)]) : '';
?>
<li class="card signage-content-card"<?= $video ? ' data-signage-thumbnail data-video-src="' . e($previewUrl) . '"' : '' ?>>
  <a class="signage-thumbnail<?= !$video && !$image ? ' signage-thumbnail--website' : '' ?>" href="<?= e($editUrl) ?>" aria-label="Edit <?= e($entry['name']) ?>">
    <?php if ($video): ?>
      <img class="signage-thumbnail__image" alt="" data-thumbnail-image hidden>
      <span class="signage-thumbnail__fallback" data-thumbnail-fallback><?= icon('presentation', 'icon--xl') ?><span data-thumbnail-message>Video preview</span></span>
      <span class="signage-thumbnail__duration" data-thumbnail-duration hidden></span>
    <?php elseif ($image): ?>
      <img class="signage-thumbnail__image" src="<?= e($previewUrl) ?>" alt="" loading="lazy">
      <span class="signage-thumbnail__kind">Image</span>
    <?php else: ?>
      <span class="signage-thumbnail__website"><?= icon('tv-minimal', 'icon--xl') ?><span><?= e($host) ?></span></span>
      <span class="signage-thumbnail__kind">Website</span>
    <?php endif ?>
  </a>
  <div class="signage-content-card__body">
    <div class="signage-content-card__heading"><a class="signage-content-card__name" href="<?= e($editUrl) ?>"><?= e($entry['name']) ?></a>
      <button class="icon-btn" type="button" popovertarget="content-menu-<?= (int) $entry['id'] ?>" aria-label="Actions for <?= e($entry['name']) ?>"><?= icon('ellipsis-vertical', 'icon--sm') ?></button>
      <div class="signage-content-menu" id="content-menu-<?= (int) $entry['id'] ?>" popover>
        <a class="menu-item" href="<?= e($editUrl) ?>"><?= icon('pencil', 'icon--sm') ?>Edit content</a>
        <p class="signage-content-menu__label">Add to TV playlist</p>
        <?php if ($screens === []): ?><a class="menu-item" href="/signage/screens/create">Add your first screen</a><?php endif ?>
        <?php foreach ($screens as $target): ?>
          <form method="post" action="/signage/screens/<?= (int) $target['id'] ?>/items"><?= csrf_field() ?><input type="hidden" name="content_id" value="<?= (int) $entry['id'] ?>"><input type="hidden" name="duration_seconds" value="30"><input type="hidden" name="from_library" value="1"><button class="menu-item" type="submit"><?= icon('tv-minimal', 'icon--sm') ?><span><?= e($target['name']) ?></span></button></form>
        <?php endforeach ?>
      </div>
    </div>
    <p class="signage-content-card__meta"><?= $video ? 'Video' : ($image ? 'Image' : 'Website') ?><span data-thumbnail-orientation></span> · <?= e(time_ago($entry['updated_at'])) ?></p>
    <p class="signage-content-card__details"><?= $video || $image ? e($bytes((int) $entry['size_bytes'])) . ' · ' : '' ?><?= e(plural((int) $entry['used_count'], 'playlist use')) ?></p>
  </div>
</li>

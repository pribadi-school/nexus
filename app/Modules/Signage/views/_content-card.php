<?php
$bytes = static fn (int $value): string => $value >= 1073741824 ? round($value / 1073741824, 2) . ' GB' : ($value >= 1048576 ? round($value / 1048576, 1) . ' MB' : round($value / 1024, 1) . ' KB');
$video = $entry['type'] === 'video';
$image = $entry['type'] === 'image';
$assignedScreens ??= [];
$editUrl = '/signage/content/' . (int) $entry['id'] . '/edit';
$host = $video || $image ? '' : (string) parse_url((string) $entry['source_url'], PHP_URL_HOST);
$previewUrl = $video || $image ? url('/signage/preview/media/' . (int) $entry['id'], ['v' => substr(hash('sha256', (string) $entry['stored_filename']), 0, 16)]) : '';
// A saved frame avoids streaming the whole video just to draw this preview.
$poster = $video ? \App\Modules\Signage\SignagePoster::find($entry) : null;
?>
<li class="card signage-content-card" data-content-card data-content-id="<?= (int) $entry['id'] ?>" data-size-bytes="<?= (int) $entry['size_bytes'] ?>"<?= $video ? ($poster === null ? ' data-signage-thumbnail' : ' data-video-duration="' . e($poster['duration']) . '"') . ' data-video-src="' . e($previewUrl) . '"' : '' ?>>
  <a class="signage-thumbnail<?= !$video && !$image ? ' signage-thumbnail--website' : '' ?>" href="<?= e($editUrl) ?>" aria-label="Edit <?= e($entry['name']) ?>">
    <?php if ($video && $poster !== null): ?>
      <img class="signage-thumbnail__image" src="<?= e($poster['url']) ?>" alt="" loading="lazy" data-thumbnail-image>
      <span class="signage-thumbnail__duration" data-thumbnail-duration><?= e(\App\Modules\Signage\SignagePoster::clock($poster['duration'])) ?></span>
    <?php elseif ($video): ?>
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
        <p class="signage-content-menu__label">TV playlists</p>
        <?php if ($screens === []): ?><a class="menu-item" href="/signage/screens/create">Add your first screen</a><?php endif ?>
        <?php if ($screens !== []): ?>
          <form method="post" action="/signage/content/<?= (int) $entry['id'] ?>/screens" data-content-targets>
            <?= csrf_field() ?>
            <input type="hidden" name="membership_mode" value="sync">
            <?php foreach ($assignedScreens as $assignedScreen): ?><input type="hidden" name="original_screen_ids[]" value="<?= (int) $assignedScreen ?>"><?php endforeach ?>
            <p class="signage-content-menu__hint">Check to add, uncheck to remove, then save.</p>
            <fieldset class="signage-content-targets"><legend class="visually-hidden">Choose TVs for <?= e($entry['name']) ?></legend>
              <?php foreach ($screens as $target): $assigned = in_array((int) $target['id'], $assignedScreens, true); ?>
                <label class="signage-content-target"><input type="checkbox" name="screen_ids[]" value="<?= (int) $target['id'] ?>"<?= $assigned ? ' checked' : '' ?>><span><?= e($target['name']) ?></span></label>
              <?php endforeach ?>
            </fieldset>
            <button class="btn btn--primary btn--sm signage-content-targets__save" type="submit">Save TV playlists</button>
          </form>
        <?php endif ?>
        <form class="signage-content-menu__delete" method="post" action="/signage/content/<?= (int) $entry['id'] ?>/delete" data-content-delete data-confirm="Delete this content?" data-confirm-text="The uploaded file will be deleted. Content used in a playlist must be removed from that playlist first." data-confirm-button="Delete content" data-confirm-tone="danger">
          <?= csrf_field() ?><input type="hidden" name="from_library" value="1"><button class="menu-item btn--danger-quiet" type="submit"><?= icon('trash-2', 'icon--sm') ?>Delete content</button>
        </form>
      </div>
    </div>
    <p class="signage-content-card__meta"><?= $video ? 'Video' : ($image ? 'Image' : 'Website') ?><span data-thumbnail-orientation><?= $poster !== null ? ($poster['height'] > $poster['width'] ? ' · Portrait' : ' · Landscape') : '' ?></span> · <?= e(time_ago($entry['updated_at'])) ?></p>
    <p class="signage-content-card__details"><?= $video || $image ? e($bytes((int) $entry['size_bytes'])) . ' · ' : '' ?><span data-content-usage><?= e(plural((int) $entry['used_count'], 'playlist use')) ?></span></p>
  </div>
</li>

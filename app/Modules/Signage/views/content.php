<?php $bytes = static fn (int $value): string => $value >= 1073741824 ? round($value / 1073741824, 2) . ' GB' : ($value >= 1048576 ? round($value / 1048576, 1) . ' MB' : round($value / 1024, 1) . ' KB'); ?>
<header class="page-header"><div><h1 class="page-title">Signage</h1><p class="page-subtitle">Screens and content for school displays.</p></div><div class="page-actions"><a class="btn btn--primary" href="/signage/content/create"><?= icon('upload', 'icon--sm') ?>Add content</a></div></header>
<?= partial('Signage::_tabs', ['tab' => 'content']) ?>
<div class="signage-library-heading"><h2 class="card__title">Content library</h2><p class="signage-muted"><?= e(plural(count($content), 'item')) ?><?= $content !== [] ? ' · ' . e($bytes((int) $usageBytes)) . ' stored' : '' ?></p></div>
<?php if ($content === []): ?>
  <section class="card"><?= partial('partials/empty-state', ['icon' => 'upload', 'title' => 'Build your content library', 'text' => 'Upload MP4 videos to your server or add website addresses. Then add them to a playlist.', 'actionHref' => '/signage/content/create', 'actionLabel' => 'Add content']) ?></section>
<?php else: ?>
  <ul class="signage-content-grid" aria-label="Content library">
    <?php foreach ($content as $entry):
      $video = $entry['type'] === 'video';
      $editUrl = '/signage/content/' . (int) $entry['id'] . '/edit';
      $host = $video ? '' : (string) parse_url((string) $entry['source_url'], PHP_URL_HOST);
      $previewUrl = $video ? url('/signage/preview/media/' . (int) $entry['id'], ['v' => substr(hash('sha256', (string) $entry['stored_filename']), 0, 16)]) : '';
    ?>
      <li class="card signage-content-card"<?= $video ? ' data-signage-thumbnail data-video-src="' . e($previewUrl) . '"' : '' ?>>
        <a class="signage-thumbnail<?= !$video ? ' signage-thumbnail--website' : '' ?>" href="<?= e($editUrl) ?>" aria-label="Edit <?= e($entry['name']) ?>">
          <?php if ($video): ?>
            <img class="signage-thumbnail__image" alt="" data-thumbnail-image hidden>
            <span class="signage-thumbnail__fallback" data-thumbnail-fallback><?= icon('presentation', 'icon--xl') ?><span data-thumbnail-message>Video preview</span></span>
            <span class="signage-thumbnail__duration" data-thumbnail-duration hidden></span>
          <?php else: ?>
            <span class="signage-thumbnail__website"><?= icon('tv-minimal', 'icon--xl') ?><span><?= e($host) ?></span></span>
            <span class="signage-thumbnail__kind">Website</span>
          <?php endif ?>
        </a>
        <div class="signage-content-card__body">
          <div class="signage-content-card__heading"><a class="signage-content-card__name" href="<?= e($editUrl) ?>"><?= e($entry['name']) ?></a><a class="icon-btn" href="<?= e($editUrl) ?>" aria-label="Edit <?= e($entry['name']) ?>"><?= icon('pencil', 'icon--sm') ?></a></div>
          <p class="signage-content-card__meta"><?= $video ? 'MP4 video' : 'Website' ?><span data-thumbnail-orientation></span> · <?= e(time_ago($entry['updated_at'])) ?></p>
          <p class="signage-content-card__details"><?= $video ? e($bytes((int) $entry['size_bytes'])) . ' · ' : '' ?><?= e(plural((int) $entry['used_count'], 'playlist use')) ?></p>
        </div>
      </li>
    <?php endforeach ?>
  </ul>
<?php endif ?>

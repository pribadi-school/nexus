<?php $base = '/signage/screens/' . (int) $screen['id']; ?>
<section class="signage-editor" id="playlist" data-screen-editor>
  <div class="signage-editor__playlist">
    <div class="signage-editor__heading"><h2 class="card__title">Playlist</h2>
      <form method="post" action="<?= e($base) ?>/playlist" data-playlist-save>
        <?= csrf_field() ?><input type="hidden" name="revision" value="<?= e($revision) ?>"><input type="hidden" name="playlist_items" value="">
        <button class="btn btn--primary btn--sm" type="submit" data-save-playlist hidden disabled>Save changes</button>
      </form>
    </div>
    <p class="signage-help">Drag from the library, reorder, drag back to the library to remove, then save to update this TV.</p>
    <p class="signage-help">Set each item's duration in minutes. Videos loop if needed to fill the selected time.</p>
    <p class="signage-editor__feedback" aria-live="polite" data-editor-feedback><?= e(plural(count($items), 'item')) ?></p>
    <p class="signage-help">Playlist duration: <strong data-playlist-duration>Calculating…</strong></p>
    <ol class="signage-editor__list" aria-label="Playlist playback order" data-playlist-drop>
      <?php foreach ($items as $index => $entry): ?>
        <?= partial('Signage::_editor-item', ['entry' => $entry, 'base' => $base, 'position' => $index + 1, 'saved' => true]) ?>
      <?php endforeach ?>
    </ol>
    <p class="signage-editor__empty" data-playlist-empty<?= $items !== [] ? ' hidden' : '' ?>><?= icon('plus') ?>Drop videos, images or websites here to start this screen's playlist.</p>
  </div>
  <div class="signage-editor__library" data-library-panel>
    <div class="signage-editor__heading"><h2 class="card__title">Content library</h2><a class="btn btn--secondary btn--sm" href="/signage/content/create"><?= icon('upload', 'icon--sm') ?>Add content</a></div>
    <label class="visually-hidden" for="screen-content-search">Search content library</label><input class="input signage-editor__search" id="screen-content-search" type="search" placeholder="Search videos, images and websites" data-library-search>
    <ul class="signage-editor__list" aria-label="Available content" data-screen-library>
      <?php foreach ($content as $entry): ?>
        <?= partial('Signage::_editor-item', ['entry' => $entry, 'base' => $base, 'position' => 0, 'saved' => false]) ?>
      <?php endforeach ?>
    </ul>
    <?php if ($content === []): ?><p class="signage-help" data-library-empty>Upload a video or image, or add a website to start.</p><?php endif ?>
    <p class="signage-help" data-library-no-results hidden>No matching content.</p>
    <p class="signage-help" data-library-all-used hidden>Everything in the library is already in this playlist.</p>
  </div>
</section>
<?= partial('Signage::_content-dialog', ['uploadLimitBytes' => \App\Modules\Signage\SignageStorage::maxUploadBytes()]) ?>

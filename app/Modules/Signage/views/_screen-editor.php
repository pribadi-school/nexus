<?php $base = '/signage/screens/' . (int) $screen['id']; ?>
<section class="signage-editor" id="playlist" data-screen-editor>
  <div class="signage-editor__playlist">
    <div class="signage-editor__heading"><h2 class="card__title">Playlist</h2>
      <form method="post" action="<?= e($base) ?>/playlist" data-playlist-save>
        <?= csrf_field() ?><input type="hidden" name="revision" value="<?= e($revision) ?>"><input type="hidden" name="playlist_items" value="">
        <button class="btn btn--primary btn--sm" type="submit" data-save-playlist hidden disabled>Save changes</button>
      </form>
    </div>
    <p class="signage-editor__summary"><span data-playlist-count><?= e(plural(count($items), 'item')) ?></span><span aria-hidden="true"> · </span><strong data-playlist-duration>Calculating…</strong></p>
    <ol class="signage-editor__list" aria-label="Playlist playback order" data-playlist-drop>
      <?php foreach ($items as $index => $entry): ?>
        <?= partial('Signage::_editor-item', ['entry' => $entry, 'base' => $base, 'position' => $index + 1, 'saved' => true]) ?>
      <?php endforeach ?>
    </ol>
    <p class="signage-editor__feedback" aria-live="polite" data-editor-feedback></p>
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
  </div>
  <details class="signage-editor__help"><summary>Playlist help</summary><p class="signage-help">Drag content between the columns to add or remove it. Drag rows to reorder (on a phone, hold a row, then drag it), or use each row’s menu. Videos play for their full file duration. Set image and website durations in minutes, then save changes.</p></details>
</section>
<?= partial('Signage::_content-dialog', ['uploadLimitBytes' => \App\Modules\Signage\SignageStorage::maxUploadBytes()]) ?>

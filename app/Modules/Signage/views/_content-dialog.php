<dialog class="dialog signage-content-dialog" data-content-dialog aria-labelledby="content-dialog-title">
  <header class="signage-content-dialog__header">
    <div><h2 class="dialog__title" id="content-dialog-title">Add content</h2><p class="signage-help">Upload a video or image, or add a website.</p></div>
    <button class="icon-btn" type="button" data-content-cancel aria-label="Close add content"><?= icon('x') ?></button>
  </header>
  <?= partial('Signage::content-form', [
      'item' => null, 'values' => ['name' => '', 'type' => 'video', 'source_url' => ''],
      'errors' => [], 'uploadLimitBytes' => $uploadLimitBytes, 'inDialog' => true,
  ]) ?>
</dialog>

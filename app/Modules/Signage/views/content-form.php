<?php
$adding = $item === null;
$inDialog = $inDialog ?? false;
$prefix = $inDialog ? 'content-dialog-' : '';
$base = $adding ? '/signage/content' : '/signage/content/' . (int) $item['id'];
$field = static fn (string $name): string => $errors[$name] ?? '';
$invalid = static fn (string $name): string => $field($name) ? ' aria-invalid="true" aria-describedby="' . $prefix . $name . '-error"' : '';
$error = static fn (string $name): string => $field($name) ? '<p class="field__error" id="' . $prefix . $name . '-error">' . e($field($name)) . '</p>' : '';
$limit = round((int) $uploadLimitBytes / 1048576, 1);
?>
<?php if (!$inDialog): ?>
<a class="back-link" href="/signage/content"><?= icon('chevron-left', 'icon--sm') ?>Content</a>
<header class="page-header"><div><h1 class="page-title"><?= $adding ? 'Add content' : 'Edit content' ?></h1><p class="page-subtitle">Upload a video or image, or add a website to use in playlists.</p></div></header>
<?= partial('Signage::_tabs', ['tab' => 'content']) ?>
<?php endif ?>
<form class="<?= $inDialog ? 'signage-content-dialog__form' : 'card form-card' ?>" method="post" enctype="multipart/form-data" action="<?= e($base) ?>" data-signage-content-form data-upload-limit-bytes="<?= (int) $uploadLimitBytes ?>"<?= !$inDialog ? ' data-busy-on-submit' : '' ?> novalidate>
  <?= csrf_field() ?>
  <?php if ($inDialog): ?><p class="notice notice--error" role="alert" data-content-error hidden></p><?php endif ?>
  <?php if ($errors !== []): ?><div class="notice notice--error" role="alert"><?= icon('circle-alert') ?><div>Check the highlighted <?= count($errors) === 1 ? 'field' : 'fields' ?>. If an upload failed, select the file again.</div></div><?php endif ?>
  <div class="form-grid">
    <div class="field field--full<?= $field('type') ? ' has-error' : '' ?>">
      <label class="field__label" for="<?= $prefix ?>type">Content type</label>
      <?php if (!$adding): ?><input type="hidden" name="type" value="<?= e($item['type']) ?>"><?php endif ?>
      <select class="select" id="<?= $prefix ?>type" name="type" data-content-type<?= !$adding ? ' disabled' : '' ?><?= $invalid('type') ?>>
        <option value="video"<?= ($values['type'] ?? 'video') === 'video' ? ' selected' : '' ?>>Video</option>
        <option value="image"<?= ($values['type'] ?? '') === 'image' ? ' selected' : '' ?>>Image</option>
        <option value="website"<?= ($values['type'] ?? '') === 'website' ? ' selected' : '' ?>>Website address</option>
      </select><?= $error('type') ?><?php if (!$adding): ?><p class="field__hint">To use a different content type, add a new content item.</p><?php endif ?>
    </div>
    <div class="field field--full<?= $field('file') ? ' has-error' : '' ?>" data-content-fields="video image">
      <label class="field__label" for="<?= $prefix ?>file" data-file-label><?= !$adding ? 'Replace file' : 'Upload file' ?></label>
      <input class="input signage-file" type="file" id="<?= $prefix ?>file" name="file" accept="video/mp4,video/quicktime,.mp4,.mov,image/png,image/jpeg,.png,.jpg,.jpeg"<?= $invalid('file') ?>><?= $error('file') ?>
      <?php if (!$adding && $item['type'] !== 'website'): ?><p class="field__hint">Current file: <?= e($item['original_filename']) ?>. Leave this blank to keep it.</p><?php endif ?>
      <p class="field__hint">Up to <?= e($limit) ?> MB</p>
      <p class="field__hint" data-upload-feedback aria-live="polite"></p>
    </div>
    <div class="field field--full<?= $field('source_url') ? ' has-error' : '' ?>" data-content-fields="website">
      <label class="field__label" for="<?= $prefix ?>source_url">Website address</label>
      <input class="input" id="<?= $prefix ?>source_url" name="source_url" type="url" value="<?= e($values['source_url'] ?? '') ?>" maxlength="2048" placeholder="https://" autocomplete="url"<?= $invalid('source_url') ?>><?= $error('source_url') ?>
      <p class="field__hint">Use a public HTTPS page that allows display inside another website. Pages that block embedding or require a sign-in may appear blank. Set its display time in the playlist.</p>
    </div>
  </div>
  <div class="form-actions"><?php if ($inDialog): ?><button class="btn btn--secondary" type="button" data-content-cancel>Cancel</button><?php else: ?><a class="btn btn--secondary" href="/signage/content">Cancel</a><?php endif ?><button class="btn btn--primary" type="submit"><?= $adding ? 'Add content' : 'Save changes' ?></button></div>
</form>
<?php if (!$adding): ?>
  <div class="signage-danger-actions"><form method="post" action="<?= e($base) ?>/delete" data-confirm="Delete this content?" data-confirm-text="Uploaded files will be removed from your server. Content used in a playlist must be removed from that playlist first." data-confirm-button="Delete content" data-confirm-tone="danger"><?= csrf_field() ?><button class="btn btn--danger-quiet" type="submit"><?= icon('trash-2', 'icon--sm') ?>Delete content</button></form></div>
<?php endif ?>

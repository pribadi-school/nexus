<?php
/**
 * @var list<array<string,mixed>> $sounds
 */
use App\Modules\Bell\BellSounds;
?>
<header class="page-header"><div><h1 class="page-title">Bell</h1><p class="page-subtitle">Sounds you can use on either area.</p></div></header>
<?= partial('Bell::_tabs', ['tab' => 'sounds']) ?>

<section class="card bell-card">
  <h2 class="card__title">Add a sound</h2>
  <form method="post" action="/bell/sounds" enctype="multipart/form-data" data-busy-on-submit>
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field"><label class="field__label" for="sound-name">Name <span class="field__required">required</span></label><input class="input" id="sound-name" name="name" maxlength="80" placeholder="e.g. Bel masuk" required></div>
      <div class="field"><label class="field__label" for="sound-file">File <span class="field__required">required</span></label><input class="input" id="sound-file" type="file" name="file" accept=".mp3,.wav,audio/mpeg,audio/wav" required>
        <p class="field__hint">MP3 or WAV, 10 MB at most.</p></div>
    </div>
    <div class="form-actions"><button class="btn btn--primary" type="submit"><?= icon('upload', 'icon--sm') ?>Upload sound</button></div>
  </form>
</section>

<section class="card bell-card">
  <div class="card__header"><h2 class="card__title">Sounds</h2><span class="chip chip--quiet num"><?= count($sounds) + count(BellSounds::BUILTIN) ?></span></div>
  <ul class="bell-settings-list">
    <?php foreach (BellSounds::BUILTIN as $key => $name): ?>
      <li class="bell-settings-row">
        <span class="bell-settings-row__rename"><strong><?= e($name) ?></strong> <span class="chip chip--quiet">Built-in</span></span>
        <button class="icon-btn" type="button" data-bell-preview="/bell/sounds/builtin/<?= e($key) ?>" aria-label="Listen to <?= e($name) ?>"><?= icon('play') ?></button>
      </li>
    <?php endforeach ?>
    <?php foreach ($sounds as $sound): ?>
      <li class="bell-settings-row">
        <span class="bell-settings-row__rename"><strong><?= e($sound['name']) ?></strong>
          <span class="field__hint"><?= e($sound['original_filename']) ?> · <?= e(number_format((int) $sound['size_bytes'] / 1048576, 1)) ?> MB · used by <?= plural((int) $sound['use_count'], 'bell') ?></span></span>
        <div class="bell-settings-row__actions">
          <button class="icon-btn" type="button" data-bell-preview="/bell/sounds/<?= (int) $sound['id'] ?>/file" aria-label="Listen to <?= e($sound['name']) ?>"><?= icon('play') ?></button>
          <form method="post" action="/bell/sounds/<?= (int) $sound['id'] ?>/delete" data-confirm="Delete &quot;<?= e($sound['name']) ?>&quot;?" data-confirm-text="Sounds used by a bell cannot be deleted." data-confirm-button="Delete" data-confirm-tone="danger"><?= csrf_field() ?><button class="icon-btn" type="submit" aria-label="Delete <?= e($sound['name']) ?>"><?= icon('trash-2') ?></button></form>
        </div>
      </li>
    <?php endforeach ?>
  </ul>
</section>

<?php
$field = static fn (string $name): string => $errors[$name] ?? '';
$invalid = static fn (string $name): string => $field($name) ? ' aria-invalid="true" aria-describedby="' . $name . '-error"' : '';
$error = static fn (string $name): string => $field($name) ? '<p class="field__error" id="' . $name . '-error">' . e($field($name)) . '</p>' : '';
?>
<a class="back-link" href="/signage/screens"><?= icon('chevron-left', 'icon--sm') ?>Screens</a>
<header class="page-header"><div><h1 class="page-title">Pair Android TV</h1><p class="page-subtitle">Connect the Nexus Signage app to one of your screens.</p></div></header>
<?= partial('Signage::_tabs', ['tab' => 'screens']) ?>

<?php if ($screens === []): ?>
  <section class="card">
    <?= partial('partials/empty-state', [
        'icon' => 'tv-minimal', 'title' => 'Add a screen first',
        'text' => 'Create a screen and assign its playlist, then pair the Android TV app with its code.',
        'actionHref' => '/signage/screens/create', 'actionLabel' => 'Add screen',
    ]) ?>
  </section>
<?php else: ?>
  <form class="card form-card" method="post" action="/signage/pair" data-busy-on-submit novalidate>
    <?= csrf_field() ?>
    <p class="signage-help">Open Nexus Signage on the TV, enter this Nexus website address, and generate a pairing code. Enter that code below while it is still on the TV.</p>
    <?php if ($errors !== []): ?><div class="notice notice--error" role="alert"><?= icon('circle-alert') ?><div>Check the highlighted <?= count($errors) === 1 ? 'field' : 'fields' ?>.</div></div><?php endif ?>
    <div class="form-grid">
      <div class="field field--full<?= $field('code') ? ' has-error' : '' ?>">
        <label class="field__label" for="code">TV pairing code <span class="field__required">required</span></label>
        <input class="input" id="code" name="code" value="<?= e($values['code'] ?? '') ?>" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="off" spellcheck="false" placeholder="e.g. 012345" required<?= $invalid('code') ?>>
        <?= $error('code') ?><p class="field__hint">The six-digit code expires after 10 minutes.</p>
      </div>
      <div class="field field--full<?= $field('screen_id') ? ' has-error' : '' ?>">
        <label class="field__label" for="screen_id">Screen <span class="field__required">required</span></label>
        <select class="select" id="screen_id" name="screen_id" required<?= $invalid('screen_id') ?>>
          <option value="">Choose a screen</option>
          <?php foreach ($screens as $screen): ?><option value="<?= (int) $screen['id'] ?>"<?= (string) ($values['screen_id'] ?? '') === (string) $screen['id'] ? ' selected' : '' ?>><?= e($screen['name']) ?><?= $screen['location'] ? ' · ' . e($screen['location']) : '' ?></option><?php endforeach ?>
        </select><?= $error('screen_id') ?>
        <p class="field__hint">Pairing gives this TV access to the selected screen's playlist. Approve only a code you can see on your TV.</p>
      </div>
    </div>
    <div class="form-actions"><a class="btn btn--secondary" href="/signage/screens">Cancel</a><button class="btn btn--primary" type="submit">Pair TV</button></div>
  </form>
<?php endif ?>

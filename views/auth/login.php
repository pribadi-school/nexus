<?php
/**
 * @var bool $passwordSet
 * @var string $name  last name typed on this device
 */
?>
<?= partial('partials/auth-brand') ?>
<?= partial('partials/flashes') ?>

<h1 class="auth-card__title">Sign in</h1>
<p class="auth-card__lead">Enter your name and the Nexus password.</p>

<?php if (!$passwordSet): ?>
  <div class="notice notice--info">
    <?= icon('info') ?>
    <div>Sign-in is off until a password is set. Add <code>APP_PASSWORD=…</code> to the <code>.env</code> file.</div>
  </div>
<?php endif ?>

<form class="auth-form" method="post" action="/login" data-busy-on-submit>
  <?= csrf_field() ?>
  <div class="field">
    <label class="field__label" for="login-name">Your name</label>
    <input class="input" id="login-name" name="name" type="text" required maxlength="60"
           autocomplete="name" autocapitalize="words" spellcheck="false"
           value="<?= e($name) ?>"<?= $name === '' ? ' autofocus' : '' ?>>
    <p class="field__hint">Shown on the notes and status changes you make.</p>
  </div>
  <div class="field">
    <label class="field__label" for="login-password">Password</label>
    <input class="input" id="login-password" name="password" type="password" required
           autocomplete="current-password"<?= $name !== '' ? ' autofocus' : '' ?>>
  </div>
  <button type="submit" class="btn btn--primary btn--block btn--lg"<?= $passwordSet ? '' : ' disabled' ?>>Sign in</button>
</form>

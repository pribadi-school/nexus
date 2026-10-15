<?php
/**
 * @var int $status
 * @var string $title
 * @var string $message
 */
$signedIn = auth_user() !== null;
?>
<?php if (!$signedIn): ?>
  <?= partial('partials/auth-brand') ?>
<?php endif ?>
<div class="<?= $signedIn ? 'card' : '' ?>">
  <div class="empty-state">
    <?= icon($status === 404 ? 'inbox' : ($status === 403 ? 'lock' : 'circle-alert'), 'icon--xl') ?>
    <h1 class="empty-state__title"><?= e($title) ?></h1>
    <p class="empty-state__text"><?= e($message) ?></p>
    <div class="empty-state__actions">
      <a class="btn btn--primary" href="<?= $signedIn ? '/' : '/login' ?>"><?= $signedIn ? 'Back to the dashboard' : 'Go to sign in' ?></a>
    </div>
  </div>
</div>

<?php
/**
 * Signed-in pages: sidebar on desktop, top bar + bottom nav on phones.
 *
 * @var string $content
 * @var string $title
 */
use App\Core\Navigation;

$user = auth_user();
$mainNav = Navigation::main();
$name = (string) ($user['name'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
<?= partial('partials/head', ['title' => $title]) ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<aside class="sidebar" aria-label="Main">
  <div class="sidebar__brand"><?= partial('partials/lockup', ['href' => '/']) ?></div>

  <nav class="nav" aria-label="Sections">
    <?php foreach ($mainNav as $item): ?>
      <a class="nav__link" href="<?= e($item['href']) ?>"<?= is_active($item['href']) ? ' aria-current="page"' : '' ?>>
        <?= icon($item['icon']) ?><span><?= e($item['label']) ?></span>
      </a>
    <?php endforeach ?>
  </nav>

  <div class="sidebar__user">
    <?= partial('partials/avatar', ['user' => $user, 'size' => 'sm']) ?>
    <div class="sidebar__user-text">
      <p class="sidebar__user-name"><?= e($name) ?></p>
      <p class="sidebar__user-meta">Signed in</p>
    </div>
    <form method="post" action="/logout">
      <?= csrf_field() ?>
      <button type="submit" class="icon-btn" title="Sign out"><?= icon('log-out', '', 'Sign out') ?></button>
    </form>
  </div>
</aside>

<header class="topbar">
  <?= partial('partials/lockup', ['href' => '/']) ?>
  <button type="button" class="avatar-btn" popovertarget="account-menu" aria-label="Account menu, signed in as <?= e($name) ?>">
    <?= partial('partials/avatar', ['user' => $user, 'size' => 'sm']) ?>
  </button>
  <div id="account-menu" class="account-menu" popover>
    <div class="account-menu__who">
      <?= partial('partials/avatar', ['user' => $user]) ?>
      <div>
        <p class="account-menu__name"><?= e($name) ?></p>
        <p class="account-menu__meta">Your name on notes</p>
      </div>
    </div>
    <form method="post" action="/logout">
      <?= csrf_field() ?>
      <button type="submit" class="menu-item"><?= icon('log-out') ?><span>Sign out</span></button>
    </form>
  </div>
</header>

<main id="main" class="main" tabindex="-1">
  <div class="main__inner">
    <?= partial('partials/flashes') ?>
    <?= $content ?>
  </div>
</main>

<nav class="bottom-nav" aria-label="Sections">
  <?php foreach ($mainNav as $item): ?>
    <a class="bottom-nav__link" href="<?= e($item['href']) ?>"<?= is_active($item['href']) ? ' aria-current="page"' : '' ?>>
      <?= icon($item['icon']) ?><span class="bottom-nav__label"><?= e($item['label']) ?></span>
    </a>
  <?php endforeach ?>
</nav>

<div class="toast-region" id="toasts" role="status" aria-live="polite"></div>
<?= partial('partials/confirm-dialog') ?>
</body>
</html>

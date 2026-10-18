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

// Breadcrumb for the desktop top bar: the section (sidebar item), then the
// page when it is not the section itself. "Classroom 7B · StudyTouch" -> "Classroom 7B".
$section = null;
foreach ($mainNav as $item) {
    if (is_active($item['href'])) {
        $section = $item;
    }
}
$pageName = trim(explode(' · ', $title)[0]);
$crumbs = [];
if ($section !== null && $pageName !== $section['label']) {
    $crumbs[] = ['label' => $section['label'], 'href' => $section['href']];
}
$crumbs[] = ['label' => $pageName !== '' ? $pageName : 'Nexus', 'href' => null];
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
  <p class="sidebar__system"><?= e(config('app.system_name')) ?></p>

  <nav class="nav" aria-label="Sections">
    <?php foreach ($mainNav as $item): ?>
      <a class="nav__link" href="<?= e($item['href']) ?>"<?= is_active($item['href']) ? ' aria-current="page"' : '' ?>>
        <?= icon($item['icon']) ?><span><?= e($item['label']) ?></span>
      </a>
    <?php endforeach ?>
  </nav>

  <div class="sidebar__footer">
    <p class="sidebar__user-meta">Signed in as <strong><?= e($name) ?></strong></p>
    <form method="post" action="/logout">
      <?= csrf_field() ?>
      <button type="submit" class="nav__link nav__link--button"><?= icon('log-out') ?><span>Sign out</span></button>
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
  <div class="pagebar">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <ol>
        <?php foreach ($crumbs as $i => $crumb): ?>
          <li>
            <?php if ($i > 0): ?><?= icon('chevron-right', 'icon--sm breadcrumb__sep') ?><?php endif ?>
            <?php if ($crumb['href'] !== null): ?>
              <a href="<?= e($crumb['href']) ?>"><?= e($crumb['label']) ?></a>
            <?php else: ?>
              <span aria-current="page"><?= e($crumb['label']) ?></span>
            <?php endif ?>
          </li>
        <?php endforeach ?>
      </ol>
    </nav>
    <a class="icon-btn" href="<?= e(request_uri()) ?>" title="Refresh"><?= icon('refresh-cw', '', 'Refresh this page') ?></a>
  </div>
  <div class="main__inner">
    <?= partial('partials/flashes') ?>
    <?= $content ?>
  </div>
  <footer class="app-footer"><?= trim(partial('partials/copyright')) ?></footer>
</main>

<nav class="bottom-nav" aria-label="Sections">
  <?php foreach ($mainNav as $item): ?>
    <a class="bottom-nav__link" href="<?= e($item['href']) ?>"<?= is_active($item['href']) ? ' aria-current="page"' : '' ?>>
      <?= icon($item['icon']) ?><span class="bottom-nav__label"><?= e($item['label'] === 'Chromebooks' ? 'Chromebook' : $item['label']) ?></span>
    </a>
  <?php endforeach ?>
</nav>

<div class="toast-region" id="toasts" role="status" aria-live="polite"></div>
<?= partial('partials/confirm-dialog') ?>
</body>
</html>

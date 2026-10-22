<?php $tab ??= 'content'; ?>
<nav class="signage-tabs" aria-label="Signage sections">
  <?php foreach (['content' => ['Content', '/signage'], 'playlists' => ['Playlists', '/signage/playlists'], 'screens' => ['Screens', '/signage/screens']] as $key => [$label, $href]): ?>
    <a class="signage-tabs__link<?= $tab === $key ? ' is-active' : '' ?>" href="<?= e($href) ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach ?>
</nav>

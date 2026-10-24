<?php $tab ??= 'content'; ?>
<nav class="signage-tabs" aria-label="Signage sections">
  <?php foreach (['screens' => ['Screens', '/signage'], 'content' => ['Content', '/signage/content'], 'playlists' => ['Playlists', '/signage/playlists'], 'schedules' => ['Schedules', '/signage/schedules']] as $key => [$label, $href]): ?>
    <a class="signage-tabs__link<?= $tab === $key ? ' is-active' : '' ?>" href="<?= e($href) ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach ?>
</nav>

<?php
use App\Modules\Bell\BellRepository;

$tab ??= 'zone-0';
$zones = (new BellRepository())->zones();
?>
<nav class="signage-tabs bell-tabs" aria-label="Bell sections">
  <?php foreach ($zones as $zone): $key = 'zone-' . $zone['id']; ?>
    <a class="signage-tabs__link bell-zone bell-zone--<?= e($zone['color']) ?><?= $tab === $key ? ' is-active' : '' ?>" href="/bell/zones/<?= (int) $zone['id'] ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>><span class="bell-dot" aria-hidden="true"></span><?= e($zone['name']) ?></a>
  <?php endforeach ?>
  <a class="signage-tabs__link<?= $tab === 'sounds' ? ' is-active' : '' ?>" href="/bell/sounds"<?= $tab === 'sounds' ? ' aria-current="page"' : '' ?>>Sounds</a>
  <a class="signage-tabs__link<?= $tab === 'pc' ? ' is-active' : '' ?>" href="/bell/pc"<?= $tab === 'pc' ? ' aria-current="page"' : '' ?>>Bell PC</a>
</nav>

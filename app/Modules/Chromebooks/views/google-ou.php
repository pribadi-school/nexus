<a class="back-link" href="/chromebooks/<?= (int) $chromebook['id'] ?>#google-admin"><?= icon('chevron-left', 'icon--sm') ?>Chromebook</a>
<header class="page-header"><div><h1 class="page-title">Change organizational unit</h1><p class="page-subtitle">Serial number: <?= e($chromebook['serial_number']) ?></p></div></header>
<section class="card form-card"><div class="card__body">
  <?php if (!empty($googleCache['report']['ou'])): ?>
    <p>Current OU: <strong><?= e($googleCache['report']['ou']) ?></strong></p>
    <?= partial('Chromebooks::_google-ou-form', ['chromebook' => $chromebook, 'currentOu' => $googleCache['report']['ou'], 'units' => $units]) ?>
  <?php else: ?>
    <p>Refresh the Chromebook’s Google details before moving it.</p>
  <?php endif ?>
</div></section>

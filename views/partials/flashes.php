<?php
// One-time messages set with Session::flash() on the previous request.
$flashes = \App\Core\Session::takeFlashes();
if ($flashes === []) {
    return;
}
$icons = ['success' => 'circle-check', 'error' => 'circle-alert', 'info' => 'info', 'warning' => 'triangle-alert'];
?>
<div class="flashes">
  <?php foreach ($flashes as $flash): $type = isset($icons[$flash['type']]) ? $flash['type'] : 'info'; ?>
    <div class="notice notice--<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>" data-flash>
      <?= icon($icons[$type]) ?>
      <div><?= e($flash['message']) ?></div>
      <button type="button" class="icon-btn notice__close" data-dismiss aria-label="Dismiss message"><?= icon('x', 'icon--sm') ?></button>
    </div>
  <?php endforeach ?>
</div>

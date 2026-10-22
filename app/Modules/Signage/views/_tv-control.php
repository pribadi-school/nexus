<?php
use App\Modules\Signage\SignageTvControl;

$control = new SignageTvControl();
$helper = $control->helperFor((int) $screen['id']);
$commands = $control->recent((int) $screen['id']);
$busy = array_filter($commands, static fn (array $c): bool => in_array($c['status'], ['queued', 'running'], true)) !== [];
$base = '/signage/screens/' . (int) $screen['id'] . '/tv-control';
$labels = ['queued' => ['Waiting for helper', 'quiet'], 'running' => ['Running…', 'action'], 'done' => ['Done', 'success'], 'failed' => ['Failed', 'problem'], 'expired' => ['Not collected', 'warning']];
$icons = ['open_nexus' => 'tv-minimal', 'status' => 'info', 'screenshot' => 'search', 'restart' => 'rotate-ccw'];
$shot = null;
foreach ($commands as $command) {
    if ($command['screenshot_filename'] !== null) { $shot = $command; break; }
}
?>
<section class="card signage-tv-control" id="tv-control" aria-labelledby="tv-control-title" data-tv-control="<?= e($base) ?>"<?= $busy ? ' data-tv-control-busy' : '' ?>>
  <div class="signage-section-head">
    <h2 class="card__title" id="tv-control-title">TV control</h2>
    <span class="chip chip--<?= $helper !== null ? 'success' : 'warning' ?>"><?= $helper !== null ? 'Helper PC online' : 'Helper PC offline' ?></span>
  </div>
  <p class="signage-help">Runs on the TV through the always-on school PC (USB debugging). Use <strong>Open Nexus</strong> when someone pressed Home.</p>
  <?php if ($helper === null): ?><p class="signage-row__error">The helper PC is off or does not list this screen. Actions wait up to 10 minutes for it.</p><?php endif ?>
  <div class="signage-tv-control__actions">
    <?php foreach (SignageTvControl::ACTIONS as $action => $label): ?>
      <form method="post" action="<?= e($base) ?>" data-tv-action<?= $action === 'restart' ? ' data-confirm="Restart this TV?" data-confirm-text="Playback stops for about two minutes while the TV restarts." data-confirm-button="Restart TV"' : '' ?>>
        <?= csrf_field() ?><input type="hidden" name="action" value="<?= e($action) ?>">
        <button class="btn btn--secondary btn--sm" type="submit"><?= icon($icons[$action], 'icon--sm') ?><?= e($label) ?></button>
      </form>
    <?php endforeach ?>
  </div>
  <?php if ($commands !== []): ?>
    <ul class="signage-tv-control__log">
      <?php foreach ($commands as $command): [$state, $tone] = $labels[$command['status']] ?? [$command['status'], 'quiet']; ?>
        <li>
          <span class="signage-tv-control__what"><strong><?= e(SignageTvControl::ACTIONS[$command['action']] ?? $command['action']) ?></strong> <span class="chip chip--<?= e($tone) ?>"><?= e($state) ?></span></span>
          <span class="signage-row__sub"><?= e(time_ago($command['created_at'])) ?><?= $command['requested_by_name'] ? ' · ' . e($command['requested_by_name']) : '' ?><?= $command['result'] ? ' · ' . e($command['result']) : '' ?></span>
        </li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>
  <?php if ($shot !== null): ?>
    <figure class="signage-tv-control__shot">
      <img src="<?= e($base . '/' . (int) $shot['id'] . '/screenshot') ?>" alt="Screenshot of <?= e($screen['name']) ?>" loading="lazy">
      <figcaption class="signage-row__sub">Screenshot · <?= e(time_ago($shot['finished_at'] ?? $shot['created_at'])) ?></figcaption>
    </figure>
  <?php endif ?>
</section>

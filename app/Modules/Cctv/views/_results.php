<?php
/**
 * The cameras for the current filters: spot, type and SD card. Also
 * returned on its own to filters.js when filters change.
 *
 * @var \App\Modules\Cctv\CctvCategory $category
 * @var list<array<string, mixed>> $cameras
 * @var array{total:int, ok:int, problem:int} $counts
 * @var bool $filtered
 */
use App\Devices\Status;

$sd = static fn (array $camera): string => $camera['sd_card_gb'] !== null ? (int) $camera['sd_card_gb'] . ' GB' : '–';
?>
<?php if ($counts['total'] === 0): ?>
  <section class="card">
    <?= partial('partials/empty-state', [
        'icon' => 'cctv',
        'title' => 'No cameras yet',
        'text' => 'Add the cameras one by one: spot, type and SD card size.',
        'actionHref' => '/cctv/new',
        'actionLabel' => 'Add the first camera',
    ]) ?>
  </section>
<?php elseif ($cameras === []): ?>
  <section class="card">
    <div class="empty-state">
      <?= icon('search', 'icon--xl') ?>
      <p class="empty-state__title">No cameras match</p>
      <p class="empty-state__text">Try another location, type or status, or check the spelling in the search.</p>
      <div class="empty-state__actions"><a class="btn btn--secondary" href="/cctv" data-clear-filters>Clear filters</a></div>
    </div>
  </section>
<?php else: ?>
  <p class="results__summary">
    <?php if ($filtered): ?>
      Showing <?= count($cameras) ?> of <?= e($category->count($counts['total'])) ?> · <a href="/cctv" data-clear-filters>Clear filters</a>
    <?php else: ?>
      <?= e($category->count($counts['total'])) ?>
    <?php endif ?>
  </p>

  <?php /* Desktop: table */ ?>
  <div class="card table-card">
    <table class="data-table">
      <thead>
        <tr>
          <th scope="col">Spot</th>
          <th scope="col">Location</th>
          <th scope="col">Type</th>
          <th scope="col">SD card</th>
          <th scope="col">Latest note</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($cameras as $camera): $problem = $camera['status'] === Status::PROBLEM; ?>
          <tr class="<?= $problem ? 'is-problem' : '' ?>" data-device-row="<?= (int) $camera['id'] ?>">
            <th scope="row">
              <a class="data-table__link" href="/cctv/<?= (int) $camera['id'] ?>"><?= e($camera['label']) ?></a>
              <?php if ($problem): ?><?= partial('partials/problem-flag', ['category' => $category]) ?><?php endif ?>
            </th>
            <td><?= e($camera['location_name'] ?? 'Not assigned') ?></td>
            <td><?= e($camera['model'] ?? '–') ?></td>
            <td class="num nowrap"><?= $sd($camera) ?></td>
            <td class="data-table__note"><?= partial('partials/latest-note', ['device' => $camera, 'href' => '/cctv/' . (int) $camera['id']]) ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>

  <?php /* Phones: cards */ ?>
  <ul class="device-cards">
    <?php foreach ($cameras as $camera): $problem = $camera['status'] === Status::PROBLEM; ?>
      <li class="device-card<?= $problem ? ' is-problem' : '' ?>" data-device-row="<?= (int) $camera['id'] ?>">
        <a class="device-card__main" href="/cctv/<?= (int) $camera['id'] ?>">
          <span class="device-card__title"><?= e($camera['label']) ?></span>
          <span class="device-card__meta"><?= e($camera['location_name'] ?? 'Not assigned') ?></span>
          <span class="device-card__meta"><?= e(implode(' · ', array_filter([$camera['model'], $camera['sd_card_gb'] !== null ? $sd($camera) . ' SD card' : null]))) ?></span>
          <?php if ($problem): ?><?= partial('partials/problem-flag', ['category' => $category]) ?><?php endif ?>
          <span class="device-card__note-label">Latest note</span>
          <?= partial('partials/latest-note', ['device' => $camera]) ?>
        </a>
        <?= icon('chevron-right', 'device-card__chevron') ?>
      </li>
    <?php endforeach ?>
  </ul>
<?php endif ?>

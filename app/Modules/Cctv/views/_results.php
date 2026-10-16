<?php
/**
 * The cameras for the current filters, under one heading per area. Also
 * returned on its own to filters.js when filters change.
 *
 * @var \App\Modules\Cctv\CctvCategory $category
 * @var list<array<string, mixed>> $cameras
 * @var list<array{area:string, cameras:list<array<string, mixed>>}> $groups
 * @var array{total:int, ok:int, problem:int} $counts
 * @var bool $filtered
 */
use App\Devices\Status;

$return = request_uri();
$checkForm = static fn (array $camera): string =>
    '<form class="check-form" method="post" action="/cctv/' . (int) $camera['id'] . '/checked">'
    . csrf_field()
    . '<input type="hidden" name="return" value="' . e($return) . '">'
    . '<button type="submit" class="btn btn--ghost btn--sm" aria-label="Mark ' . e($camera['label']) . ' as checked" title="Mark as checked">'
    . icon('check', 'icon--sm') . '<span class="check-form__text">Checked</span></button></form>';
$lastChecked = static fn (array $camera): string => $camera['last_checked_at']
    ? '<time datetime="' . e(iso_time($camera['last_checked_at'])) . '" title="' . e(format_datetime($camera['last_checked_at'])) . '">' . e(time_ago($camera['last_checked_at'])) . '</time>'
    : 'Never';
?>
<?php if ($counts['total'] === 0): ?>
  <section class="card">
    <?= partial('partials/empty-state', [
        'icon' => 'cctv',
        'title' => 'No cameras yet',
        'text' => 'Add the cameras one by one: area, label, exact location, model and IP. Start by adding the areas they are in.',
        'actionHref' => '/cctv/new',
        'actionLabel' => 'Add the first camera',
    ]) ?>
  </section>
<?php elseif ($cameras === []): ?>
  <section class="card">
    <div class="empty-state">
      <?= icon('search', 'icon--xl') ?>
      <p class="empty-state__title">No cameras match</p>
      <p class="empty-state__text">Try another area or status, or check the spelling in the search.</p>
      <div class="empty-state__actions"><a class="btn btn--secondary" href="/cctv" data-clear-filters>Clear filters</a></div>
    </div>
  </section>
<?php else: ?>
  <p class="results__summary">
    <?php if ($filtered): ?>
      Showing <?= count($cameras) ?> of <?= e($category->count($counts['total'])) ?> · <a href="/cctv" data-clear-filters>Clear filters</a>
    <?php else: ?>
      <?= e($category->count($counts['total'])) ?> in <?= e(plural(count($groups), 'area', 'areas')) ?>
    <?php endif ?>
  </p>

  <?php foreach ($groups as $i => $group): $headingId = 'area-' . $i; ?>
    <section class="area-group" aria-labelledby="<?= $headingId ?>">
      <h2 class="area-group__title" id="<?= $headingId ?>">
        <?= e($group['area']) ?> <span class="area-group__count"><?= count($group['cameras']) ?></span>
      </h2>

      <?php /* Desktop: table */ ?>
      <div class="card table-card">
        <table class="data-table cctv-table">
          <thead>
            <tr>
              <th scope="col">Camera</th>
              <th scope="col">Exact location</th>
              <th scope="col">Model</th>
              <th scope="col">IP address</th>
              <th scope="col">SD card</th>
              <th scope="col">Status</th>
              <th scope="col">Last checked</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($group['cameras'] as $camera): $problem = $camera['status'] === Status::PROBLEM; ?>
              <tr class="<?= $problem ? 'is-problem' : '' ?>" data-device-row="<?= (int) $camera['id'] ?>">
                <th scope="row"><a class="data-table__link" href="/cctv/<?= (int) $camera['id'] ?>"><?= e($camera['label']) ?></a></th>
                <td><?= e($camera['location'] ?? '–') ?></td>
                <td><?= e($camera['model'] ?? '–') ?></td>
                <td class="num"><?= e($camera['ip_address'] ?? '–') ?></td>
                <td class="num"><?= $camera['sd_card_gb'] !== null ? (int) $camera['sd_card_gb'] . ' GB' : '–' ?></td>
                <td><?= partial('partials/status-toggle', ['category' => $category, 'device' => $camera, 'title' => $camera['label'], 'return' => $return]) ?></td>
                <td class="data-table__muted"><div class="checked-cell"><?= $lastChecked($camera) ?><?= $checkForm($camera) ?></div></td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>

      <?php /* Phones: cards */ ?>
      <ul class="device-cards">
        <?php foreach ($group['cameras'] as $camera): $problem = $camera['status'] === Status::PROBLEM; ?>
          <li class="device-card<?= $problem ? ' is-problem' : '' ?>" data-device-row="<?= (int) $camera['id'] ?>">
            <a class="device-card__main" href="/cctv/<?= (int) $camera['id'] ?>">
              <span class="device-card__title"><?= e($camera['label']) ?></span>
              <?php if ($camera['location']): ?><span class="device-card__meta"><?= e($camera['location']) ?></span><?php endif ?>
              <span class="device-card__meta">
                <?= e(implode(' · ', array_filter([$camera['ip_address'], $camera['model']]))) ?: 'No IP or model yet' ?>
              </span>
              <span class="device-card__meta">Checked <?= $camera['last_checked_at'] ? e(time_ago($camera['last_checked_at'])) : 'never' ?></span>
            </a>
            <?= partial('partials/status-toggle', ['category' => $category, 'device' => $camera, 'title' => $camera['label'], 'return' => $return]) ?>
          </li>
        <?php endforeach ?>
      </ul>
    </section>
  <?php endforeach ?>
<?php endif ?>

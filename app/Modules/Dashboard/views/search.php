<?php
/**
 * Results of the dashboard search, across every category.
 *
 * @var string $query
 * @var list<array<string, mixed>> $results  device_id, category, status, title, context, url
 */
use App\Devices\Status;

$byCategory = [];
foreach ($results as $row) {
    $byCategory[$row['category']][] = $row;
}
?>
<header class="page-header">
  <div>
    <h1 class="page-title">Search</h1>
    <p class="page-subtitle">Rooms, camera spots, asset IDs, serial numbers, students and emails.</p>
  </div>
</header>

<form class="dash-search" method="get" action="/search" role="search">
  <div class="filters__search">
    <?= icon('search', 'icon--sm') ?>
    <label class="visually-hidden" for="search-q">Search all devices</label>
    <input class="input" id="search-q" type="search" name="q" value="<?= e($query) ?>" autocomplete="off" enterkeyhint="search"
           placeholder="Search a room, camera spot, asset ID or student…"<?= $query === '' ? ' autofocus' : '' ?>>
  </div>
  <button type="submit" class="btn btn--secondary">Search<?= icon('arrow-right', 'icon--sm') ?></button>
</form>

<?php if ($query === ''): ?>
  <section class="card">
    <?= partial('partials/empty-state', ['icon' => 'search', 'title' => 'Find any device', 'text' => 'Type part of a room name, camera spot, asset ID, serial number or student name.', 'compact' => true]) ?>
  </section>
<?php elseif ($results === []): ?>
  <section class="card">
    <?= partial('partials/empty-state', ['icon' => 'search', 'title' => 'No devices match "' . $query . '"', 'text' => 'Check the spelling, or search a shorter part of the name.', 'compact' => true]) ?>
  </section>
<?php else: ?>
  <p class="results__summary"><?= e(plural(count($results), 'device')) ?> found</p>
  <div class="stack">
    <?php foreach ($byCategory as $key => $rows): $category = categories()->find((string) $key); ?>
      <section class="card" aria-label="<?= e($category?->name() ?? $key) ?>">
        <div class="card__header">
          <h2 class="card__title"><?= e($category?->name() ?? $key) ?></h2>
          <span class="chip chip--quiet num"><?= count($rows) ?></span>
        </div>
        <ul class="search-results">
          <?php foreach ($rows as $row): $problem = $row['status'] === Status::PROBLEM; ?>
            <li class="search-result<?= $problem ? ' is-problem' : '' ?>">
              <a href="<?= e($row['url']) ?>">
                <span class="search-result__text">
                  <span class="search-result__title"><?= e($row['title']) ?></span>
                  <span class="search-result__context"><?= e($row['context']) ?></span>
                  <?php if ($problem && $category !== null): ?><?= partial('partials/problem-flag', ['category' => $category]) ?><?php endif ?>
                </span>
                <?= icon('chevron-right', 'icon--sm device-card__chevron') ?>
              </a>
            </li>
          <?php endforeach ?>
        </ul>
      </section>
    <?php endforeach ?>
  </div>
<?php endif ?>

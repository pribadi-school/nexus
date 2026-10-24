<?php
/**
 * One area's schedule, laid out like a classic alarm-clock list: a switch,
 * a big time, what and when, and the row's own buttons.
 *
 * @var array<string,mixed> $zone
 * @var list<array<string,mixed>> $profiles
 * @var array<string,mixed>|null $selected
 * @var list<array<string,mixed>> $others
 * @var list<array<string,mixed>> $entries
 * @var array<string,string> $soundNames
 */
use App\Modules\Bell\BellData;

$zoneId = (int) $zone['id'];
$isActive = $selected !== null && (int) $selected['id'] === (int) $zone['active_profile_id'];
$other = $others[0] ?? null;
?>
<header class="page-header">
  <div><h1 class="page-title">Bell</h1><p class="page-subtitle">Two areas, two separate schedules. Changes reach the bell PC within about 30 seconds.</p></div>
  <?php if ($selected !== null): ?><div class="page-actions"><a class="btn btn--primary" href="/bell/profiles/<?= (int) $selected['id'] ?>/entries/create"><?= icon('plus', 'icon--sm') ?>Add bell</a></div><?php endif ?>
</header>
<?= partial('Bell::_tabs', ['tab' => $tab]) ?>

<section class="card bell-card bell-panel bell-zone bell-zone--<?= e($zone['color']) ?>" data-bell-panel>
  <div class="bell-panel__head">
    <div class="bell-panel__title">
      <span class="bell-dot" aria-hidden="true"></span>
      <h2 class="card__title"><?= e($zone['name']) ?></h2>
      <span class="chip chip--quiet"><?= icon('volume-2', 'icon--sm') ?>Volume <?= (int) $zone['volume'] ?>%</span>
    </div>
    <div class="bell-panel__actions">
      <?php if ($selected !== null): ?>
        <a class="btn btn--secondary btn--sm" href="/bell/profiles/<?= (int) $selected['id'] ?>/export.csv"><?= icon('download', 'icon--sm') ?>Export</a>
        <details class="bell-import">
          <summary class="btn btn--secondary btn--sm"><?= icon('upload', 'icon--sm') ?>Import</summary>
          <form class="bell-import__form" method="post" action="/bell/profiles/<?= (int) $selected['id'] ?>/import" enctype="multipart/form-data" data-busy-on-submit>
            <?= csrf_field() ?>
            <p class="field__hint">Columns: <?= e(implode(' · ', BellData::CSV_HEADER)) ?>. Excel files saved as CSV work. Nothing is imported if any line has a mistake.</p>
            <input class="input" type="file" name="file" accept=".csv,text/csv" required>
            <label class="checkbox"><input type="checkbox" name="replace" value="1"><span>Replace this profile's current bells</span></label>
            <button class="btn btn--primary btn--sm" type="submit">Import</button>
          </form>
        </details>
      <?php endif ?>
      <a class="btn btn--secondary btn--sm" href="/bell/zones/<?= $zoneId ?>/settings"><?= icon('settings', 'icon--sm') ?><span class="bell-label-long">Area settings</span><span class="bell-label-short">Settings</span></a>
    </div>
  </div>
  <div class="bell-clock" aria-live="off">
    <span class="bell-clock__time" data-bell-clock>--:--:--</span>
    <span class="bell-clock__next" data-bell-next>Next bell: -</span>
  </div>

  <?php if ($selected === null): ?>
    <?= partial('partials/empty-state', ['icon' => 'bell', 'title' => 'No profile', 'text' => 'Add a profile in Area settings first.', 'compact' => true]) ?>
  <?php else: ?>
  <div class="bell-profiles">
    <nav class="bell-profile-list" aria-label="Profiles of <?= e($zone['name']) ?>">
      <?php foreach ($profiles as $profile): $on = (int) $profile['id'] === (int) $zone['active_profile_id']; $cur = (int) $profile['id'] === (int) $selected['id']; ?>
        <a class="bell-profile<?= $cur ? ' is-current' : '' ?>" href="/bell/zones/<?= $zoneId ?>?profile=<?= (int) $profile['id'] ?>"<?= $cur ? ' aria-current="page"' : '' ?>>
          <?= e($profile['name']) ?><?php if ($on): ?><span class="chip chip--success">In use</span><?php endif ?>
        </a>
      <?php endforeach ?>
    </nav>
    <?php if (!$isActive): ?>
      <form method="post" action="/bell/zones/<?= $zoneId ?>/active-profile" data-busy-on-submit
            data-confirm="Use the &quot;<?= e($selected['name']) ?>&quot; profile on <?= e($zone['name']) ?>?" data-confirm-text="The <?= e($zone['name']) ?> bells will follow this profile from now on. The other area is not changed." data-confirm-button="Use this profile">
        <?= csrf_field() ?><input type="hidden" name="profile_id" value="<?= (int) $selected['id'] ?>">
        <button class="btn btn--primary btn--sm" type="submit"><?= icon('check', 'icon--sm') ?>Use this profile now</button>
      </form>
    <?php endif ?>
  </div>

  <?php if ($entries === []): ?>
    <?= partial('partials/empty-state', [
        'icon' => 'bell', 'title' => 'No bells in this profile',
        'text' => 'Add the first bell, for example 07:00:00 on Monday to Friday.',
        'actionHref' => '/bell/profiles/' . (int) $selected['id'] . '/entries/create', 'actionLabel' => 'Add bell', 'compact' => true,
    ]) ?>
  <?php else: ?>
    <ul class="bell-list" aria-label="Bells of <?= e($zone['name']) ?>, profile <?= e($selected['name']) ?>">
      <?php foreach ($entries as $entry): $days = BellData::daysList((string) $entry['days']); $sound = $soundNames[$entry['sound_ref']] ?? 'Missing sound'; $href = '/bell/entries/' . (int) $entry['id']; ?>
        <li class="bell-row<?= $entry['enabled'] ? '' : ' is-off' ?>"<?= $isActive ? ' data-bell-row data-time="' . e($entry['ring_time']) . '" data-days="' . e($entry['days']) . '" data-enabled="' . (int) $entry['enabled'] . '" data-label="' . e($entry['label']) . '"' : '' ?>>
          <form method="post" action="<?= $href ?>/toggle" class="bell-row__switch">
            <?= csrf_field() ?>
            <button class="bell-switch" type="submit" role="switch" aria-checked="<?= $entry['enabled'] ? 'true' : 'false' ?>" aria-label="<?= $entry['enabled'] ? 'Turn off' : 'Turn on' ?> the <?= e(substr((string) $entry['ring_time'], 0, 8)) ?> bell"><span></span></button>
          </form>
          <div class="bell-row__time num"><?= e(substr((string) $entry['ring_time'], 0, 8)) ?></div>
          <div class="bell-row__body">
            <p class="bell-row__label"><?= e($entry['label'] !== '' ? $entry['label'] : 'Bell') ?>
              <?php if ($entry['both_floors']): ?><span class="chip chip--action">Both areas</span><?php endif ?>
            </p>
            <p class="bell-days" aria-label="Days: <?= e(implode(', ', array_map(static fn (int $d): string => BellData::DAYS[$d][0], $days))) ?>">
              <?php foreach (BellData::DAYS as $n => $names): ?><span class="bell-day<?= in_array($n, $days, true) ? ' is-on' : '' ?>"><?= e(substr($names[0], 0, 2)) ?></span><?php endforeach ?>
            </p>
            <p class="bell-row__sound"><?= icon('volume-2', 'icon--sm') ?><?= e($sound) ?></p>
          </div>
          <div class="bell-row__actions">
            <?php
            $ref = (string) $entry['sound_ref'];
            $src = str_starts_with($ref, 'builtin:') ? '/bell/sounds/builtin/' . substr($ref, 8) : '/bell/sounds/' . (int) substr($ref, 6) . '/file';
            ?>
            <button class="icon-btn" type="button" data-bell-preview="<?= e($src) ?>" title="Listen here in the browser" aria-label="Listen to the sound of the <?= e(substr((string) $entry['ring_time'], 0, 8)) ?> bell"><?= icon('play') ?></button>
            <a class="icon-btn" href="<?= $href ?>/edit" title="Edit" aria-label="Edit the <?= e(substr((string) $entry['ring_time'], 0, 8)) ?> bell"><?= icon('pencil') ?></a>
            <?php if ($other !== null): ?>
              <form method="post" action="<?= $href ?>/copy" data-busy-on-submit>
                <?= csrf_field() ?><input type="hidden" name="zone_id" value="<?= (int) $other['id'] ?>">
                <button class="icon-btn" type="submit" title="Copy to <?= e($other['name']) ?>" aria-label="Copy to <?= e($other['name']) ?>"><?= icon('copy') ?></button>
              </form>
            <?php endif ?>
            <form method="post" action="<?= $href ?>/delete" data-confirm="Delete this bell?" data-confirm-text="It is removed from this profile only." data-confirm-button="Delete" data-confirm-tone="danger">
              <?= csrf_field() ?>
              <button class="icon-btn" type="submit" title="Delete" aria-label="Delete the <?= e(substr((string) $entry['ring_time'], 0, 8)) ?> bell"><?= icon('trash-2') ?></button>
            </form>
          </div>
        </li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>
  <?php endif ?>
</section>

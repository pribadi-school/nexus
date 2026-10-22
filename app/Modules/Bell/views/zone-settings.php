<?php
/**
 * @var array<string,mixed> $zone
 * @var list<array<string,mixed>> $profiles
 * @var list<array<string,mixed>> $holidays
 * @var list<array<string,mixed>> $others
 */
use App\Modules\Bell\BellData;

$zoneId = (int) $zone['id'];
?>
<a class="back-link" href="/bell/zones/<?= $zoneId ?>"><?= icon('chevron-left', 'icon--sm') ?><?= e($zone['name']) ?></a>
<header class="page-header"><div><h1 class="page-title"><?= e($zone['name']) ?> settings</h1><p class="page-subtitle">Name, volume, profiles and holidays of this floor only.</p></div></header>
<?= partial('Bell::_tabs', ['tab' => 'zone-' . $zoneId]) ?>

<section class="card form-card bell-zone bell-zone--<?= e($zone['color']) ?>">
  <h2 class="card__title">Floor</h2>
  <form method="post" action="/bell/zones/<?= $zoneId ?>/settings" data-busy-on-submit>
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field"><label class="field__label" for="name">Name</label><input class="input" id="name" name="name" maxlength="60" value="<?= e($zone['name']) ?>" required></div>
      <div class="field"><label class="field__label" for="color">Colour</label>
        <select class="select" id="color" name="color"><?php foreach (BellData::COLORS as $key => $label): ?><option value="<?= e($key) ?>"<?= $zone['color'] === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select></div>
      <div class="field field--full"><label class="field__label" for="volume">Volume (0–100)</label>
        <input class="input input--short num" id="volume" name="volume" type="number" min="0" max="100" step="1" value="<?= (int) $zone['volume'] ?>" required>
        <p class="field__hint">How loud this floor's bells are. Also set the real loudness on the mixer.</p></div>
    </div>
    <div class="form-actions"><button class="btn btn--primary" type="submit">Save floor</button></div>
  </form>
</section>

<section class="card form-card" id="profiles">
  <h2 class="card__title">Profiles</h2>
  <p class="signage-help">A profile is a full day plan, such as Normal, Jumat, Ujian or Pendek. Only the one marked "In use" rings. Profiles of this floor never change the other floor.</p>
  <ul class="bell-settings-list">
    <?php foreach ($profiles as $profile): $on = (int) $profile['id'] === (int) $zone['active_profile_id']; ?>
      <li class="bell-settings-row">
        <form class="bell-settings-row__rename" method="post" action="/bell/profiles/<?= (int) $profile['id'] ?>" data-busy-on-submit>
          <?= csrf_field() ?>
          <label class="visually-hidden" for="profile-<?= (int) $profile['id'] ?>">Profile name</label>
          <input class="input" id="profile-<?= (int) $profile['id'] ?>" name="name" maxlength="60" value="<?= e($profile['name']) ?>" required>
          <button class="btn btn--secondary btn--sm" type="submit">Rename</button>
        </form>
        <span class="bell-settings-row__meta"><?= plural((int) $profile['entry_count'], 'bell') ?><?= $on ? ' · <strong>In use</strong>' : '' ?></span>
        <div class="bell-settings-row__actions">
          <a class="btn btn--secondary btn--sm" href="/bell/zones/<?= $zoneId ?>?profile=<?= (int) $profile['id'] ?>">Open</a>
          <form method="post" action="/bell/profiles/<?= (int) $profile['id'] ?>/copy" data-busy-on-submit><?= csrf_field() ?><input type="hidden" name="zone_id" value="<?= $zoneId ?>"><button class="btn btn--secondary btn--sm" type="submit"><?= icon('copy', 'icon--sm') ?>Duplicate</button></form>
          <?php foreach ($others as $other): ?>
            <form method="post" action="/bell/profiles/<?= (int) $profile['id'] ?>/copy" data-busy-on-submit><?= csrf_field() ?><input type="hidden" name="zone_id" value="<?= (int) $other['id'] ?>"><button class="btn btn--secondary btn--sm" type="submit"><?= icon('copy', 'icon--sm') ?>Copy to <?= e($other['name']) ?></button></form>
          <?php endforeach ?>
          <?php if (count($profiles) > 1): ?>
            <form method="post" action="/bell/profiles/<?= (int) $profile['id'] ?>/delete" data-confirm="Delete the &quot;<?= e($profile['name']) ?>&quot; profile?" data-confirm-text="Its <?= plural((int) $profile['entry_count'], 'bell') ?> are deleted too<?= $on ? ', and another profile will be put in use' : '' ?>." data-confirm-button="Delete" data-confirm-tone="danger"><?= csrf_field() ?><button class="btn btn--secondary btn--sm" type="submit"><?= icon('trash-2', 'icon--sm') ?>Delete</button></form>
          <?php endif ?>
        </div>
      </li>
    <?php endforeach ?>
  </ul>
  <form class="bell-inline-form" method="post" action="/bell/zones/<?= $zoneId ?>/profiles" data-busy-on-submit>
    <?= csrf_field() ?>
    <label class="visually-hidden" for="new-profile">New profile name</label>
    <input class="input" id="new-profile" name="name" maxlength="60" placeholder="New profile, e.g. Pendek" required>
    <button class="btn btn--primary" type="submit"><?= icon('plus', 'icon--sm') ?>Add profile</button>
  </form>
</section>

<section class="card form-card" id="holidays">
  <h2 class="card__title">Holidays</h2>
  <p class="signage-help">No bells ring on this floor on these dates, whatever profile is in use.</p>
  <?php if ($holidays === []): ?><p class="notes-placeholder">No holidays added.</p><?php else: ?>
    <ul class="bell-settings-list">
      <?php foreach ($holidays as $holiday): ?>
        <li class="bell-settings-row">
          <span class="bell-settings-row__rename"><strong class="num"><?= e(date('D, j M Y', strtotime((string) $holiday['holiday_date']))) ?></strong> <?= e($holiday['label']) ?></span>
          <form method="post" action="/bell/holidays/<?= (int) $holiday['id'] ?>/delete" data-busy-on-submit><?= csrf_field() ?><button class="btn btn--secondary btn--sm" type="submit"><?= icon('trash-2', 'icon--sm') ?>Remove</button></form>
        </li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>
  <form class="bell-inline-form" method="post" action="/bell/zones/<?= $zoneId ?>/holidays" data-busy-on-submit>
    <?= csrf_field() ?>
    <label class="visually-hidden" for="holiday-date">Holiday date</label>
    <input class="input input--date" id="holiday-date" type="date" name="date" required>
    <label class="visually-hidden" for="holiday-label">Holiday name</label>
    <input class="input" id="holiday-label" name="label" maxlength="120" placeholder="Name, e.g. Hari Kemerdekaan">
    <button class="btn btn--primary" type="submit"><?= icon('calendar-x', 'icon--sm') ?>Add holiday</button>
  </form>
</section>

<section class="card form-card">
  <h2 class="card__title">Backup</h2>
  <p class="signage-help">One JSON file with both floors, all profiles, bells and holidays. The sound files are not inside it (only their names).</p>
  <div class="form-actions"><a class="btn btn--secondary" href="/bell/backup.json"><?= icon('download', 'icon--sm') ?>Download backup</a></div>
</section>

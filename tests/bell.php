<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Modules\Bell\BellData;
use App\Modules\Bell\BellRepository;

// Run: php -c .dev\php.ini tests\bell.php   (local database only; everything is rolled back)
if (config('app.env') !== 'local') { fwrite(STDERR, "Local database only.\n"); exit(1); }
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('Failed: ' . $message);
    $checks++;
};
$throws = static function (callable $fn): bool {
    try { $fn(); } catch (InvalidArgumentException) { return true; }
    return false;
};

// ---- Pure helpers ---------------------------------------------------------
$check(BellData::time('7:05') === '07:05:00' && BellData::time('07:05:09') === '07:05:09', 'times are normalised to HH:MM:SS');
$check(BellData::time('24:00') === null && BellData::time('12:60') === null && BellData::time('abc') === null && BellData::time('') === null, 'impossible times are refused');
$check(BellData::daysString(['5', 1, '3', 3, 9, 'x']) === '135', 'days are sorted, de-duplicated and limited to 1-7');
$check(BellData::daysString([]) === null, 'no days is invalid');
$check(BellData::parseDays('Sen-Jum') === '12345' && BellData::parseDays('Mon Tue') === '12' && BellData::parseDays('setiap hari') === '1234567', 'day text: range, English names, every day');
$check(BellData::parseDays('Sab-Sen') === '167' && BellData::parseDays('1,3,5') === '135' && BellData::parseDays('Sen;Rab') === '13', 'day text: wrap-around range, digits, separators');
$check(BellData::parseDays('Blursday') === null && BellData::parseDays('') === null, 'unknown day names are refused');
[$data, $errors] = BellData::validateEntry(['ring_time' => '07:00', 'days' => [1, 2], 'sound_ref' => 'builtin:denting', 'label' => ' Masuk ', 'enabled' => '1'], []);
$check($errors === [] && $data['ring_time'] === '07:00:00' && $data['days'] === '12' && $data['label'] === 'Masuk' && $data['enabled'] === 1 && $data['both_floors'] === 0, 'a valid row passes and is cleaned');
[, $errors] = BellData::validateEntry(['ring_time' => '99:00', 'days' => [], 'sound_ref' => 'sound:77'], [1, 2]);
$check(isset($errors['ring_time'], $errors['days'], $errors['sound_ref']), 'bad time, no days and an unknown sound are refused');
$check(BellData::validSound('sound:2', [1, 2]) && !BellData::validSound('sound:3', [1, 2]) && !BellData::validSound('builtin:nope', []) && !BellData::validSound('sound:x', [1]), 'sound references are checked');

$entries = [
    ['ring_time' => '07:00:00', 'days' => '12345', 'sound_ref' => 'builtin:bel-sekolah', 'label' => 'Masuk; "pagi"', 'enabled' => 1, 'both_floors' => 0],
    ['ring_time' => '09:30:00', 'days' => '1234567', 'sound_ref' => 'builtin:denting', 'label' => 'Istirahat', 'enabled' => 0, 'both_floors' => 1],
];
$names = ['builtin:bel-sekolah' => 'Bel sekolah', 'builtin:denting' => 'Denting'];
$csv = BellData::toCsv($entries, $names);
$check(str_starts_with($csv, "\xEF\xBB\xBF") && str_contains($csv, 'Waktu;Hari;"File suara";Label;Aktif;"Dua lantai"'), 'CSV has a byte-order mark and Indonesian semicolon columns (opens in Excel)');
[$rows, $problems] = BellData::fromCsv($csv, ['bel sekolah' => 'builtin:bel-sekolah', 'denting' => 'builtin:denting']);
$check($problems === [] && $rows === $entries, 'export then import gives the same rows (including quotes and semicolons in a label)');
[$rows, $problems] = BellData::fromCsv("Waktu,Hari,File suara,Label\n7:00,Sen-Jum,Denting,Masuk\n", ['denting' => 'builtin:denting']);
$check($problems === [] && $rows[0]['days'] === '12345' && $rows[0]['enabled'] === 1 && $rows[0]['both_floors'] === 0, 'comma CSVs and short rows work; blank Aktif means on');
[, $problems] = BellData::fromCsv("Waktu;Hari;File suara\n25:00;Sen;Denting\n07:00;Blursday;Denting\n07:00;Sen;Gong\n", ['denting' => 'builtin:denting']);
$check(count($problems) === 3 && str_contains($problems[0], 'Line 2') && str_contains($problems[2], 'Gong'), 'every bad line is reported with its line number');

// ---- Zones are independent (database, rolled back) ---------------------------
$bell = new BellRepository();
$pdo = db()->pdo();
$pdo->beginTransaction();
$failure = null;
try {
    $zones = $bell->zones();
    $check(count($zones) >= 2, 'the two floors exist (migration 017)');
    [$a, $b] = [(int) $zones[0]['id'], (int) $zones[1]['id']];
    $pa = $bell->profiles($a);
    $pb = $bell->profiles($b);
    $check(count($pa) >= 2 && count($pb) >= 2, 'every floor has its own profiles');
    $check(!array_intersect(array_column($pa, 'id'), array_column($pb, 'id')), 'profiles are not shared between floors');

    $bActive = (int) $bell->zone($b)['active_profile_id'];
    $other = null;
    foreach ($pa as $p) if ((int) $p['id'] !== (int) $bell->zone($a)['active_profile_id']) { $other = (int) $p['id']; break; }
    $bell->setActiveProfile($a, $other);
    $check((int) $bell->zone($a)['active_profile_id'] === $other && (int) $bell->zone($b)['active_profile_id'] === $bActive, 'switching floor A\'s profile never changes floor B');
    $check($throws(fn () => $bell->setActiveProfile($a, (int) $pb[0]['id'])), 'a floor cannot use the other floor\'s profile');

    $bell->updateZone($a, (string) $bell->zone($a)['name'], 'blue', 35);
    $check((int) $bell->zone($a)['volume'] === 35 && (int) $bell->zone($b)['volume'] === (int) $zones[1]['volume'], 'volume is per floor');
    $check($throws(fn () => $bell->updateZone($a, '', 'blue', 50)) && $throws(fn () => $bell->updateZone($a, 'X', 'pink', 50)) && $throws(fn () => $bell->updateZone($a, 'X', 'blue', 101)), 'zone settings are validated');
    $check($throws(fn () => $bell->updateZone($a, (string) $bell->zone($b)['name'], 'blue', 50)), 'two floors cannot share a name');

    $bell->addHoliday($a, '2031-08-17', 'Merdeka');
    $check(count($bell->holidays($a)) > count($bell->holidays($b)) && $bell->holidays($b) === array_values(array_filter($bell->holidays($b), static fn ($h) => $h['holiday_date'] !== '2031-08-17')), 'a holiday on floor A is not a holiday on floor B');
    $check($throws(fn () => $bell->addHoliday($a, '2031-08-17', '')) && $throws(fn () => $bell->addHoliday($a, '2031-02-31', '')), 'duplicate and impossible holidays are refused');

    // Entries and the sync payload.
    $row = static fn (string $time, array $o = []): array => $o + ['ring_time' => $time, 'days' => '12345', 'sound_ref' => 'builtin:bel-sekolah', 'label' => $time, 'enabled' => 1, 'both_floors' => 0];
    $activeA = (int) $bell->zone($a)['active_profile_id'];
    $activeB = (int) $bell->zone($b)['active_profile_id'];
    $own = $bell->saveEntry(null, $activeA, $row('07:00:00'));
    $wide = $bell->saveEntry(null, $activeA, $row('09:30:00', ['both_floors' => 1]));
    $off = $bell->saveEntry(null, $activeA, $row('10:00:00', ['both_floors' => 1, 'enabled' => 0]));
    $mine = $bell->saveEntry(null, $activeB, $row('08:00:00'));
    $inactive = $bell->saveEntry(null, (int) $pa[0]['id'] === $activeA ? (int) $pa[1]['id'] : (int) $pa[0]['id'], $row('11:00:00', ['both_floors' => 1]));

    $payload = $bell->payload();
    $zoneOf = static fn (array $payload, int $id): array => array_values(array_filter($payload['zones'], static fn ($z) => $z['id'] === $id))[0];
    $idsA = array_column($zoneOf($payload, $a)['entries'], 'id');
    $idsB = array_column($zoneOf($payload, $b)['entries'], 'id');
    $check(in_array($own, $idsA, true) && in_array($wide, $idsA, true) && in_array($off, $idsA, true), 'floor A receives its own rows (even switched-off ones)');
    $check(!in_array($own, $idsB, true) && !in_array($off, $idsB, true), 'floor B does not receive floor A\'s ordinary or switched-off rows');
    $check(in_array($wide, $idsB, true) && in_array($mine, $idsB, true), 'floor B receives its own row and floor A\'s school-wide row');
    $shared = array_values(array_filter($zoneOf($payload, $b)['entries'], static fn ($e) => $e['id'] === $wide))[0];
    $check($shared['shared_from'] === $zoneOf($payload, $a)['name'], 'a school-wide row says which floor it comes from');
    $check(!in_array($inactive, $idsA, true) && !in_array($inactive, $idsB, true), 'rows of a profile that is not in use are not sent');
    $check($zoneOf($payload, $a)['volume'] === 35 && in_array('2031-08-17', array_column($zoneOf($payload, $a)['holidays'], 'date'), true), 'volume and holidays travel with their own floor');

    $v1 = BellRepository::version($payload);
    $check($v1 === BellRepository::version($bell->payload()), 'the version is stable while nothing changes');
    $bell->setEntryEnabled($own, false);
    $check($v1 !== BellRepository::version($bell->payload()), 'the version changes when a row changes');

    // Copy between floors.
    $bell->copyEntry($own, $b);
    $copy = array_values(array_filter($bell->entries($activeB), static fn ($e) => $e['ring_time'] === '07:00:00'));
    $check(count($copy) === 1 && (int) $copy[0]['both_floors'] === 0 && (int) $copy[0]['zone_id'] === $b, 'copying a row to the other floor puts it in that floor\'s profile');
    $newProfile = $bell->copyProfile($activeA, $b);
    $check((int) $bell->profile($newProfile)['zone_id'] === $b && count($bell->entries($newProfile)) === count($bell->entries($activeA)), 'copying a profile to the other floor copies all its rows');
    $check(array_sum(array_column($bell->entries($newProfile), 'both_floors')) === 0, 'a copied profile does not carry school-wide flags');
    $dup = $bell->copyProfile($activeA, $a);
    $check(str_contains((string) $bell->profile($dup)['name'], 'salinan'), 'duplicating inside a floor gives a new name');

    // Profiles.
    $check($throws(fn () => $bell->createProfile($a, (string) $pa[0]['name'])), 'profile names are unique per floor');
    $check($pa[0]['name'] === $pb[0]['name'], 'two floors may use the same profile name');
    $bell->renameProfile($dup, 'Ujian tengah semester');
    $check($bell->profile($dup)['name'] === 'Ujian tengah semester', 'profiles can be renamed');
    $bell->setActiveProfile($a, $dup);
    $bell->deleteProfile($dup);
    $check((int) $bell->zone($a)['active_profile_id'] !== $dup && $bell->zone($a)['active_profile_id'] !== null, 'deleting the profile in use puts another one in use');
    $solo = $bell->createProfile($b, 'Solo');
    foreach ($bell->profiles($b) as $p) if ((int) $p['id'] !== $solo) $bell->deleteProfile((int) $p['id']);
    $check($throws(fn () => $bell->deleteProfile($solo)), 'the last profile of a floor cannot be deleted');

    // Import replaces or adds, all in one go.
    $before = count($bell->entries($solo));
    $bell->importEntries($solo, [$row('06:00:00'), $row('06:30:00')], false);
    $check(count($bell->entries($solo)) === $before + 2, 'import adds rows');
    $bell->importEntries($solo, [$row('13:00:00')], true);
    $check(count($bell->entries($solo)) === 1, 'import with "replace" swaps the rows');

    // The PC key: only its hash is stored.
    $token = $bell->createKey();
    $check(strlen($token) === 64 && $bell->keyMatches($token) && !$bell->keyMatches($token . 'x') && !$bell->keyMatches(''), 'the connection key matches only itself');
    $check($bell->pc()['token_hash'] !== $token && $bell->pc()['token_hash'] === hash('sha256', $token), 'only a hash of the key is stored');
    $second = $bell->createKey();
    $check(!$bell->keyMatches($token) && $bell->keyMatches($second), 'making a new key stops the old one');
    $bell->revokeKey();
    $check(!$bell->keyMatches($second), 'a removed key no longer works');
    $bell->recordSync('PC-TEST', '1.0.0', ['zones' => []]);
    $check($bell->isOnline($bell->pc()), 'a PC that just checked in is online');

    // Events: kept, newest first, capped.
    $bell->addEvents([['zone_id' => $a, 'occurred_at' => '2026-10-22 07:00:00', 'kind' => 'played', 'message' => 'x']]);
    $check($bell->events(5)[0]['zone_name'] !== null, 'events list the floor name');

    // The backup is complete and readable.
    $backup = $bell->backup();
    $check($backup['app'] === 'nexus-bell' && count($backup['zones']) >= 2 && isset($backup['zones'][0]['profiles'][0]['entries']), 'backup contains floors, profiles and rows');
    $check(json_encode($backup, JSON_THROW_ON_ERROR) !== '', 'backup is valid JSON');
} catch (Throwable $error) {
    $failure = $error;
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}
if ($failure !== null) { fwrite(STDERR, $failure->getMessage() . ' (' . basename($failure->getFile()) . ':' . $failure->getLine() . ")\n"); exit(1); }
echo "bell: $checks checks passed\n";

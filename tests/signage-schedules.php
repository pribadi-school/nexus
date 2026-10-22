<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Request;
use App\Modules\Signage\SignageController;
use App\Modules\Signage\SignagePlayerController;
use App\Modules\Signage\SignageRepository;
use App\Modules\Signage\SignageSchedules;

if (config('app.env') !== 'local') { fwrite(STDERR, "Local database only.\n"); exit(1); }
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('Failed: ' . $message);
    $checks++;
};
$failure = null;
$tables = ['signage_content', 'signage_screens', 'signage_playlists', 'signage_playlist_items', 'signage_schedules', 'signage_schedule_screens'];
$before = [];
foreach ($tables as $table) $before[$table] = db()->value("SELECT COUNT(*) FROM $table");
$pdo = db()->pdo();
$pdo->beginTransaction();
try {
    $at = static fn (string $when): DateTimeImmutable => new DateTimeImmutable($when, local_tz());
    $row = static fn (array $o = []): array => $o + ['enabled' => 1, 'days' => 127, 'start_date' => null, 'end_date' => null, 'start_time' => '07:00:00', 'end_time' => '07:30:00'];

    // The rule itself (2026-10-19 is a Monday).
    $monday = $at('2026-10-19 07:10:00');
    $check(SignageSchedules::isActive($row(), $monday), 'inside the window on a matching day is active');
    $check(!SignageSchedules::isActive($row(), $at('2026-10-19 06:59:59')) && !SignageSchedules::isActive($row(), $at('2026-10-19 07:30:00')), 'window starts at the start time and ends at the end time');
    $check(!SignageSchedules::isActive($row(['days' => 1 << 1]), $monday) && SignageSchedules::isActive($row(['days' => 1]), $monday), 'weekday bits: Monday is bit 1, Tuesday bit 2');
    $check(SignageSchedules::isActive($row(['days' => 64]), $at('2026-10-25 07:10:00')), 'Sunday is bit 64');
    $check(!SignageSchedules::isActive($row(['enabled' => 0]), $monday), 'a turned-off schedule never runs');
    $oneOff = $row(['start_date' => '2026-10-23', 'end_date' => '2026-10-23']);
    $check(SignageSchedules::isActive($oneOff, $at('2026-10-23 07:10:00')) && !SignageSchedules::isActive($oneOff, $at('2026-10-30 07:10:00')) && !SignageSchedules::isActive($oneOff, $at('2026-10-22 07:10:00')), 'a one-day schedule runs only on its date');
    $check(SignageSchedules::state($oneOff, $at('2026-10-24 10:00:00')) === 'ended' && SignageSchedules::state($oneOff, $at('2026-10-22 10:00:00')) === 'upcoming' && SignageSchedules::state($row(['enabled' => 0]), $monday) === 'off', 'states: ended, upcoming, off');
    $check(str_contains(SignageSchedules::summary($row(['days' => 31])), 'Mon–Fri') && str_contains(SignageSchedules::summary($oneOff), 'Fri 23 Oct 2026'), 'readable summaries');

    // Validation.
    [$data, $screenIds, $errors] = SignageSchedules::validate(['name' => 'Flag', 'playlist_id' => '5', 'screen_ids' => ['3', '9', '3'], 'mode' => 'weekly', 'days' => ['1', '3'], 'start_time' => '07:00', 'end_time' => '07:30', 'enabled' => '1'], [3], [5]);
    $check($errors === [] && $screenIds === [3] && $data['days'] === 5 && $data['start_time'] === '07:00:00', 'a valid weekly form passes; unknown TVs are dropped');
    [, , $bad] = SignageSchedules::validate(['name' => '', 'playlist_id' => '8', 'mode' => 'weekly', 'start_time' => '09:00', 'end_time' => '08:00'], [3], [5]);
    $check(isset($bad['name'], $bad['playlist_id'], $bad['screen_ids'], $bad['days'], $bad['end_time']), 'missing name, playlist, TVs, days and an end before the start are refused');
    [$once, , $onceErrors] = SignageSchedules::validate(['name' => 'Event', 'playlist_id' => '5', 'screen_ids' => ['3'], 'mode' => 'once', 'date' => '2026-02-31', 'start_time' => '07:00', 'end_time' => '08:00'], [3], [5]);
    $check(isset($onceErrors['date']), 'an impossible date is refused');
    [$once, , $onceErrors] = SignageSchedules::validate(['name' => 'Event', 'playlist_id' => '5', 'screen_ids' => ['3'], 'mode' => 'once', 'date' => '2026-10-23', 'start_time' => '07:00', 'end_time' => '08:00'], [3], [5]);
    $check($onceErrors === [] && $once['start_date'] === '2026-10-23' && $once['end_date'] === '2026-10-23' && $once['days'] === 127, 'one-day mode stores the same first and last date');

    // Against the database.
    $repo = new SignageRepository();
    $schedules = new SignageSchedules();
    $site = $repo->createContent(['name' => 'Sched normal', 'type' => 'website', 'source_url' => 'https://example.com']);
    $event = $repo->createContent(['name' => 'Sched event', 'type' => 'website', 'source_url' => 'https://example.org']);
    $screenData = ['name' => 'Schedule TV', 'location' => null, 'orientation' => 'landscape', 'fit' => 'contain', 'muted' => 1, 'enabled' => 1];
    $tv = $repo->createScreenWithPlaylist($screenData);
    $other = $repo->createScreenWithPlaylist(array_replace($screenData, ['name' => 'Unscheduled TV']));
    $normal = $repo->screenPlaylistId($tv);
    $repo->addPlaylistItem($normal, $site, 30);
    $special = $repo->createSharedPlaylist('Ceremony test');
    $repo->withSharedPlaylist($special, fn (int $id) => $repo->addPlaylistItem($id, $event, 30));
    $window = ['name' => 'All day test', 'playlist_id' => $special, 'days' => 127, 'start_time' => '00:00:00', 'end_time' => '23:59:00', 'start_date' => null, 'end_date' => null, 'enabled' => 1];
    $id = $schedules->save(null, $window, [$tv]);
    $check($schedules->activeFor($tv, $at('2026-10-19 12:00:00'))['id'] == $id && $schedules->activeFor($other, $at('2026-10-19 12:00:00')) === null, 'only the chosen TV is scheduled');
    $check($schedules->activeFor($tv, $at('2026-10-19 12:00:00'))['playlist_name'] === 'Ceremony test', 'active schedule carries its playlist');

    // Two overlapping schedules: the one that starts later wins.
    $later = $schedules->save(null, ['name' => 'Later test', 'start_time' => '10:00:00', 'end_time' => '11:00:00'] + $window, [$tv]);
    $check((int) $schedules->activeFor($tv, $at('2026-10-19 10:30:00'))['id'] === $later && (int) $schedules->activeFor($tv, $at('2026-10-19 12:00:00'))['id'] === $id, 'overlapping schedules: the later start wins, then the other resumes');

    // What the TV receives, now and after the schedule is turned off.
    $token = $repo->screen($tv)['token'];
    $manifest = static function () use ($token, $screenData): array {
        $request = new Request('GET', '/signage/player/' . $token . '/manifest', [], [], []); $request->params['token'] = $token;
        return json_decode((new SignagePlayerController())->manifest($request)->body, true, 16, JSON_THROW_ON_ERROR);
    };
    if ($at('now')->format('H:i') < '23:58') {
        $now = $manifest();
        $check($now['playlist']['name'] === 'Ceremony test' && $now['items'][0]['name'] === 'Sched event', 'while scheduled the TV receives the scheduled playlist');
        $schedules->setEnabled($id, false); $schedules->setEnabled($later, false);
        $after = $manifest();
        $check((int) $after['playlist']['id'] === $normal && $after['items'][0]['name'] === 'Sched normal' && $after['revision'] !== $now['revision'], 'when it is off the TV gets its normal playlist again, with a new revision so players switch');
        $schedules->setEnabled($id, true);
    }
    $check(in_array($special, $schedules->playlistIdsFor($repo->screen($tv)), true) && in_array($normal, $schedules->playlistIdsFor($repo->screen($tv)), true), 'media may be fetched from both the normal and scheduled playlists');

    // Safety rules and pages.
    try { $repo->deleteSharedPlaylist($special); $check(false, 'playlist in a schedule cannot be deleted'); }
    catch (InvalidArgumentException $e) { $check(str_contains($e->getMessage(), 'schedules'), 'a playlist used by a schedule cannot be deleted'); }
    $listed = $schedules->find($id);
    $check($listed['screens'] === [['id' => $tv, 'name' => 'Schedule TV']] && $listed['playlist_name'] === 'Ceremony test', 'schedule lists its TVs and playlist');
    $schedules->save($id, ['name' => 'All day test'] + $window, [$tv, $other]);
    $check(count($schedules->find($id)['screens']) === 2, 'editing replaces the chosen TVs');
    $list = (new App\Modules\Signage\SignageScheduleController())->index(new Request('GET', '/signage/schedules', [], [], []))->body;
    $check(str_contains($list, 'All day test') && str_contains($list, 'Later test') && str_contains($list, 'href="/signage/schedules"'), 'Schedules tab lists schedules');
    $newForm = (new App\Modules\Signage\SignageScheduleController())->create(new Request('GET', '/signage/schedules/create', [], [], []))->body;
    $check(str_contains($newForm, 'name="screen_ids[]"') && str_contains($newForm, 'name="days[]"') && str_contains($newForm, 'Ceremony test'), 'form offers TVs, weekdays and shared playlists');
    $page = new Request('GET', '/signage/screens/' . $tv . '/edit', [], [], []); $page->params['id'] = (string) $tv;
    if ($at('now')->format('H:i') < '23:58') {
        $check(str_contains((new SignageController())->editScreen($page)->body, 'Right now this TV plays'), 'the TV page says a schedule is overriding it');
    }
    $schedules->delete($id); $schedules->delete($later);
    $check($schedules->find($id) === null && $repo->screen($tv) !== null, 'deleting a schedule leaves TVs and playlists alone');
    $repo->deleteSharedPlaylist($special);
    $check($repo->playlist($special) === null, 'the playlist can be deleted once no schedule uses it');
} catch (Throwable $error) { $failure = $error; }
finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
foreach ($tables as $table) {
    if (db()->value("SELECT COUNT(*) FROM $table") !== $before[$table]) { fwrite(STDERR, "Rollback failed.\n"); exit(1); }
}
if ($failure) { fwrite(STDERR, $failure->getMessage() . "\n" . $failure->getFile() . ':' . $failure->getLine() . "\n"); exit(1); }
echo "Passed $checks schedule checks. All changes rolled back.\n";

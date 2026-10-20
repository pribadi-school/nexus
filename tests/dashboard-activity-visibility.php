<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Devices\NoteRepository;
use App\Modules\Dashboard\DashboardActivityVisibility;
use App\Modules\StudyTouch\StudyTouchCategory;
use App\Modules\StudyTouch\StudyTouchRepository;

if (config('app.env') !== 'local') throw new RuntimeException('Local rollback tests only.');
$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException('Failed: ' . $message);
    $checks++;
};
$path = BASE_PATH . '/.dev/dashboard-activity-test-' . bin2hex(random_bytes(8)) . '.json';
$visibility = new DashboardActivityVisibility($path);
$notesBefore = db()->all('SELECT * FROM device_notes ORDER BY id');
$devicesBefore = db()->all('SELECT * FROM devices ORDER BY id');
$roomsBefore = db()->all('SELECT * FROM rooms ORDER BY id');
$pdo = db()->pdo(); $pdo->beginTransaction();
try {
    // Keep real boards out of fixture matching; these label changes are rolled back.
    $rooms = db()->all("SELECT id FROM rooms WHERE UPPER(REPLACE(name, ' ', '')) IN ('12B','XIIB','GRADE12B','GRADEXIIB')");
    foreach ($rooms as $room) db()->update('rooms', ['name' => 'Visibility fixture ' . bin2hex(random_bytes(4)) . ' ' . $room['id']], ['id' => $room['id']]);
    $check($visibility->since() === null, 'unset preference leaves the dashboard unchanged');
    try { $visibility->configure(true); throw new LogicException('Missing board accepted'); }
    catch (RuntimeException $error) { $check(!$error instanceof LogicException && !file_exists($path), 'missing target changes neither preference nor history'); }

    $room = db()->one("SELECT id FROM rooms WHERE name='Grade 12B'");
    $roomId = $room === null ? db()->insert('rooms', ['name' => 'Grade 12B']) : (int) $room['id'];
    $board = (new StudyTouchRepository())->create(new StudyTouchCategory(), [
        'room_id' => $roomId, 'model' => 'StudyTouch', 'status' => 'ok', 'firmware_version' => 'test'], null);
    $insert = static fn (string $kind, string $at, ?string $meta = null): int => db()->insert('device_notes', [
        'device_id' => $board, 'user_id' => null, 'kind' => $kind, 'body' => 'Dashboard visibility fixture', 'meta' => $meta, 'created_at' => $at]);
    $old = $insert('report', '2026-10-10 09:00:00');
    try { $visibility->configure(true); throw new LogicException('Missing problem accepted'); }
    catch (RuntimeException $error) { $check(!$error instanceof LogicException && !file_exists($path), 'a report alone never becomes the cutoff'); }
    $problem = $insert('problem', '2026-10-10 10:00:00');
    $sameTime = $insert('status', '2026-10-10 10:00:00', '{"from":"ok","to":"problem"}');
    $newer = $insert('fix', '2026-10-10 11:00:00');
    $snapshot = db()->all('SELECT * FROM device_notes ORDER BY id');
    $deviceSnapshot = db()->all('SELECT * FROM devices ORDER BY id');
    $preview = $visibility->configure();
    $check($preview['mode'] === 'preview' && $visibility->since() === null && !file_exists($path), 'preview writes nothing');
    $check($preview['problem_note_id'] === $sameTime, 'latest Grade 12B problem status is identified');
    $applied = $visibility->configure(true);
    $check($applied['device_history_changed'] === false && $applied['all_activity_page_changed'] === false, 'only the dashboard preference changes');
    $check($visibility->since() === '2026-10-10 10:00:00', 'cutoff survives rereading the private setting');
    $notes = new NoteRepository();
    $dashboardIds = array_column($notes->recent(10000, null, $visibility->since()), 'id');
    $check(!in_array($old, $dashboardIds, true), 'older dashboard entry is hidden');
    foreach ([$problem, $sameTime, $newer] as $id) $check(in_array($id, $dashboardIds, true), 'problem, same-time companion and newer entries remain visible');
    $check(in_array($old, array_column($notes->recent(10000), 'id'), true), 'All activity retains older entries');
    $check(in_array($old, array_column($notes->forDevice($board), 'id'), true), 'device history retains older entries');
    $check(db()->all('SELECT * FROM device_notes ORDER BY id') === $snapshot, 'all history rows, dates and bodies are unchanged');
    $check(db()->all('SELECT * FROM devices ORDER BY id') === $deviceSnapshot, 'all device records are unchanged');
    $check($notes->recent(10000, $old, $visibility->since()) === [], 'a cursor older than the cutoff cannot reveal earlier dashboard entries');
    $laterProblem = $insert('problem', '2026-10-11 10:00:00');
    $again = $visibility->configure(true);
    $check($again['problem_note_id'] === $sameTime && $visibility->since() === '2026-10-10 10:00:00', 'rerunning never advances the cutoff automatically');
    $check(in_array($laterProblem, array_column($notes->recent(10000, null, $visibility->since()), 'id'), true), 'future problems and new activity remain visible');
    file_put_contents($path, '{broken');
    $check($visibility->since() === null, 'invalid settings never make the dashboard inaccessible');
    try { $visibility->configure(true); throw new LogicException('Invalid preference overwritten'); }
    catch (JsonException) { $check(file_get_contents($path) === '{broken', 'invalid preferences are never silently overwritten'); }
    $visibility->reset(); $visibility->reset();
    $check($visibility->since() === null && in_array($old, array_column($notes->recent(10000), 'id'), true), 'reset is idempotent and restores visibility');
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (is_file($path)) unlink($path);
}
$check(db()->all('SELECT * FROM device_notes ORDER BY id') === $notesBefore, 'test notes roll back completely');
$check(db()->all('SELECT * FROM devices ORDER BY id') === $devicesBefore, 'test devices roll back completely');
$check(db()->all('SELECT * FROM rooms ORDER BY id') === $roomsBefore, 'all room labels roll back completely');
echo "Passed $checks dashboard visibility checks. All history preserved; fixtures removed.\n";

<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Devices\ActivityHistoryCleanup;
use App\Devices\DeviceService;
use App\Modules\StudyTouch\StudyTouchCategory;
use App\Modules\StudyTouch\StudyTouchRepository;

if (config('app.env') !== 'local') throw new RuntimeException('Local database only.');
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('Failed: ' . $message);
    $checks++;
};
$beforeNotes = db()->all('SELECT * FROM device_notes ORDER BY id');
$backups = [];
$pdo = db()->pdo();
$pdo->beginTransaction();
try {
    // This local copy has no matching board; never run a broad cleanup without it.
    $cleanup = new ActivityHistoryCleanup();
    $matches = db()->value("SELECT COUNT(*) FROM studytouch_boards b JOIN rooms r ON r.id=b.room_id JOIN devices d ON d.id=b.device_id WHERE d.deleted_at IS NULL AND UPPER(REPLACE(r.name,' ','')) IN ('12B','XIIB')");
    if ((int) $matches !== 0) throw new RuntimeException('Run this fixture test in a local copy without a StudyTouch 12B board.');
    try { $cleanup->run(true); throw new LogicException('Missing keep target accepted'); }
    catch (RuntimeException $error) { $check(!($error instanceof LogicException), 'missing target aborts without deleting history'); }
    $check(db()->all('SELECT * FROM device_notes ORDER BY id') === $beforeNotes, 'failed cleanup leaves notes intact');

    $room = db()->one("SELECT id FROM rooms WHERE name='12B'");
    $roomId = $room === null ? db()->insert('rooms', ['name' => '12B']) : (int) $room['id'];
    $board = (new StudyTouchRepository())->create(new StudyTouchCategory(), ['room_id' => $roomId, 'model' => 'StudyTouch', 'status' => 'ok', 'firmware_version' => 'test'], null);
    $service = new DeviceService();
    $service->addNote($board, null, 'report', 'Keep the StudyTouch 12B history.');
    $all = db()->all('SELECT * FROM device_notes ORDER BY id');
    $master = [];
    foreach (['devices', 'studytouch_boards', 'chromebooks', 'chromebook_assignments', 'cctv_cameras', 'rooms', 'users'] as $table) {
        $master[$table] = db()->all("SELECT * FROM `$table` ORDER BY 1");
    }
    $preview = $cleanup->run();
    $check($preview['mode'] === 'preview' && $preview['backup'] === null && $preview['kept_history_entries'] === 1, 'preview identifies 12B without writing');
    $check(db()->all('SELECT * FROM device_notes ORDER BY id') === $all, 'preview keeps all history');
    $result = $cleanup->run(true);
    if ($result['backup'] !== null) $backups[] = $result['backup'];
    $kept = db()->all('SELECT * FROM device_notes ORDER BY id');
    $check(count($kept) === 1 && (int) $kept[0]['device_id'] === $board, 'only StudyTouch 12B history remains');
    $check(is_file($result['backup']), 'deleted history is backed up privately');
    foreach ($master as $table => $rows) $check(db()->all("SELECT * FROM `$table` ORDER BY 1") === $rows, "$table data is unchanged");
    $check($cleanup->restore($result['backup']) === count($all) - 1, 'backup restores all removed history');
    $check(db()->all('SELECT * FROM device_notes ORDER BY id') === $all, 'restore preserves note IDs, dates, authors, bodies and metadata');
    $check($cleanup->restore($result['backup']) === 0, 'repeating restore is safe');
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    foreach ($backups as $path) if (is_file($path)) unlink($path);
}
$check(db()->all('SELECT * FROM device_notes ORDER BY id') === $beforeNotes, 'test history changes rolled back');
echo "Passed $checks history cleanup checks. All fixtures and backups removed.\n";

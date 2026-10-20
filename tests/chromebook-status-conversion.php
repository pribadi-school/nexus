<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Devices\DeviceService;
use App\Modules\Chromebooks\ChromebookRepository;
if (config('app.env') !== 'local') throw new RuntimeException('Local rollback tests only.');
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void { if (!$ok) throw new RuntimeException('Failed: ' . $label); $checks++; };
$before = db()->all('SELECT * FROM devices ORDER BY id');
$notesBefore = db()->all('SELECT * FROM device_notes ORDER BY id');
$backupBefore = db()->all('SELECT * FROM chromebook_status_conversion ORDER BY device_id');
$pdo = db()->pdo(); $pdo->beginTransaction();
try {
    $repo = new ChromebookRepository(); $ids = [];
    foreach (['reported', 'repaired', 'ok', 'ok', 'problem'] as $i => $oldStatus) {
        $id = $repo->create(categories()->get('chromebook'), ['academic_year' => 2026, 'asset_id' => 'CVT-' . bin2hex(random_bytes(4)),
            'serial_number' => null, 'model' => null, 'status' => $oldStatus, 'student_name' => null, 'student_email' => null], null);
        db()->update('devices', ['status_changed_at' => '2026-01-02 00:00:00'], ['id' => $id]);
        $ids[] = $id;
    }
    $service = new DeviceService();
    $borrowedNote = $service->addNote($ids[3], null, 'borrowed', 'Existing typed loan note');
    db()->update('device_notes', ['created_at' => '2026-01-03 00:00:00'], ['id' => $borrowedNote]);
    $fixedNote = $service->addNote($ids[4], null, 'fixed', 'Older typed fix note');
    db()->update('device_notes', ['created_at' => '2026-01-01 00:00:00'], ['id' => $fixedNote]);
    $idSql = implode(',', $ids);
    $sql = file_get_contents(BASE_PATH . '/database/migrations/010_chromebook_status_workflow.sql');
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $statement) {
        $statement = trim($statement);
        if (!str_starts_with($statement, 'INSERT INTO chromebook_status_conversion') && !str_starts_with($statement, 'UPDATE devices') && !str_starts_with($statement, 'INSERT INTO device_notes')) continue;
        $statement = str_replace("WHERE d.category = 'chromebook'", "WHERE d.category = 'chromebook' AND d.id IN ($idSql)", $statement);
        $statement = str_replace('ON c.device_id = d.id', "ON c.device_id = d.id AND d.id IN ($idSql)", $statement);
        $statement = str_replace('WHERE old_status <>', "WHERE device_id IN ($idSql) AND old_status <>", $statement);
        $pdo->exec($statement);
    }
    foreach (['problem','fixed','unmarked','borrowed','problem'] as $i => $expected) $check($repo->find($ids[$i])['status'] === $expected, "conversion produces $expected for fixture $i");
    $backup = db()->one('SELECT * FROM chromebook_status_conversion WHERE device_id = ?', [$ids[3]]);
    $check($backup['old_status'] === 'ok' && (int) $backup['source_note_id'] === $borrowedNote && $backup['new_status'] === 'borrowed', 'conversion preserves the original status and source note');
    $check($repo->find($ids[3])['status_changed_at'] === '2026-01-03 00:00:00', 'a promoted note keeps its original date');
    $check(db()->value('SELECT body FROM device_notes WHERE id = ?', [$borrowedNote]) === 'Existing typed loan note', 'the original note text remains unchanged');
    $converted = db()->one("SELECT meta FROM device_notes WHERE device_id = ? AND kind = 'status' ORDER BY id DESC LIMIT 1", [$ids[3]]);
    $check(json_decode($converted['meta'], true)['to'] === 'borrowed', 'converted current statuses appear in editable history');
} finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
$check(db()->all('SELECT * FROM devices ORDER BY id') === $before && db()->all('SELECT * FROM device_notes ORDER BY id') === $notesBefore
    && db()->all('SELECT * FROM chromebook_status_conversion ORDER BY device_id') === $backupBefore, 'conversion fixtures and backup rows roll back');
echo "Passed $checks status conversion checks. Fixtures rolled back.\n";

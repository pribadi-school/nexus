<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Devices\DeviceController;
use App\Devices\DeviceRepository;
use App\Devices\DeviceService;
use App\Devices\NoteRepository;
use App\Modules\Chromebooks\ChromebookController;
use App\Modules\Chromebooks\ChromebookCsv;
use App\Modules\Chromebooks\ChromebookRepository;

if (config('app.env') !== 'local') throw new RuntimeException('Local rollback tests only.');
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('Failed: ' . $label);
    $checks++;
};
$before = db()->all('SELECT * FROM devices ORDER BY id');
$notesBefore = db()->all('SELECT * FROM device_notes ORDER BY id');
$repo = new ChromebookRepository();
$category = categories()->get('chromebook');
$controller = new ChromebookController();
$devices = new DeviceController();
$request = static function (int $id, string $action, array $body): Request {
    $r = new Request('POST', "/devices/$id/$action", [], $body, ['HTTP_X_REQUESTED_WITH' => 'fetch']);
    $r->params['id'] = (string) $id;
    return $r;
};
$pdo = db()->pdo();
$pdo->beginTransaction();
try {
    $token = 'STS-' . bin2hex(random_bytes(2));
    $year = $category->years()[0];
    $initial = $repo->counts($year);
    $sharedInitial = (new DeviceRepository())->countsByCategory()['chromebook'];
    $ids = [];
    foreach (['unmarked', ...$category->statuses()] as $status) {
        $ids[$status] = $repo->create($category, [
            'academic_year' => $year, 'asset_id' => "$token-$status", 'serial_number' => null,
            'model' => 'Status fixture', 'status' => $status, 'student_name' => null, 'student_email' => null,
        ], null);
    }
    $check($category->statuses() === ['normal', 'problem', 'fixed', 'borrowed', 'replaced', 'broken'], 'six statuses include Normal first');
    $counts = $repo->counts($year);
    $shared = (new DeviceRepository())->countsByCategory()['chromebook'];
    foreach (array_keys($ids) as $status) {
        $check($counts[$status] === $initial[$status] + 1 && $shared[$status] === $sharedInitial[$status] + 1, "$status is counted independently, without folding into OK");
        $filtered = $repo->search(['year' => $year, 'q' => $token, 'status' => $status]);
        $check(array_column($filtered['rows'], 'id') === [$ids[$status]], "$status filter returns only that status");
    }
    $legacy = $repo->find($ids['unmarked']);
    $showRequest = new Request('GET', '/chromebooks/' . $ids['unmarked'], [], [], []);
    $showRequest->params['id'] = (string) $ids['unmarked'];
    $html = $controller->show($showRequest)->body;
    $check(!str_contains($html, 'data-status-toggle') && !str_contains($html, 'data-set-status') && !str_contains($html, 'id="note-status"') && !str_contains($html, 'Kind of note'), 'notes are plain text and the old switch and note status picker are absent');
    $check(str_contains($html, 'id="assign"') && str_contains($html, 'id="student_name"') && str_contains($html, 'id="student_email"'), 'name and email fields are restored on the detail page');
    $check(!str_contains($html, 'OK (legacy)') && $repo->find($ids['unmarked']) === $legacy, 'unmarked records do not acquire a false fixed status');
    foreach ($category->statuses() as $status) {
        $response = $devices->status($request($ids['unmarked'], 'status', ['status' => $status]));
        $json = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        $note = db()->one('SELECT * FROM device_notes WHERE device_id = ? AND kind = ? ORDER BY id DESC LIMIT 1', [$ids['unmarked'], 'status']);
        $check($json['changed'] === true && $repo->find($ids['unmarked'])['status'] === $status && json_decode($note['meta'], true)['to'] === $status, "$status saves and writes an audit entry");
        $count = (int) db()->value('SELECT COUNT(*) FROM device_notes WHERE device_id = ?', [$ids['unmarked']]);
        $devices->status($request($ids['unmarked'], 'status', ['status' => $status]));
        $check((int) db()->value('SELECT COUNT(*) FROM device_notes WHERE device_id = ?', [$ids['unmarked']]) === $count, 'saving the current status does not duplicate history');
    }
    foreach (['ok', 'reported', 'repaired', 'bogus'] as $status) {
        try {
            $devices->status($request($ids['unmarked'], 'status', ['status' => $status]));
            throw new RuntimeException('Invalid Chromebook status was accepted.');
        } catch (HttpException $e) {
            $check($e->status === 422, "Chromebook status endpoint rejects $status");
        }
    }
    $devices->addNote($request($ids['unmarked'], 'notes', ['body' => 'Plain note without a kind']));
    $devices->addNote($request($ids['unmarked'], 'notes', ['kind' => 'fixed', 'body' => 'Old form cannot change status', 'set_status' => 'fixed']));
    $check($repo->find($ids['unmarked'])['status'] === 'broken', 'plain notes never change status, including old form submissions');
    $check(db()->value('SELECT kind FROM device_notes WHERE device_id = ? ORDER BY id DESC LIMIT 1', [$ids['unmarked']]) === 'note', 'notes always use the untyped note kind');
    foreach (['cctv', 'studytouch'] as $key) {
        $other = (new DeviceService())->create(categories()->get($key), ['serial_number' => null, 'model' => null, 'status' => 'ok'], null);
        try {
            $devices->status($request($other, 'status', ['status' => 'repaired']));
            throw new RuntimeException('Cross-category status was accepted.');
        } catch (HttpException $e) {
            $check($e->status === 422 && categories()->get($key)->statuses() === ['ok', 'problem'], "$key keeps its own statuses");
        }
    }
    $export = $controller->export(new Request('GET', '/chromebooks/export', ['year' => (string) $year, 'q' => $token], [], []));
    $lines = preg_split('/\r\n|\n|\r/', trim(preg_replace('/^\xEF\xBB\xBF/', '', $export->body)));
    $table = array_map(static fn ($line) => str_getcsv($line, ',', '"', ''), $lines);
    $plan = ChromebookCsv::plan($table, $year, $repo);
    $check($plan['error'] === null && count($plan['rows']) === 7 && array_unique(array_column($plan['rows'], 'action')) === ['same'], 'six-status CSV export reimports without changes');
    $rows = [];
    foreach (['Problem', 'Fixed', 'Borrowed', 'Replaced', 'Broken', '-'] as $i => $status) $rows[] = ["$token-new$i", '', '', '', '', $status];
    $plan = ChromebookCsv::plan([ChromebookCsv::COLUMNS, ...$rows], $year, $repo);
    $check(array_unique(array_column($plan['rows'], 'action')) === ['add'], 'imports accept all statuses and legacy exports');
    ChromebookCsv::apply($plan['rows'], $year, $category, $repo, null);
    foreach (['problem', 'fixed', 'borrowed', 'replaced', 'broken', 'normal'] as $i => $status) $check($repo->findByAsset("$token-new$i")['status'] === $status, "import persists $status");
    $check(in_array($ids['replaced'], $category->searchIds('Replaced', 1000), true), 'dashboard search recognizes new status labels');
    $infoId = $ids['fixed'];
    $repo->assign($repo->find($infoId), 'Automatic assignment fixture', null, null);
    $notes = new NoteRepository();
    $check($notes->countForDevice($infoId, manualOnly: true) === 0 && $notes->forDevice($infoId, manualOnly: true) === [], 'manual notes exclude automatic assignment and status history');
    $showRequest->params['id'] = (string) $infoId;
    $emptyHtml = $controller->show($showRequest)->body;
    $check(str_contains($emptyHtml, 'class="notes-placeholder">-</p>') && !str_contains($emptyHtml, 'Assigned to Automatic assignment fixture'), 'Notes section stays with a dash when no manual notes exist');
    $manualBody = 'Assigned to the technician for screen repair, typed manually.';
    (new DeviceService())->addNote($infoId, null, 'report', $manualBody);
    $repo->assign($repo->find($infoId), 'Later automatic fixture', null, null);
    $manualNotes = $notes->forDevice($infoId, manualOnly: true);
    $check(count($manualNotes) === 1 && $manualNotes[0]['body'] === $manualBody && $notes->countForDevice($infoId, manualOnly: true) === 1, 'manually typed notes remain even when they contain the words Assigned to');
    $check($repo->find($infoId)['latest_note'] === $manualBody, 'automatic assignment does not hide the most recent manual note');
    $check($notes->countForDevice($infoId) > 1 && count($repo->history($infoId)) === 2, 'hidden automatic history is retained in storage');
    $noteHtml = $controller->show($showRequest)->body;
    $check(str_contains($noteHtml, $manualBody) && !str_contains($noteHtml, 'Assigned to Later automatic fixture.'), 'detail Notes show user-entered information without automatic assignment entries');
    $check($category->noteKinds() === ['note'], 'notes have no types');
    foreach ($category->noteKinds() as $kind) {
        $devices->addNote($request($infoId, 'notes', ['kind' => $kind, 'body' => "Manual $kind fixture", 'set_status' => '']));
        $check(db()->value('SELECT kind FROM device_notes WHERE device_id = ? ORDER BY id DESC LIMIT 1', [$infoId]) === $kind, "$kind notes can be saved");
    }
    $check(count($notes->forDevice($infoId, manualOnly: true)) === 2, 'new plain notes and older manually entered notes remain visible');
    $create = $controller->create(new Request('GET', '/chromebooks/new', [], [], []))->body;
    $check(str_contains($create, '<option value="normal" selected>Normal</option>') && !str_contains($create, 'name="status" value="ok"'), 'add form preselects Normal');
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}
$check(db()->all('SELECT * FROM devices ORDER BY id') === $before && db()->all('SELECT * FROM device_notes ORDER BY id') === $notesBefore, 'all fixtures roll back and existing data is unchanged');
echo "Passed $checks Chromebook status checks. Fixtures rolled back.\n";

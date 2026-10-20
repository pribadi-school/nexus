<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Core\HttpException;
use App\Core\Request;
use App\Devices\DeviceController;
use App\Devices\DeviceRepository;
use App\Devices\DeviceService;
use App\Devices\HistoryService;
use App\Devices\NoteRepository;
use App\Modules\Chromebooks\ChromebookController;
use App\Modules\Chromebooks\ChromebookRepository;

if (config('app.env') !== 'local') throw new RuntimeException('Local rollback tests only.');
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('Failed: ' . $label);
    $checks++;
};
$before = db()->all('SELECT * FROM devices ORDER BY id');
$notesBefore = db()->all('SELECT * FROM device_notes ORDER BY id');
$revisionsBefore = db()->all('SELECT * FROM device_note_revisions ORDER BY id');
$repo = new ChromebookRepository(); $category = categories()->get('chromebook');
$service = new DeviceService(); $history = new HistoryService();
$get = static fn (int $id): array => db()->one('SELECT * FROM device_notes WHERE id = ?', [$id]);
$pdo = db()->pdo(); $pdo->beginTransaction();
try {
    $token = 'HST-' . bin2hex(random_bytes(3));
    $id = $repo->create($category, ['academic_year' => 2026, 'asset_id' => $token, 'serial_number' => null,
        'model' => null, 'status' => 'problem', 'student_name' => null, 'student_email' => null], null);
    $first = db()->one("SELECT * FROM device_notes WHERE device_id = ? AND kind = 'status' ORDER BY id LIMIT 1", [$id]);
    $service->changeStatus($category, $repo->find($id), 'borrowed', null);
    $latest = db()->one("SELECT * FROM device_notes WHERE device_id = ? AND kind = 'status' ORDER BY id DESC LIMIT 1", [$id]);
    $noteId = $service->addNote($id, null, 'note', 'Original note');
    $note = $get($noteId);
    $check($history->edit($category, $id, $noteId, 'Corrected <b>plain</b> note', '', HistoryService::version($note), null), 'saved plain notes are editable');
    $check($get($noteId)['body'] === 'Corrected <b>plain</b> note' && $repo->find($id)['status'] === 'borrowed', 'editing note text cannot change current status');
    $check(db()->value('SELECT body FROM device_note_revisions WHERE note_id = ? ORDER BY id LIMIT 1', [$noteId]) === 'Original note', 'original text is preserved in revisions');
    $count = (int) db()->value('SELECT COUNT(*) FROM device_note_revisions');
    $check(!$history->edit($category, $id, $noteId, $get($noteId)['body'], '', HistoryService::version($get($noteId)), null)
        && (int) db()->value('SELECT COUNT(*) FROM device_note_revisions') === $count, 'saving unchanged text creates no revision');
    try { $history->edit($category, $id, $noteId, 'Stale edit', '', HistoryService::version($note), null); throw new LogicException('Stale edit accepted'); }
    catch (HttpException $e) { $check($e->status === 409 && $get($noteId)['body'] === 'Corrected <b>plain</b> note', 'stale forms cannot overwrite a newer correction'); }
    try { $history->edit($category, $id, $noteId, '', '', HistoryService::version($get($noteId)), null); throw new LogicException('Blank accepted'); }
    catch (HttpException $e) { $check($e->status === 422, 'empty notes are rejected'); }

    $check($history->edit($category, $id, $latest['id'], '', 'fixed', HistoryService::version($latest), null), 'past status entries are editable');
    $check($repo->find($id)['status'] === 'fixed' && !in_array($id, array_column((new DeviceRepository())->needingAttention(10000), 'device_id'), true), 'editing the latest status updates current status and dashboard membership');
    $check(json_decode(db()->value('SELECT meta FROM device_note_revisions WHERE note_id = ? ORDER BY id LIMIT 1', [$latest['id']]), true)['to'] === 'borrowed', 'previous status value is preserved');
    $check($history->edit($category, $id, $first['id'], '', 'broken', HistoryService::version($first), null), 'an older status entry can be corrected');
    $check($repo->find($id)['status'] === 'fixed', 'correcting an older status does not replace the latest status');
    $check(json_decode($get($latest['id'])['meta'], true)['from'] === 'broken', 'the following status entry keeps a consistent from/to chain');
    $check((int) db()->value('SELECT COUNT(*) FROM device_note_revisions WHERE note_id = ?', [$latest['id']]) === 2, 'automatic correction of the following entry also preserves its previous version');
    try { $history->edit($category, $id, $latest['id'], '', 'reported', HistoryService::version($get($latest['id'])), null); throw new LogicException('Old status accepted'); }
    catch (HttpException $e) { $check($e->status === 422 && $repo->find($id)['status'] === 'fixed', 'retired statuses cannot be reintroduced through history editing'); }
    $assignment = $service->addNote($id, null, 'assignment', 'Automatic assignment');
    try { $history->edit($category, $id, $assignment, 'Replacement', '', HistoryService::version($get($assignment)), null); throw new LogicException('Assignment accepted'); }
    catch (HttpException $e) { $check($e->status === 404, 'automatic assignment history cannot be rewritten through the notes form'); }
    try { $history->edit($category, $id + 10000, $noteId, 'Wrong device', '', HistoryService::version($get($noteId)), null); throw new LogicException('Wrong device accepted'); }
    catch (HttpException $e) { $check($e->status === 404, 'note IDs cannot be used to edit another device'); }

    $request = new Request('POST', "/devices/$id/notes/$noteId", [], ['body' => 'Controller correction', 'version' => HistoryService::version($get($noteId))], []);
    $request->params = ['id' => (string) $id, 'noteId' => (string) $noteId];
    $response = (new DeviceController())->editNote($request);
    $check($response->status === 303 && str_ends_with($response->headers['Location'], '#note-' . $noteId) && $get($noteId)['body'] === 'Controller correction', 'the edit endpoint saves and returns to the corrected entry');
    $show = new Request('GET', "/chromebooks/$id", [], [], []); $show->params['id'] = (string) $id;
    $html = (new ChromebookController())->show($show)->body;
    $check(str_contains($html, 'History of changes') && str_contains($html, 'Edit status change') && str_contains($html, 'Edit note'), 'notes and past status changes both expose edit controls');
    $check(!str_contains($html, 'Kind of note') && !str_contains($html, 'Status after this note') && str_contains($html, 'name="version"'), 'notes remain plain and edit forms protect against stale overwrites');
    $check(count((new NoteRepository())->statusHistory($id)) === 2, 'corrections update history rather than creating duplicate change events');
} finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
$check(db()->all('SELECT * FROM devices ORDER BY id') === $before && db()->all('SELECT * FROM device_notes ORDER BY id') === $notesBefore
    && db()->all('SELECT * FROM device_note_revisions ORDER BY id') === $revisionsBefore, 'all history test data rolls back');
echo "Passed $checks editable history checks. Fixtures rolled back.\n";

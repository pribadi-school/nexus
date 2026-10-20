<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Core\HttpException;
use App\Core\Request;
use App\Devices\DeviceRepository;
use App\Devices\DeviceService;
use App\Devices\NoteRepository;
use App\Modules\Chromebooks\ChromebookController;
use App\Modules\Chromebooks\ChromebookRepository;

if (config('app.env') !== 'local') throw new RuntimeException('Local rollback tests only.');
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void { if (!$ok) throw new RuntimeException('Failed: ' . $label); $checks++; };
$before = db()->all('SELECT * FROM devices ORDER BY id');
$notesBefore = db()->all('SELECT * FROM device_notes ORDER BY id');
$assignmentsBefore = db()->all('SELECT * FROM chromebook_assignments ORDER BY id');
$repo = new ChromebookRepository(); $category = categories()->get('chromebook'); $controller = new ChromebookController();
$pdo = db()->pdo(); $pdo->beginTransaction();
try {
    $token = 'UNI-' . bin2hex(random_bytes(3));
    $id = $repo->create($category, ['academic_year' => 2026, 'asset_id' => $token, 'serial_number' => "$token-SN", 'model' => 'Original model',
        'status' => 'normal', 'student_name' => 'Original fixture', 'student_email' => 'original@example.test'], null);
    $showRequest = new Request('GET', "/chromebooks/$id", [], [], []); $showRequest->params['id'] = (string) $id;
    $save = static function (array $body) use ($controller, $id) { $r = new Request('POST', "/chromebooks/$id", [], $body, []); $r->params['id'] = (string) $id; return $controller->update($r); };
    $body = ['academic_year' => '0', 'asset_id' => "$token-new", 'serial_number' => "$token-new-SN", 'model' => 'Updated model',
        'student_name' => 'Updated fixture', 'student_email' => 'updated@example.test', 'status' => 'borrowed', 'note' => "Loan recorded\r\nby the team."];
    $check($save($body)->status === 303, 'one request saves the full detail page');
    $saved = $repo->find($id);
    foreach (['asset_id', 'serial_number', 'model', 'student_name', 'student_email', 'status'] as $field) $check($saved[$field] === $body[$field], "universal save includes $field");
    $check((int) $saved['academic_year'] === 0 && count($repo->history($id)) === 2, 'year/category and student assignment trail are saved together');
    $manual = (new NoteRepository())->forDevice($id, manualOnly: true);
    $check(count($manual) === 1 && $manual[0]['kind'] === 'note' && $manual[0]['body'] === "Loan recorded\nby the team.", 'optional new note is saved with the same request');
    $check(count((new NoteRepository())->statusHistory($id)) === 1 && in_array($id, array_column((new DeviceRepository())->needingAttention(10000), 'device_id'), true), 'status still records immutable history and updates dashboard attention');

    $currentBody = array_replace($body, ['note' => '']);
    $snapshot = $repo->find($id); $log = db()->all('SELECT * FROM device_notes WHERE device_id = ? ORDER BY id', [$id]);
    $check($save($currentBody)->status === 303 && $repo->find($id) === $snapshot && db()->all('SELECT * FROM device_notes WHERE device_id = ? ORDER BY id', [$id]) === $log, 'saving unchanged boxes adds no notes, events or timestamp changes');
    $bad = array_replace($body, ['student_name' => 'Unsaved name', 'student_email' => 'invalid-email', 'status' => 'fixed', 'note' => 'Unsaved note']);
    $invalid = $save($bad);
    $check($invalid->status === 422 && $repo->find($id) === $snapshot && db()->all('SELECT * FROM device_notes WHERE device_id = ? ORDER BY id', [$id]) === $log, 'validation failure saves none of the boxes');
    $check(str_contains($invalid->body, 'value="Unsaved name"') && str_contains($invalid->body, 'value="invalid-email"') && str_contains($invalid->body, '>Unsaved note</textarea>'), 'all unsaved text remains available after validation failure');
    $check(str_contains($invalid->body, 'value="fixed" form="chromebook-edit" required checked'), 'unsaved status choice also remains selected');
    $tooLong = $save(array_replace($currentBody, ['note' => str_repeat('x', 2001), 'status' => 'fixed']));
    $check($tooLong->status === 422 && $repo->find($id) === $snapshot, 'an invalid new note cannot partially save a status change');

    $other = $repo->create($category, ['academic_year' => 2026, 'asset_id' => "$token-other", 'serial_number' => "$token-other-SN", 'model' => null,
        'status' => 'normal', 'student_name' => null, 'student_email' => null], null);
    $duplicate = $save(array_replace($body, ['serial_number' => "$token-other-SN", 'student_name' => 'Must roll back', 'status' => 'broken']));
    $check($duplicate->status === 422 && $repo->find($id) === $snapshot && db()->all('SELECT * FROM device_notes WHERE device_id = ? ORDER BY id', [$id]) === $log, 'duplicate serial rolls back details, student, status and new note');
    $check(str_contains($duplicate->body, 'Must roll back') && str_contains($duplicate->body, 'serial_number-error'), 'duplicate errors return to the unified page with the entered values');

    $html = $controller->show($showRequest)->body;
    $check(substr_count($html, '>Save changes</button>') === 1 && !str_contains($html, '>Save status</button>') && !str_contains($html, '>Save name and email</button>') && !str_contains($html, '>Add note</button>'), 'only one editing save button is rendered');
    $check(!str_contains($html, 'href="/chromebooks/' . $id . '/edit"') && str_contains($html, 'form="chromebook-edit">Save changes</button>'), 'top-right save replaces the separate Edit link');
    $check(strpos($html, 'id="student-details-title"') < strpos($html, 'id="status-title"'), 'Name and Email appears before Status');
    foreach (['student_name','student_email','academic_year','asset_id','serial_number','model','status','note'] as $field) {
        $check((bool) preg_match('/<(?:input|select|textarea)[^>]*name="' . $field . '"[^>]*form="chromebook-edit"/s', $html), "$field belongs to the universal form");
    }
    $check(!str_contains($html, 'Edit note') && !str_contains($html, 'Edit status change') && !str_contains($html, 'Save correction') && str_contains($html, 'History of changes'), 'notes and status history remain visible without edit controls');
    $oldEdit = new Request('GET', "/chromebooks/$id/edit", [], [], []); $oldEdit->params['id'] = (string) $id;
    $check($controller->edit($oldEdit)->headers['Location'] === "/chromebooks/$id", 'old edit-page links redirect to the combined detail page');
    $router = require BASE_PATH . '/app/routes.php';
    try { $router->dispatch(new Request('POST', "/devices/$id/notes/" . $manual[0]['id'], [], ['body' => 'Attempted history rewrite'], [])); throw new LogicException('History edit route still exists'); }
    catch (HttpException $e) { $check($e->status === 404 && db()->value('SELECT body FROM device_notes WHERE id = ?', [$manual[0]['id']]) === $manual[0]['body'], 'retired history editing endpoint cannot modify records'); }
} finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
$check(db()->all('SELECT * FROM devices ORDER BY id') === $before && db()->all('SELECT * FROM device_notes ORDER BY id') === $notesBefore && db()->all('SELECT * FROM chromebook_assignments ORDER BY id') === $assignmentsBefore, 'all editor fixtures roll back');
echo "Passed $checks unified Chromebook editor checks. Fixtures rolled back.\n";

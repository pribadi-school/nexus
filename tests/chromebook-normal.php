<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Core\Request;
use App\Devices\DeviceRepository;
use App\Devices\DeviceService;
use App\Devices\NoteRepository;
use App\Modules\Chromebooks\ChromebookController;
use App\Modules\Chromebooks\ChromebookCsv;
use App\Modules\Chromebooks\ChromebookRepository;

if (config('app.env') !== 'local') throw new RuntimeException('Local rollback tests only.');
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void { if (!$ok) throw new RuntimeException('Failed: ' . $label); $checks++; };
$before = db()->all('SELECT * FROM devices ORDER BY id');
$notesBefore = db()->all('SELECT * FROM device_notes ORDER BY id');
$repo = new ChromebookRepository(); $category = categories()->get('chromebook'); $controller = new ChromebookController();
$pdo = db()->pdo(); $pdo->beginTransaction();
try {
    $token = 'NRM-' . bin2hex(random_bytes(3));
    $result = $controller->store(new Request('POST', '/chromebooks', [], ['academic_year' => '2026', 'asset_id' => $token], []));
    $check($result->status === 303, 'a Chromebook can be added without choosing a status or writing notes');
    $id = (int) basename($result->headers['Location']);
    $check($repo->find($id)['status'] === 'normal' && (new NoteRepository())->countForDevice($id) === 0, 'new records default to Normal without synthetic notes or changes');
    $check(!in_array($id, array_column((new DeviceRepository())->needingAttention(10000), 'device_id'), true), 'Normal never appears in Needs attention');

    $request = static function (array $body) use ($id): Request { $r = new Request('POST', "/chromebooks/$id/assign", [], $body, []); $r->params['id'] = (string) $id; return $r; };
    $controller->assign($request(['student_name' => 'Normal fixture student', 'student_email' => 'normal-fixture@example.test']));
    $check($repo->find($id)['student_name'] === 'Normal fixture student' && $repo->find($id)['student_email'] === 'normal-fixture@example.test', 'restored form saves both name and email');
    $check($repo->find($id)['status'] === 'normal' && (new NoteRepository())->countForDevice($id, manualOnly: true) === 0, 'editing name/email preserves status and does not add manual notes');
    $history = $repo->history($id);
    db()->update('devices', ['updated_at' => '2020-01-01 00:00:00'], ['id' => $id]);
    $controller->assign($request(['student_name' => 'Normal fixture student', 'student_email' => 'corrected@example.test']));
    $check($repo->find($id)['student_email'] === 'corrected@example.test' && $repo->find($id)['updated_at'] !== '2020-01-01 00:00:00' && $repo->history($id) === $history, 'email-only changes update the record without creating a new student assignment');
    $show = new Request('GET', "/chromebooks/$id", [], [], []); $show->params['id'] = (string) $id;
    $html = $controller->show($show)->body;
    $check(str_contains($html, 'value="Normal fixture student"') && str_contains($html, 'value="corrected@example.test"'), 'detail page displays the saved name and email in editable fields');
    $controller->assign($request(['student_name' => 'Draft name', 'student_email' => 'invalid-email']));
    $html = $controller->show($show)->body;
    $check($repo->find($id)['student_name'] === 'Normal fixture student' && str_contains($html, 'value="Draft name"') && str_contains($html, 'value="invalid-email"') && str_contains($html, 'id="student-email-error"'), 'validation preserves entered values without saving invalid data');

    $listing = $controller->index(new Request('GET', '/chromebooks', ['q' => $token], [], []))->body;
    $check(str_contains($listing, '<th scope="col">Status</th>'), 'desktop table has its own Status column');
    preg_match('/<tbody>.*?<td>(.*?)<\/td>/s', $listing, $studentCell);
    $check(isset($studentCell[1]) && str_contains($studentCell[1], 'corrected@example.test') && !str_contains($studentCell[1], 'chromebook-status'), 'student cell contains name and email without a dash/status badge below');
    $check((bool) preg_match('/<td class="nowrap">\s*<span class="chip[^>]+>Normal<\/span>\s*<\/td>/s', $listing), 'Normal appears in the dedicated status cell');
    $check(str_contains($controller->index(new Request('GET', '/chromebooks', ['status' => 'normal', 'q' => $token], [], []))->body, $token), 'Normal is available in status filters');

    $plan = ChromebookCsv::plan([ChromebookCsv::COLUMNS, ["$token-import", '', '', '', '', '']], 2026, $repo);
    ChromebookCsv::apply($plan['rows'], 2026, $category, $repo, null);
    $imported = $repo->findByAsset("$token-import");
    $check($imported['status'] === 'normal' && (new NoteRepository())->countForDevice((int) $imported['id']) === 0, 'blank imported statuses also default to Normal without notes');

    $ids = [];
    foreach (['ok', 'unmarked', ...$category->statuses()] as $i => $status) {
        $ids[$status] = $repo->create($category, ['academic_year' => 2026, 'asset_id' => "$token-$i", 'serial_number' => null,
            'model' => null, 'status' => $status, 'student_name' => null, 'student_email' => null], null);
    }
    $savedNotes = db()->all('SELECT * FROM device_notes ORDER BY id');
    $sql = file_get_contents(BASE_PATH . '/database/migrations/012_chromebook_normal_status.sql');
    preg_match("/UPDATE devices.*?;/s", $sql, $statement);
    db()->run(rtrim($statement[0], ';') . ' AND id IN (' . implode(',', $ids) . ')');
    foreach ($ids as $status => $fixtureId) $check($repo->find($fixtureId)['status'] === (in_array($status, ['ok', 'unmarked'], true) ? 'normal' : $status), "Normal conversion preserves recorded $status condition");
    $check(db()->all('SELECT * FROM device_notes ORDER BY id') === $savedNotes, 'Normal conversion does not add or change any notes');
} finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
$check(db()->all('SELECT * FROM devices ORDER BY id') === $before && db()->all('SELECT * FROM device_notes ORDER BY id') === $notesBefore, 'fixtures roll back and real records remain unchanged');
echo "Passed $checks Normal status and student detail checks. Fixtures rolled back.\n";

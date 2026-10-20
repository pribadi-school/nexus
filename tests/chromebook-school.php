<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Request;
use App\Modules\Chromebooks\ChromebookCategory;
use App\Modules\Chromebooks\ChromebookController;
use App\Modules\Chromebooks\ChromebookCsv;
use App\Modules\Chromebooks\ChromebookRepository;

if (config('app.env') !== 'local') throw new RuntimeException('Local rollback tests only.');
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('Failed: ' . $label);
    $checks++;
};
$repo = new ChromebookRepository(); $category = new ChromebookCategory(); $controller = new ChromebookController();
$before = db()->all('SELECT * FROM chromebooks ORDER BY device_id');
$pdo = db()->pdo(); $pdo->beginTransaction();
try {
    $schoolBefore = $repo->counts(ChromebookCategory::SCHOOL_YEAR)['total'];
    $years = $category->years();
    $check($years[0] >= ChromebookCategory::FIRST_YEAR && end($years) === 0 && count(array_keys($years, 0, true)) === 1, 'numeric default year stays first and School appears once');
    $token = 'SCH-' . bin2hex(random_bytes(5));
    $make = static function (int $year, string $suffix) use ($controller, $token): int {
        $response = $controller->store(new Request('POST', '/chromebooks', [], [
            'academic_year' => (string) $year, 'asset_id' => $token . $suffix, 'serial_number' => $token . '-serial-' . $suffix,
            'model' => 'School fixture', 'student_name' => 'Fixture school lab', 'student_email' => '', 'status' => 'problem'], []));
        if ($response->status !== 303) throw new RuntimeException('Fixture create failed: ' . $response->body);
        return (int) basename($response->headers['Location']);
    };
    $school = $make(0, 'A'); $student = $make($years[0], 'B');
    $check((int) $repo->find($school)['academic_year'] === 0, 'Add Chromebook accepts School without a schema change');
    $filters = ['year' => 0, 'q' => $token, 'status' => null];
    $check(array_column($repo->search($filters)['rows'], 'id') === [$school], 'School filter never becomes All years');
    $check($repo->search(array_replace($filters, ['year' => null]))['total'] === 2, 'All years includes school-owned and student devices');
    $check($repo->counts(0)['total'] === $schoolBefore + 1 && $repo->yearCounts()[0]['total'] === $schoolBefore + 1, 'School counts remain separate from numeric years');
    $page = $controller->index(new Request('GET', '/chromebooks', ['year' => '0', 'q' => $token], [], []));
    $check(str_contains($page->body, '<option value="0" selected>School') && str_contains($page->body, 'Chromebooks School'), 'School filter, heading and breadcrumb render the human label');
    $create = $controller->create(new Request('GET', '/chromebooks/new', ['year' => '0'], [], []));
    $check(str_contains($create->body, '<option value="0" selected>School</option>') && str_contains($create->body, 'e.g. School-01'), 'Add form preselects School and uses a readable example');
    $showRequest = new Request('GET', '/chromebooks/' . $school, [], [], []); $showRequest->params['id'] = (string) $school;
    $show = $controller->show($showRequest);
    $check(str_contains($show->body, '<dt>Year / category</dt><dd>School</dd>') && !str_contains($show->body, 'Academic year 0'), 'detail page never exposes the storage sentinel');
    $export = $controller->export(new Request('GET', '/chromebooks/export', ['year' => '0', 'q' => $token], [], []));
    $check(str_contains($export->headers['Content-Disposition'], 'chromebooks-school-') && str_contains($export->body, $token . 'A') && !str_contains($export->body, $token . 'B'), 'CSV export respects School and its filename');
    $example = $controller->example(new Request('GET', '/chromebooks/import/example', ['year' => '0'], [], []));
    $check(str_contains($example->body, 'School-01'), 'School example CSV uses school asset IDs');
    $history = $repo->history($student);
    $update = new Request('POST', '/chromebooks/' . $student, [], ['academic_year' => '0',
        'asset_id' => $token . 'B', 'serial_number' => $token . '-serial-B', 'model' => 'School fixture'], []);
    $update->params['id'] = (string) $student;
    $check($controller->update($update)->status === 303 && (int) $repo->find($student)['academic_year'] === 0, 'Edit moves an existing Chromebook into School');
    $check($repo->history($student) === $history && $repo->find($student)['student_name'] === 'Fixture school lab', 'changing category preserves assignment and history');
    $import = $controller->importForm(new Request('GET', '/chromebooks/import', ['year' => '0'], [], []));
    $check(str_contains($import->body, '<option value="0" selected>School</option>') && str_contains($import->body, '/chromebooks/import/example?year=0'), 'import form preserves the School selection');
    $table = [ChromebookCsv::COLUMNS, [$token . 'C', $token . '-serial-C', '', '', 'Fixture imported lab', 'OK']];
    $plan = ChromebookCsv::plan($table, 0, $repo);
    $check($plan['error'] === null && $plan['rows'][0]['action'] === 'add', 'School import previews new devices');
    $applied = ChromebookCsv::apply($plan['rows'], 0, $category, $repo, null);
    $check($applied['added'] === 1 && (int) $repo->findByAsset($token . 'C')['academic_year'] === 0, 'School import saves in the School category');
    $conflict = ChromebookCsv::plan([ChromebookCsv::COLUMNS, [$token . 'A', '', '', '', '', '']], $years[0], $repo);
    $check($conflict['rows'][0]['action'] === 'error' && str_contains($conflict['rows'][0]['message'], 'School list'), 'cross-category imports cannot silently move existing devices');
    $invalid = $controller->store(new Request('POST', '/chromebooks', [], ['academic_year' => '-1', 'status' => 'ok'], []));
    $check($invalid->status === 422, 'invalid categories are rejected');
    $edit = new Request('GET', '/chromebooks/' . $school . '/edit', [], [], []); $edit->params['id'] = (string) $school;
    $check(str_contains($controller->edit($edit)->body, '<option value="0" selected>School</option>'), 'Edit keeps the School option selected');
} finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
$check(db()->all('SELECT * FROM chromebooks ORDER BY device_id') === $before, 'all existing Chromebook data remains unchanged');
echo "Passed $checks School category checks. Fixtures rolled back.\n";

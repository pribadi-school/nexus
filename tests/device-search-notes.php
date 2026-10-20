<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Request;
use App\Core\View;
use App\Devices\DeviceRepository;
use App\Devices\DeviceService;
use App\Modules\Cctv\CctvController;
use App\Modules\Cctv\CctvRepository;
use App\Modules\Chromebooks\ChromebookController;
use App\Modules\Chromebooks\ChromebookRepository;
use App\Modules\Dashboard\DashboardController;
use App\Modules\StudyTouch\StudyTouchController;
use App\Modules\StudyTouch\StudyTouchRepository;

if (config('app.env') !== 'local') throw new RuntimeException('Local rollback tests only.');
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('Failed: ' . $label);
    $checks++;
};
$before = db()->all('SELECT id, status, updated_at FROM devices ORDER BY id');
$notesBefore = (int) db()->value('SELECT COUNT(*) FROM device_notes');
$pdo = db()->pdo();
$pdo->beginTransaction();
try {
    $token = 'SRCH-' . bin2hex(random_bytes(4));
    $search = new DeviceRepository();
    $ids = [];
    $room = db()->insert('rooms', ['name' => $token . '-room', 'sort_order' => 999]);
    $location = db()->insert('cctv_locations', ['name' => $token . '-location', 'sort_order' => 999]);
    $author = db()->insert('users', ['name' => $token . '-author']);
    foreach (['studytouch', 'cctv', 'chromebook'] as $key) {
        $ids[$key] = (new DeviceService())->create(categories()->get($key), [
            'serial_number' => $token . '-' . $key, 'model' => $token . '-model', 'status' => 'ok',
        ], null);
    }
    db()->insert('studytouch_boards', ['device_id' => $ids['studytouch'], 'room_id' => $room, 'firmware_version' => $token . '-fw']);
    db()->insert('cctv_cameras', ['device_id' => $ids['cctv'], 'label' => $token . '-camera', 'location_id' => $location, 'sd_card_gb' => 256]);
    db()->insert('chromebooks', ['device_id' => $ids['chromebook'], 'academic_year' => 0, 'asset_id' => $token . '-asset', 'student_name' => $token . '-student', 'student_email' => $token . '@example.test']);
    db()->insert('chromebook_assignments', ['device_id' => $ids['chromebook'], 'student_name' => $token . '-former', 'class_name' => null, 'assigned_at' => '2025-01-01 00:00:00', 'ended_at' => '2025-02-01 00:00:00']);
    $addNote = static fn (int $id, string $body, string $kind, string $at, ?int $user = null): int => db()->insert('device_notes', [
        'device_id' => $id, 'body' => $body, 'kind' => $kind, 'created_at' => $at, 'user_id' => $user,
    ]);
    $keyword = $token . '-touchfailure';
    $latest = 'Fixed <img src=x onerror=alert(1)> ' . $token;
    foreach ($ids as $id) {
        $addNote($id, str_repeat('Old context. ', 30) . $keyword . ' naïve display issue', 'problem', '2020-01-01 00:00:00', $author);
        $addNote($id, $latest, 'fix', '2026-10-19 10:00:00');
        $addNote($id, 'A later ID with an older timestamp', 'report', '2026-10-18 10:00:00');
        $addNote($id, 'Automatic status changed', 'status', '2026-10-20 10:00:00');
    }
    $found = $search->search($keyword);
    $check(count($found) === 3 && count(array_unique(array_column($found, 'device_id'))) === 3, 'historical problem text finds every category once, including currently OK devices');
    $check(count(array_filter($found, static fn ($row) => str_contains($row['matching_note'], $keyword))) === 3, 'results carry the matching historical note, not an unrelated newer note');
    $check(count($search->search('  ' . $keyword . '  ')) === 3 && $search->search('   ') === [], 'search trims whitespace and empty input does not return everything');
    $check(count($search->search($token . '-author')) === 3, 'note authors are searchable');
    foreach (['-room', '-fw', '-camera', '-location', '-asset', '-student', '@example.test', '-former'] as $term) {
        $q = $token . $term;
        $check(count($search->search($q)) === 1, 'metadata or previous assignment is searchable: ' . $term);
    }
    $check(count($search->search($token . '-model')) === 3, 'models are searchable across categories');
    $check(in_array($ids['chromebook'], array_column($search->search('School'), 'device_id'), true), 'School is searchable by its label');
    $check(in_array($ids['cctv'], array_column($search->search('256 GB'), 'device_id'), true), 'SD card capacity is searchable');
    $check(in_array($ids['studytouch'], array_column($search->search('Running well'), 'device_id'), true), 'visible status labels are searchable');
    $check(count($search->search($keyword, 1)) === 3, 'limits apply independently to each category');
    $literal = $token . ' 100%_done\\path';
    $addNote($ids['studytouch'], $literal, 'report', '2020-02-01 00:00:00');
    $check(count($search->search($literal)) === 1 && $search->search($token . ' 100%_other') === [], 'percent, underscore and backslash are treated as literal text');
    $check($search->search("' OR 1=1 -- " . $token) === [], 'SQL-looking input cannot broaden results');
    $page = (new DashboardController())->search(new Request('GET', '/search', ['q' => $keyword], [], []))->body;
    $check(substr_count($page, '<mark>' . $keyword . '</mark>') === 3 && str_contains($page, '#notes'), 'long matching notes are centred, highlighted and linked to notes');
    $unsafePage = (new DashboardController())->search(new Request('GET', '/search', ['q' => $token], [], []))->body;
    $check(str_contains($unsafePage, '&lt;img') && !str_contains($unsafePage, '<img src=x'), 'search note HTML is escaped before highlighting');
    $unicode = View::partial('Dashboard::_matching-note', ['query' => 'écran', 'row' => [
        'matching_note' => str_repeat('界', 300) . 'ÉCRAN tactile', 'matching_note_kind' => 'problem', 'matching_note_at' => '2026-10-19 10:00:00', 'matching_note_author' => null,
    ]]);
    $check(str_contains($unicode, '<mark>ÉCRAN</mark>') && mb_check_encoding($unicode, 'UTF-8'), 'Unicode matching and excerpt boundaries preserve text');
    $repos = ['studytouch' => new StudyTouchRepository(), 'cctv' => new CctvRepository(), 'chromebook' => new ChromebookRepository()];
    $controllers = ['studytouch' => new StudyTouchController(), 'cctv' => new CctvController(), 'chromebook' => new ChromebookController()];
    foreach ($ids as $key => $id) {
        $row = $repos[$key]->find($id);
        $check($row['latest_note'] === $latest, "$key latest note ignores automatic status and sorts by date before ID");
        $newest = $latest . ' newest';
        $addNote($id, $newest, 'report', '2026-10-19 10:00:00');
        $check($repos[$key]->find($id)['latest_note'] === $newest, "$key same-time notes resolve to newest ID");
        $path = categories()->get($key)->path();
        $html = $controllers[$key]->index(new Request('GET', $path, ['q' => $token], [], []))->body;
        $check(str_contains($html, '<th scope="col">Latest note</th>') && substr_count($html, e($newest)) >= 2, "$key table and mobile cards show latest notes for OK devices");
        $check(!str_contains($html, '<img src=x'), "$key note text cannot inject HTML");
        db()->update('devices', ['status' => 'problem'], ['id' => $id]);
        $problemHtml = $controllers[$key]->index(new Request('GET', $path, ['q' => $token], [], []))->body;
        $check(str_contains($problemHtml, 'is-problem') && str_contains($problemHtml, e($newest)), "$key problem flags and latest notes coexist");
    }
    $empty = (new DeviceService())->create(categories()->get('cctv'), ['serial_number' => null, 'model' => null, 'status' => 'ok'], null);
    db()->insert('cctv_cameras', ['device_id' => $empty, 'label' => $token . '-empty']);
    $addNote($empty, 'Only automatic status', 'status', '2026-10-20 10:00:00');
    $check($repos['cctv']->find($empty)['latest_note'] === null && str_contains(View::partial('partials/latest-note', ['device' => $repos['cctv']->find($empty)]), 'No note yet'), 'no written note has an explicit empty state');
    db()->update('devices', ['deleted_at' => '2026-10-20 00:00:00'], ['id' => $ids['cctv']]);
    $check(count($search->search($keyword)) === 2 && $repos['cctv']->find($ids['cctv']) === null, 'deleted devices stay out of search and lists even when their notes match');
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}
$check(db()->all('SELECT id, status, updated_at FROM devices ORDER BY id') === $before && (int) db()->value('SELECT COUNT(*) FROM device_notes') === $notesBefore, 'fixtures roll back without changing existing device data or history');
echo "Passed $checks device search and latest-note checks. Fixtures rolled back.\n";

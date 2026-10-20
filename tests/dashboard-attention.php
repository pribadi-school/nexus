<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Request;
use App\Devices\DeviceRepository;
use App\Devices\DeviceService;
use App\Modules\Chromebooks\ChromebookRepository;
use App\Modules\Dashboard\DashboardController;

if (config('app.env') !== 'local') throw new RuntimeException('Local rollback tests only.');
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('Failed: ' . $label);
    $checks++;
};
$before = db()->all('SELECT * FROM devices ORDER BY id');
$notesBefore = db()->all('SELECT * FROM device_notes ORDER BY id');
$repo = new DeviceRepository();
$initial = $repo->attentionCounts();
$pdo = db()->pdo();
$pdo->beginTransaction();
try {
    $token = 'ATT-' . bin2hex(random_bytes(3));
    $chromebooks = new ChromebookRepository();
    $make = static function (string $suffix, string $status = 'unmarked') use ($chromebooks, $token): int {
        return $chromebooks->create(categories()->get('chromebook'), [
            'academic_year' => 2026, 'asset_id' => "$token-$suffix", 'serial_number' => null,
            'model' => null, 'status' => $status, 'student_name' => null, 'student_email' => null,
        ], null);
    };
    $note = static fn (int $id, string $kind, string $body = 'Attention fixture', string $at = '2030-01-01 10:00:00'): int => db()->insert('device_notes', [
        'device_id' => $id, 'user_id' => null, 'kind' => $kind, 'body' => $body, 'created_at' => $at,
    ]);
    $problem = $make('problem', 'problem'); $note($problem, 'note', 'Fixture cracked screen');
    $borrowed = $make('borrowed', 'borrowed'); $note($borrowed, 'note', 'Fixture loan for class');
    $fallback = $make('fallback', 'problem');
    $fixed = $make('fixed', 'fixed'); $note($fixed, 'problem');
    $replaced = $make('replaced', 'replaced'); $note($replaced, 'borrowed');
    $broken = $make('broken', 'broken'); $note($broken, 'note');
    $text = $make('text'); $note($text, 'report', 'The words problem and borrowed in a report are not a state.');
    $deleted = $make('deleted', 'borrowed'); $note($deleted, 'note'); (new DeviceService())->delete($deleted, null);
    $camera = (new DeviceService())->create(categories()->get('cctv'), ['serial_number' => null, 'model' => null, 'status' => 'problem'], null);
    db()->insert('cctv_cameras', ['device_id' => $camera, 'label' => "$token-camera"]);
    $note($camera, 'report', 'Camera needs inspection');

    $rows = $repo->needingAttention(10000);
    $byId = array_column($rows, null, 'device_id');
    $check($byId[$problem]['attention_kind'] === 'problem', 'Problem status needs attention');
    $check($byId[$borrowed]['attention_kind'] === 'borrowed', 'Borrowed status needs attention');
    $check($byId[$fallback]['attention_kind'] === 'problem', 'an existing problem status remains visible when there is no manual note');
    $check($byId[$camera]['attention_kind'] === 'problem', 'other device categories still use their current problem status');
    foreach ([$fixed, $replaced, $broken, $text, $deleted] as $id) $check(!isset($byId[$id]), 'resolved, other kinds and deleted devices are excluded: ' . $id);
    $check($repo->attentionCounts() === ['problem' => $initial['problem'] + 3, 'borrowed' => $initial['borrowed'] + 1], 'counts include both kinds, once per device');
    $check(count($rows) === array_sum($repo->attentionCounts()), 'the full list and summary use identical membership');
    $limited = $repo->needingAttention(1);
    $check(count($limited) === 1 && array_sum($repo->attentionCounts()) > count($limited), 'the summary is not capped by the list limit');

    $note($borrowed, 'status', 'An automatic status update', '2031-01-01 00:00:00');
    $note($borrowed, 'assignment', 'An automatic assignment', '2032-01-01 00:00:00');
    $byId = array_column($repo->needingAttention(10000), null, 'device_id');
    $check($byId[$borrowed]['latest_note'] === 'Fixture loan for class' && $byId[$borrowed]['attention_since'] === $chromebooks->find($borrowed)['status_changed_at'], 'notes do not change the status date');
    (new DeviceService())->changeStatus(categories()->get('chromebook'), $chromebooks->find($problem), 'borrowed', null);
    db()->update('devices', ['status_changed_at' => '2030-01-02 00:00:00'], ['id' => $problem]);
    $counts = $repo->attentionCounts();
    $check($counts === ['problem' => $initial['problem'] + 2, 'borrowed' => $initial['borrowed'] + 2], 'Borrowed status moves the device between counts without duplicating it');
    $byId = array_column($repo->needingAttention(10000), null, 'device_id');
    $check($byId[$problem]['attention_kind'] === 'borrowed' && $repo->needingAttention(1)[0]['device_id'] === $problem, 'status timestamp controls attention list order');
    (new DeviceService())->changeStatus(categories()->get('chromebook'), $chromebooks->find($problem), 'fixed', null);
    $check(!in_array($problem, array_column($repo->needingAttention(10000), 'device_id'), true), 'Fixed status clears Borrowed');
    (new DeviceService())->changeStatus(categories()->get('chromebook'), $chromebooks->find($fixed), 'problem', null);
    $check(in_array($fixed, array_column($repo->needingAttention(10000), 'device_id'), true), 'Problem status can reopen attention after Fixed');

    $html = (new DashboardController())->index(new Request('GET', '/', [], [], []))->body;
    $check(str_contains($html, 'attention-row--problem') && str_contains($html, 'attention-row--borrowed'), 'dashboard renders distinct row color classes for both kinds');
    $check(str_contains($html, 'chip--problem attention-kind') && str_contains($html, 'chip--action attention-kind'), 'red Problem and blue Borrowed badges have explicit text labels');
    $check(str_contains($html, '>Borrowed</span>') && str_contains($html, '>Problem</span>'), 'kind labels remain readable when other table columns are hidden on mobile');
    $check(!str_contains($html, 'devices have a problem') && str_contains($html, 'Problem and borrowed devices, newest first.'), 'combined attention is not described as only problems');
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}
$check(db()->all('SELECT * FROM devices ORDER BY id') === $before && db()->all('SELECT * FROM device_notes ORDER BY id') === $notesBefore, 'fixtures roll back without changing real data');
echo "Passed $checks dashboard attention checks. Fixtures rolled back.\n";

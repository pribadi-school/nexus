<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Modules\Signage\SignageRepository;
use App\Modules\Signage\SignageTvControl;

if (config('app.env') !== 'local') { fwrite(STDERR, "Local database only.\n"); exit(1); }
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('Failed: ' . $message);
    $checks++;
};
$failure = null;
$tables = ['signage_screens', 'signage_playlists', 'signage_tv_commands', 'signage_tv_agents'];
$before = [];
foreach ($tables as $table) $before[$table] = db()->value("SELECT COUNT(*) FROM $table");
$pdo = db()->pdo();
$pdo->beginTransaction();
try {
    $repo = new SignageRepository();
    $control = new SignageTvControl();
    $screenData = ['name' => 'TV control regression', 'location' => null, 'orientation' => 'landscape', 'fit' => 'contain', 'muted' => 1, 'enabled' => 1];
    $screen = $repo->createScreenWithPlaylist($screenData + ['playlist_id' => null]);
    $other = $repo->createScreenWithPlaylist(array_replace($screenData, ['name' => 'Other TV']) + ['playlist_id' => null]);

    try { $control->queue($screen, 'rm -rf /', null); $check(false, 'free-form action refused'); }
    catch (RuntimeException) { $check(true, 'free-form action refused'); }
    $restart = $control->queue($screen, 'restart', null);
    $check($control->queue($screen, 'restart', null) === $restart, 'pressing twice does not queue a second restart');
    $status = $control->queue($other, 'status', null);

    $check($control->helperFor($screen) === null, 'no helper before check-in');
    $claimed = $control->poll('Test helper', $screen . ',junk,0', '1.0.0');
    $check(array_column($claimed, 'id') === [$restart], 'helper receives only actions for its own screens');
    $check($control->helperFor($screen) !== null && $control->helperFor($other) === null, 'helper online only for listed screens');
    $check($control->poll('Test helper', (string) $screen, '1.0.0') === [], 'running actions are not handed out twice');

    $control->finish($restart, true, "Restart\n sent.", null);
    $row = $control->command($screen, $restart);
    $check($row['status'] === 'done' && $row['result'] === 'Restart sent.' && $row['finished_at'] !== null, 'result recorded and whitespace tidied');
    try { $control->finish($restart, true, 'again', null); $check(false, 'finished action cannot be reported twice'); }
    catch (RuntimeException) { $check(true, 'finished action cannot be reported twice'); }

    $shot = $control->queue($screen, 'screenshot', null);
    $control->poll('Test helper', (string) $screen, '1.0.0');
    $control->finish($shot, true, 'Screenshot taken.', base64_encode('not an image'));
    $row = $control->command($screen, $shot);
    $check($row['status'] === 'failed' && $row['screenshot_filename'] === null, 'invalid screenshot is refused, not stored');
    $check($control->command($other, $shot) === null, 'commands are scoped to their screen');

    db()->run('UPDATE signage_tv_commands SET created_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() - 3600), $status]);
    $control->recent($other);
    $check($control->command($other, $status)['status'] === 'expired', 'old queued actions expire instead of running late');

    $html = partial('Signage::_tv-control', ['screen' => $repo->screen($screen)]);
    $check(str_contains($html, 'Helper PC online') && str_contains($html, 'data-confirm="Restart this TV?"'), 'card shows helper state and confirms restart');
    foreach (array_keys(SignageTvControl::ACTIONS) as $action) $check(str_contains($html, 'value="' . $action . '"'), "card has $action button");
} catch (Throwable $error) { $failure = $error; }
finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
foreach ($tables as $table) {
    if (db()->value("SELECT COUNT(*) FROM $table") !== $before[$table]) { fwrite(STDERR, "Rollback failed.\n"); exit(1); }
}
if ($failure) { fwrite(STDERR, $failure->getMessage() . "\n"); exit(1); }
echo "Passed $checks TV control checks. All changes rolled back.\n";

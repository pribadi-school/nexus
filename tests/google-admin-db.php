<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$app = require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/support/GoogleFixture.php';

use App\Auth\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Modules\Chromebooks\ChromebookController;
use App\Modules\Chromebooks\ChromebookRepository;
use App\Modules\Chromebooks\GoogleApiError;
use App\Modules\Chromebooks\GoogleChromebookController;
use App\Modules\Chromebooks\GoogleDeviceService;

if (config('app.env') !== 'local') { fwrite(STDERR, "Local database only.\n"); exit(1); }
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('Failed: ' . $label);
    $checks++;
};
$reject = static function (Closure $fn, string $label) use ($check): void {
    try { $fn(); } catch (GoogleApiError) { $check(true, $label); return; }
    $check(false, $label);
};
$httpReject = static function (Closure $fn, int $status, string $label) use ($check): void {
    try { $fn(); } catch (HttpException $e) { $check($e->status === $status, $label); return; }
    $check(false, $label);
};
$config = new ReflectionProperty(Config::class, 'items');
$oldConfig = $config->getValue();
$authUser = new ReflectionProperty(Auth::class, 'user');
$authLoaded = new ReflectionProperty(Auth::class, 'loaded');
$oldUser = $authUser->getValue(); $oldLoaded = $authLoaded->getValue();
$fixture = new GoogleFixture();
$pdo = db()->pdo();
$pdo->beginTransaction();
try {
    $serial = 'GOOGLE-TEST-' . bin2hex(random_bytes(6));
    $fixture->device['serialNumber'] = $serial;
    $fixture->settings['ou_password_hash'] = password_hash('fixture-management-password', PASSWORD_DEFAULT);
    $config->setValue(null, array_replace($oldConfig, ['google' => $fixture->settings]));
    $userId = db()->insert('users', ['name' => $serial, 'created_at' => Database::now()]);
    $deviceId = db()->insert('devices', ['category' => 'chromebook', 'serial_number' => $serial, 'model' => 'Local model',
        'status' => 'problem', 'created_at' => Database::now(), 'updated_at' => Database::now()]);
    db()->insert('chromebooks', ['device_id' => $deviceId, 'academic_year' => 2026, 'student_name' => 'Local student']);
    $device = (new ChromebookRepository())->find($deviceId);
    $authUser->setValue(null, ['id' => $userId, 'name' => $serial]); $authLoaded->setValue(null, true);
    $client = $fixture->client();
    $service = new GoogleDeviceService($client);
    $cache = $service->refresh($device);
    $check($cache['report']['lan_ip'] === '192.0.2.20' && $cache['refreshed_at'] && !$cache['error'], 'report cached with separate refresh timestamp');
    $calls = count($fixture->calls);
    $service->refresh($device);
    $service->refresh($device, true);
    $check(count($fixture->calls) === $calls, 'fresh automatic refresh and duplicate manual requests do not hit Google');
    $local = (new ChromebookRepository())->find($deviceId);
    $check($local['student_name'] === 'Local student' && $local['model'] === 'Local model' && $local['status'] === 'problem', 'Google data never overwrites local inventory');
    $past = gmdate('Y-m-d H:i:s', time() - 600);
    db()->update('chromebook_google_cache', ['attempted_at' => $past, 'refreshed_at' => $past], ['device_id' => $deviceId]);
    $fixture->telemetryStatus = 403;
    $cache = $service->refresh($device);
    $check($cache['report']['telemetry_warning'] && $cache['report']['network_state'] === null && $cache['report']['lan_source'] === 'directory', 'telemetry permission failure preserves Directory access without inventing network state');
    db()->update('chromebook_google_cache', ['attempted_at' => $past, 'refreshed_at' => $past], ['device_id' => $deviceId]);
    $fixture->readStatus = 403;
    $before = $cache['report'];
    $cache = $service->refresh($device);
    $check($cache['error'] && $cache['report'] === $before && $cache['refreshed_at'] === $past, 'failed refresh retains saved report and old freshness');
    $calls = count($fixture->calls); $service->refresh($device);
    $check(count($fixture->calls) === $calls, 'failure backoff prevents repeated calls');
    $changed = $device; $changed['serial_number'] = 'OTHER-SERIAL';
    $check($service->cached($changed) === null, 'serial changes hide the previous device report');
    $fixture->settings['customer_id'] = 'C_DIFFERENT';
    $other = new GoogleDeviceService($fixture->client());
    $check($other->cached($device) === null, 'tenant changes hide previous account data');
    $fixture->settings['customer_id'] = 'C_TEST';
    $fixture->readStatus = 200; $fixture->telemetryStatus = 200;
    $reject(fn () => $service->move($device, '/Staff', '/Old', $userId), 'stale OU form rejected');
    $reject(fn () => $service->move($device, '/Missing', '/Students', $userId), 'unlisted target OU rejected');
    $check($service->moves($deviceId) === [], 'validation failures never write remote move audit');
    $service->move($device, '/Staff', '/Students', $userId);
    $moves = $service->moves($deviceId);
    $check($moves[0]['state'] === 'accepted' && $moves[0]['from_ou'] === '/Students' && $moves[0]['to_ou'] === '/Staff'
        && (int) $moves[0]['requested_by'] === $userId, 'accepted OU change audited with actor and source/target');
    $check($service->cached($device)['refreshed_at'] === null, 'accepted OU change invalidates report freshness for verification');
    $cache = $service->refresh($device, true);
    $check($cache['report']['ou'] === '/Staff', 'follow-up read verifies new OU');
    $fixture->moveTimeout = true;
    $reject(fn () => $service->move($device, '/Students', '/Staff', $userId), 'ambiguous move timeout surfaced');
    $check($service->moves($deviceId)[0]['state'] === 'unconfirmed', 'ambiguous response audited without claiming rejection');
    $fixture->moveTimeout = false; $fixture->moveStatus = 403;
    $reject(fn () => $service->move($device, '/Students', '/Staff', $userId), 'Google write denial surfaced');
    $check($service->moves($deviceId)[0]['state'] === 'rejected', 'explicit denial audited as rejected');
    $controller = new GoogleChromebookController();
    $request = new Request('POST', '/', [], ['management_password' => 'wrong', 'confirm' => '1'], ['REMOTE_ADDR' => '203.0.113.250']);
    $request->params = ['id' => (string) $deviceId];
    for ($i = 0; $i < 5; $i++) $httpReject(fn () => $controller->move($request), 403, 'incorrect management password blocked');
    $httpReject(fn () => $controller->move($request), 429, 'repeated password guesses throttled');
    $missingConfirm = new Request('POST', '/', [], ['management_password' => 'fixture-management-password'], ['REMOTE_ADDR' => '203.0.113.251']);
    $missingConfirm->params = $request->params;
    $httpReject(fn () => $controller->move($missingConfirm), 422, 'correct password still requires explicit confirmation');
    $page = new Request('GET', '/', [], [], []); $page->params = $request->params;
    $html = (new ChromebookController())->show($page)->body;
    $check(str_contains($html, 'google-admin') && str_contains($html, '192.0.2.20') && str_contains($html, 'Local student'), 'detail page renders cached Google report alongside local data without network');
    $config->setValue(null, array_replace($oldConfig, ['google' => array_replace($fixture->settings, ['ou_password_hash' => ''])]));
    $httpReject(fn () => $controller->move($missingConfirm), 403, 'OU management disabled without management hash');
    $router = require BASE_PATH . '/app/routes.php';
    $httpReject(fn () => $router->dispatch(new Request('POST', '/chromebooks/' . $deviceId . '/google/refresh', [], [], [])), 419, 'refresh endpoint requires CSRF');
    $httpReject(fn () => $router->dispatch(new Request('POST', '/chromebooks/' . $deviceId . '/google/ou', [], [], [])), 419, 'OU endpoint requires CSRF');
    $authUser->setValue(null, null);
    $response = $router->dispatch(new Request('GET', '/chromebooks/' . $deviceId . '/google/ou', [], [], ['HTTP_X_REQUESTED_WITH' => 'fetch']));
    $check($response->status === 401, 'Google endpoints require signed-in Nexus session');
    echo "Google database checks: $checks passed.\n";
} finally {
    $pdo->rollBack();
    $fixture->cleanup();
    $config->setValue(null, $oldConfig);
    $authUser->setValue(null, $oldUser); $authLoaded->setValue(null, $oldLoaded);
}

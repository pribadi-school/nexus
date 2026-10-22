<?php

declare(strict_types=1);

// Run on the local database. All pairing, throttle and screen rows roll back.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Auth\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Modules\Signage\SignagePairingController;
use App\Modules\Signage\SignagePairingRepository;
use App\Modules\Signage\SignageRepository;

if (config('app.env') !== 'local') {
    fwrite(STDERR, "Refusing to run: APP_ENV must be local.\n");
    exit(1);
}
if (db()->value("SELECT 1 FROM schema_migrations WHERE version = '007_signage_pairing'") === null) {
    fwrite(STDERR, "Apply migration 007_signage_pairing to the local database first.\n");
    exit(1);
}
$checks = 0;
$check = static function (bool $condition, string $description) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('Failed: ' . $description);
    }
    $checks++;
};
$httpError = static function (int $status, callable $action, string $description) use ($check): void {
    try {
        $action();
    } catch (HttpException $error) {
        $check($error->status === $status, $description);
        return;
    }
    throw new RuntimeException('Failed: ' . $description . ' was accepted');
};
$request = static fn (string $path, array $body = [], string $ip = '192.0.2.101', string $method = 'POST'): Request =>
    new Request($method, $path, [], $body, ['REMOTE_ADDR' => $ip]);
$json = static fn ($response): array => json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
$tables = ['signage_pairings', 'signage_pairing_ips', 'signage_screens', 'signage_content', 'signage_playlists', 'signage_playlist_items'];
$before = [];
foreach ($tables as $table) {
    $before[$table] = db()->all("SELECT * FROM $table ORDER BY " . ($table === 'signage_pairing_ips' ? 'ip_hash' : 'id'));
}
$sessionBefore = $_SESSION ?? [];
$authUser = new ReflectionProperty(Auth::class, 'user');
$authLoaded = new ReflectionProperty(Auth::class, 'loaded');
$userBefore = $authUser->getValue();
$loadedBefore = $authLoaded->getValue();
$pdo = db()->pdo();
$pdo->beginTransaction();
$failure = null;
try {
    $repository = new SignagePairingRepository();
    $controller = new SignagePairingController();
    $router = require BASE_PATH . '/app/routes.php';
    $registeredRoutes = (new ReflectionProperty($router, 'routes'))->getValue($router);
    $exempt = array_values(array_map(static fn (array $route): string => $route['pattern'], array_filter(
        $registeredRoutes, static fn (array $route): bool => $route['method'] === 'POST' && !$route['csrf'],
    )));
    sort($exempt);
    $check($exempt === ['/signage/api/pairing/poll', '/signage/api/pairing/start', '/signage/api/tv-agent/poll', '/signage/api/tv-agent/result'], 'only token-checked device APIs (pairing and the TV helper) are exempt from session CSRF');
    $httpError(419, fn () => $router->dispatch($request('/login', ['name' => 'Test', 'password' => 'invalid'])), 'existing login POST retains CSRF protection');
    $httpError(419, fn () => $router->dispatch($request('/signage/screens', ['name' => 'Test'])), 'existing signage screen POST retains CSRF protection');
    $deviceKey = bin2hex(random_bytes(32));
    $start = $router->dispatch($request('/signage/api/pairing/start', ['device_key' => $deviceKey, 'device_name' => 'Test Android TV']));
    $started = $json($start);
    $check($start->status === 200 && $started['status'] === 'pending' && preg_match('/^[0-9]{6}$/D', $started['code']) === 1, 'public start works without session CSRF token');
    $check($started['expires_in'] > 590 && $started['expires_in'] <= 600 && $started['poll_seconds'] === 5, 'pairing expiry and poll interval');
    $row = db()->one('SELECT * FROM signage_pairings WHERE device_key_hash = ?', [hash('sha256', $deviceKey)]);
    $check($row !== null && !in_array($deviceKey, $row, true) && !in_array('192.0.2.101', $row, true), 'only hashed device key and hashed IP are stored');
    $repeat = $repository->start($deviceKey, 'Changed test name', '192.0.2.102');
    $check($repeat['code'] === $started['code'] && (int) db()->value('SELECT COUNT(*) FROM signage_pairings WHERE device_key_hash = ?', [hash('sha256', $deviceKey)]) === 1, 'repeated start is idempotent across addresses');
    $pending = $json($router->dispatch($request('/signage/api/pairing/poll', ['device_key' => $deviceKey, 'code' => $started['code']])));
    $check($pending['status'] === 'pending' && !isset($pending['player_token']), 'public pending poll never reveals a player token');
    $wrongCode = str_pad((string) (((int) $started['code'] + 1) % 1000000), 6, '0', STR_PAD_LEFT);
    $httpError(410, fn () => $repository->poll($deviceKey, $wrongCode), 'wrong pairing code is rejected');
    $httpError(410, fn () => $repository->poll(bin2hex(random_bytes(32)), $started['code']), 'unknown device secret is rejected');
    $bad = $controller->start($request('/signage/api/pairing/start', ['device_key' => ['bad']]));
    $check($bad->status === 422 && $json($bad)['status'] === 'error', 'array-shaped device secret produces safe JSON validation error');
    $badName = $controller->start($request('/signage/api/pairing/start', ['device_key' => $deviceKey, 'device_name' => ['bad']]));
    $check($badName->status === 422, 'array-shaped device name is rejected');
    $httpError(422, fn () => $repository->start(bin2hex(random_bytes(32)), str_repeat('x', 161), '192.0.2.101'), 'device name length is bounded');
    $httpError(422, fn () => $repository->poll($deviceKey, '12345'), 'short pairing code is rejected');

    // Keep the leading zero through the admin form and native poll contract.
    for ($leadingCode = 0; $leadingCode < 1000; $leadingCode++) {
        $code = str_pad((string) $leadingCode, 6, '0', STR_PAD_LEFT);
        if (db()->value('SELECT 1 FROM signage_pairings WHERE code = ?', [$code]) === null) {
            break;
        }
    }
    db()->update('signage_pairings', ['code' => $code], ['id' => $row['id']]);
    $screenRepository = new SignageRepository();
    $screenData = [
        'name' => 'Pairing regression screen', 'location' => null, 'playlist_id' => null,
        'orientation' => 'landscape', 'fit' => 'contain', 'muted' => 1, 'enabled' => 1,
    ];
    $screenId = $screenRepository->createScreen($screenData);
    $otherScreen = $screenRepository->createScreen(array_replace($screenData, ['name' => 'Other test screen']));
    $authUser->setValue(null, null);
    $authLoaded->setValue(null, true);
    $claimBody = ['code' => $code, 'screen_id' => (string) $screenId];
    $httpError(419, fn () => $router->dispatch($request('/signage/pair', $claimBody)), 'admin claim remains protected by CSRF');
    $csrf = Csrf::token();
    $unauthenticated = $router->dispatch($request('/signage/pair', $claimBody + ['_csrf' => $csrf]));
    $check($unauthenticated->status === 303 && $unauthenticated->headers['Location'] === '/login', 'claim requires a signed-in administrator');
    $check(db()->value('SELECT paired_at FROM signage_pairings WHERE id = ?', [$row['id']]) === null, 'unauthenticated claim leaves pending request untouched');
    $authUser->setValue(null, ['id' => 0, 'name' => 'Pairing regression test']);
    $authLoaded->setValue(null, true);
    $httpError(419, fn () => $router->dispatch($request('/signage/pair', $claimBody + ['_csrf' => 'wrong'])), 'authenticated claim still requires correct CSRF');
    $screenForm = $controller->show($request('/signage/pair', [], '192.0.2.101', 'GET'));
    $check($screenForm->status === 200 && str_contains($screenForm->body, 'TV pairing code') && str_contains($screenForm->body, 'name="_csrf"'), 'pair form renders existing screens and CSRF field');
    $badForm = $controller->claim($request('/signage/pair', ['code' => ['bad'], 'screen_id' => ['bad']]));
    $check($badForm->status === 422, 'malformed admin form values stay on the validation form');
    $approved = $router->dispatch($request('/signage/pair', $claimBody + ['_csrf' => $csrf]));
    $check($approved->status === 303 && $approved->headers['Location'] === '/signage/screens/' . $screenId . '/edit', 'valid signed-in claim returns to screen settings');
    $paired = $repository->poll($deviceKey, $code);
    $token = $screenRepository->screen($screenId)['token'];
    $check($paired['status'] === 'paired' && $paired['player_token'] === $token && $paired['manifest_url'] === '/signage/player/' . $token . '/manifest', 'approved TV receives screen token and manifest address');
    $check($repository->poll($deviceKey, $code) === $paired, 'paired poll response is idempotent while code remains valid');
    try {
        $repository->claim($code, $otherScreen);
        throw new LogicException('Claimed code was assigned to another screen.');
    } catch (InvalidArgumentException) {
        $check((int) db()->value('SELECT screen_id FROM signage_pairings WHERE id = ?', [$row['id']]) === $screenId, 'second claim cannot change approved screen ownership');
    }
    $screenRepository->rotateScreenToken($screenId);
    $httpError(410, fn () => $repository->poll($deviceKey, $code), 'token rotation revokes prior paired polling');
    $check(db()->value('SELECT paired_token FROM signage_pairings WHERE id = ?', [$row['id']]) === $token, 'pairing retains approved token snapshot instead of exposing replacement');

    $deleteKey = bin2hex(random_bytes(32));
    $deletePair = $repository->start($deleteKey, 'Delete regression TV', '192.0.2.103');
    $repository->claim($deletePair['code'], $otherScreen);
    $screenRepository->deleteScreen($otherScreen);
    $httpError(410, fn () => $repository->poll($deleteKey, $deletePair['code']), 'screen deletion revokes paired polling');
    $check(db()->value('SELECT screen_id FROM signage_pairings WHERE device_key_hash = ?', [hash('sha256', $deleteKey)]) === null, 'screen foreign key clears when screen is deleted');

    $expiryKey = bin2hex(random_bytes(32));
    $expiryPair = $repository->start($expiryKey, 'Expired test TV', '192.0.2.104');
    db()->update('signage_pairings', ['expires_at' => gmdate('Y-m-d H:i:s', time() - 1)], ['device_key_hash' => hash('sha256', $expiryKey)]);
    $httpError(410, fn () => $repository->poll($expiryKey, $expiryPair['code']), 'pending code expires');
    try {
        $repository->claim($expiryPair['code'], $screenId);
        throw new LogicException('Expired code was approved.');
    } catch (InvalidArgumentException) {
        $check(true, 'expired code cannot be approved');
    }
    $restarted = $repository->start($expiryKey, 'Expired test TV', '192.0.2.104');
    $check($restarted['status'] === 'pending' && $restarted['expires_in'] > 590, 'expired device can request a fresh code');
    $repository->claim($restarted['code'], $screenId);
    db()->update('signage_pairings', ['expires_at' => gmdate('Y-m-d H:i:s', time() - 1)], ['device_key_hash' => hash('sha256', $expiryKey)]);
    $expiredResponse = $controller->poll($request('/signage/api/pairing/poll', ['device_key' => $expiryKey, 'code' => $restarted['code']]));
    $check($expiredResponse->status === 410 && $json($expiredResponse)['status'] === 'expired', 'paired code also expires with safe JSON error');

    $rateIp = '198.51.100.' . random_int(10, 240);
    for ($i = 0; $i < SignagePairingRepository::MAX_REQUESTS; $i++) {
        $rateKey = bin2hex(random_bytes(32));
        $ratePair = $repository->start($rateKey, 'Rate regression TV', $rateIp);
    }
    $check($repository->start($rateKey, 'Rate regression TV', $rateIp)['code'] === $ratePair['code'], 'idempotent start does not consume another rate-limit slot');
    $rateResponse = $controller->start($request('/signage/api/pairing/start', ['device_key' => bin2hex(random_bytes(32)), 'device_name' => 'Rate regression TV'], $rateIp));
    $check($rateResponse->status === 429 && $rateResponse->headers['Retry-After'] === '600' && $json($rateResponse)['status'] === 'error', 'twenty new starts per address in ten minutes enforced with retry header');
    $oldKey = bin2hex(random_bytes(32));
    $oldPair = $repository->start($oldKey, 'Old test TV', '192.0.2.105');
    db()->update('signage_pairings', ['expires_at' => gmdate('Y-m-d H:i:s', time() - 86401)], ['device_key_hash' => hash('sha256', $oldKey)]);
    $repository->start(bin2hex(random_bytes(32)), 'Cleanup test TV', '192.0.2.106');
    $check(db()->value('SELECT id FROM signage_pairings WHERE device_key_hash = ?', [hash('sha256', $oldKey)]) === null, 'expired pairing requests older than one day are cleaned up');
} catch (Throwable $error) {
    $failure = $error;
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION = $sessionBefore;
    $authUser->setValue(null, $userBefore);
    $authLoaded->setValue(null, $loadedBefore);
}
foreach ($tables as $table) {
    $after = db()->all("SELECT * FROM $table ORDER BY " . ($table === 'signage_pairing_ips' ? 'ip_hash' : 'id'));
    if ($after !== $before[$table]) {
        fwrite(STDERR, "Failed: test rows were not rolled back in $table.\n");
        exit(1);
    }
}
if ($failure !== null) {
    fwrite(STDERR, $failure->getMessage() . "\n");
    exit(1);
}
echo "Passed $checks Android TV pairing checks. All test rows rolled back.\n";

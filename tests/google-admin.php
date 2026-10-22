<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/support/GoogleFixture.php';

use App\Core\View;
use App\Modules\Chromebooks\GoogleApiError;
use App\Modules\Chromebooks\GoogleDeviceService;

$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('Failed: ' . $label);
    $checks++;
};
$reject = static function (Closure $fn, string $label) use ($check): void {
    try { $fn(); } catch (GoogleApiError) { $check(true, $label); return; }
    $check(false, $label);
};
$fixture = new GoogleFixture();
try {
    $client = $fixture->client();
    $fixture->pages = [
        '' => ['chromeosdevices' => [['deviceId' => 'partial', 'serialNumber' => 'TEST-SERIAL-123-MORE']], 'nextPageToken' => 'next'],
        'next' => ['chromeosdevices' => [$fixture->device]],
    ];
    $check($client->findDevice('TEST-SERIAL-123')['deviceId'] === 'google-device-1', 'exact serial match across pages, partial match ignored');
    $lookups = array_values(array_filter($fixture->calls, static fn ($call) => str_contains($call[1], '/devices/chromeos?')));
    $check(!str_contains($lookups[0][1], 'pageToken=') && str_contains($lookups[1][1], 'pageToken=next'), 'first lookup omits page token; later lookup sends the returned token');
    $claims = $fixture->claims[0];
    $check($claims['scope'] === 'https://www.googleapis.com/auth/admin.directory.device.chromeos.readonly'
        && $claims['sub'] === 'admin@example.invalid' && $claims['aud'] === 'https://oauth2.googleapis.com/token'
        && $claims['exp'] - $claims['iat'] === 3600, 'signed delegated JWT uses least privilege read scope');
    $reject(fn () => $client->findDevice('TEST OR status:active'), 'query injection rejected');
    $fixture->pages = ['' => ['chromeosdevices' => [$fixture->device, $fixture->device]]];
    $reject(fn () => $client->findDevice('TEST-SERIAL-123'), 'ambiguous serial rejected');
    $reject(fn () => $client->getDevice('google-device-1', 'OTHER-SERIAL'), 'identity mismatch rejected');
    $fixture->pages = [];
    $check($client->orgUnits() === ['/', '/Staff', '/Students'], 'OU list includes root and sorts paths');
    $client->telemetry('google-device-1');
    $check(str_contains(end($fixture->calls)[1], 'readMask=deviceId%2CserialNumber%2CnetworkStatusReport'), 'telemetry requests explicit minimal fields');
    $client->move('google-device-1', '/Staff');
    $call = end($fixture->calls);
    $check($call[0] === 'POST' && str_contains($call[1], 'orgUnitPath=%2FStaff') && json_decode($call[2], true)['deviceIds'] === ['google-device-1'], 'OU write endpoint and body');
    $check(end($fixture->claims)['scope'] === 'https://www.googleapis.com/auth/admin.directory.device.chromeos', 'write gets separate scoped authorization');
    $fixture->readStatus = 403;
    $reject(fn () => $client->getDevice('google-device-1', 'TEST-SERIAL-123'), 'permission failure surfaced');
    $fixture->readStatus = 400;
    $fixture->readError = ['error' => ['message' => 'Invalid Input: customerId private@example.invalid secret-customer-123', 'errors' => [['reason' => 'invalid']]]];
    try { $client->findDevice('TEST-SERIAL-123'); $check(false, 'customer error expected'); }
    catch (GoogleApiError $e) {
        $check($e->httpStatus === 400 && str_contains($e->getMessage(), 'Google Directory HTTP 400')
            && str_contains($e->getMessage(), 'GOOGLE_CUSTOMER_ID'), 'bad customer response includes stage, status and configuration guidance');
        $check(!str_contains($e->getMessage(), 'private@example.invalid') && !str_contains($e->getMessage(), 'secret-customer'), 'raw Google error values never enter the UI');
    }
    $fixture->readStatus = 403;
    $fixture->readError = ['error' => ['details' => [['reason' => 'SERVICE_DISABLED', 'metadata' => ['consumer' => 'private-project-id']]]]];
    try { $client->findDevice('TEST-SERIAL-123'); $check(false, 'disabled API error expected'); }
    catch (GoogleApiError $e) {
        $check(str_contains($e->getMessage(), 'SERVICE_DISABLED') && str_contains($e->getMessage(), 'Enable the Admin SDK API')
            && !str_contains($e->getMessage(), 'private-project'), 'disabled API has actionable guidance without leaking metadata');
    }
    $fixture->readStatus = 503;
    $fixture->readError = ['error' => ['message' => 'private backend data', 'errors' => [['reason' => 'backendError']]]];
    try { $client->findDevice('TEST-SERIAL-123'); $check(false, 'server error expected'); }
    catch (GoogleApiError $e) { $check(str_contains($e->getMessage(), 'HTTP 503') && str_contains($e->getMessage(), 'Try again later'), 'transient server failure is distinguished from bad configuration'); }
    $fixture->readStatus = 200; $fixture->readError = null;
    $telemetry = $fixture->telemetry;
    $telemetry['networkStatusReport'][] = ['reportTime' => '2026-10-18T01:00:00Z', 'connectionState' => 'NOT_CONNECTED'];
    $report = GoogleDeviceService::report($fixture->device, $telemetry);
    $check($report['lan_ip'] === '192.0.2.20' && $report['lan_source'] === 'telemetry' && $report['signal_dbm'] === -55, 'latest network report and signal preserved');
    $check($report['last_sync'] === '2026-10-19T01:00:00Z' && $report['network_reported_at'] === '2026-10-19T01:05:00Z', 'Google timestamps kept separately');
    $fallback = GoogleDeviceService::report($fixture->device, []);
    $check($fallback['lan_source'] === 'directory' && $fallback['network_state'] === null && $fallback['network_reported_at'] === null, 'last known IP does not fabricate connectivity or report time');
    $data = ['chromebook' => ['id' => 1, 'serial_number' => 'TEST-SERIAL-123'], 'googleConfigured' => true,
        'googleCanMove' => true, 'googleCache' => ['report' => $report + ['telemetry_warning' => null], 'refreshed_at' => '2026-10-19 02:00:00'], 'googleMoves' => []];
    $users = GoogleDeviceService::report($fixture->device + ['recentUsers' => [['type' => 'USER_TYPE_MANAGED', 'email' => 'newest@pribadidepok.sch.id'], ['type' => 'USER_TYPE_UNMANAGED'], ['type' => 'USER_TYPE_MANAGED', 'email' => 'b@x.id'], ['type' => 'USER_TYPE_MANAGED', 'email' => 'fourth@x.id']], 'activeTimeRanges' => [['date' => '2026-10-17', 'activeTime' => 5000], ['date' => '2026-10-19', 'activeTime' => 0], ['date' => '2026-10-18', 'activeTime' => 900]]], []);
    $check($users['recent_users'] === ['newest@pribadidepok.sch.id', 'Unmanaged user', 'b@x.id'] && $users['last_active_date'] === '2026-10-18', 'three newest users kept and last active day ignores zero-use days');
    $check(!array_key_exists('location', $users), 'location label no longer stored');
    $data['googleCache']['report']['recent_users'] = ['<script>alert(1)</script>'];
    $html = View::partial('Chromebooks::_google-details', $data);
    $check(str_contains($html, '&lt;script&gt;') && !str_contains($html, '<script>alert'), 'Google content escaped in report');
    $check(str_contains($html, 'Last policy sync') && str_contains($html, 'Recent users') && !str_contains($html, 'Location') && str_contains($html, 'Network reported') && str_contains($html, 'Nexus refreshed') && str_contains($html, 'Management password'), 'details contain freshness and OU controls');
    $data['googleConfigured'] = false;
    $html = View::partial('Chromebooks::_google-details', $data);
    $check(str_contains($html, 'Set up Google Admin') && !str_contains($html, 'Library') && !str_contains($html, 'data-google-auto-refresh'), 'disconnected mode hides reports and disables polling');
    echo "Google offline checks: $checks passed.\n";
} finally { $fixture->cleanup(); }

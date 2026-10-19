<?php
declare(strict_types=1);

use App\Modules\Chromebooks\GoogleAdminClient;
use App\Modules\Chromebooks\GoogleApiError;

/** Isolated fake Google server. No real credentials and no network calls. */
final class GoogleFixture
{
    public array $calls = [];
    public array $claims = [];
    public array $device = ['deviceId' => 'google-device-1', 'serialNumber' => 'TEST-SERIAL-123',
        'orgUnitPath' => '/Students', 'status' => 'ACTIVE', 'lastSync' => '2026-10-19T01:00:00Z',
        'annotatedLocation' => 'Library', 'osVersion' => '130.0', 'model' => 'Test Chromebook',
        'lastKnownNetwork' => [['ipAddress' => '192.0.2.10', 'wanIpAddress' => '198.51.100.5']]];
    public array $telemetry = ['deviceId' => 'google-device-1', 'networkStatusReport' => [
        ['reportTime' => '2026-10-19T01:05:00Z', 'connectionState' => 'ONLINE', 'connectionType' => 'WIFI',
            'lanIpAddress' => '192.0.2.20', 'gatewayIpAddress' => '192.0.2.1', 'signalStrengthDbm' => -55],
    ]];
    public array $pages = [];
    public array $units = ['/', '/Students', '/Staff'];
    public int $readStatus = 200;
    public ?array $readError = null;
    public int $telemetryStatus = 200;
    public int $moveStatus = 200;
    public bool $moveTimeout = false;
    public array $settings;
    private string $path;
    private string $opensslConfig;
    private string $publicKey;

    public function __construct()
    {
        $this->path = BASE_PATH . '/storage/cache/google-test-' . bin2hex(random_bytes(8)) . '.json';
        $this->opensslConfig = $this->path . '.cnf';
        file_put_contents($this->opensslConfig, "[req]\ndistinguished_name = dn\n[dn]\n");
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => $this->opensslConfig];
        $key = openssl_pkey_new($options);
        if (!$key || !openssl_pkey_export($key, $pem, null, $options)) throw new RuntimeException('Cannot generate temporary test key.');
        $this->publicKey = openssl_pkey_get_details($key)['key'];
        file_put_contents($this->path, json_encode(['type' => 'service_account', 'client_email' => 'fixture@example.invalid', 'private_key' => $pem]));
        $this->settings = ['credentials' => $this->path, 'admin_email' => 'admin@example.invalid',
            'customer_id' => 'C_TEST', 'telemetry' => true, 'ou_password_hash' => ''];
    }

    public function client(): GoogleAdminClient
    {
        return new GoogleAdminClient($this->settings, $this->transport(...));
    }

    public function cleanup(): void
    {
        @unlink($this->path);
        @unlink($this->opensslConfig);
    }

    private function transport(string $method, string $url, array $headers, ?string $body): array
    {
        $this->calls[] = [$method, $url, $body];
        $reply = static fn (array $data, int $status = 200): array => ['status' => $status, 'body' => json_encode($data, JSON_THROW_ON_ERROR)];
        if ($url === 'https://oauth2.googleapis.com/token') {
            parse_str((string) $body, $form);
            [$header, $payload, $sig] = explode('.', $form['assertion']);
            $decode = static fn ($s) => base64_decode(strtr($s, '-_', '+/'));
            if (openssl_verify($header . '.' . $payload, $decode($sig), $this->publicKey, OPENSSL_ALGO_SHA256) !== 1) throw new RuntimeException('Invalid JWT signature');
            $this->claims[] = json_decode($decode($payload), true, 512, JSON_THROW_ON_ERROR);
            return $reply(['access_token' => 'fixture-token']);
        }
        if (!in_array('Authorization: Bearer fixture-token', $headers, true)) throw new RuntimeException('Missing bearer token');
        $path = parse_url($url, PHP_URL_PATH);
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
        if (str_contains($path, '/telemetry/devices/')) return $reply($this->telemetry, $this->telemetryStatus);
        if (str_ends_with($path, '/orgunits')) return $reply(['organizationUnits' => array_map(static fn ($path) => ['orgUnitPath' => $path], $this->units)]);
        if (str_ends_with($path, '/moveDevicesToOu') && $method === 'POST') {
            if ($this->moveTimeout) throw new GoogleApiError('Simulated connection timeout');
            if ($this->moveStatus === 200) $this->device['orgUnitPath'] = $query['orgUnitPath'];
            return $reply([], $this->moveStatus);
        }
        if (str_ends_with($path, '/devices/chromeos')) {
            if (array_key_exists('pageToken', $query) && $query['pageToken'] === '') {
                return $reply(['error' => ['message' => 'Invalid Input: pageToken', 'errors' => [['reason' => 'invalid']]]], 400);
            }
            if ($this->readError !== null) return $reply($this->readError, $this->readStatus);
            return $reply($this->pages[$query['pageToken'] ?? ''] ?? ['chromeosdevices' => [$this->device]], $this->readStatus);
        }
        if (str_ends_with($path, '/devices/chromeos/google-device-1')) return $reply($this->device, $this->readStatus);
        throw new RuntimeException('Unexpected fake request: ' . $url);
    }
}

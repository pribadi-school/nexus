<?php
declare(strict_types=1);

namespace App\Modules\Chromebooks;

/** Server-side Google APIs. No credential or Google bearer token reaches a browser. */
final class GoogleAdminClient
{
    private array $tokens = [];

    /** The injectable transport is only for offline contract tests. */
    public function __construct(private array $settings, private ?\Closure $transport = null) {}

    public function configured(): bool
    {
        return $this->settings['credentials'] !== '' && $this->settings['admin_email'] !== ''
            && $this->settings['customer_id'] !== '';
    }

    public function connectionKey(): string
    {
        return hash('sha256', json_encode([
            $this->settings['credentials'], $this->settings['admin_email'], $this->settings['customer_id'],
            $this->settings['telemetry'], @hash_file('sha256', $this->settings['credentials']) ?: '',
        ], JSON_THROW_ON_ERROR));
    }

    public function telemetryEnabled(): bool
    {
        return (bool) $this->settings['telemetry'];
    }

    public function findDevice(string $serial): array
    {
        // Google supports partial matching; always verify the complete serial locally.
        if (!preg_match('/^[a-zA-Z0-9._-]{1,64}$/D', $serial)) {
            throw new GoogleApiError('Check the Chromebook serial number before connecting it to Google.');
        }
        $matches = [];
        $page = '';
        $seen = [];
        do {
            if (isset($seen[$page]) || count($seen) >= 10) {
                throw new GoogleApiError('Google returned too many matching results. Check the complete serial number.');
            }
            $seen[$page] = true;
            $query = ['query' => 'id:' . $serial, 'projection' => 'FULL', 'maxResults' => 300];
            if ($page !== '') $query['pageToken'] = $page;
            $data = $this->api('GET', $this->directory('/devices/chromeos'), $query);
            foreach ($data['chromeosdevices'] ?? [] as $device) {
                if (strcasecmp(trim($device['serialNumber'] ?? ''), $serial) === 0) {
                    $matches[] = $device;
                }
            }
            $page = (string) ($data['nextPageToken'] ?? '');
        } while ($page !== '');
        if (count($matches) !== 1 || empty($matches[0]['deviceId'])) {
            throw new GoogleApiError($matches === []
                ? 'No enrolled Google device matches this serial number.'
                : 'Google returned multiple devices for this serial number. Resolve the duplicate in Google Admin.');
        }
        return $matches[0];
    }

    public function getDevice(string $id, string $serial): array
    {
        $device = $this->api('GET', $this->directory('/devices/chromeos/' . rawurlencode($id)), ['projection' => 'FULL']);
        if (($device['deviceId'] ?? '') !== $id || strcasecmp($device['serialNumber'] ?? '', $serial) !== 0) {
            throw new GoogleApiError('The Google device no longer matches this Chromebook. Refresh the connection.');
        }
        return $device;
    }

    public function telemetry(string $id): array
    {
        $url = 'https://chromemanagement.googleapis.com/v1/customers/' . rawurlencode($this->settings['customer_id'])
            . '/telemetry/devices/' . rawurlencode($id);
        $data = $this->api('GET', $url, ['readMask' => 'deviceId,serialNumber,networkStatusReport'], scope: 'telemetry');
        if (isset($data['deviceId']) && $data['deviceId'] !== $id) {
            throw new GoogleApiError('Google telemetry returned a different device.');
        }
        return $data;
    }

    public function orgUnits(): array
    {
        $data = $this->api('GET', $this->directory('/orgunits'), ['type' => 'ALL_INCLUDING_PARENT', 'orgUnitPath' => '/'], scope: 'ou');
        $paths = [];
        foreach ($data['organizationUnits'] ?? [] as $unit) {
            if (isset($unit['orgUnitPath']) && is_string($unit['orgUnitPath'])) $paths[] = $unit['orgUnitPath'];
        }
        sort($paths, SORT_NATURAL | SORT_FLAG_CASE);
        return array_values(array_unique($paths));
    }

    public function move(string $id, string $target): void
    {
        $this->api('POST', $this->directory('/devices/chromeos/moveDevicesToOu'), ['orgUnitPath' => $target], ['deviceIds' => [$id]], 'write');
    }

    private function directory(string $path): string
    {
        return 'https://admin.googleapis.com/admin/directory/v1/customer/' . rawurlencode($this->settings['customer_id']) . $path;
    }

    private function api(string $method, string $url, array $query = [], ?array $body = null, string $scope = 'read'): array
    {
        $token = $this->token($scope);
        if ($query !== []) $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $result = $this->http($method, $url, [
            'Authorization: Bearer ' . $token, 'Accept: application/json', 'Content-Type: application/json',
        ], $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR));
        if ($result['status'] < 200 || $result['status'] >= 300) {
            $service = str_starts_with($url, 'https://chromemanagement.googleapis.com/') ? 'Google telemetry' : 'Google Directory';
            throw new GoogleApiError(self::apiError($service, $result['status'], $result['body']), $result['status']);
        }
        return $this->decode($result['body']);
    }

    /** Show only recognized error codes and parameter names, never raw Google messages or metadata. */
    private static function apiError(string $service, int $status, string $body): string
    {
        $data = json_decode($body, true);
        $error = is_array($data['error'] ?? null) ? $data['error'] : [];
        $knownReasons = [
            'badRequest', 'invalid', 'invalidParameter', 'notFound', 'customerNotFound', 'domainNotFound',
            'authError', 'forbidden', 'insufficientPermissions', 'accessNotConfigured',
            'rateLimitExceeded', 'userRateLimitExceeded', 'dailyLimitExceeded', 'quotaExceeded',
            'backendError', 'internalError', 'SERVICE_DISABLED', 'API_DISABLED',
            'ACCESS_TOKEN_SCOPE_INSUFFICIENT', 'PERMISSION_DENIED', 'INVALID_ARGUMENT', 'RESOURCE_EXHAUSTED',
        ];
        $reasons = [];
        foreach (['errors', 'details'] as $collection) {
            foreach (is_array($error[$collection] ?? null) ? $error[$collection] : [] as $entry) {
                $reason = is_array($entry) ? ($entry['reason'] ?? null) : null;
                if (is_string($reason) && in_array($reason, $knownReasons, true)) $reasons[] = $reason;
            }
        }
        if (is_string($error['status'] ?? null) && in_array($error['status'], $knownReasons, true)) $reasons[] = $error['status'];
        $reasons = array_values(array_unique($reasons));
        $field = null;
        $googleMessage = is_string($error['message'] ?? null) ? $error['message'] : '';
        foreach (['customerId', 'pageToken', 'query', 'projection', 'maxResults', 'orgUnitPath', 'deviceIds'] as $parameter) {
            if (preg_match('/\b' . $parameter . '\b/i', $googleMessage)) { $field = $parameter; break; }
        }
        $disabled = array_intersect($reasons, ['accessNotConfigured', 'SERVICE_DISABLED', 'API_DISABLED']) !== [];
        $limited = array_intersect($reasons, ['rateLimitExceeded', 'userRateLimitExceeded', 'dailyLimitExceeded', 'quotaExceeded', 'RESOURCE_EXHAUSTED']) !== [];
        $hint = match (true) {
            $disabled => 'Enable the ' . ($service === 'Google telemetry' ? 'Chrome Management API' : 'Admin SDK API') . ' in the Google Cloud project that owns the service account.',
            $limited || $status === 429 => 'Google is limiting requests. Wait before refreshing again.',
            $status === 400 && $field === 'customerId' => 'Check GOOGLE_CUSTOMER_ID in .env. Copy the exact Customer ID from Google Admin → Account → Account settings → Profile.',
            $status === 400 && $field === 'pageToken' => 'Google rejected the pagination token. Refresh to restart the device lookup.',
            $status === 400 => 'Google rejected the request. Check the exact Customer ID in .env and the complete Chromebook serial number.',
            $status === 401 || $status === 403 => 'Google denied access. Check API scopes, delegated admin privileges and reporting permissions.',
            $status === 404 && $service === 'Google Directory' => 'Check the Customer ID, enrolled device and administrator access in Google Admin.',
            $status === 404 => 'Google has no telemetry report for this device. Check enrollment and reporting policies.',
            $status >= 500 => 'Google’s service could not complete the request. Try again later.',
            default => 'Google could not complete the request. Check the connection settings and try again.',
        };
        $details = implode(', ', array_filter([...array_slice($reasons, 0, 3), $field]));
        return $service . ' HTTP ' . $status . ($details !== '' ? ' (' . $details . ')' : '') . '. ' . $hint;
    }

    private function token(string $purpose): string
    {
        if (isset($this->tokens[$purpose])) return $this->tokens[$purpose];
        if (!$this->configured()) throw new GoogleApiError('Google Admin is not configured yet.');
        if (!function_exists('openssl_sign')) throw new GoogleApiError('Enable the PHP OpenSSL extension to connect Google Admin.');
        $path = realpath($this->settings['credentials']);
        $public = str_replace('\\', '/', (string) realpath(BASE_PATH . '/public')) . '/';
        if (!$path || str_starts_with(strtolower(str_replace('\\', '/', $path)), strtolower($public))) {
            throw new GoogleApiError('The Google service account file must exist outside the public website folder.');
        }
        $key = $this->decode((string) @file_get_contents($path));
        if (($key['type'] ?? '') !== 'service_account' || empty($key['client_email']) || empty($key['private_key'])) {
            throw new GoogleApiError('The Google service account file is invalid.');
        }
        $scope = 'https://www.googleapis.com/auth/' . match ($purpose) {
            'write' => 'admin.directory.device.chromeos',
            'ou' => 'admin.directory.orgunit.readonly',
            'telemetry' => 'chrome.management.telemetry.readonly',
            default => 'admin.directory.device.chromeos.readonly',
        };
        $base64 = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $now = time();
        $jwt = $base64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR)) . '.' . $base64(json_encode([
            'iss' => $key['client_email'], 'sub' => $this->settings['admin_email'], 'scope' => $scope,
            'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));
        if (!@openssl_sign($jwt, $signature, $key['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new GoogleApiError('The Google service account signing key is invalid.');
        }
        $result = $this->http('POST', 'https://oauth2.googleapis.com/token', ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt . '.' . $base64($signature),
        ]));
        $data = $this->decode($result['body']);
        if ($result['status'] !== 200 || !is_string($data['access_token'] ?? null)) {
            throw new GoogleApiError('Google authorization failed. Check domain-wide delegation, scopes and the delegated admin email.');
        }
        return $this->tokens[$purpose] = $data['access_token'];
    }

    private function decode(string $body): array
    {
        if (trim($body) === '') return [];
        try { $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new GoogleApiError('Google returned an unreadable response.'); }
        if (!is_array($data)) throw new GoogleApiError('Google returned an unreadable response.');
        return $data;
    }

    private function http(string $method, string $url, array $headers, ?string $body): array
    {
        // Endpoints are fixed by this class; never follow redirects carrying a bearer token.
        if ($this->transport) return ($this->transport)($method, $url, $headers, $body);
        if (!function_exists('curl_init')) throw new GoogleApiError('Enable the PHP cURL extension to connect Google Admin.');
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($body !== null) curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($response === false) throw new GoogleApiError('Cannot reach Google securely. Check the server connection and CA certificates.');
        return ['status' => $status, 'body' => $response];
    }
}

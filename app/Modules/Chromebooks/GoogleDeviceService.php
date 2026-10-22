<?php
declare(strict_types=1);

namespace App\Modules\Chromebooks;

use App\Core\Database;

final class GoogleDeviceService
{
    public const REFRESH_SECONDS = 300;

    public function __construct(private GoogleAdminClient $google) {}

    public function cached(array $device): ?array
    {
        if (!$this->google->configured()) return null;
        $row = db()->one('SELECT * FROM chromebook_google_cache WHERE device_id = ? AND connection_key = ? AND serial_number = ?', [
            $device['id'], $this->google->connectionKey(), trim((string) $device['serial_number']),
        ]);
        if ($row === null) return null;
        $row['report'] = $row['report_json'] ? json_decode($row['report_json'], true, 512, JSON_THROW_ON_ERROR) : null;
        unset($row['report_json']);
        return $row;
    }

    public function refresh(array $device, bool $force = false): ?array
    {
        if (!$this->google->configured()) throw new GoogleApiError('Google Admin is not configured yet.');
        $serial = trim((string) $device['serial_number']);
        if ($serial === '') throw new GoogleApiError('Add this Chromebook’s serial number before connecting to Google.');
        return $this->locked((int) $device['id'], function () use ($device, $serial, $force): ?array {
            $cached = $this->cached($device);
            $sinceAttempt = $cached && $cached['attempted_at'] ? time() - strtotime($cached['attempted_at'] . ' UTC') : PHP_INT_MAX;
            $sinceRefresh = $cached && $cached['refreshed_at'] ? time() - strtotime($cached['refreshed_at'] . ' UTC') : PHP_INT_MAX;
            if ($sinceAttempt < 30 || (!$force && $sinceRefresh < self::REFRESH_SECONDS && !$cached['error'])) return $cached;
            if (!$force && $cached && $cached['error'] && $sinceAttempt < 60) return $cached;
            $key = $this->google->connectionKey();
            db()->run('INSERT INTO chromebook_google_cache (device_id, connection_key, serial_number, attempted_at)
                VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE
                google_device_id = IF(connection_key = VALUES(connection_key) AND serial_number = VALUES(serial_number), google_device_id, NULL),
                report_json = IF(connection_key = VALUES(connection_key) AND serial_number = VALUES(serial_number), report_json, NULL),
                refreshed_at = IF(connection_key = VALUES(connection_key) AND serial_number = VALUES(serial_number), refreshed_at, NULL),
                connection_key = VALUES(connection_key), serial_number = VALUES(serial_number), attempted_at = VALUES(attempted_at), error = NULL',
                [$device['id'], $key, $serial, Database::now()]);
            try {
                $directory = !empty($cached['google_device_id'])
                    ? $this->google->getDevice($cached['google_device_id'], $serial) : $this->google->findDevice($serial);
                $telemetry = [];
                $warning = null;
                if ($this->google->telemetryEnabled()) {
                    try { $telemetry = $this->google->telemetry($directory['deviceId']); }
                    catch (GoogleApiError $e) { $warning = 'Network telemetry unavailable. ' . $e->getMessage(); }
                }
                $report = self::report($directory, $telemetry) + ['telemetry_warning' => $warning];
                db()->update('chromebook_google_cache', [
                    'google_device_id' => $directory['deviceId'], 'report_json' => json_encode($report, JSON_THROW_ON_ERROR),
                    'refreshed_at' => Database::now(), 'error' => null,
                ], ['device_id' => $device['id']]);
            } catch (GoogleApiError $e) {
                db()->update('chromebook_google_cache', ['error' => $e->getMessage()], ['device_id' => $device['id']]);
            }
            return $this->cached($device);
        });
    }

    public function move(array $device, string $target, string $expectedOu, int $userId): void
    {
        if ($target === '' || strlen($target) > 1000 || !str_starts_with($target, '/')) throw new GoogleApiError('Choose a valid organizational unit.');
        $this->locked((int) $device['id'], function () use ($device, $target, $expectedOu, $userId): void {
            // Fetch a fresh identity and OU before writing. Never trust a browser-supplied Google ID.
            $serial = trim((string) $device['serial_number']);
            $cached = $this->cached($device);
            $current = !empty($cached['google_device_id']) ? $this->google->getDevice($cached['google_device_id'], $serial) : $this->google->findDevice($serial);
            $from = (string) ($current['orgUnitPath'] ?? '');
            if ($from === '' || $from !== $expectedOu) throw new GoogleApiError('The OU changed since you opened this page. Refresh before moving the Chromebook.');
            if ($from === $target) return;
            if (!in_array($target, $this->google->orgUnits(), true)) throw new GoogleApiError('That organizational unit is no longer available. Reload the OU list.');
            $auditId = db()->insert('chromebook_google_moves', [
                'device_id' => $device['id'], 'google_device_id' => $current['deviceId'], 'from_ou' => $from,
                'to_ou' => $target, 'requested_by' => $userId, 'requested_at' => Database::now(), 'state' => 'pending',
            ]);
            try {
                $this->google->move($current['deviceId'], $target);
            } catch (GoogleApiError $e) {
                // A timeout or 5xx can happen after Google accepted a write. Do not retry automatically.
                $state = $e->httpStatus >= 400 && $e->httpStatus < 500 ? 'rejected' : 'unconfirmed';
                db()->update('chromebook_google_moves', ['state' => $state, 'completed_at' => Database::now()], ['id' => $auditId]);
                throw new GoogleApiError($state === 'rejected' ? $e->getMessage() : 'Google did not confirm the move. Refresh the device and check its OU before trying again.');
            }
            db()->update('chromebook_google_moves', ['state' => 'accepted', 'completed_at' => Database::now()], ['id' => $auditId]);
            // Google's accepted response confirms the request, not that device policies have applied.
            // Invalidate the cache so a subsequent read verifies the resulting OU.
            db()->update('chromebook_google_cache', ['refreshed_at' => null, 'attempted_at' => null], ['device_id' => $device['id']]);
        });
    }

    public function moves(int $id): array
    {
        return db()->all('SELECT m.*, u.name AS requested_by_name FROM chromebook_google_moves m
            JOIN users u ON u.id = m.requested_by WHERE m.device_id = ? ORDER BY m.id DESC LIMIT 5', [$id]);
    }

    private function locked(int $id, \Closure $work): mixed
    {
        $key = 'nexus-google-' . $id;
        if ((int) db()->value('SELECT GET_LOCK(?, 0)', [$key]) !== 1) throw new GoogleApiError('This Chromebook is already updating. Try again shortly.');
        try { return $work(); }
        finally { db()->value('SELECT RELEASE_LOCK(?)', [$key]); }
    }

    /** Keep only the fields needed by Nexus; do not persist the full user/activity payload. */
    public static function report(array $directory, array $telemetry): array
    {
        $networks = $telemetry['networkStatusReport'] ?? [];
        usort($networks, static function (array $a, array $b): int {
            $time = (strtotime($b['reportTime'] ?? '') ?: 0) <=> (strtotime($a['reportTime'] ?? '') ?: 0);
            if ($time !== 0) return $time;
            $connected = ['ONLINE', 'CONNECTED', 'PORTAL'];
            return (int) in_array($b['connectionState'] ?? '', $connected, true) <=> (int) in_array($a['connectionState'] ?? '', $connected, true);
        });
        $network = $networks[0] ?? [];
        $known = $directory['lastKnownNetwork'][0] ?? [];
        // Google lists recent users newest first. Keep three; unmanaged (guest or
        // personal) accounts have no email.
        $users = [];
        foreach (array_slice($directory['recentUsers'] ?? [], 0, 3) as $user) {
            $users[] = (string) ($user['email'] ?? '') !== '' ? (string) $user['email']
                : (($user['type'] ?? '') === 'USER_TYPE_UNMANAGED' ? 'Unmanaged user' : 'Unknown user');
        }
        // Daily usage ranges; the newest day with use is the last active day.
        $days = array_filter($directory['activeTimeRanges'] ?? [], static fn ($range): bool => (int) ($range['activeTime'] ?? 0) > 0 && !empty($range['date']));
        usort($days, static fn (array $a, array $b): int => strcmp((string) $b['date'], (string) $a['date']));
        return [
            'serial' => $directory['serialNumber'] ?? null,
            'model' => $directory['model'] ?? null,
            'status' => $directory['status'] ?? null,
            'os_version' => $directory['osVersion'] ?? null,
            'last_sync' => $directory['lastSync'] ?? null,
            'recent_users' => $users,
            'last_active_date' => $days[0]['date'] ?? null,
            'ou' => $directory['orgUnitPath'] ?? null,
            'lan_ip' => $network['lanIpAddress'] ?? $known['ipAddress'] ?? null,
            'lan_source' => isset($network['lanIpAddress']) ? 'telemetry' : 'directory',
            'wan_ip' => $known['wanIpAddress'] ?? null,
            'gateway' => $network['gatewayIpAddress'] ?? null,
            'network_state' => $network['connectionState'] ?? null,
            'network_type' => $network['connectionType'] ?? null,
            'network_reported_at' => $network['reportTime'] ?? null,
            'signal_dbm' => $network['signalStrengthDbm'] ?? null,
        ];
    }
}

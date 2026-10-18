<?php

declare(strict_types=1);

namespace App\Modules\Signage;

use App\Core\Database;
use App\Core\HttpException;
use InvalidArgumentException;
use PDOException;

final class SignagePairingRepository
{
    public const LIFETIME_SECONDS = 600;
    public const POLL_SECONDS = 5;
    public const MAX_REQUESTS = 20;

    /** @return array{status:string,code:string,expires_in:int,poll_seconds:int} */
    public function start(string $deviceKey, string $deviceName, string $ip): array
    {
        $this->validateDeviceKey($deviceKey);
        if ($deviceName === '' || mb_strlen($deviceName) > 160 || preg_match('/[\x00-\x1f\x7f]/', $deviceName)) {
            throw new HttpException(422, 'Use a device name of 1 to 160 characters.');
        }
        $keyHash = hash('sha256', $deviceKey);
        $now = Database::now();
        $existing = db()->one('SELECT * FROM signage_pairings WHERE device_key_hash = ? AND expires_at > ?', [$keyHash, $now]);
        if ($existing !== null) {
            return $this->pending($existing);
        }

        // One locked row serializes starts from the same address, including
        // first-time requests. Only an irreversible keyed hash of the IP is kept.
        $ipHash = hash_hmac('sha256', $ip, (string) config('auth.password'));
        try {
            return db()->transaction(function () use ($keyHash, $deviceName, $ipHash, $now): array {
                db()->run('INSERT INTO signage_pairing_ips (ip_hash, last_request_at) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE last_request_at = VALUES(last_request_at)', [$ipHash, $now]);
                db()->value('SELECT ip_hash FROM signage_pairing_ips WHERE ip_hash = ? FOR UPDATE', [$ipHash]);
                db()->run('DELETE FROM signage_pairings WHERE expires_at < ?', [gmdate('Y-m-d H:i:s', time() - 86400)]);
                db()->run('DELETE FROM signage_pairing_ips WHERE last_request_at < ?', [gmdate('Y-m-d H:i:s', time() - 86400)]);

                $existing = db()->one('SELECT * FROM signage_pairings WHERE device_key_hash = ?', [$keyHash]);
                if ($existing !== null && $existing['expires_at'] > $now) {
                    return $this->pending($existing);
                }
                $requests = (int) db()->value('SELECT COUNT(*) FROM signage_pairings WHERE ip_hash = ? AND created_at > ?', [
                    $ipHash, gmdate('Y-m-d H:i:s', time() - self::LIFETIME_SECONDS),
                ]);
                if ($requests >= self::MAX_REQUESTS) {
                    throw new HttpException(429, 'Too many new pairing requests. Wait 10 minutes and try again.');
                }
                if ($existing !== null) {
                    db()->run('DELETE FROM signage_pairings WHERE id = ?', [$existing['id']]);
                }
                for ($attempt = 0; $attempt < 10; $attempt++) {
                    $row = [
                        'device_key_hash' => $keyHash,
                        'code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
                        'device_name' => $deviceName, 'ip_hash' => $ipHash,
                        'created_at' => $now, 'expires_at' => gmdate('Y-m-d H:i:s', time() + self::LIFETIME_SECONDS),
                    ];
                    try {
                        db()->insert('signage_pairings', $row);
                        return $this->pending($row);
                    } catch (PDOException $error) {
                        if ((int) ($error->errorInfo[1] ?? 0) !== 1062) {
                            throw $error;
                        }
                        if (db()->value('SELECT 1 FROM signage_pairings WHERE device_key_hash = ? FOR UPDATE', [$keyHash]) !== null) {
                            throw $error;
                        }
                        // A different TV has this short code. Generate another.
                    }
                }
                throw new HttpException(503, 'Unable to generate a pairing code. Try again shortly.');
            });
        } catch (PDOException $error) {
            // Concurrent starts for one device can arrive from different IPs.
            // The unique device hash ensures they still share one pairing.
            if ((int) ($error->errorInfo[1] ?? 0) === 1062) {
                $existing = db()->one('SELECT * FROM signage_pairings WHERE device_key_hash = ? AND expires_at > ?', [$keyHash, $now]);
                if ($existing !== null) {
                    return $this->pending($existing);
                }
            }
            throw $error;
        }
    }

    /** @return array<string,mixed> */
    public function poll(string $deviceKey, string $code): array
    {
        $this->validateDeviceKey($deviceKey);
        $this->validateCode($code);
        $row = db()->one('SELECT p.*, s.token AS current_token, s.name AS screen_name
            FROM signage_pairings p LEFT JOIN signage_screens s ON s.id = p.screen_id
            WHERE p.device_key_hash = ?', [hash('sha256', $deviceKey)]);
        if ($row === null || !hash_equals((string) $row['code'], $code) || $row['expires_at'] <= Database::now()) {
            throw new HttpException(410, 'This pairing code expired. Generate a new code on the TV.');
        }
        if ($row['paired_at'] === null) {
            $pending = $this->pending($row);
            unset($pending['code']);
            return $pending;
        }
        // Never reveal a replacement token after the admin revokes a player.
        if ($row['paired_token'] === null || $row['current_token'] === null
            || !hash_equals((string) $row['paired_token'], (string) $row['current_token'])) {
            throw new HttpException(410, 'This pairing was revoked. Generate a new code on the TV.');
        }
        return [
            'status' => 'paired', 'player_token' => $row['paired_token'],
            'manifest_url' => '/signage/player/' . $row['paired_token'] . '/manifest',
            'screen_name' => $row['screen_name'],
        ];
    }

    /** @return array<string,mixed> paired screen */
    public function claim(string $code, int $screenId): array
    {
        if (!preg_match('/^[0-9]{6}$/D', $code)) {
            throw new InvalidArgumentException('Enter the six-digit code shown on the TV.');
        }
        return db()->transaction(function () use ($code, $screenId): array {
            // Read the screen before locking the request, matching token changes
            // and deletion so an approval cannot race a revoked player token.
            $screen = db()->one('SELECT * FROM signage_screens WHERE id = ? FOR UPDATE', [$screenId]);
            if ($screen === null) {
                throw new InvalidArgumentException('Choose an existing screen.');
            }
            $row = db()->one('SELECT * FROM signage_pairings WHERE code = ? FOR UPDATE', [$code]);
            if ($row === null || $row['expires_at'] <= Database::now()) {
                throw new InvalidArgumentException('This code expired or was not found. Generate a new code on the TV.');
            }
            if ($row['paired_at'] !== null) {
                throw new InvalidArgumentException('This code has already been paired. Generate a new code to pair another TV.');
            }
            db()->update('signage_pairings', [
                'screen_id' => $screenId, 'paired_token' => $screen['token'], 'paired_at' => Database::now(),
            ], ['id' => $row['id']]);
            return $screen;
        });
    }

    private function validateDeviceKey(string $key): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $key)) {
            throw new HttpException(422, 'Generate a valid device key before pairing.');
        }
    }

    private function validateCode(string $code): void
    {
        if (!preg_match('/^[0-9]{6}$/D', $code)) {
            throw new HttpException(422, 'Use the six-digit pairing code.');
        }
    }

    /** @param array<string,mixed> $row @return array{status:string,code:string,expires_in:int,poll_seconds:int} */
    private function pending(array $row): array
    {
        return [
            'status' => 'pending', 'code' => (string) $row['code'],
            'expires_in' => max(0, (int) strtotime($row['expires_at'] . ' UTC') - time()),
            'poll_seconds' => self::POLL_SECONDS,
        ];
    }
}

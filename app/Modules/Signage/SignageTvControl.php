<?php

declare(strict_types=1);

namespace App\Modules\Signage;

use App\Core\Database;
use RuntimeException;

/**
 * Queue of fixed ADB actions for TVs, run by a helper PC on the school network
 * (bin/tv-agent.ps1). Only these actions exist: nobody can send a free-form
 * command through the website. The helper checks each TV's serial itself.
 */
final class SignageTvControl
{
    public const ACTIONS = [
        'open_nexus' => 'Open Nexus',
        'status' => 'Check status',
        'screenshot' => 'Screenshot',
        'restart' => 'Restart TV',
    ];
    /** A helper that has not checked in for this long is shown as offline. */
    public const ONLINE_SECONDS = 45;
    /** Queued actions older than this are dropped: a late restart surprises people. */
    private const QUEUE_SECONDS = 600;
    private const RUNNING_SECONDS = 300;
    public const MAX_SCREENSHOT_BYTES = 3 * 1024 * 1024;

    public static function enabled(): bool
    {
        return self::token() !== '';
    }

    public static function token(): string
    {
        return (string) config('signage.tv_agent_token', '');
    }

    public function queue(int $screenId, string $action, ?int $userId): int
    {
        if (!isset(self::ACTIONS[$action])) {
            throw new RuntimeException('Choose one of the TV actions.');
        }
        $this->expire();
        // Pressing a button twice should not reboot a TV twice.
        $pending = db()->one("SELECT id FROM signage_tv_commands WHERE screen_id = ? AND action = ? AND status IN ('queued', 'running') ORDER BY id DESC LIMIT 1", [$screenId, $action]);
        if ($pending !== null) {
            return (int) $pending['id'];
        }
        return db()->insert('signage_tv_commands', [
            'screen_id' => $screenId, 'action' => $action, 'status' => 'queued',
            'requested_by' => $userId, 'created_at' => Database::now(),
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function recent(int $screenId, int $limit = 5): array
    {
        $this->expire();
        return db()->all('SELECT c.*, u.name AS requested_by_name FROM signage_tv_commands c
            LEFT JOIN users u ON u.id = c.requested_by
            WHERE c.screen_id = ? ORDER BY c.id DESC LIMIT ' . max(1, min(20, $limit)), [$screenId]);
    }

    /** @return array<string,mixed>|null */
    public function command(int $screenId, int $id): ?array
    {
        return db()->one('SELECT * FROM signage_tv_commands WHERE id = ? AND screen_id = ?', [$id, $screenId]);
    }

    /** Whether a helper checked in recently and lists this screen. */
    public function helperFor(int $screenId): ?array
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - self::ONLINE_SECONDS);
        foreach (db()->all('SELECT * FROM signage_tv_agents WHERE last_seen_at >= ? ORDER BY last_seen_at DESC', [$cutoff]) as $agent) {
            if (in_array($screenId, self::ids((string) $agent['screen_ids']), true)) {
                return $agent;
            }
        }
        return null;
    }

    /**
     * Helper check-in: records which screens it controls and hands over queued
     * actions for those screens, marking them running.
     * @return list<array{id:int,screen_id:int,action:string}>
     */
    public function poll(string $name, string $screenIds, string $version): array
    {
        $ids = self::ids($screenIds);
        $now = Database::now();
        db()->run('INSERT INTO signage_tv_agents (name, screen_ids, version, last_seen_at) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE screen_ids = VALUES(screen_ids), version = VALUES(version), last_seen_at = VALUES(last_seen_at)',
            [$name, implode(',', $ids), $version, $now]);
        $this->expire();
        if ($ids === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $claimed = [];
        foreach (db()->all("SELECT id, screen_id, action FROM signage_tv_commands WHERE status = 'queued' AND screen_id IN ($marks) ORDER BY id LIMIT 5", $ids) as $row) {
            // The status condition keeps two helpers from taking the same action.
            if (db()->run("UPDATE signage_tv_commands SET status = 'running', started_at = ? WHERE id = ? AND status = 'queued'", [$now, $row['id']])->rowCount() === 1) {
                $claimed[] = ['id' => (int) $row['id'], 'screen_id' => (int) $row['screen_id'], 'action' => (string) $row['action']];
            }
        }
        return $claimed;
    }

    public function finish(int $id, bool $ok, string $message, ?string $screenshot): void
    {
        $command = db()->one("SELECT * FROM signage_tv_commands WHERE id = ? AND status = 'running'", [$id]);
        if ($command === null) {
            throw new RuntimeException('That action is not running.');
        }
        $filename = null;
        if ($ok && $command['action'] === 'screenshot') {
            try {
                $filename = $this->storeScreenshot((int) $command['id'], (string) $screenshot);
            } catch (RuntimeException $error) {
                [$ok, $message] = [false, $error->getMessage()];
            }
        }
        $message = trim(preg_replace('/\s+/u', ' ', $message) ?? '');
        db()->update('signage_tv_commands', [
            'status' => $ok ? 'done' : 'failed',
            'result' => mb_substr($message !== '' ? $message : ($ok ? 'Done.' : 'Failed.'), 0, 500),
            'screenshot_filename' => $filename,
            'finished_at' => Database::now(),
        ], ['id' => $id]);
    }

    public static function screenshotPath(string $filename): string
    {
        return self::directory() . '/' . basename($filename);
    }

    private function storeScreenshot(int $id, string $base64): string
    {
        $bytes = base64_decode($base64, true);
        if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_SCREENSHOT_BYTES) {
            throw new RuntimeException('The screenshot is missing or too large.');
        }
        $info = @getimagesizefromstring($bytes);
        if ($info === false || ($info[2] ?? null) !== IMAGETYPE_JPEG) {
            throw new RuntimeException('The screenshot must be a JPEG image.');
        }
        $directory = self::directory();
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('The server could not save the screenshot.');
        }
        // Keep only recent screenshots: they can show student content.
        $old = glob($directory . '/*.jpg') ?: [];
        rsort($old, SORT_NATURAL);
        foreach (array_slice($old, 50) as $file) {
            @unlink($file);
        }
        $filename = $id . '-' . bin2hex(random_bytes(8)) . '.jpg';
        if (@file_put_contents($directory . '/' . $filename, $bytes, LOCK_EX) === false) {
            throw new RuntimeException('The server could not save the screenshot.');
        }
        return $filename;
    }

    private function expire(): void
    {
        db()->run("UPDATE signage_tv_commands SET status = 'expired', result = 'The helper PC did not collect this in time.', finished_at = ?
            WHERE status = 'queued' AND created_at < ?", [Database::now(), gmdate('Y-m-d H:i:s', time() - self::QUEUE_SECONDS)]);
        db()->run("UPDATE signage_tv_commands SET status = 'failed', result = 'The helper PC did not report back.', finished_at = ?
            WHERE status = 'running' AND started_at < ?", [Database::now(), gmdate('Y-m-d H:i:s', time() - self::RUNNING_SECONDS)]);
    }

    /** @return list<int> */
    private static function ids(string $list): array
    {
        $ids = [];
        foreach (explode(',', $list) as $part) {
            if (preg_match('/^[1-9][0-9]{0,9}$/D', trim($part)) && (int) $part <= 4294967295) {
                $ids[] = (int) $part;
            }
        }
        return array_values(array_unique(array_slice($ids, 0, 50)));
    }

    private static function directory(): string
    {
        return BASE_PATH . '/storage/signage/tv-screenshots';
    }
}

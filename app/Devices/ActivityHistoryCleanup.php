<?php
declare(strict_types=1);

namespace App\Devices;

use RuntimeException;

/** One-off history maintenance. Never writes device records or assignment history. */
final class ActivityHistoryCleanup
{
    public function run(bool $apply = false): array
    {
        return db()->transaction(function () use ($apply): array {
            $boards = db()->all("SELECT d.id, r.name FROM devices d
                JOIN studytouch_boards b ON b.device_id = d.id JOIN rooms r ON r.id = b.room_id
                WHERE d.category = 'studytouch' AND d.deleted_at IS NULL
                  AND UPPER(REPLACE(r.name, ' ', '')) IN ('12B', 'XIIB')");
            if (count($boards) !== 1) throw new RuntimeException('Expected exactly one active StudyTouch 12B. No history was changed.');
            $keepId = (int) $boards[0]['id'];
            $notes = db()->all('SELECT * FROM device_notes ORDER BY id' . ($apply ? ' FOR UPDATE' : ''));
            $kept = array_values(array_filter($notes, static fn (array $row): bool => (int) $row['device_id'] === $keepId));
            $removed = array_values(array_filter($notes, static fn (array $row): bool => (int) $row['device_id'] !== $keepId));
            $summary = ['mode' => $apply ? 'applied' : 'preview', 'kept_device_id' => $keepId,
                'kept_room' => $boards[0]['name'], 'kept_history_entries' => count($kept),
                ($apply ? 'removed_history_entries' : 'history_entries_to_remove') => count($removed), 'backup' => null];
            if (!$apply || $removed === []) return $summary;

            $directory = BASE_PATH . '/storage/private';
            if (!is_dir($directory) || is_link($directory)) throw new RuntimeException('A private backup folder is required. No history was changed.');
            $path = $directory . '/activity-history-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.json';
            $backup = ['format' => 'nexus-activity-history-v1', 'database' => $this->databaseFingerprint(),
                'kept_device_id' => $keepId, 'created_at' => gmdate('c'), 'removed_notes' => $removed];
            $handle = @fopen($path, 'x');
            if ($handle === false) throw new RuntimeException('Could not create a history backup. No history was changed.');
            try {
                if (PHP_OS_FAMILY !== 'Windows' && !chmod($path, 0600)) throw new RuntimeException('Could not protect the backup.');
                $json = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $written = fwrite($handle, $json);
                if ($written !== strlen($json) || !fflush($handle)) throw new RuntimeException('Could not finish the backup. No history was changed.');
            } finally { fclose($handle); }

            foreach (array_chunk(array_column($removed, 'id'), 500) as $ids) {
                db()->run('DELETE FROM device_notes WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') AND device_id <> ?', [...$ids, $keepId]);
            }
            if (db()->all('SELECT * FROM device_notes ORDER BY id FOR UPDATE') !== $kept) throw new RuntimeException('History verification failed; cleanup rolled back.');
            $summary['backup'] = $path;
            return $summary;
        });
    }

    /** Restore only missing history rows from this database's own private backup. */
    public function restore(string $path): int
    {
        $resolved = realpath($path);
        $directory = realpath(BASE_PATH . '/storage/private');
        if ($resolved === false || $directory === false || is_link($path)
            || dirname($resolved) !== $directory || !str_starts_with(basename($resolved), 'activity-history-')) {
            throw new RuntimeException('Choose an activity history backup from storage/private.');
        }
        $backup = json_decode((string) file_get_contents($resolved), true, 512, JSON_THROW_ON_ERROR);
        if (($backup['format'] ?? null) !== 'nexus-activity-history-v1'
            || ($backup['database'] ?? null) !== $this->databaseFingerprint() || !is_array($backup['removed_notes'] ?? null)) {
            throw new RuntimeException('This is not a history backup for this database.');
        }
        return db()->transaction(static function () use ($backup): int {
            $restored = 0;
            foreach ($backup['removed_notes'] as $row) {
                if (!is_array($row) || array_keys($row) !== ['id', 'device_id', 'user_id', 'kind', 'body', 'meta', 'created_at']) {
                    throw new RuntimeException('Invalid history backup row.');
                }
                $existing = db()->one('SELECT * FROM device_notes WHERE id = ? FOR UPDATE', [$row['id']]);
                if ($existing !== null) {
                    if ($existing !== $row) throw new RuntimeException('A history ID is already in use; restore rolled back.');
                    continue;
                }
                db()->insert('device_notes', $row);
                $restored++;
            }
            return $restored;
        });
    }

    private function databaseFingerprint(): string
    {
        return hash('sha256', (string) config('database.host') . ':' . config('database.port') . '/' . config('database.name'));
    }
}

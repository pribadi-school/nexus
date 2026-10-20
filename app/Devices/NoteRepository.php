<?php

declare(strict_types=1);

namespace App\Devices;

/** The notes log (device_notes): every report, problem, fix and status change. */
final class NoteRepository
{
    private const SELECT = 'SELECT n.id, n.device_id, n.kind, n.body, n.meta, n.created_at, n.edited_at,
                                   d.category, u.name AS author_name, eu.name AS editor_name
                            FROM device_notes n
                            JOIN devices d ON d.id = n.device_id
                            LEFT JOIN users u ON u.id = n.user_id
                            LEFT JOIN users eu ON eu.id = n.edited_by';

    /**
     * Newest notes across all devices, for the dashboard feed. Pass the id of
     * the last note already shown as $beforeId to get the next page.
     * The dashboard may supply a UTC cutoff; other callers retain full history.
     *
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit, ?int $beforeId = null, ?string $since = null): array
    {
        $where = 'd.deleted_at IS NULL';
        $params = [];

        if ($since !== null) {
            $where .= ' AND n.created_at >= ?';
            $params[] = $since;
        }

        if ($beforeId !== null) {
            $cursor = db()->one('SELECT id, created_at FROM device_notes WHERE id = ?', [$beforeId]);
            if ($cursor === null) {
                return [];
            }
            $where .= ' AND (n.created_at < ? OR (n.created_at = ? AND n.id < ?))';
            array_push($params, $cursor['created_at'], $cursor['created_at'], $cursor['id']);
        }

        $rows = db()->all(
            self::SELECT . " WHERE $where ORDER BY n.created_at DESC, n.id DESC LIMIT " . max(1, $limit),
            $params,
        );
        return categories()->describeRows(array_map([$this, 'hydrate'], $rows));
    }

    /**
     * One device's notes log, newest first. Manual-only views leave automatic
     * status and assignment entries in storage and filter them before paging.
     *
     * @return list<array<string, mixed>>
     */
    public function forDevice(int $deviceId, int $limit = 200, bool $manualOnly = false): array
    {
        $rows = db()->all(
            self::SELECT . ' WHERE n.device_id = ?' . ($manualOnly ? " AND n.kind NOT IN ('status', 'assignment')" : '')
                . ' ORDER BY n.created_at DESC, n.id DESC LIMIT ' . max(1, $limit),
            [$deviceId],
        );
        return array_map([$this, 'hydrate'], $rows);
    }

    public function countForDevice(int $deviceId, bool $manualOnly = false): int
    {
        return (int) db()->value('SELECT COUNT(*) FROM device_notes WHERE device_id = ?'
            . ($manualOnly ? " AND kind NOT IN ('status', 'assignment')" : ''), [$deviceId]);
    }

    public function statusHistory(int $deviceId, int $limit = 200): array
    {
        return array_map([$this, 'hydrate'], db()->all(self::SELECT . " WHERE n.device_id = ? AND n.kind = 'status'
            ORDER BY n.created_at DESC, n.id DESC LIMIT " . max(1, $limit), [$deviceId]));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['meta'] = is_string($row['meta']) ? (json_decode($row['meta'], true) ?: []) : [];
        $row['author_name'] = ($row['author_name'] ?? '') !== '' ? $row['author_name'] : 'Someone';
        return $row;
    }
}

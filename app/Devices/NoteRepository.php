<?php

declare(strict_types=1);

namespace App\Devices;

/** The notes log (device_notes): every report, problem, fix and status change. */
final class NoteRepository
{
    private const SELECT = 'SELECT n.id, n.device_id, n.kind, n.body, n.meta, n.created_at,
                                   d.category, u.name AS author_name
                            FROM device_notes n
                            JOIN devices d ON d.id = n.device_id
                            LEFT JOIN users u ON u.id = n.user_id';

    /**
     * Newest notes across all devices, for the dashboard feed. Pass the id of
     * the last note already shown as $beforeId to get the next page.
     *
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit, ?int $beforeId = null): array
    {
        $where = 'd.deleted_at IS NULL';
        $params = [];

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

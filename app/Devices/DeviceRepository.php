<?php

declare(strict_types=1);

namespace App\Devices;

/** Queries on the shared `devices` table, for every category at once. */
final class DeviceRepository
{
    /** @return array<string, mixed>|null A device that has not been deleted. */
    public function find(int $id): ?array
    {
        return db()->one('SELECT * FROM devices WHERE id = ? AND deleted_at IS NULL', [$id]);
    }

    /**
     * Totals per category, with a zero row for categories that have no devices.
     *
     * @return array<string, array{total:int, ok:int, problem:int}>
     */
    public function countsByCategory(): array
    {
        $counts = [];
        foreach (categories()->all() as $key => $_) {
            $counts[$key] = ['total' => 0] + array_fill_keys(Status::KNOWN, 0);
        }

        $rows = db()->all(
            'SELECT category, status, COUNT(*) AS n
             FROM devices WHERE deleted_at IS NULL
             GROUP BY category, status',
        );
        foreach ($rows as $row) {
            $key = $row['category'];
            if (!isset($counts[$key])) {
                continue;
            }
            $n = (int) $row['n'];
            $counts[$key]['total'] += $n;
            if (Status::isValid($row['status'])) {
                $counts[$key][$row['status']] += $n;
            }
        }
        return $counts;
    }

    /**
     * Devices that currently have a problem, newest problem first, with
     * display names and the latest note a person wrote (not status lines).
     *
     * @return list<array<string, mixed>>
     */
    public function withProblems(int $limit = 10): array
    {
        $rows = db()->all(
            "SELECT d.id AS device_id, d.category, d.status_changed_at,
                    n.body AS latest_note, n.created_at AS latest_note_at
             FROM devices d
             LEFT JOIN device_notes n ON n.id = (
                 SELECT n2.id FROM device_notes n2
                 WHERE n2.device_id = d.id AND n2.kind <> 'status'
                 ORDER BY n2.created_at DESC, n2.id DESC LIMIT 1
             )
             WHERE d.deleted_at IS NULL AND d.status = ?
             ORDER BY d.status_changed_at IS NULL, d.status_changed_at DESC, d.id DESC
             LIMIT " . max(1, $limit),
            [Status::PROBLEM],
        );
        return categories()->describeRows($rows);
    }

    /**
     * Devices of any category matching a search, using each category's own
     * searchable fields (Category::searchIds), with display names.
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $query, int $perCategory = 100): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        $like = '%' . addcslashes($query, '%_\\') . '%';
        $rows = [];
        foreach (categories()->all() as $key => $category) {
            $ids = $category->searchIds($query, $perCategory);
            if ($ids === []) {
                continue;
            }
            $found = db()->all(
                'SELECT d.id AS device_id, d.category, d.status,
                        n.id AS matching_note_id, n.body AS matching_note, n.kind AS matching_note_kind,
                        n.created_at AS matching_note_at, u.name AS matching_note_author
                 FROM devices d
                 LEFT JOIN device_notes n ON n.id = (
                     SELECT sn.id FROM device_notes sn LEFT JOIN users su ON su.id = sn.user_id
                     WHERE sn.device_id = d.id AND (sn.body LIKE ? OR sn.kind LIKE ? OR su.name LIKE ?)
                     ORDER BY sn.created_at DESC, sn.id DESC LIMIT 1
                 )
                 LEFT JOIN users u ON u.id = n.user_id
                 WHERE d.deleted_at IS NULL AND d.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
                [$like, $like, $like, ...$ids],
            );
            // Keep the category's own order (best matches first).
            $order = array_flip($ids);
            usort($found, static fn ($a, $b) => $order[(int) $a['device_id']] <=> $order[(int) $b['device_id']]);
            array_push($rows, ...$found);
        }
        return categories()->describeRows($rows);
    }
}

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
            $counts[$key] = ['total' => 0, 'ok' => 0, 'problem' => 0];
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
            if ($row['status'] === Status::PROBLEM) {
                $counts[$key]['problem'] += $n;
            } else {
                $counts[$key]['ok'] += $n;
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
        $rows = [];
        foreach (categories()->all() as $key => $category) {
            $ids = $category->searchIds($query, $perCategory);
            if ($ids === []) {
                continue;
            }
            $found = db()->all(
                'SELECT id AS device_id, category, status FROM devices
                 WHERE deleted_at IS NULL AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
                $ids,
            );
            // Keep the category's own order (best matches first).
            $order = array_flip($ids);
            usort($found, static fn ($a, $b) => $order[(int) $a['device_id']] <=> $order[(int) $b['device_id']]);
            array_push($rows, ...$found);
        }
        return categories()->describeRows($rows);
    }
}

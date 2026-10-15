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
     * display names.
     *
     * @return list<array<string, mixed>>
     */
    public function withProblems(int $limit = 10): array
    {
        $rows = db()->all(
            'SELECT id AS device_id, category, status_changed_at
             FROM devices
             WHERE deleted_at IS NULL AND status = ?
             ORDER BY status_changed_at IS NULL, status_changed_at DESC, id DESC
             LIMIT ' . max(1, $limit),
            [Status::PROBLEM],
        );
        return categories()->describeRows($rows);
    }
}

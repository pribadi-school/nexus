<?php

declare(strict_types=1);

namespace App\Modules\Cctv;

use App\Devices\DeviceService;
use App\Devices\Status;

/**
 * Cameras = `devices` rows (category cctv) + `cctv_cameras`. A camera is its
 * spot (label), its type (devices.model, see CctvCategory::TYPES) and its SD
 * card size, with an optional location from the editable locations list.
 */
final class CctvRepository
{
    private const SELECT = "SELECT d.id, d.model, d.status, d.status_changed_at,
                                   d.updated_at, d.created_at, d.last_checked_at,
                                   c.label, c.sd_card_gb, c.location_id, l.name AS location_name,
                                   uu.name AS updated_by_name, uc.name AS created_by_name
                            FROM devices d
                            JOIN cctv_cameras c ON c.device_id = d.id
                            LEFT JOIN cctv_locations l ON l.id = c.location_id
                            LEFT JOIN users uu ON uu.id = d.updated_by
                            LEFT JOIN users uc ON uc.id = d.created_by
                            WHERE d.category = 'cctv' AND d.deleted_at IS NULL";

    /**
     * @param array{status:?string, type:?string, location?:?string, q:?string} $filters
     * @return list<array<string, mixed>> in the order they were added (the school's sheet order)
     */
    public function search(array $filters): array
    {
        $sql = self::SELECT;
        $params = [];

        if ($filters['status'] !== null && Status::isValid($filters['status'])) {
            $sql .= ' AND d.status = ?';
            $params[] = $filters['status'];
        }
        if ($filters['type'] !== null) {
            $sql .= ' AND d.model = ?';
            $params[] = $filters['type'];
        }
        if (($filters['location'] ?? null) === 'unassigned') {
            $sql .= ' AND c.location_id IS NULL';
        } elseif (isset($filters['location']) && ctype_digit($filters['location'])) {
            $sql .= ' AND c.location_id = ?';
            $params[] = (int) $filters['location'];
        }
        if ($filters['q'] !== null) {
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            $sql .= ' AND (c.label LIKE ? OR l.name LIKE ?)';
            array_push($params, $like, $like);
        }

        return db()->all($sql . ' ORDER BY d.id', $params);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return db()->one(self::SELECT . ' AND d.id = ?', [$id]);
    }

    /** @return array{total:int, ok:int, problem:int} */
    public function counts(): array
    {
        $row = db()->one(
            "SELECT COUNT(*) AS total, COALESCE(SUM(status = 'problem'), 0) AS problem
             FROM devices WHERE category = 'cctv' AND deleted_at IS NULL",
        );
        $total = (int) ($row['total'] ?? 0);
        $problem = (int) ($row['problem'] ?? 0);
        return ['total' => $total, 'ok' => $total - $problem, 'problem' => $problem];
    }

    /** @param array{label:string, model:string, sd_card_gb:?int, location_id:?int, status:string} $data */
    public function create(CctvCategory $category, array $data, ?int $userId): int
    {
        return db()->transaction(function () use ($category, $data, $userId): int {
            $id = (new DeviceService())->create($category, [
                'serial_number' => null,
                'model' => $data['model'],
                'status' => $data['status'],
            ], $userId);
            db()->insert('cctv_cameras', ['device_id' => $id, 'label' => $data['label'], 'sd_card_gb' => $data['sd_card_gb'], 'location_id' => $data['location_id']]);
            return $id;
        });
    }

    /**
     * @param array<string, mixed> $camera the current row (from find())
     * @param array{label:string, model:string, sd_card_gb:?int, location_id:?int} $data
     */
    public function update(array $camera, array $data, ?int $userId): void
    {
        db()->transaction(function () use ($camera, $data, $userId): void {
            (new DeviceService())->update((int) $camera['id'], [
                'serial_number' => db()->value('SELECT serial_number FROM devices WHERE id = ?', [$camera['id']]),
                'model' => $data['model'],
            ], $userId);
            db()->update('cctv_cameras', ['label' => $data['label'], 'sd_card_gb' => $data['sd_card_gb'], 'location_id' => $data['location_id']], ['device_id' => $camera['id']]);
        });
    }
}

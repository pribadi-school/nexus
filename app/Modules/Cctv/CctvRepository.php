<?php

declare(strict_types=1);

namespace App\Modules\Cctv;

use App\Devices\DeviceService;
use App\Devices\Status;

/** Cameras = `devices` rows (category cctv) + `cctv_cameras`. */
final class CctvRepository
{
    private const SELECT = "SELECT d.id, d.serial_number, d.model, d.status, d.status_changed_at,
                                   d.updated_at, d.created_at, d.last_checked_at,
                                   c.area_id, a.name AS area_name, c.label, c.location, c.ip_address, c.sd_card_gb,
                                   uu.name AS updated_by_name, uc.name AS created_by_name
                            FROM devices d
                            JOIN cctv_cameras c ON c.device_id = d.id
                            JOIN cctv_areas a ON a.id = c.area_id
                            LEFT JOIN users uu ON uu.id = d.updated_by
                            LEFT JOIN users uc ON uc.id = d.created_by
                            WHERE d.category = 'cctv' AND d.deleted_at IS NULL";

    /**
     * @param array{status:?string, area:?int, q:?string} $filters
     * @return list<array<string, mixed>> ordered by area, then label
     */
    public function search(array $filters): array
    {
        $sql = self::SELECT;
        $params = [];

        if ($filters['status'] !== null && Status::isValid($filters['status'])) {
            $sql .= ' AND d.status = ?';
            $params[] = $filters['status'];
        }
        if ($filters['area'] !== null) {
            $sql .= ' AND c.area_id = ?';
            $params[] = $filters['area'];
        }
        if ($filters['q'] !== null) {
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            $sql .= ' AND (c.label LIKE ? OR c.location LIKE ? OR c.ip_address LIKE ? OR d.model LIKE ? OR d.serial_number LIKE ? OR a.name LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        return db()->all($sql . ' ORDER BY a.sort_order, a.name, c.label, d.id', $params);
    }

    /**
     * @param list<array<string, mixed>> $cameras from search()
     * @return list<array{area:string, cameras:list<array<string, mixed>>}>
     */
    public static function groupByArea(array $cameras): array
    {
        $groups = [];
        foreach ($cameras as $camera) {
            $groups[(int) $camera['area_id']] ??= ['area' => $camera['area_name'], 'cameras' => []];
            $groups[(int) $camera['area_id']]['cameras'][] = $camera;
        }
        return array_values($groups);
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

    /** @return list<string> Models already used, to suggest while typing. */
    public function models(): array
    {
        return array_column(db()->all(
            "SELECT DISTINCT model AS v FROM devices WHERE category = 'cctv' AND model IS NOT NULL ORDER BY v",
        ), 'v');
    }

    /** @param array<string, mixed> $data validated form values */
    public function create(CctvCategory $category, array $data, ?int $userId): int
    {
        return db()->transaction(function () use ($category, $data, $userId): int {
            $id = (new DeviceService())->create($category, [
                'serial_number' => $data['serial_number'],
                'model' => $data['model'],
                'status' => $data['status'],
            ], $userId);
            db()->insert('cctv_cameras', ['device_id' => $id] + $this->detail($data));
            return $id;
        });
    }

    /**
     * Saves changes; moving to another area is logged in the notes.
     *
     * @param array<string, mixed> $camera the current row (from find())
     * @param array<string, mixed> $data validated form values
     */
    public function update(array $camera, array $data, ?int $userId): void
    {
        db()->transaction(function () use ($camera, $data, $userId): void {
            $service = new DeviceService();
            $service->update((int) $camera['id'], [
                'serial_number' => $data['serial_number'],
                'model' => $data['model'],
            ], $userId);
            db()->update('cctv_cameras', $this->detail($data), ['device_id' => $camera['id']]);

            if ((int) $camera['area_id'] !== (int) $data['area_id']) {
                $newArea = (string) db()->value('SELECT name FROM cctv_areas WHERE id = ?', [$data['area_id']]);
                $service->addNote((int) $camera['id'], $userId, 'report', "Moved from {$camera['area_name']} to $newArea.");
            }
        });
    }

    /** @return array<string, mixed> */
    private function detail(array $data): array
    {
        return [
            'area_id' => (int) $data['area_id'],
            'label' => $data['label'],
            'location' => $data['location'],
            'ip_address' => $data['ip_address'],
            'sd_card_gb' => $data['sd_card_gb'] === null ? null : (int) $data['sd_card_gb'],
        ];
    }
}

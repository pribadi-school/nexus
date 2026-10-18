<?php

declare(strict_types=1);

namespace App\Modules\Cctv;

use App\Core\Router;
use App\Devices\Category;
use App\Devices\LookupList;

/** Cameras, each known by its spot. Detail table: cctv_cameras. */
final class CctvCategory extends Category
{
    /** Camera types, kept in devices.model: the newer C200 or the older cameras. */
    public const TYPES = ['C200', 'Old version'];

    /** SD card sizes in GB; 256 is what the cameras are being upgraded to. */
    public const SD_SIZES = [32, 64, 128, 256];

    public function key(): string
    {
        return 'cctv';
    }

    public function name(): string
    {
        return 'CCTV';
    }

    public function icon(): string
    {
        return 'cctv';
    }

    public function noun(): array
    {
        return ['camera', 'cameras'];
    }

    public function noteExample(): string
    {
        return 'e.g. Picture is blurry at night. Lens needs cleaning.';
    }

    public function searchIds(string $query, int $limit): array
    {
        $like = self::likePattern($query);
        return array_map('intval', array_column(db()->all(
            "SELECT d.id FROM devices d
             JOIN cctv_cameras c ON c.device_id = d.id
             LEFT JOIN cctv_locations l ON l.id = c.location_id
             WHERE d.category = 'cctv' AND d.deleted_at IS NULL
               AND (c.label LIKE ? OR d.model LIKE ? OR l.name LIKE ?)
             ORDER BY d.id LIMIT " . max(1, $limit),
            [$like, $like, $like],
        ), 'id'));
    }

    public function lists(): array
    {
        return [new LookupList(
            table: 'cctv_locations',
            path: '/cctv/locations',
            title: 'Locations',
            singular: 'location',
            intro: 'The floors and other locations cameras are in.',
            category: $this,
            usageSql: "SELECT c.location_id AS id, COUNT(*) AS n
                       FROM cctv_cameras c JOIN devices d ON d.id = c.device_id AND d.deleted_at IS NULL
                       WHERE c.location_id IS NOT NULL GROUP BY c.location_id",
            usageNoun: ['camera', 'cameras'],
        )];
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/cctv', [CctvController::class, 'index']);
        $router->get('/cctv/new', [CctvController::class, 'create']);
        $router->post('/cctv', [CctvController::class, 'store']);
        $router->get('/cctv/{id}', [CctvController::class, 'show']);
        $router->get('/cctv/{id}/edit', [CctvController::class, 'edit']);
        $router->post('/cctv/{id}', [CctvController::class, 'update']);
        $router->post('/cctv/{id}/checked', [CctvController::class, 'checked']);
        $router->post('/cctv/{id}/delete', [CctvController::class, 'destroy']);
    }

    public function describe(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $rows = db()->all(
            'SELECT d.id, d.model, c.label
             FROM devices d
             JOIN cctv_cameras c ON c.device_id = d.id
             WHERE d.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            $ids,
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['id']] = [
                'title' => $row['label'],
                'context' => 'CCTV' . ($row['model'] ? ' · ' . $row['model'] : ''),
            ];
        }
        return $out;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Cctv;

use App\Core\Router;
use App\Devices\Category;
use App\Devices\LookupList;

/** Cameras, grouped by area. Detail table: cctv_cameras. */
final class CctvCategory extends Category
{
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

    public function lists(): array
    {
        return [new LookupList(
            table: 'cctv_areas',
            path: '/cctv/areas',
            title: 'Areas',
            singular: 'area',
            intro: 'The areas cameras are grouped under on the CCTV page. Each camera belongs to one area.',
            category: $this,
            usageSql: "SELECT c.area_id AS id, COUNT(*) AS n
                       FROM cctv_cameras c JOIN devices d ON d.id = c.device_id AND d.deleted_at IS NULL
                       GROUP BY c.area_id",
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
            'SELECT d.id, c.label, a.name AS area
             FROM devices d
             JOIN cctv_cameras c ON c.device_id = d.id
             JOIN cctv_areas a ON a.id = c.area_id
             WHERE d.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            $ids,
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['id']] = [
                'title' => $row['label'],
                'context' => 'CCTV · ' . $row['area'],
            ];
        }
        return $out;
    }
}

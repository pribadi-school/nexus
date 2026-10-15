<?php

declare(strict_types=1);

namespace App\Modules\Cctv;

use App\Core\Router;
use App\Devices\Category;
use App\Devices\ComingSoonController;

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

    public function registerRoutes(Router $router): void
    {
        // Placeholder until the CCTV pages are built.
        $router->get('/cctv', [ComingSoonController::class, 'show']);
        $router->get('/cctv/{id}', [ComingSoonController::class, 'show']);
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

<?php

declare(strict_types=1);

namespace App\Modules\Chromebooks;

use App\Core\Router;
use App\Devices\Category;
use App\Devices\ComingSoonController;
use App\Devices\Status;

/** Student Chromebooks with assignment history. Detail tables: chromebooks, chromebook_assignments. */
final class ChromebookCategory extends Category
{
    public function key(): string
    {
        return 'chromebook';
    }

    public function name(): string
    {
        return 'Chromebooks';
    }

    public function icon(): string
    {
        return 'laptop';
    }

    public function noun(): array
    {
        return ['Chromebook', 'Chromebooks'];
    }

    public function path(): string
    {
        return '/chromebooks';
    }

    public function statusLabel(string $status): string
    {
        return $status === Status::PROBLEM ? 'Has problem' : 'OK';
    }

    public function registerRoutes(Router $router): void
    {
        // Placeholder until the Chromebook pages are built.
        $router->get('/chromebooks', [ComingSoonController::class, 'show']);
        $router->get('/chromebooks/{id}', [ComingSoonController::class, 'show']);
    }

    public function describe(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $rows = db()->all(
            'SELECT d.id, d.serial_number, c.student_name, k.name AS class_name
             FROM devices d
             JOIN chromebooks c ON c.device_id = d.id
             LEFT JOIN classes k ON k.id = c.class_id
             WHERE d.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            $ids,
        );

        $out = [];
        foreach ($rows as $row) {
            $serial = $row['serial_number'] ?? '';
            $out[(int) $row['id']] = $row['student_name']
                ? [
                    'title' => $row['student_name'] . ($row['class_name'] ? ' (' . $row['class_name'] . ')' : ''),
                    'context' => 'Chromebook · ' . $serial,
                ]
                : [
                    'title' => 'Chromebook ' . $serial,
                    'context' => 'Chromebook · Not assigned',
                ];
        }
        return $out;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Chromebooks;

use App\Core\Router;
use App\Devices\Category;
use App\Devices\LookupList;
use App\Devices\NoteKind;
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

    public function noteKinds(): array
    {
        return [NoteKind::REPORT, NoteKind::PROBLEM, NoteKind::FIX];
    }

    public function noteExample(): string
    {
        return 'e.g. Screen cracked in the bottom corner. Student reported it after class.';
    }

    public function lists(): array
    {
        return [new LookupList(
            table: 'classes',
            path: '/chromebooks/classes',
            title: 'Classes',
            singular: 'class',
            intro: 'The classes Chromebooks are assigned to. History keeps the class name as it was, so renaming a class (for example at the yearly move up) does not change old records.',
            category: $this,
            usageSql: "SELECT c.class_id AS id, COUNT(*) AS n
                       FROM chromebooks c JOIN devices d ON d.id = c.device_id AND d.deleted_at IS NULL
                       WHERE c.class_id IS NOT NULL
                       GROUP BY c.class_id",
            usageNoun: ['Chromebook', 'Chromebooks'],
        )];
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/chromebooks', [ChromebookController::class, 'index']);
        $router->get('/chromebooks/new', [ChromebookController::class, 'create']);
        $router->post('/chromebooks', [ChromebookController::class, 'store']);
        $router->get('/chromebooks/export', [ChromebookController::class, 'export']);
        $router->get('/chromebooks/import', [ChromebookController::class, 'importForm']);
        $router->post('/chromebooks/import', [ChromebookController::class, 'importPreview']);
        $router->post('/chromebooks/import/confirm', [ChromebookController::class, 'importConfirm']);
        $router->get('/chromebooks/import/example', [ChromebookController::class, 'example']);
        $router->get('/chromebooks/{id}', [ChromebookController::class, 'show']);
        $router->get('/chromebooks/{id}/edit', [ChromebookController::class, 'edit']);
        $router->post('/chromebooks/{id}', [ChromebookController::class, 'update']);
        $router->post('/chromebooks/{id}/assign', [ChromebookController::class, 'assign']);
        $router->post('/chromebooks/{id}/delete', [ChromebookController::class, 'destroy']);
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

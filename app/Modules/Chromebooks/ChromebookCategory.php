<?php

declare(strict_types=1);

namespace App\Modules\Chromebooks;

use App\Core\Router;
use App\Devices\Category;
use App\Devices\NoteKind;
use App\Devices\Status;

/**
 * Student Chromebooks, one batch per academic year (asset IDs like 2026-05),
 * with assignment history. Detail tables: chromebooks, chromebook_assignments.
 */
final class ChromebookCategory extends Category
{
    /** The first batch; the page always shows a tab for every year from here to now. */
    public const FIRST_YEAR = 2024;

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
            'SELECT d.id, d.serial_number, c.asset_id, c.student_name
             FROM devices d
             JOIN chromebooks c ON c.device_id = d.id
             WHERE d.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            $ids,
        );

        $out = [];
        foreach ($rows as $row) {
            $label = $row['asset_id'] ?? $row['serial_number'] ?? '#' . $row['id'];
            $out[(int) $row['id']] = $row['student_name']
                ? ['title' => $row['student_name'], 'context' => 'Chromebook · ' . $label]
                : ['title' => 'Chromebook ' . $label, 'context' => 'Chromebook · Not assigned'];
        }
        return $out;
    }

    /** @return list<int> Every academic year from the first batch to now (or the latest batch, if later), newest first. */
    public function years(): array
    {
        $now = (int) (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Jakarta')))->format('Y');
        $latest = (int) db()->value('SELECT MAX(academic_year) FROM chromebooks');
        return range(max($now, $latest), self::FIRST_YEAR);
    }
}

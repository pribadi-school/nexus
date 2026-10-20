<?php

declare(strict_types=1);

namespace App\Modules\Chromebooks;

use App\Core\Router;
use App\Devices\Category;
use App\Devices\NoteKind;
use App\Devices\Status;

/**
 * Student Chromebooks by academic year and school-owned Chromebooks,
 * with assignment history. Detail tables: chromebooks, chromebook_assignments.
 */
final class ChromebookCategory extends Category
{
    /** Numeric academic batches start here; School is a separate option. */
    public const FIRST_YEAR = 2024;

    /** School-owned devices have no academic batch; zero is reserved in the existing year column. */
    public const SCHOOL_YEAR = 0;

    public static function yearLabel(int $year): string
    {
        return $year === self::SCHOOL_YEAR ? 'School' : (string) $year;
    }

    public static function yearHeading(int $year): string
    {
        return $year === self::SCHOOL_YEAR ? 'School' : 'Academic year ' . $year;
    }

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

    public function searchIds(string $query, int $limit): array
    {
        $like = self::likePattern($query);
        return array_map('intval', array_column(db()->all(
            "SELECT d.id FROM devices d
             JOIN chromebooks c ON c.device_id = d.id
             WHERE d.category = 'chromebook' AND d.deleted_at IS NULL
               AND (c.asset_id LIKE ? OR d.serial_number LIKE ? OR c.student_name LIKE ? OR c.student_email LIKE ?)
             ORDER BY c.academic_year DESC, c.asset_id LIMIT " . max(1, $limit),
            [$like, $like, $like, $like],
        ), 'id'));
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/chromebooks', [ChromebookController::class, 'index']);
        $router->get('/chromebooks/google', [GoogleChromebookController::class, 'setup']);
        $router->post('/chromebooks/{id}/google/refresh', [GoogleChromebookController::class, 'refresh']);
        $router->get('/chromebooks/{id}/google/ou', [GoogleChromebookController::class, 'orgUnits']);
        $router->post('/chromebooks/{id}/google/ou', [GoogleChromebookController::class, 'move']);
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

    /** @return list<int> Academic years newest first, followed by the School category. */
    public function years(): array
    {
        $now = (int) (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Jakarta')))->format('Y');
        $latest = (int) db()->value('SELECT MAX(academic_year) FROM chromebooks');
        return [...range(max($now, $latest), self::FIRST_YEAR), self::SCHOOL_YEAR];
    }
}

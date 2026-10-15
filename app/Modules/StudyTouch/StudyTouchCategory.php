<?php

declare(strict_types=1);

namespace App\Modules\StudyTouch;

use App\Core\Router;
use App\Devices\Category;
use App\Devices\ComingSoonController;
use App\Devices\NoteKind;

/** Interactive boards in classrooms and other rooms. Detail table: studytouch_boards. */
final class StudyTouchCategory extends Category
{
    public function key(): string
    {
        return 'studytouch';
    }

    public function name(): string
    {
        return 'StudyTouch';
    }

    public function icon(): string
    {
        return 'presentation';
    }

    public function noun(): array
    {
        return ['board', 'boards'];
    }

    public function noteKinds(): array
    {
        return [NoteKind::REPORT, NoteKind::PROBLEM, NoteKind::FIX, NoteKind::FIRMWARE];
    }

    public function registerRoutes(Router $router): void
    {
        // Placeholder until the StudyTouch pages are built.
        $router->get('/studytouch', [ComingSoonController::class, 'show']);
        $router->get('/studytouch/{id}', [ComingSoonController::class, 'show']);
    }

    public function describe(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $rows = db()->all(
            'SELECT d.id, d.model, r.name AS room
             FROM devices d
             JOIN studytouch_boards b ON b.device_id = d.id
             JOIN rooms r ON r.id = b.room_id
             WHERE d.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            $ids,
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['id']] = [
                'title' => $row['room'],
                'context' => 'StudyTouch' . ($row['model'] ? ' · ' . $row['model'] : ''),
            ];
        }
        return $out;
    }
}

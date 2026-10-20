<?php

declare(strict_types=1);

namespace App\Modules\StudyTouch;

use App\Core\Router;
use App\Devices\Category;
use App\Devices\LookupList;
use App\Devices\NoteKind;

/** Interactive boards in classrooms and other rooms. Detail table: studytouch_boards. */
final class StudyTouchCategory extends Category
{
    /** The two kinds of board the school has; the exact type number is not tracked. */
    public const MODELS = ['StudyTouch', 'Merah Putih'];

    /** Every board runs this version (released 29 September 2025); new boards start with it. */
    public const CURRENT_FIRMWARE = 'v2.0.290925';

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

    public function noteExample(): string
    {
        return 'e.g. Touch does not respond on the left side. Teacher reported it this morning.';
    }

    public function searchIds(string $query, int $limit): array
    {
        [$match, $params] = $this->searchMatch($query, ['r.name', 'b.firmware_version']);
        return array_map('intval', array_column(db()->all(
            "SELECT d.id FROM devices d
             JOIN studytouch_boards b ON b.device_id = d.id
             JOIN rooms r ON r.id = b.room_id
             WHERE d.category = 'studytouch' AND d.deleted_at IS NULL
               AND $match
             ORDER BY r.sort_order, r.name LIMIT " . max(1, $limit),
            $params,
        ), 'id'));
    }

    public function lists(): array
    {
        return [new LookupList(
            table: 'rooms',
            path: '/studytouch/rooms',
            title: 'Rooms',
            singular: 'room',
            intro: 'The rooms StudyTouch boards are in. Each board belongs to one room.',
            category: $this,
            usageSql: "SELECT b.room_id AS id, COUNT(*) AS n
                       FROM studytouch_boards b JOIN devices d ON d.id = b.device_id AND d.deleted_at IS NULL
                       GROUP BY b.room_id",
            usageNoun: ['board', 'boards'],
        )];
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/studytouch', [StudyTouchController::class, 'index']);
        $router->get('/studytouch/new', [StudyTouchController::class, 'create']);
        $router->post('/studytouch', [StudyTouchController::class, 'store']);
        $router->get('/studytouch/{id}', [StudyTouchController::class, 'show']);
        $router->get('/studytouch/{id}/edit', [StudyTouchController::class, 'edit']);
        $router->post('/studytouch/{id}', [StudyTouchController::class, 'update']);
        $router->post('/studytouch/{id}/delete', [StudyTouchController::class, 'destroy']);
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
                'context' => 'StudyTouch' . ($row['model'] && $row['model'] !== 'StudyTouch' ? ' · ' . $row['model'] : ''),
            ];
        }
        return $out;
    }
}

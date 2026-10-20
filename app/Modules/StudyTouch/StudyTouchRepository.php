<?php

declare(strict_types=1);

namespace App\Modules\StudyTouch;

use App\Devices\DeviceService;
use App\Devices\Status;

/** Boards = `devices` rows (category studytouch) + `studytouch_boards`. */
final class StudyTouchRepository
{
    private const SELECT = "SELECT d.id, d.serial_number, d.model, d.status, d.status_changed_at,
                                   d.updated_at, d.created_at, d.last_checked_at,
                                   b.firmware_version, b.room_id, r.name AS room_name,
                                   uu.name AS updated_by_name, uc.name AS created_by_name,
                                   n.body AS latest_note, n.created_at AS latest_note_at
                            FROM devices d
                            -- Latest note a person wrote (not the automatic status lines),
                            -- shown for every board in the Latest note column.
                            LEFT JOIN device_notes n ON n.id = (
                                SELECT n2.id FROM device_notes n2
                                WHERE n2.device_id = d.id AND n2.kind <> 'status'
                                ORDER BY n2.created_at DESC, n2.id DESC LIMIT 1
                            )
                            JOIN studytouch_boards b ON b.device_id = d.id
                            JOIN rooms r ON r.id = b.room_id
                            LEFT JOIN users uu ON uu.id = d.updated_by
                            LEFT JOIN users uc ON uc.id = d.created_by
                            WHERE d.category = 'studytouch' AND d.deleted_at IS NULL";

    /**
     * @param array{status:?string, model?:?string, q:?string} $filters
     * @return list<array<string, mixed>>
     */
    public function search(array $filters): array
    {
        $sql = self::SELECT;
        $params = [];

        if ($filters['status'] !== null && Status::isValid($filters['status'])) {
            $sql .= ' AND d.status = ?';
            $params[] = $filters['status'];
        }
        if (in_array($filters['model'] ?? null, StudyTouchCategory::MODELS, true)) {
            $sql .= ' AND d.model = ?';
            $params[] = $filters['model'];
        }
        if ($filters['q'] !== null) {
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            $sql .= ' AND (d.model LIKE ? OR r.name LIKE ? OR b.firmware_version LIKE ?)';
            array_push($params, $like, $like, $like);
        }

        return db()->all($sql . ' ORDER BY r.sort_order, r.name, d.id', $params);
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
             FROM devices WHERE category = 'studytouch' AND deleted_at IS NULL",
        );
        $total = (int) ($row['total'] ?? 0);
        $problem = (int) ($row['problem'] ?? 0);
        return ['total' => $total, 'ok' => $total - $problem, 'problem' => $problem];
    }

    /** @return list<string> Firmware versions already used, to suggest while typing. */
    public function firmwareVersions(): array
    {
        return array_column(db()->all(
            'SELECT DISTINCT b.firmware_version AS v FROM studytouch_boards b WHERE b.firmware_version IS NOT NULL ORDER BY v',
        ), 'v');
    }

    /**
     * Boards are known by their room, so no serial number is kept.
     *
     * @param array{room_id:int, model:string, firmware_version:?string, status:string} $data
     */
    public function create(StudyTouchCategory $category, array $data, ?int $userId): int
    {
        return db()->transaction(function () use ($category, $data, $userId): int {
            $id = (new DeviceService())->create($category, [
                'serial_number' => null,
                'model' => $data['model'],
                'status' => $data['status'],
            ], $userId);
            db()->insert('studytouch_boards', [
                'device_id' => $id,
                'room_id' => $data['room_id'],
                'firmware_version' => $data['firmware_version'],
            ]);
            return $id;
        });
    }

    /**
     * Saves changes; a new firmware version is logged in the notes.
     *
     * @param array<string, mixed> $board the current row (from find())
     * @param array{room_id:int, model:string, firmware_version:?string} $data
     */
    public function update(array $board, array $data, ?int $userId): void
    {
        db()->transaction(function () use ($board, $data, $userId): void {
            $service = new DeviceService();
            $service->update((int) $board['id'], [
                'serial_number' => $board['serial_number'],
                'model' => $data['model'],
            ], $userId);
            db()->update('studytouch_boards', [
                'room_id' => $data['room_id'],
                'firmware_version' => $data['firmware_version'],
            ], ['device_id' => $board['id']]);

            if ((int) $board['room_id'] !== $data['room_id']) {
                $newRoom = (string) db()->value('SELECT name FROM rooms WHERE id = ?', [$data['room_id']]);
                $service->addNote((int) $board['id'], $userId, 'report', "Moved from {$board['room_name']} to $newRoom.");
            }

            $from = $board['firmware_version'];
            $to = $data['firmware_version'];
            if ($from !== $to) {
                $body = match (true) {
                    $from === null => "Firmware recorded as $to.",
                    $to === null => "Firmware version cleared (was $from).",
                    default => "Firmware updated from $from to $to.",
                };
                $service->addNote((int) $board['id'], $userId, 'firmware', $body, ['from' => $from, 'to' => $to]);
            }
        });
    }
}

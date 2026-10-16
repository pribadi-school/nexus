<?php

declare(strict_types=1);

namespace App\Modules\Chromebooks;

use App\Core\Database;
use App\Devices\DeviceService;
use App\Devices\NoteKind;
use App\Devices\Status;

/**
 * Chromebooks = `devices` rows (category chromebook) + `chromebooks`, with
 * their assignment history in `chromebook_assignments`.
 *
 * Rule: the open assignment row (ended_at IS NULL) always matches the student
 * in `chromebooks`, and every change of student or class writes an
 * "assignment" note in the same transaction.
 */
final class ChromebookRepository
{
    public const PER_PAGE = 50;

    private const SELECT = "SELECT d.id, d.serial_number, d.model, d.status, d.status_changed_at,
                                   d.updated_at, d.created_at, d.last_checked_at,
                                   c.student_name, c.class_id, k.name AS class_name, c.assigned_at,
                                   uu.name AS updated_by_name, uc.name AS created_by_name
                            FROM devices d
                            JOIN chromebooks c ON c.device_id = d.id
                            LEFT JOIN classes k ON k.id = c.class_id
                            LEFT JOIN users uu ON uu.id = d.updated_by
                            LEFT JOIN users uc ON uc.id = d.created_by
                            WHERE d.category = 'chromebook' AND d.deleted_at IS NULL";

    /** Sorted by class, then student; unassigned Chromebooks last. */
    private const ORDER = ' ORDER BY c.class_id IS NULL, k.sort_order, k.name, c.student_name IS NULL, c.student_name, d.serial_number, d.id';

    /**
     * @param array{status:?string, class:?int, q:?string} $filters  class 0 = not assigned to a class
     * @return array{rows:list<array<string, mixed>>, total:int}
     */
    public function search(array $filters, int $page = 1, ?int $perPage = self::PER_PAGE): array
    {
        [$where, $params] = $this->where($filters);
        $total = (int) db()->value(
            "SELECT COUNT(*) FROM devices d JOIN chromebooks c ON c.device_id = d.id
             WHERE d.category = 'chromebook' AND d.deleted_at IS NULL" . $where,
            $params,
        );
        $sql = self::SELECT . $where . self::ORDER;
        if ($perPage !== null) {
            $sql .= ' LIMIT ' . $perPage . ' OFFSET ' . (max(1, $page) - 1) * $perPage;
        }
        return ['rows' => db()->all($sql, $params), 'total' => $total];
    }

    /** @return array{0:string, 1:list<mixed>} */
    private function where(array $filters): array
    {
        $sql = '';
        $params = [];
        if ($filters['status'] !== null && Status::isValid($filters['status'])) {
            $sql .= ' AND d.status = ?';
            $params[] = $filters['status'];
        }
        if ($filters['class'] === 0) {
            $sql .= ' AND c.class_id IS NULL';
        } elseif ($filters['class'] !== null) {
            $sql .= ' AND c.class_id = ?';
            $params[] = $filters['class'];
        }
        if ($filters['q'] !== null) {
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            $sql .= ' AND (d.serial_number LIKE ? OR c.student_name LIKE ?)';
            array_push($params, $like, $like);
        }
        return [$sql, $params];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return db()->one(self::SELECT . ' AND d.id = ?', [$id]);
    }

    /** @return array<string, mixed>|null */
    public function findBySerial(string $serial): ?array
    {
        return db()->one(self::SELECT . ' AND d.serial_number = ?', [$serial]);
    }

    /** @return array{total:int, ok:int, problem:int, unassigned:int} */
    public function counts(): array
    {
        $row = db()->one(
            "SELECT COUNT(*) AS total, COALESCE(SUM(d.status = 'problem'), 0) AS problem,
                    COALESCE(SUM(c.student_name IS NULL), 0) AS unassigned
             FROM devices d JOIN chromebooks c ON c.device_id = d.id
             WHERE d.category = 'chromebook' AND d.deleted_at IS NULL",
        );
        $total = (int) ($row['total'] ?? 0);
        $problem = (int) ($row['problem'] ?? 0);
        return ['total' => $total, 'ok' => $total - $problem, 'problem' => $problem, 'unassigned' => (int) ($row['unassigned'] ?? 0)];
    }

    /** @return list<array<string, mixed>> newest first */
    public function history(int $deviceId): array
    {
        return db()->all(
            'SELECT a.student_name, a.class_name, a.assigned_at, a.ended_at, u.name AS assigned_by_name
             FROM chromebook_assignments a LEFT JOIN users u ON u.id = a.assigned_by
             WHERE a.device_id = ? ORDER BY a.assigned_at DESC, a.id DESC',
            [$deviceId],
        );
    }

    /** @return list<string> */
    public function models(): array
    {
        return array_column(db()->all(
            "SELECT DISTINCT model AS v FROM devices WHERE category = 'chromebook' AND model IS NOT NULL ORDER BY v",
        ), 'v');
    }

    /**
     * @param array{serial_number:?string, model:?string, status:string, student_name:?string, class_id:?int} $data
     */
    public function create(ChromebookCategory $category, array $data, ?int $userId): int
    {
        return db()->transaction(function () use ($category, $data, $userId): int {
            $id = (new DeviceService())->create($category, [
                'serial_number' => $data['serial_number'],
                'model' => $data['model'],
                'status' => $data['status'],
            ], $userId);
            db()->insert('chromebooks', ['device_id' => $id, 'student_name' => null, 'class_id' => null, 'assigned_at' => null]);
            if ($data['student_name'] !== null || $data['class_id'] !== null) {
                $this->assign(['id' => $id, 'student_name' => null, 'class_id' => null, 'class_name' => null], $data['student_name'], $data['class_id'], $userId);
            }
            return $id;
        });
    }

    /** @param array{serial_number:?string, model:?string} $data */
    public function update(array $chromebook, array $data, ?int $userId): void
    {
        (new DeviceService())->update((int) $chromebook['id'], $data, $userId);
    }

    /**
     * Gives the Chromebook to another student and/or class (null student =
     * hand it back). Closes the open history row, opens a new one and writes
     * an assignment note. Returns false when nothing changed.
     *
     * @param array<string, mixed> $chromebook the current row (from find())
     */
    public function assign(array $chromebook, ?string $student, ?int $classId, ?int $userId): bool
    {
        $oldStudent = $chromebook['student_name'];
        $oldClassId = $chromebook['class_id'] === null ? null : (int) $chromebook['class_id'];
        if ($oldStudent === $student && $oldClassId === $classId) {
            return false;
        }
        $className = $classId === null ? null : (string) db()->value('SELECT name FROM classes WHERE id = ?', [$classId]);

        db()->transaction(function () use ($chromebook, $student, $classId, $className, $oldStudent, $userId): void {
            $id = (int) $chromebook['id'];
            $now = Database::now();
            db()->run('UPDATE chromebook_assignments SET ended_at = ? WHERE device_id = ? AND ended_at IS NULL', [$now, $id]);
            if ($student !== null) {
                db()->insert('chromebook_assignments', [
                    'device_id' => $id,
                    'student_name' => $student,
                    'class_name' => $className,
                    'assigned_at' => $now,
                    'assigned_by' => $userId,
                ]);
            }
            db()->update('chromebooks', [
                'student_name' => $student,
                'class_id' => $classId,
                'assigned_at' => $student === null ? null : $now,
            ], ['device_id' => $id]);

            $who = static fn (?string $name, ?string $class): string => $name === null
                ? ($class === null ? 'nobody' : "class $class (no student)")
                : $name . ($class === null ? '' : " ($class)");
            $from = $who($oldStudent, $chromebook['class_name']);
            $to = $who($student, $className);
            $body = match (true) {
                $oldStudent === null && $student !== null => "Assigned to $to.",
                $student === null && $oldStudent !== null => "Returned by $from; not assigned now.",
                default => "Reassigned from $from to $to.",
            };
            (new DeviceService())->addNote($id, $userId, NoteKind::ASSIGNMENT, $body, [
                'from' => ['student' => $oldStudent, 'class' => $chromebook['class_name']],
                'to' => ['student' => $student, 'class' => $className],
            ]);
        });
        return true;
    }
}

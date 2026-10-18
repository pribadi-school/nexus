<?php

declare(strict_types=1);

namespace App\Modules\Chromebooks;

use App\Core\Database;
use App\Devices\DeviceService;
use App\Devices\DeviceServiceError;
use App\Devices\NoteKind;
use App\Devices\Status;

/**
 * Chromebooks = `devices` rows (category chromebook) + `chromebooks`, with
 * their assignment history in `chromebook_assignments`.
 *
 * Each Chromebook belongs to the batch of one academic year and usually has
 * an asset ID ("2026-05"); asset IDs are unique among Chromebooks that are
 * not deleted. Rule: the open assignment row (ended_at IS NULL) always
 * matches the student in `chromebooks`, and every change of student writes
 * an "assignment" note in the same transaction.
 */
final class ChromebookRepository
{
    public const PER_PAGE = 50;

    /** Thrown (as DeviceServiceError reason) when an asset ID is already used. */
    public const DUPLICATE_ASSET = 'duplicate_asset';

    private const SELECT = "SELECT d.id, d.serial_number, d.model, d.status, d.status_changed_at,
                                   d.updated_at, d.created_at, d.last_checked_at,
                                   c.academic_year, c.asset_id, c.student_name, c.student_email, c.assigned_at,
                                   uu.name AS updated_by_name, uc.name AS created_by_name
                            FROM devices d
                            JOIN chromebooks c ON c.device_id = d.id
                            LEFT JOIN users uu ON uu.id = d.updated_by
                            LEFT JOIN users uc ON uc.id = d.created_by
                            WHERE d.category = 'chromebook' AND d.deleted_at IS NULL";

    /** Newest academic year first, then by asset ID (2026-9 before 2026-10), then Chromebooks without one. */
    private const ORDER = ' ORDER BY c.academic_year DESC, c.asset_id IS NULL, CHAR_LENGTH(c.asset_id), c.asset_id, c.student_name, d.id';

    /**
     * @param array{year:?int, status:?string, q:?string} $filters  year null = every year
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
        if ($filters['year'] !== null) {
            $sql .= ' AND c.academic_year = ?';
            $params[] = $filters['year'];
        }
        if ($filters['status'] !== null && Status::isValid($filters['status'])) {
            $sql .= ' AND d.status = ?';
            $params[] = $filters['status'];
        }
        if ($filters['q'] !== null) {
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            $sql .= ' AND (c.asset_id LIKE ? OR d.serial_number LIKE ? OR c.student_name LIKE ? OR c.student_email LIKE ?)';
            array_push($params, $like, $like, $like, $like);
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

    /** @return array<string, mixed>|null */
    public function findByAsset(string $assetId): ?array
    {
        return db()->one(self::SELECT . ' AND c.asset_id = ?', [$assetId]);
    }

    /** @return array{total:int, ok:int, problem:int, unassigned:int} for one academic year, or every year when null */
    public function counts(?int $year): array
    {
        $row = db()->one(
            "SELECT COUNT(*) AS total, COALESCE(SUM(d.status = 'problem'), 0) AS problem,
                    COALESCE(SUM(c.student_name IS NULL), 0) AS unassigned
             FROM devices d JOIN chromebooks c ON c.device_id = d.id
             WHERE d.category = 'chromebook' AND d.deleted_at IS NULL" . ($year !== null ? ' AND c.academic_year = ?' : ''),
            $year !== null ? [$year] : [],
        );
        $total = (int) ($row['total'] ?? 0);
        $problem = (int) ($row['problem'] ?? 0);
        return ['total' => $total, 'ok' => $total - $problem, 'problem' => $problem, 'unassigned' => (int) ($row['unassigned'] ?? 0)];
    }

    /** @return array<int, array{total:int, problem:int}> per academic year, for the year filter */
    public function yearCounts(): array
    {
        $out = [];
        foreach (db()->all(
            "SELECT c.academic_year AS y, COUNT(*) AS total, COALESCE(SUM(d.status = 'problem'), 0) AS problem
             FROM devices d JOIN chromebooks c ON c.device_id = d.id
             WHERE d.category = 'chromebook' AND d.deleted_at IS NULL
             GROUP BY c.academic_year",
        ) as $row) {
            $out[(int) $row['y']] = ['total' => (int) $row['total'], 'problem' => (int) $row['problem']];
        }
        return $out;
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
            "SELECT DISTINCT model AS v FROM devices WHERE category = 'chromebook' AND deleted_at IS NULL AND model IS NOT NULL ORDER BY v",
        ), 'v');
    }

    /**
     * @param array{academic_year:int, asset_id:?string, serial_number:?string, model:?string, status:string,
     *              student_name:?string, student_email:?string} $data
     */
    public function create(ChromebookCategory $category, array $data, ?int $userId): int
    {
        return db()->transaction(function () use ($category, $data, $userId): int {
            $this->assertAssetFree($data['asset_id'], null);
            $id = (new DeviceService())->create($category, [
                'serial_number' => $data['serial_number'],
                'model' => $data['model'],
                'status' => $data['status'],
            ], $userId);
            db()->insert('chromebooks', [
                'device_id' => $id,
                'academic_year' => $data['academic_year'],
                'asset_id' => $data['asset_id'],
                'student_name' => null,
                'student_email' => null,
                'assigned_at' => null,
            ]);
            if ($data['student_name'] !== null) {
                $this->assign(['id' => $id, 'student_name' => null, 'student_email' => null], $data['student_name'], $data['student_email'], $userId);
            }
            return $id;
        });
    }

    /** @param array{academic_year:int, asset_id:?string, serial_number:?string, model:?string} $data */
    public function update(array $chromebook, array $data, ?int $userId): void
    {
        db()->transaction(function () use ($chromebook, $data, $userId): void {
            $this->assertAssetFree($data['asset_id'], (int) $chromebook['id']);
            (new DeviceService())->update((int) $chromebook['id'], [
                'serial_number' => $data['serial_number'],
                'model' => $data['model'],
            ], $userId);
            db()->update('chromebooks', [
                'academic_year' => $data['academic_year'],
                'asset_id' => $data['asset_id'],
            ], ['device_id' => $chromebook['id']]);
        });
    }

    /**
     * Gives the Chromebook to another student (null = handed back). A new
     * student closes the open history row, opens a new one and writes an
     * assignment note; a new email for the same student is just saved.
     * Returns false when nothing changed.
     *
     * @param array<string, mixed> $chromebook the current row (from find())
     */
    public function assign(array $chromebook, ?string $student, ?string $email, ?int $userId): bool
    {
        $id = (int) $chromebook['id'];
        $email = $student === null ? null : $email;
        $oldStudent = $chromebook['student_name'];
        if ($oldStudent === $student) {
            if ($chromebook['student_email'] === $email) {
                return false;
            }
            db()->update('chromebooks', ['student_email' => $email], ['device_id' => $id]);
            return true;
        }

        db()->transaction(function () use ($id, $student, $email, $oldStudent, $userId): void {
            $now = Database::now();
            db()->run('UPDATE chromebook_assignments SET ended_at = ? WHERE device_id = ? AND ended_at IS NULL', [$now, $id]);
            if ($student !== null) {
                db()->insert('chromebook_assignments', [
                    'device_id' => $id,
                    'student_name' => $student,
                    'assigned_at' => $now,
                    'assigned_by' => $userId,
                ]);
            }
            db()->update('chromebooks', [
                'student_name' => $student,
                'student_email' => $email,
                'assigned_at' => $student === null ? null : $now,
            ], ['device_id' => $id]);

            $body = match (true) {
                $oldStudent === null => "Assigned to $student.",
                $student === null => "Returned by $oldStudent; not assigned now.",
                default => "Reassigned from $oldStudent to $student.",
            };
            (new DeviceService())->addNote($id, $userId, NoteKind::ASSIGNMENT, $body, [
                'from' => ['student' => $oldStudent],
                'to' => ['student' => $student],
            ]);
        });
        return true;
    }

    private function assertAssetFree(?string $assetId, ?int $exceptId): void
    {
        if ($assetId === null) {
            return;
        }
        $other = $this->findByAsset($assetId);
        if ($other !== null && (int) $other['id'] !== $exceptId) {
            throw new DeviceServiceError(self::DUPLICATE_ASSET, 'asset_id');
        }
    }
}

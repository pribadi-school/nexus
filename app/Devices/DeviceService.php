<?php

declare(strict_types=1);

namespace App\Devices;

use App\Core\Database;
use PDOException;

/**
 * Writes to the shared `devices` table and the notes log, for every category.
 * Category modules call these inside their own transactions when they also
 * write a detail table.
 *
 * Rule: every status change writes a "status" note, in the same transaction.
 */
final class DeviceService
{
    /** Thrown when a serial number is already used by another device of the same category. */
    public const DUPLICATE_SERIAL = 'duplicate_serial';

    /**
     * Inserts the shared part of a new device and returns its id. If it starts
     * with a problem, that is logged like any other status change.
     *
     * @param array{serial_number:?string, model:?string, status:string} $fields
     */
    public function create(Category $category, array $fields, ?int $userId): int
    {
        $now = Database::now();
        $id = $this->guardSerial(fn () => db()->insert('devices', [
            'category' => $category->key(),
            'serial_number' => $fields['serial_number'],
            'model' => $fields['model'],
            'status' => $fields['status'],
            'status_changed_at' => $now,
            'last_checked_at' => $now,
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        if ($fields['status'] === Status::PROBLEM) {
            $this->writeStatusNote($category, $id, null, Status::PROBLEM, $userId, $now);
        }
        return $id;
    }

    /** @param array{serial_number:?string, model:?string} $fields */
    public function update(int $id, array $fields, ?int $userId): void
    {
        $this->guardSerial(fn () => db()->update('devices', [
            'serial_number' => $fields['serial_number'],
            'model' => $fields['model'],
            'updated_by' => $userId,
            'updated_at' => Database::now(),
        ], ['id' => $id]));
    }

    /**
     * Sets a new status and logs it. Returns false when the device already
     * had that status (nothing is written).
     *
     * @param array<string, mixed> $device a devices row
     */
    public function changeStatus(Category $category, array $device, string $status, ?int $userId): bool
    {
        if ($device['status'] === $status) {
            return false;
        }
        $now = Database::now();

        db()->transaction(function () use ($category, $device, $status, $userId, $now): void {
            db()->update('devices', [
                'status' => $status,
                'status_changed_at' => $now,
                'last_checked_at' => $now,
                'updated_by' => $userId,
                'updated_at' => $now,
            ], ['id' => $device['id']]);
            $this->writeStatusNote($category, (int) $device['id'], (string) $device['status'], $status, $userId, $now);
        });
        return true;
    }

    /**
     * Adds a note to a device's log and marks the device as updated.
     *
     * @param array<string, mixed>|null $meta
     */
    public function addNote(int $deviceId, ?int $userId, string $kind, string $body, ?array $meta = null): int
    {
        $now = Database::now();
        $noteId = db()->insert('device_notes', [
            'device_id' => $deviceId,
            'user_id' => $userId,
            'kind' => $kind,
            'body' => $body,
            'meta' => $meta === null ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);
        db()->update('devices', ['updated_by' => $userId, 'updated_at' => $now], ['id' => $deviceId]);
        return $noteId;
    }

    /** Records that someone looked at the device and found it as listed. */
    public function markChecked(int $deviceId, ?int $userId): void
    {
        $now = Database::now();
        db()->update('devices', [
            'last_checked_at' => $now,
            'updated_by' => $userId,
            'updated_at' => $now,
        ], ['id' => $deviceId]);
    }

    /** Hides the device everywhere. Its row and notes stay in the database. */
    public function delete(int $deviceId, ?int $userId): void
    {
        db()->update('devices', ['deleted_at' => Database::now(), 'updated_by' => $userId], ['id' => $deviceId]);
    }

    private function writeStatusNote(Category $category, int $deviceId, ?string $from, string $to, ?int $userId, string $now): void
    {
        db()->insert('device_notes', [
            'device_id' => $deviceId,
            'user_id' => $userId,
            'kind' => NoteKind::STATUS,
            'body' => 'Status changed to ' . $category->statusLabel($to),
            'meta' => json_encode(['from' => $from, 'to' => $to], JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);
    }

    /**
     * Runs a write and turns the unique-serial violation into a
     * DeviceServiceError the form can show next to the field.
     *
     * @template T
     * @param callable(): T $write
     * @return T
     */
    private function guardSerial(callable $write): mixed
    {
        try {
            return $write();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'uq_devices_active_serial')) {
                throw new DeviceServiceError(self::DUPLICATE_SERIAL, 'serial_number');
            }
            throw $e;
        }
    }
}

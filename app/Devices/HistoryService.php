<?php
declare(strict_types=1);
namespace App\Devices;

use App\Core\Database;
use App\Core\HttpException;

/** Corrects history while keeping the previous version and the status chain. */
final class HistoryService
{
    public static function version(array $note): string
    {
        $meta = is_string($note['meta'] ?? null) ? json_decode($note['meta'], true) : ($note['meta'] ?? null);
        return hash('sha256', json_encode([$note['id'], $note['body'], $meta, $note['edited_at'] ?? null], JSON_THROW_ON_ERROR));
    }

    public function edit(Category $category, int $deviceId, int $noteId, string $body, string $status, string $version, ?int $userId): bool
    {
        return db()->transaction(function () use ($category, $deviceId, $noteId, $body, $status, $version, $userId): bool {
            $device = db()->one('SELECT * FROM devices WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$deviceId]);
            if ($device === null || $device['category'] !== $category->key()) throw HttpException::notFound();
            $note = db()->one('SELECT * FROM device_notes WHERE id = ? AND device_id = ? FOR UPDATE', [$noteId, $deviceId]);
            if ($note === null || $note['kind'] === NoteKind::ASSIGNMENT) throw HttpException::notFound('That history entry cannot be edited.');
            if (!hash_equals(self::version($note), $version)) throw new HttpException(409, 'This entry changed since you opened it. Reload it before editing again.');

            $meta = is_string($note['meta']) ? (json_decode($note['meta'], true) ?: []) : [];
            $isStatus = $note['kind'] === NoteKind::STATUS;
            if ($isStatus) {
                if (!$category->acceptsStatus($status)) throw new HttpException(422, 'Choose a valid Chromebook status.');
                if (($meta['to'] ?? null) === $status) return false;
                $meta['to'] = $status;
                $body = 'Status changed to ' . $category->statusLabel($status);
            } elseif ($body === '' || mb_strlen($body) > DeviceController::NOTE_MAX) {
                throw new HttpException(422, 'Write a note of 1 to ' . DeviceController::NOTE_MAX . ' characters.');
            }
            $encoded = $isStatus ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : $note['meta'];
            if ($body === $note['body'] && $encoded === $note['meta']) return false;
            $now = Database::now();
            $this->saveRevision($note, $userId, $now);
            db()->update('device_notes', ['body' => $body, 'meta' => $encoded, 'edited_by' => $userId, 'edited_at' => $now], ['id' => $noteId]);

            if ($isStatus) {
                $next = db()->one("SELECT * FROM device_notes WHERE device_id = ? AND kind = 'status'
                    AND (created_at > ? OR (created_at = ? AND id > ?)) ORDER BY created_at, id LIMIT 1 FOR UPDATE",
                    [$deviceId, $note['created_at'], $note['created_at'], $noteId]);
                if ($next === null) {
                    db()->update('devices', ['status' => $status, 'status_changed_at' => $note['created_at'], 'updated_at' => $now, 'updated_by' => $userId], ['id' => $deviceId]);
                } else {
                    $nextMeta = json_decode($next['meta'] ?? '', true) ?: [];
                    $nextMeta['from'] = $status;
                    $this->saveRevision($next, $userId, $now);
                    db()->update('device_notes', ['meta' => json_encode($nextMeta, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'edited_at' => $now, 'edited_by' => $userId], ['id' => $next['id']]);
                }
            }
            db()->update('devices', ['updated_at' => $now, 'updated_by' => $userId], ['id' => $deviceId]);
            return true;
        });
    }

    private function saveRevision(array $note, ?int $userId, string $now): void
    {
        db()->insert('device_note_revisions', ['note_id' => $note['id'], 'body' => $note['body'], 'meta' => $note['meta'], 'edited_by' => $userId, 'edited_at' => $now]);
    }
}

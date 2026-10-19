<?php

declare(strict_types=1);

namespace App\Modules\Signage;

use App\Core\Database;
use App\Core\HttpException;
use InvalidArgumentException;

final class SignageRepository
{
    private const SCREEN_SELECT = 'SELECT s.*, p.name AS playlist_name,
        (SELECT COUNT(*) FROM signage_playlist_items i WHERE i.playlist_id = s.playlist_id) AS item_count,
        c.name AS last_content_name
        FROM signage_screens s
        LEFT JOIN signage_playlists p ON p.id = s.playlist_id
        LEFT JOIN signage_content c ON c.id = s.last_content_id';

    /** @return list<array<string,mixed>> */
    public function screens(): array
    {
        return db()->all(self::SCREEN_SELECT . ' ORDER BY s.name, s.id');
    }

    /** @return array<string,mixed>|null */
    public function screen(int $id): ?array
    {
        return db()->one(self::SCREEN_SELECT . ' WHERE s.id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function screenByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return null;
        }
        return db()->one(self::SCREEN_SELECT . ' WHERE s.token = ?', [$token]);
    }

    /** @param array<string,mixed> $data */
    public function createScreen(array $data): int
    {
        return db()->transaction(function () use ($data): int {
            $this->lockAssignedPlaylist($data['playlist_id']);
            $now = Database::now();
            return db()->insert('signage_screens', $data + [
                'token' => bin2hex(random_bytes(32)), 'created_at' => $now, 'updated_at' => $now,
            ]);
        });
    }

    /** Create a screen and its own empty playlist in the same transaction. */
    public function createScreenWithPlaylist(array $data): int
    {
        return db()->transaction(function () use ($data): int {
            $data['playlist_id'] = $this->createPlaylist(['name' => $data['name'], 'description' => null]);
            return $this->createScreen($data);
        });
    }

    /**
     * Initialize older screens without changing what they play. Shared legacy
     * playlists are copied before editing so one TV cannot change another TV.
     */
    public function screenPlaylistId(int $screenId): int
    {
        return db()->transaction(function () use ($screenId): int {
            $screen = db()->one('SELECT * FROM signage_screens WHERE id = ? FOR UPDATE', [$screenId]);
            if ($screen === null) {
                throw HttpException::notFound('That screen no longer exists.');
            }
            $previous = $screen['playlist_id'] === null ? null : (int) $screen['playlist_id'];
            if ($previous !== null) {
                $this->lockPlaylist($previous);
                if (!(bool) db()->value('SELECT 1 FROM signage_screens WHERE playlist_id = ? AND id <> ? LIMIT 1', [$previous, $screenId])) {
                    return $previous;
                }
            }
            $id = $this->createPlaylist(['name' => $screen['name'], 'description' => null]);
            if ($previous !== null) {
                db()->run('INSERT INTO signage_playlist_items (playlist_id, content_id, sort_order, duration_seconds)
                    SELECT ?, content_id, sort_order, duration_seconds FROM signage_playlist_items
                    WHERE playlist_id = ? ORDER BY sort_order, id', [$id, $previous]);
            }
            db()->update('signage_screens', ['playlist_id' => $id, 'updated_at' => Database::now()], ['id' => $screenId]);
            return $id;
        });
    }

    /** Keep ownership checks and the resulting playlist change under one lock. */
    public function withScreenPlaylist(int $screenId, callable $change): void
    {
        db()->transaction(function () use ($screenId, $change): void {
            $change($this->screenPlaylistId($screenId));
        });
    }

    /** @param array<string,mixed> $data */
    public function updateScreen(int $id, array $data): void
    {
        db()->transaction(function () use ($id, $data): void {
            $this->lockAssignedPlaylist($data['playlist_id']);
            $screen = db()->one('SELECT * FROM signage_screens WHERE id = ? FOR UPDATE', [$id]);
            if ($screen === null) {
                throw HttpException::notFound('That screen no longer exists.');
            }
            if ((int) $screen['playlist_id'] !== (int) $data['playlist_id']) {
                $data['last_content_id'] = null;
                $data['last_error'] = null;
            }
            db()->update('signage_screens', $data + ['updated_at' => Database::now()], ['id' => $id]);
        });
    }

    public function deleteScreen(int $id): void
    {
        db()->transaction(function () use ($id): void {
            $screen = db()->one('SELECT * FROM signage_screens WHERE id = ? FOR UPDATE', [$id]);
            if ($screen === null) {
                throw HttpException::notFound();
            }
            $playlist = $screen['playlist_id'];
            if ($playlist !== null) {
                $this->lockPlaylist((int) $playlist);
            }
            db()->run('DELETE FROM signage_screens WHERE id = ?', [$id]);
            if ($playlist !== null && !db()->value('SELECT 1 FROM signage_screens WHERE playlist_id = ? LIMIT 1', [$playlist])) {
                db()->run('DELETE FROM signage_playlists WHERE id = ?', [$playlist]);
            }
        });
    }

    /** Sets (or clears, with null) the TV photo; returns the previous file name to delete. */
    public function setScreenPhoto(int $id, ?string $filename): ?string
    {
        return db()->transaction(function () use ($id, $filename): ?string {
            $previous = db()->value('SELECT photo_filename FROM signage_screens WHERE id = ? FOR UPDATE', [$id]);
            db()->update('signage_screens', ['photo_filename' => $filename, 'updated_at' => Database::now()], ['id' => $id]);
            return is_string($previous) && $previous !== '' ? $previous : null;
        });
    }

    public function rotateScreenToken(int $id): void
    {
        db()->update('signage_screens', [
            'token' => bin2hex(random_bytes(32)), 'last_seen_at' => null,
            'last_content_id' => null, 'last_error' => null, 'updated_at' => Database::now(),
        ], ['id' => $id]);
    }

    /** @return list<array<string,mixed>> */
    public function content(): array
    {
        return db()->all('SELECT c.*,
            (SELECT COUNT(*) FROM signage_playlist_items i WHERE i.content_id = c.id) AS used_count
            FROM signage_content c ORDER BY c.name, c.id');
    }

    /** @return array<string,mixed>|null */
    public function contentItem(int $id): ?array
    {
        return db()->one('SELECT c.*,
            (SELECT COUNT(*) FROM signage_playlist_items i WHERE i.content_id = c.id) AS used_count
            FROM signage_content c WHERE c.id = ?', [$id]);
    }

    public function usageBytes(): int
    {
        return (int) db()->value('SELECT COALESCE(SUM(size_bytes), 0) FROM signage_content');
    }

    /** @param array<string,mixed> $data */
    public function createContent(array $data): int
    {
        $now = Database::now();
        return db()->insert('signage_content', $data + ['created_at' => $now, 'updated_at' => $now]);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> the previous row */
    public function updateContent(int $id, array $data): array
    {
        return db()->transaction(function () use ($id, $data): array {
            $item = db()->one('SELECT * FROM signage_content WHERE id = ? FOR UPDATE', [$id]);
            if ($item === null) {
                throw HttpException::notFound('That content no longer exists.');
            }
            db()->update('signage_content', $data + ['updated_at' => Database::now()], ['id' => $id]);
            return $item;
        });
    }

    /** @return array<string,mixed> deleted content, for removing its stored file after commit */
    public function deleteContent(int $id): array
    {
        return db()->transaction(function () use ($id): array {
            $item = db()->one('SELECT * FROM signage_content WHERE id = ? FOR UPDATE', [$id]);
            if ($item === null) {
                throw HttpException::notFound('That content no longer exists.');
            }
            if (db()->value('SELECT 1 FROM signage_playlist_items WHERE content_id = ? LIMIT 1', [$id]) !== null) {
                throw new InvalidArgumentException('Remove this content from its playlists before deleting it.');
            }
            db()->run('DELETE FROM signage_content WHERE id = ?', [$id]);
            db()->run('UPDATE signage_screens SET last_content_id = NULL WHERE last_content_id = ?', [$id]);
            return $item;
        });
    }

    /** @return list<array<string,mixed>> */
    public function playlists(): array
    {
        return db()->all('SELECT p.*,
            (SELECT COUNT(*) FROM signage_playlist_items i WHERE i.playlist_id = p.id) AS item_count,
            (SELECT COUNT(*) FROM signage_screens s WHERE s.playlist_id = p.id) AS screen_count
            FROM signage_playlists p ORDER BY p.name, p.id');
    }

    /** @param list<int> $playlistIds @return array<int,array<string,mixed>> First content item, keyed by playlist. */
    public function playlistCovers(array $playlistIds): array
    {
        $playlistIds = array_values(array_unique(array_filter(array_map('intval', $playlistIds))));
        if ($playlistIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($playlistIds), '?'));
        $rows = db()->all('SELECT c.*, i.playlist_id FROM signage_playlist_items i
            JOIN signage_content c ON c.id = i.content_id
            WHERE i.playlist_id IN (' . $placeholders . ')
            AND i.id = (SELECT first_item.id FROM signage_playlist_items first_item
                WHERE first_item.playlist_id = i.playlist_id ORDER BY first_item.sort_order, first_item.id LIMIT 1)', $playlistIds);
        return array_column($rows, null, 'playlist_id');
    }

    /** @return array<string,mixed>|null */
    public function playlist(int $id): ?array
    {
        return db()->one('SELECT * FROM signage_playlists WHERE id = ?', [$id]);
    }

    /** @return list<array<string,mixed>> id is the content ID; item_id identifies this playlist occurrence. */
    public function playlistItems(int $playlistId): array
    {
        return db()->all('SELECT c.*, i.id AS item_id, i.playlist_id, i.sort_order, i.duration_seconds
            FROM signage_playlist_items i JOIN signage_content c ON c.id = i.content_id
            WHERE i.playlist_id = ? ORDER BY i.sort_order, i.id', [$playlistId]);
    }

    public function playlistRevision(int $id): string
    {
        return hash('sha256', json_encode($this->playlistItems($id), JSON_THROW_ON_ERROR));
    }

    /** Save an editor draft atomically, preserving IDs for existing entries. */
    public function savePlaylistItems(int $id, array $entries, string $revision): void
    {
        db()->transaction(function () use ($id, $entries, $revision): void {
            $this->lockPlaylist($id);
            if (!hash_equals($this->playlistRevision($id), $revision)) {
                throw new HttpException(409, 'This playlist changed in another tab. Reload the page before saving.');
            }
            if (count($entries) > 1000) {
                throw new InvalidArgumentException('A playlist can contain up to 1,000 items.');
            }
            $current = array_column($this->playlistItems($id), null, 'item_id');
            $seen = [];
            foreach ($entries as $entry) {
                if (!is_array($entry) || !is_int($entry['content_id'] ?? null) || $entry['content_id'] < 1
                    || !is_int($entry['duration_seconds'] ?? null) || $entry['duration_seconds'] < 5 || $entry['duration_seconds'] > 86400
                    || (!is_null($entry['item_id'] ?? null) && (!is_int($entry['item_id']) || $entry['item_id'] < 1))) {
                    throw new InvalidArgumentException('Choose valid content and a display duration from 5 seconds to 1,440 minutes.');
                }
                $itemId = $entry['item_id'] ?? null;
                if ($itemId !== null) {
                    if (isset($seen[$itemId]) || !isset($current[$itemId]) || (int) $current[$itemId]['id'] !== $entry['content_id']) {
                        throw new InvalidArgumentException('An item does not belong to this screen. Reload the page.');
                    }
                    $seen[$itemId] = true;
                }
            }
            $contentIds = array_values(array_unique(array_column($entries, 'content_id')));
            $content = $contentIds === [] ? [] : array_column(db()->all('SELECT id, type FROM signage_content WHERE id IN ('
                . implode(',', array_fill(0, count($contentIds), '?')) . ') ORDER BY id FOR UPDATE', $contentIds), null, 'id');
            if (count($content) !== count($contentIds)) {
                throw new InvalidArgumentException('Some content was removed from the library. Reload the page.');
            }
            foreach (array_diff(array_keys($current), array_keys($seen)) as $removed) {
                db()->run('DELETE FROM signage_playlist_items WHERE id = ? AND playlist_id = ?', [$removed, $id]);
            }
            foreach ($entries as $order => $entry) {
                $data = ['sort_order' => $order, 'duration_seconds' => $entry['duration_seconds']];
                if (($entry['item_id'] ?? null) !== null) {
                    db()->update('signage_playlist_items', $data, ['id' => $entry['item_id'], 'playlist_id' => $id]);
                } else {
                    db()->insert('signage_playlist_items', $data + ['playlist_id' => $id, 'content_id' => $entry['content_id']]);
                }
            }
            $this->touchPlaylist($id);
        });
    }

    /** @param array<string,mixed> $data */
    public function createPlaylist(array $data): int
    {
        $now = Database::now();
        return db()->insert('signage_playlists', $data + ['created_at' => $now, 'updated_at' => $now]);
    }

    /** @param array<string,mixed> $data */
    public function updatePlaylist(int $id, array $data): void
    {
        db()->transaction(function () use ($id, $data): void {
            $this->lockPlaylist($id);
            db()->update('signage_playlists', $data + ['updated_at' => Database::now()], ['id' => $id]);
        });
    }

    public function deletePlaylist(int $id): void
    {
        db()->transaction(function () use ($id): void {
            $this->lockPlaylist($id);
            db()->run('DELETE FROM signage_playlists WHERE id = ?', [$id]);
        });
    }

    public function addPlaylistItem(int $id, int $contentId, int $duration): void
    {
        db()->transaction(function () use ($id, $contentId, $duration): void {
            $this->lockPlaylist($id);
            $items = $this->lockedItems($id);
            if (count($items) >= 1000) {
                throw new InvalidArgumentException('This playlist has reached its limit of 1,000 items.');
            }
            $content = db()->one('SELECT id, type FROM signage_content WHERE id = ? FOR UPDATE', [$contentId]);
            if ($content === null) {
                throw new InvalidArgumentException('That content no longer exists. Choose another item.');
            }
            db()->insert('signage_playlist_items', [
                'playlist_id' => $id, 'content_id' => $contentId, 'sort_order' => count($items),
                'duration_seconds' => $duration,
            ]);
            $this->touchPlaylist($id);
        });
    }

    public function updatePlaylistItem(int $id, int $itemId, int $duration): void
    {
        db()->transaction(function () use ($id, $itemId, $duration): void {
            $this->lockPlaylist($id);
            $this->memberItem($id, $itemId);
            db()->update('signage_playlist_items', ['duration_seconds' => $duration], ['id' => $itemId, 'playlist_id' => $id]);
            $this->touchPlaylist($id);
        });
    }

    public function movePlaylistItem(int $id, int $itemId, string $direction): void
    {
        if (!in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Choose a valid direction.');
        }
        db()->transaction(function () use ($id, $itemId, $direction): void {
            $this->lockPlaylist($id);
            $items = $this->lockedItems($id);
            $ids = array_map('intval', array_column($items, 'id'));
            $position = array_search($itemId, $ids, true);
            if ($position === false) {
                throw HttpException::notFound('That item is not in this playlist.');
            }
            $target = $position + ($direction === 'up' ? -1 : 1);
            if (!isset($ids[$target])) {
                return;
            }
            [$ids[$position], $ids[$target]] = [$ids[$target], $ids[$position]];
            $this->saveOrder($id, $ids);
            $this->touchPlaylist($id);
        });
    }

    public function deletePlaylistItem(int $id, int $itemId): void
    {
        db()->transaction(function () use ($id, $itemId): void {
            $this->lockPlaylist($id);
            $this->memberItem($id, $itemId);
            db()->run('DELETE FROM signage_playlist_items WHERE id = ? AND playlist_id = ?', [$itemId, $id]);
            $this->saveOrder($id, array_map('intval', array_column($this->lockedItems($id), 'id')));
            $this->touchPlaylist($id);
        });
    }

    private function lockAssignedPlaylist(?int $id): void
    {
        if ($id !== null && db()->value('SELECT id FROM signage_playlists WHERE id = ? FOR UPDATE', [$id]) === null) {
            throw new InvalidArgumentException('That playlist no longer exists. Choose another one.');
        }
    }

    private function lockPlaylist(int $id): void
    {
        if (db()->value('SELECT id FROM signage_playlists WHERE id = ? FOR UPDATE', [$id]) === null) {
            throw HttpException::notFound('That playlist no longer exists.');
        }
    }

    private function memberItem(int $id, int $itemId): void
    {
        if (db()->value('SELECT id FROM signage_playlist_items WHERE id = ? AND playlist_id = ? FOR UPDATE', [$itemId, $id]) === null) {
            throw HttpException::notFound('That item is not in this playlist.');
        }
    }

    /** @return list<array<string,mixed>> */
    private function lockedItems(int $id): array
    {
        return db()->all('SELECT id FROM signage_playlist_items WHERE playlist_id = ? ORDER BY sort_order, id FOR UPDATE', [$id]);
    }

    /** @param list<int> $ids */
    private function saveOrder(int $playlistId, array $ids): void
    {
        foreach ($ids as $order => $id) {
            db()->update('signage_playlist_items', ['sort_order' => $order], ['id' => $id, 'playlist_id' => $playlistId]);
        }
    }

    private function touchPlaylist(int $id): void
    {
        db()->update('signage_playlists', ['updated_at' => Database::now()], ['id' => $id]);
    }
}

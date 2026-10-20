<?php

declare(strict_types=1);

namespace App\Devices;

use App\Core\Router;

/**
 * One device category (StudyTouch, CCTV, Chromebooks, ...). Everything shared
 * (status, notes log, dashboard counts, the activity feed) works off the
 * `devices` table; a category adds its own detail table, pages and words.
 *
 * To add a category: create app/Modules/<Name>/<Name>Category.php extending
 * this class, add its detail table in a new migration, and list the class in
 * config/modules.php.
 */
abstract class Category
{
    /** Value stored in devices.category, e.g. "studytouch". */
    abstract public function key(): string;

    /** Navigation label, e.g. "StudyTouch". */
    abstract public function name(): string;

    /** Lucide icon name for navigation. */
    abstract public function icon(): string;

    /** @return array{0:string, 1:string} Singular and plural noun: ['board', 'boards']. */
    abstract public function noun(): array;

    abstract public function registerRoutes(Router $router): void;

    /**
     * Display names for devices of this category, for lists that mix
     * categories (dashboard feed, "needs attention").
     *
     * @param list<int> $ids
     * @return array<int, array{title:string, context:string}>
     */
    abstract public function describe(array $ids): array;

    public function path(): string
    {
        return '/' . $this->key();
    }

    public function detailPath(int $id): string
    {
        return $this->path() . '/' . $id;
    }

    public function statusLabel(string $status): string
    {
        return $status === Status::PROBLEM ? 'Has problem' : 'Running well';
    }

    /** @return list<string> Statuses offered for new records and status changes. */
    public function statuses(): array
    {
        return Status::ALL;
    }

    public function acceptsStatus(string $status): bool
    {
        return in_array($status, $this->statuses(), true);
    }

    /** "3 boards" */
    public function count(int $n): string
    {
        [$one, $many] = $this->noun();
        return plural($n, $one, $many);
    }

    /** @return list<string> Note kinds people can choose when writing a note. */
    public function noteKinds(): array
    {
        return [NoteKind::REPORT, NoteKind::PROBLEM, NoteKind::FIX];
    }

    /** Placeholder for the note box: a typical note for this kind of device. */
    public function noteExample(): string
    {
        return 'e.g. Does not turn on. Reported this morning.';
    }

    /** @return list<LookupList> Editable lists this category owns (rooms, CCTV areas). */
    public function lists(): array
    {
        return [];
    }

    /**
     * Ids of this category's devices matching the dashboard search (not
     * deleted), best matches first. Override with the category's own fields.
     *
     * @return list<int>
     */
    public function searchIds(string $query, int $limit): array
    {
        return [];
    }

    /** LIKE pattern for $query with % and _ escaped. */
    protected static function likePattern(string $query): string
    {
        return '%' . addcslashes($query, '%_\\') . '%';
    }

    /**
     * Shared dashboard search: visible device details plus the entire notes log.
     * $fields contains trusted SQL expressions supplied by category code only.
     *
     * @param list<string> $fields
     * @return array{0:string, 1:list<string>}
     */
    protected function searchMatch(string $query, array $fields): array
    {
        $like = self::likePattern($query);
        $fields = array_merge(['d.model', 'd.serial_number', 'd.status'], $fields);
        $clauses = array_map(static fn (string $field): string => "$field LIKE ?", $fields);
        $params = array_fill(0, count($fields), $like);
        $clauses[] = '? LIKE ?';
        array_push($params, $this->name(), $like);
        $clauses[] = '? LIKE ?';
        array_push($params, implode(' ', $this->noun()), $like);
        foreach (array_unique([Status::OK, ...$this->statuses()]) as $status) {
            $clauses[] = '(d.status = ? AND ? LIKE ?)';
            array_push($params, $status, $this->statusLabel($status), $like);
        }
        $clauses[] = 'EXISTS (
            SELECT 1 FROM device_notes sn LEFT JOIN users su ON su.id = sn.user_id
            WHERE sn.device_id = d.id AND (sn.body LIKE ? OR sn.kind LIKE ? OR su.name LIKE ?)
        )';
        array_push($params, $like, $like, $like);
        return ['(' . implode(' OR ', $clauses) . ')', $params];
    }

    /** "Classroom 7B", for headings, toasts and confirmations about one device. */
    public function title(int $deviceId): string
    {
        return $this->describe([$deviceId])[$deviceId]['title'] ?? $this->noun()[0] . ' #' . $deviceId;
    }
}

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

    /** "Classroom 7B", for headings, toasts and confirmations about one device. */
    public function title(int $deviceId): string
    {
        return $this->describe([$deviceId])[$deviceId]['title'] ?? $this->noun()[0] . ' #' . $deviceId;
    }
}

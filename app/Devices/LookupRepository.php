<?php

declare(strict_types=1);

namespace App\Devices;

use App\Core\Database;
use PDOException;

final class LookupRepository
{
    public function __construct(private LookupList $list)
    {
    }

    /** @return list<array{id:int, name:string}> In display order, for selects and filters. */
    public function options(): array
    {
        return db()->all("SELECT id, name FROM `{$this->list->table}` ORDER BY sort_order, name");
    }

    /** @return list<array{id:int, name:string, used:int}> In display order, with how many devices use each. */
    public function allWithUsage(): array
    {
        $used = [];
        foreach (db()->all($this->list->usageSql) as $row) {
            $used[(int) $row['id']] = (int) $row['n'];
        }
        return array_map(
            static fn (array $row) => $row + ['used' => $used[(int) $row['id']] ?? 0],
            $this->options(),
        );
    }

    /** @return array{id:int, name:string}|null */
    public function find(int $id): ?array
    {
        return db()->one("SELECT id, name FROM `{$this->list->table}` WHERE id = ?", [$id]);
    }

    public function nameTaken(string $name, ?int $exceptId = null): bool
    {
        // The unique index's collation compares case-insensitively; so does this.
        return db()->value(
            "SELECT 1 FROM `{$this->list->table}` WHERE name = ? AND id <> ?",
            [$name, $exceptId ?? 0],
        ) !== null;
    }

    public function add(string $name): int
    {
        $next = (int) db()->value("SELECT COALESCE(MAX(sort_order), 0) + 10 FROM `{$this->list->table}`");
        return db()->insert($this->list->table, ['name' => $name, 'sort_order' => $next, 'created_at' => Database::now()]);
    }

    public function rename(int $id, string $name): void
    {
        db()->update($this->list->table, ['name' => $name], ['id' => $id]);
    }

    /** Moves an item one place up (-1) or down (+1) in the list. */
    public function move(int $id, int $direction): void
    {
        $items = array_column($this->options(), 'id');
        $index = array_search($id, array_map('intval', $items), true);
        $swap = $index === false ? null : $index + $direction;
        if ($swap === null || $swap < 0 || $swap >= count($items)) {
            return;
        }
        [$items[$index], $items[$swap]] = [$items[$swap], $items[$index]];

        db()->transaction(function () use ($items): void {
            foreach (array_values($items) as $position => $itemId) {
                db()->update($this->list->table, ['sort_order' => ($position + 1) * 10], ['id' => (int) $itemId]);
            }
        });
    }

    /**
     * Deletes an unused item. Returns false when the database refuses because
     * deleted devices still point at it.
     */
    public function delete(int $id): bool
    {
        try {
            db()->run("DELETE FROM `{$this->list->table}` WHERE id = ?", [$id]);
            return true;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
    }
}

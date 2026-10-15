<?php

declare(strict_types=1);

namespace App\Devices;

use App\Core\Router;
use RuntimeException;

/** The categories listed in config/modules.php, in navigation order. */
final class CategoryRegistry
{
    private static ?self $instance = null;

    /** @param array<string, Category> $categories keyed by Category::key() */
    private function __construct(private array $categories)
    {
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            $categories = [];
            foreach (config('modules', []) as $class) {
                $category = new $class();
                $categories[$category->key()] = $category;
            }
            self::$instance = new self($categories);
        }
        return self::$instance;
    }

    /** @return array<string, Category> */
    public function all(): array
    {
        return $this->categories;
    }

    public function find(string $key): ?Category
    {
        return $this->categories[$key] ?? null;
    }

    public function get(string $key): Category
    {
        return $this->find($key) ?? throw new RuntimeException("Unknown device category: $key");
    }

    /** The category whose pages contain $path ("/cctv/12" -> CCTV). */
    public function forPath(string $path): ?Category
    {
        foreach ($this->categories as $category) {
            if ($path === $category->path() || str_starts_with($path, $category->path() . '/')) {
                return $category;
            }
        }
        return null;
    }

    /** @return list<LookupList> Every category's editable lists. */
    public function lists(): array
    {
        $lists = [];
        foreach ($this->categories as $category) {
            array_push($lists, ...$category->lists());
        }
        return $lists;
    }

    /** The editable list whose page contains $path ("/studytouch/rooms/4" -> rooms). */
    public function listForPath(string $path): ?LookupList
    {
        foreach ($this->lists() as $list) {
            if ($path === $list->path || str_starts_with($path, $list->path . '/')) {
                return $list;
            }
        }
        return null;
    }

    public function registerRoutes(Router $router): void
    {
        foreach ($this->categories as $category) {
            $category->registerRoutes($router);
            foreach ($category->lists() as $list) {
                $router->get($list->path, [LookupController::class, 'index']);
                $router->post($list->path, [LookupController::class, 'store']);
                $router->post($list->path . '/{id}', [LookupController::class, 'update']);
                $router->post($list->path . '/{id}/delete', [LookupController::class, 'destroy']);
            }
        }
    }

    /**
     * Adds 'title', 'context' and 'url' to rows that have 'device_id' and
     * 'category', asking each category once for all of its devices.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function describeRows(array $rows): array
    {
        $idsByCategory = [];
        foreach ($rows as $row) {
            $idsByCategory[$row['category']][] = (int) $row['device_id'];
        }

        $names = [];
        foreach ($idsByCategory as $key => $ids) {
            $category = $this->find((string) $key);
            if ($category !== null) {
                $names[$key] = $category->describe(array_values(array_unique($ids)));
            }
        }

        foreach ($rows as &$row) {
            $id = (int) $row['device_id'];
            $category = $this->find((string) $row['category']);
            $row['title'] = $names[$row['category']][$id]['title'] ?? 'Device #' . $id;
            $row['context'] = $names[$row['category']][$id]['context'] ?? ($category?->name() ?? '');
            $row['url'] = '/devices/' . $id;
        }
        return $rows;
    }
}

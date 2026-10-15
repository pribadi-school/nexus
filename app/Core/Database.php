<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use Throwable;

/**
 * Thin PDO wrapper. Always use placeholders; never concatenate user input.
 *
 *   db()->all('SELECT * FROM rooms WHERE id > ?', [3]);
 *   db()->insert('rooms', ['name' => 'Library']);
 *   db()->transaction(fn () => ...);
 */
final class Database
{
    private static ?self $instance = null;

    private function __construct(private PDO $pdo)
    {
    }

    public static function connection(): self
    {
        if (self::$instance === null) {
            $c = config('database');
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['name']);
            $pdo = new PDO($dsn, $c['user'], $c['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
            // Store and compare every DATETIME in UTC, whatever the server's zone.
            $pdo->exec("SET time_zone = '+00:00'");
            self::$instance = new self($pdo);
        }
        return self::$instance;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** @param array<int|string, mixed> $params */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** @return list<array<string, mixed>> */
    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function value(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** @param array<string, mixed> $data */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn ($c) => "`$c`", $columns)),
            implode(', ', array_fill(0, count($columns), '?')),
        );
        $this->run($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where column => value, joined with AND
     */
    public function update(string $table, array $data, array $where): int
    {
        $set = implode(', ', array_map(static fn ($c) => "`$c` = ?", array_keys($data)));
        $cond = implode(' AND ', array_map(static fn ($c) => "`$c` = ?", array_keys($where)));
        $stmt = $this->run("UPDATE `$table` SET $set WHERE $cond", [...array_values($data), ...array_values($where)]);
        return $stmt->rowCount();
    }

    /**
     * Runs $fn inside a transaction and returns its result. Rolls back and
     * rethrows on any error.
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Current time in UTC, formatted for a DATETIME column. */
    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}

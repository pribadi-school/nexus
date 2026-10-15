<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Compares database/migrations/*.sql with the schema_migrations table, so the
 * dashboard can tell an admin when the server database needs an import.
 */
final class Migrations
{
    /** @return list<string> Versions (file names without .sql), oldest first. */
    public static function available(): array
    {
        $files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
        $versions = array_map(static fn ($f) => basename($f, '.sql'), $files);
        sort($versions);
        return $versions;
    }

    /** @return list<string> */
    public static function applied(): array
    {
        try {
            return array_map('strval', array_column(db()->all('SELECT version FROM schema_migrations'), 'version'));
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<string> Migrations not yet imported, oldest first. */
    public static function pending(): array
    {
        return array_values(array_diff(self::available(), self::applied()));
    }
}

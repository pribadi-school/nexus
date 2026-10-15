<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Database;

/**
 * People are just names: whoever signs in with the shared password types
 * their name, and that name becomes the author of their notes.
 */
final class UserRepository
{
    public const MAX_NAME = 60;

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return db()->one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    /** Trimmed, with single spaces: "  rina   w " -> "rina w". */
    public static function normaliseName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    /**
     * Finds the person with this name (case-insensitive) or adds them, and
     * records the sign-in.
     *
     * @return array<string, mixed>
     */
    public function recordSignIn(string $name): array
    {
        $name = self::normaliseName($name);
        $now = Database::now();

        $user = db()->one('SELECT * FROM users WHERE name = ?', [$name]);
        if ($user === null) {
            $id = db()->insert('users', ['name' => $name, 'last_login_at' => $now, 'created_at' => $now]);
        } else {
            $id = (int) $user['id'];
            db()->update('users', ['last_login_at' => $now], ['id' => $id]);
        }
        return (array) $this->find($id);
    }
}

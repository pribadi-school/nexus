<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Database;

/**
 * Slows down password guessing: after max_attempts wrong passwords from one
 * IP address within lockout_minutes, that address has to wait.
 */
final class LoginThrottle
{
    private int $max;
    private int $minutes;

    public function __construct()
    {
        $this->max = max(1, (int) config('auth.max_attempts', 8));
        $this->minutes = max(1, (int) config('auth.lockout_minutes', 15));
    }

    public function minutes(): int
    {
        return $this->minutes;
    }

    public function isLocked(string $ip): bool
    {
        $count = (int) db()->value(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ?',
            [$ip, $this->windowStart()],
        );
        return $count >= $this->max;
    }

    public function recordFailure(string $ip): void
    {
        db()->insert('login_attempts', ['ip' => $ip, 'attempted_at' => Database::now()]);
        // Housekeeping: old attempts no longer count.
        db()->run('DELETE FROM login_attempts WHERE attempted_at <= ?', [$this->windowStart()]);
    }

    public function clear(string $ip): void
    {
        db()->run('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
    }

    private function windowStart(): string
    {
        return gmdate('Y-m-d H:i:s', time() - $this->minutes * 60);
    }
}

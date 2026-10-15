<?php

declare(strict_types=1);

namespace App\Core;

/**
 * File sessions stored in storage/sessions (outside the web root, and not the
 * shared server /tmp, which other sites' cleanup jobs can empty).
 */
final class Session
{
    public static function start(): void
    {
        $days = (int) config('app.session_days', 14);
        $lifetime = $days * 86400;
        $secure = str_starts_with((string) config('app.url'), 'https://');

        ini_set('session.save_path', BASE_PATH . '/storage/sessions');
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_name('nexus_session');
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            // Lax: sent when following a link to Nexus, never on cross-site POSTs.
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Read a value and remove it. */
    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $value;
    }

    /** New session id, same data. Call on sign-in to prevent session fixation. */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    /** One-time message shown on the next page ("success" | "error" | "info"). */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type:string, message:string}> */
    public static function takeFlashes(): array
    {
        return self::pull('_flash', []);
    }
}

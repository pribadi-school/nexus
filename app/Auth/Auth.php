<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Session;

/**
 * The signed-in person. A session stays valid only while APP_PASSWORD is
 * unchanged: changing the password in .env signs everyone out.
 */
final class Auth
{
    /** @var array<string, mixed>|null */
    private static ?array $user = null;
    private static bool $loaded = false;

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = Session::get('auth.user_id');
            $key = Session::get('auth.key');
            if (is_int($id) && is_string($key) && self::passwordIsSet() && hash_equals(self::sessionKey(), $key)) {
                self::$user = (new UserRepository())->find($id);
            }
            if (self::$user === null) {
                Session::forget('auth.user_id');
                Session::forget('auth.key');
            }
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user === null ? null : (int) $user['id'];
    }

    public static function passwordIsSet(): bool
    {
        return (string) config('auth.password') !== '';
    }

    /** Compares in constant time, so response timing reveals nothing. */
    public static function checkPassword(string $given): bool
    {
        $expected = (string) config('auth.password');
        return $expected !== '' && hash_equals(hash('sha256', $expected), hash('sha256', $given));
    }

    /** @param array<string, mixed> $user */
    public static function login(array $user): void
    {
        Session::regenerate();
        Session::put('auth.user_id', (int) $user['id']);
        Session::put('auth.key', self::sessionKey());
        self::$user = $user;
        self::$loaded = true;
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$user = null;
        self::$loaded = true;
    }

    /** Derived from the current password; stored in the session at sign-in. */
    private static function sessionKey(): string
    {
        return hash_hmac('sha256', 'nexus-session', (string) config('auth.password'));
    }
}

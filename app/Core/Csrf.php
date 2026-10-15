<?php

declare(strict_types=1);

namespace App\Core;

/**
 * One token per session. Forms send it as the hidden _csrf field
 * (csrf_field()); fetch() calls send it in the X-CSRF-Token header, read from
 * the <meta name="csrf-token"> tag.
 */
final class Csrf
{
    public static function token(): string
    {
        $token = Session::get('_csrf');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::put('_csrf', $token);
        }
        return $token;
    }

    public static function verify(Request $request): bool
    {
        $sent = $request->body['_csrf'] ?? $request->header('X-CSRF-Token');
        $token = Session::get('_csrf');
        return is_string($sent) && is_string($token) && $token !== '' && hash_equals($token, $sent);
    }
}

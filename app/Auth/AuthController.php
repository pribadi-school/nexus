<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

final class AuthController
{
    /** Remembers the last name typed on this device, to fill the form next time. */
    private const NAME_COOKIE = 'nexus_name';

    /** GET /login */
    public function showLogin(Request $request): Response
    {
        if (Auth::user() !== null) {
            return Response::redirect('/');
        }

        $name = Session::pull('login.name') ?? ($_COOKIE[self::NAME_COOKIE] ?? '');

        return Response::html(View::render('auth/login', [
            'passwordSet' => Auth::passwordIsSet(),
            'name' => is_string($name) ? $name : '',
        ], 'layouts/auth', 'Sign in'));
    }

    /** POST /login */
    public function login(Request $request): Response
    {
        $name = UserRepository::normaliseName((string) $request->input('name'));
        $password = (string) ($request->body['password'] ?? '');
        $ip = $request->ip();
        $throttle = new LoginThrottle();

        Session::put('login.name', $name);

        if ($name === '' || mb_strlen($name) > UserRepository::MAX_NAME) {
            Session::flash('error', 'Type your name (up to ' . UserRepository::MAX_NAME . ' characters). It is shown on the notes you write.');
            return Response::redirect('/login');
        }
        if (!Auth::passwordIsSet()) {
            Session::flash('error', 'Sign-in is turned off until APP_PASSWORD is set in the .env file.');
            return Response::redirect('/login');
        }
        if ($throttle->isLocked($ip)) {
            Session::flash('error', sprintf('Too many wrong passwords. Wait %d minutes, then try again.', $throttle->minutes()));
            return Response::redirect('/login');
        }
        if (!Auth::checkPassword($password)) {
            $throttle->recordFailure($ip);
            Session::flash('error', 'That password is not right. Ask the IT team for the Nexus password.');
            return Response::redirect('/login');
        }

        $throttle->clear($ip);
        Session::forget('login.name');
        $user = (new UserRepository())->recordSignIn($name);
        Auth::login($user);
        $this->rememberName((string) $user['name']);

        return Response::redirect(safe_path(Session::pull('auth.intended'), '/'));
    }

    /** POST /logout */
    public function logout(Request $request): Response
    {
        Auth::logout();
        Session::flash('info', 'You are signed out.');
        return Response::redirect('/login');
    }

    private function rememberName(string $name): void
    {
        setcookie(self::NAME_COOKIE, $name, [
            'expires' => time() + 365 * 86400,
            'path' => '/',
            'secure' => str_starts_with((string) config('app.url'), 'https://'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

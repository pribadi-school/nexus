<?php

declare(strict_types=1);

namespace App\Core;

use App\Auth\Auth;

/**
 * Maps "METHOD /path/{param}" to [Controller::class, 'method'].
 *
 * Every route needs a signed-in person unless it is marked public: true.
 * Every POST checks the CSRF token.
 */
final class Router
{
    /** @var list<array{method:string, pattern:string, regex:string, handler:array{0:class-string,1:string}, public:bool}> */
    private array $routes = [];

    /** @param array{0:class-string,1:string} $handler */
    public function get(string $pattern, array $handler, bool $public = false): void
    {
        $this->add('GET', $pattern, $handler, $public);
    }

    /** @param array{0:class-string,1:string} $handler */
    public function post(string $pattern, array $handler, bool $public = false): void
    {
        $this->add('POST', $pattern, $handler, $public);
    }

    /** @param array{0:class-string,1:string} $handler */
    private function add(string $method, string $pattern, array $handler, bool $public): void
    {
        // {id} matches digits; any other {name} matches one path segment.
        $regex = preg_replace_callback(
            '/\{(\w+)\}/',
            static fn ($m) => $m[1] === 'id' ? '(?P<id>\d+)' : '(?P<' . $m[1] . '>[^/]+)',
            $pattern,
        );
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
            'public' => $public,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $m)) {
                continue;
            }
            $method = $request->method === 'HEAD' ? 'GET' : $request->method;
            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }
            $request->params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            return $this->run($route, $request);
        }

        if ($allowed !== []) {
            throw new HttpException(405, 'That action is not available here.');
        }
        throw HttpException::notFound();
    }

    /** @param array{method:string, pattern:string, regex:string, handler:array{0:class-string,1:string}, public:bool} $route */
    private function run(array $route, Request $request): Response
    {
        if ($request->method === 'POST' && !Csrf::verify($request)) {
            throw new HttpException(419, 'This form expired. Go back, reload the page and try again.');
        }

        if (!$route['public'] && Auth::user() === null) {
            if ($request->isFetch()) {
                return Response::json(['error' => 'Your session ended. Sign in again.'], 401);
            }
            if ($request->method === 'GET') {
                Session::put('auth.intended', $request->server['REQUEST_URI'] ?? '/');
            }
            return Response::redirect('/login');
        }

        [$class, $action] = $route['handler'];
        $result = (new $class())->$action($request);

        return $result instanceof Response ? $result : Response::html((string) $result);
    }
}

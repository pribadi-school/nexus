<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @var array<string, string> Route parameters such as {id}. */
    public array $params = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $server
     * @param array<string, mixed> $files
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly array $server,
        public readonly array $files = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
        $path = '/' . trim($path, '/');

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $_POST,
            $_SERVER,
            $_FILES,
        );
    }

    /** A query-string value, trimmed. Empty strings count as missing. */
    public function query(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            return $default;
        }
        return trim($value);
    }

    /** A form field, trimmed. */
    public function input(string $key, ?string $default = null): ?string
    {
        $value = $this->body[$key] ?? null;
        if (!is_string($value)) {
            return $default;
        }
        return trim($value);
    }

    public function param(string $key): string
    {
        return $this->params[$key] ?? '';
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $this->server[$key] ?? null;
        return is_string($value) ? $value : null;
    }

    /** True for fetch() calls from assets/js, which send this header. */
    public function isFetch(): bool
    {
        return $this->header('X-Requested-With') === 'fetch';
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '');
    }

    public function isLocalhost(): bool
    {
        return in_array($this->ip(), ['127.0.0.1', '::1'], true);
    }
}

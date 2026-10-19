<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    private ?\Closure $stream = null;
    private bool $playerDocument = false;

    /** @param array<string, string> $headers */
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /** @param array<mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    /** 303 so a redirect after a POST always becomes a GET. */
    public static function redirect(string $to, int $status = 303): self
    {
        return new self('', $status, ['Location' => $to]);
    }

    /** Only the standalone signage player may embed websites or be embedded. */
    public static function playerHtml(string $body): self
    {
        $response = self::html($body);
        $response->playerDocument = true;
        return $response;
    }

    /** @param array<string, string> $headers */
    public static function stream(callable $send, int $status = 200, array $headers = []): self
    {
        $response = new self('', $status, $headers);
        $response->stream = \Closure::fromCallable($send);
        return $response;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->securityHeaders() + $this->headers as $name => $value) {
            header("$name: $value");
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            return;
        }
        if ($this->stream !== null) {
            ($this->stream)();
            return;
        }
        echo $this->body;
    }

    /** @return array<string, string> */
    private function securityHeaders(): array
    {
        $headers = [
            // Only our own files.
            'Content-Security-Policy' => implode('; ', [
                "default-src 'self'",
                "img-src 'self' data:",
                "style-src 'self'",
                "script-src 'self'",
                "font-src 'self'",
                "connect-src 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
                "base-uri 'self'",
            ]),
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'X-Frame-Options' => 'DENY',
            // Dynamic pages stay uncached; explicitly versioned private assets
            // can opt into browser caching without overriding security headers.
            'Cache-Control' => $this->headers['Cache-Control'] ?? 'no-store',
        ];
        if ($this->playerDocument) {
            $headers['Content-Security-Policy'] = implode('; ', [
                "default-src 'self'",
                "img-src 'self' data:",
                "style-src 'self'",
                "script-src 'self'",
                "font-src 'self'",
                "connect-src 'self'",
                "media-src 'self'",
                'frame-src https: http:',
                "form-action 'none'",
                'frame-ancestors https: http:',
                "base-uri 'none'",
            ]);
            unset($headers['X-Frame-Options']);
            $headers['Referrer-Policy'] = 'no-referrer';
        }
        if ($this->stream !== null) {
            $headers['Cache-Control'] = 'private, max-age=0, must-revalidate';
        }
        return $headers;
    }
}

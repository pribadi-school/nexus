<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

final class ErrorHandler
{
    private static bool $debug = false;
    private static string $logFile = '';

    public static function register(bool $debug, string $logFile): void
    {
        self::$debug = $debug;
        self::$logFile = $logFile;

        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', $logFile);

        // Turn warnings and notices into exceptions so they are not ignored.
        // Deprecations are only logged: the host may upgrade PHP under us, and
        // a deprecation must not take the site down.
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
                error_log("Deprecated: $message in $file:$line");
                return true;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $e): void {
            self::log($e);
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, $e . PHP_EOL);
                exit(1);
            }
            self::page($e)->send();
        });
    }

    public static function log(Throwable $e): void
    {
        $line = sprintf("[%s] %s: %s in %s:%d\n%s\n\n", gmdate('Y-m-d H:i:s'), $e::class, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
        @file_put_contents(self::$logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * A plain 500 page. It does not use the layout or the database, because
     * either may be what failed.
     */
    public static function page(Throwable $e): Response
    {
        $detail = self::$debug
            ? '<pre class="error-detail">' . e($e::class . ': ' . $e->getMessage() . "\n\n" . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString()) . '</pre>'
            : '<p>The problem has been logged. Try again in a moment.</p>';

        $tokens = e(asset('css/tokens.css'));
        $css = e(asset('css/base.css'));
        $html = <<<HTML
            <!doctype html>
            <html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Something went wrong · Nexus</title><link rel="stylesheet" href="{$tokens}"><link rel="stylesheet" href="{$css}"></head>
            <body class="error-plain"><main><h1>Something went wrong</h1>{$detail}<p><a href="/">Back to the dashboard</a></p></main></body></html>
            HTML;

        return Response::html($html, 500);
    }
}

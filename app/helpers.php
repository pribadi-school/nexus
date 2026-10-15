<?php

declare(strict_types=1);

use App\Auth\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Env;
use App\Core\View;
use App\Devices\CategoryRegistry;

// Small global helpers used by controllers and templates.

function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function db(): Database
{
    return Database::connection();
}

/** Escape for HTML text and attribute values. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @param array<string, mixed> $query */
function url(string $path, array $query = []): string
{
    $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
    return $path . ($query ? '?' . http_build_query($query) : '');
}

/** URL of a file in public/assets, with a version so browsers re-fetch it after a change. */
function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . $path;
    $version = is_file($file) ? (string) filemtime($file) : '0';
    return '/assets/' . $path . '?v=' . $version;
}

/**
 * Inline Lucide icon from the sprite (names listed in tools/build-assets.mjs).
 * Decorative by default; pass $label when the icon is the only content.
 */
function icon(string $name, string $class = '', ?string $label = null): string
{
    static $sprite = null;
    $sprite ??= asset('icons/sprite.svg');

    $a11y = $label === null ? 'aria-hidden="true"' : 'role="img" aria-label="' . e($label) . '"';
    return sprintf(
        '<svg class="icon %s" %s focusable="false"><use href="%s#i-%s"></use></svg>',
        e($class),
        $a11y,
        e($sprite),
        e($name),
    );
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

/** @param array<string, mixed> $data */
function partial(string $template, array $data = []): string
{
    return View::partial($template, $data);
}

/** @return array<string, mixed>|null */
function auth_user(): ?array
{
    return Auth::user();
}

function categories(): CategoryRegistry
{
    return CategoryRegistry::instance();
}

/** Path of the current request, e.g. "/studytouch". */
function request_path(): string
{
    return '/' . trim(rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)), '/');
}

/** Path + query of the current request, e.g. "/studytouch?status=problem". */
function request_uri(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    return str_starts_with($uri, '/') ? $uri : '/';
}

/** $path if it is a page on this site ("/x?y"), else $fallback. Guards redirects. */
function safe_path(?string $path, string $fallback): string
{
    return is_string($path) && str_starts_with($path, '/') && !str_starts_with($path, '//') && !str_contains($path, '\\')
        ? $path
        : $fallback;
}

/** True when $path is the current page or a page below it. */
function is_active(string $path): bool
{
    $current = request_path();
    return $path === '/' ? $current === '/' : ($current === $path || str_starts_with($current, $path . '/'));
}

// ---------------------------------------------------------------- time ---

function local_tz(): DateTimeZone
{
    static $tz = null;
    return $tz ??= new DateTimeZone((string) config('app.timezone', 'Asia/Jakarta'));
}

/** A UTC DATETIME string from MySQL, in the school's time zone. */
function local_time(?string $utc): ?DateTimeImmutable
{
    if ($utc === null || $utc === '') {
        return null;
    }
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(local_tz());
}

/** "15 Oct 2026, 14:05" */
function format_datetime(?string $utc): string
{
    return local_time($utc)?->format('j M Y, H:i') ?? '';
}

/** ISO 8601, for <time datetime="..."> */
function iso_time(?string $utc): string
{
    return local_time($utc)?->format(DATE_ATOM) ?? '';
}

/** "just now", "5 min ago", "3 h ago", "Yesterday, 14:05", "12 Oct, 09:30", "12 Oct 2025" */
function time_ago(?string $utc): string
{
    $time = local_time($utc);
    if ($time === null) {
        return '';
    }
    $now = new DateTimeImmutable('now', local_tz());
    $seconds = $now->getTimestamp() - $time->getTimestamp();

    if ($seconds < 60) {
        return 'just now';
    }
    if ($seconds < 3600) {
        return intdiv($seconds, 60) . ' min ago';
    }
    if ($time->format('Y-m-d') === $now->format('Y-m-d')) {
        return intdiv($seconds, 3600) . ' h ago';
    }
    if ($time->format('Y-m-d') === $now->modify('-1 day')->format('Y-m-d')) {
        return 'Yesterday, ' . $time->format('H:i');
    }
    if ($time->format('Y') === $now->format('Y')) {
        return $time->format('j M, H:i');
    }
    return $time->format('j M Y');
}

/** "Today", "Yesterday", "Monday, 12 October" for grouping a feed by day. */
function day_label(?string $utc): string
{
    $time = local_time($utc);
    if ($time === null) {
        return '';
    }
    $today = new DateTimeImmutable('today', local_tz());
    return match ($time->format('Y-m-d')) {
        $today->format('Y-m-d') => 'Today',
        $today->modify('-1 day')->format('Y-m-d') => 'Yesterday',
        default => $time->format($time->format('Y') === $today->format('Y') ? 'l, j F' : 'l, j F Y'),
    };
}

// --------------------------------------------------------------- people ---

/** "Rina Wulandari" -> "RW" */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = array_map(static fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($parts, 0, 2));
    return implode('', $letters) ?: '?';
}

/** English plural for counts: plural(3, 'board') -> "3 boards". */
function plural(int $count, string $singular, ?string $pluralForm = null): string
{
    return $count . ' ' . ($count === 1 ? $singular : ($pluralForm ?? $singular . 's'));
}

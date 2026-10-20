<?php

declare(strict_types=1);

namespace App\Devices;

/** Values of devices.status. Each category picks its own words (Category::statusLabel). */
final class Status
{
    public const OK = 'ok';
    public const PROBLEM = 'problem';
    public const REPORTED = 'reported';
    public const REPAIRED = 'repaired';
    public const REPLACED = 'replaced';
    public const FIXED = 'fixed';
    public const BORROWED = 'borrowed';
    public const BROKEN = 'broken';
    public const UNMARKED = 'unmarked';
    public const ALL = [self::OK, self::PROBLEM];
    public const CHROMEBOOK = [self::PROBLEM, self::FIXED, self::BORROWED, self::REPLACED, self::BROKEN];
    public const KNOWN = [self::OK, self::UNMARKED, self::REPORTED, self::REPAIRED, ...self::CHROMEBOOK];

    public static function isValid(string $status): bool
    {
        return in_array($status, self::KNOWN, true);
    }
}

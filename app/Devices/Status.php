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
    public const ALL = [self::OK, self::PROBLEM];
    public const CHROMEBOOK = [self::REPORTED, self::PROBLEM, self::REPAIRED, self::REPLACED];
    public const KNOWN = [self::OK, ...self::CHROMEBOOK];

    public static function isValid(string $status): bool
    {
        return in_array($status, self::KNOWN, true);
    }
}

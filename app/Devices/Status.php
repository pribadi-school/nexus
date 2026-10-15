<?php

declare(strict_types=1);

namespace App\Devices;

/** Values of devices.status. Each category picks its own words (Category::statusLabel). */
final class Status
{
    public const OK = 'ok';
    public const PROBLEM = 'problem';
    public const ALL = [self::OK, self::PROBLEM];

    public static function isValid(string $status): bool
    {
        return in_array($status, self::ALL, true);
    }
}

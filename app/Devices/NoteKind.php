<?php

declare(strict_types=1);

namespace App\Devices;

/**
 * Values of device_notes.kind, with how each one looks in a notes log.
 * People write report/problem/fix/firmware notes; the app writes status and
 * assignment notes itself.
 */
final class NoteKind
{
    public const REPORT = 'report';
    public const PROBLEM = 'problem';
    public const FIX = 'fix';
    public const FIRMWARE = 'firmware';
    public const STATUS = 'status';
    public const ASSIGNMENT = 'assignment';

    /** tone = colour role in the design system: neutral | problem | success | action */
    private const META = [
        self::REPORT => ['label' => 'Report', 'icon' => 'message-square-text', 'tone' => 'neutral'],
        self::PROBLEM => ['label' => 'Problem', 'icon' => 'triangle-alert', 'tone' => 'problem'],
        self::FIX => ['label' => 'Fix', 'icon' => 'wrench', 'tone' => 'success'],
        self::FIRMWARE => ['label' => 'Firmware', 'icon' => 'cpu', 'tone' => 'action'],
        self::STATUS => ['label' => 'Status', 'icon' => 'refresh-cw', 'tone' => 'neutral'],
        self::ASSIGNMENT => ['label' => 'Reassigned', 'icon' => 'arrow-left-right', 'tone' => 'neutral'],
    ];

    /** @return array{label:string, icon:string, tone:string} */
    public static function meta(string $kind): array
    {
        return self::META[$kind] ?? self::META[self::REPORT];
    }

    public static function isValid(string $kind): bool
    {
        return isset(self::META[$kind]);
    }
}

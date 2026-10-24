<?php

declare(strict_types=1);

namespace App\Modules\Signage;

/**
 * The Android player APKs kept in releases/ (committed, so the server has them after
 * "Update from Remote"). The newest version of each build is found by file name, so
 * shipping a new player needs no code change.
 */
final class SignageApp
{
    /** @var array<string, array{prefix: string, label: string, hint: string}> */
    public const BUILDS = [
        'landscape' => ['prefix' => 'nexus-signage-landscape-wifi-', 'label' => 'Landscape TV', 'hint' => 'Xiaomi and other horizontal TVs (Android 7 and up)'],
        'vertical' => ['prefix' => 'nexus-signage-t960x-portrait-', 'label' => 'Vertical TV', 'hint' => 'The vertical T960X display (Android 7.1.2)'],
    ];

    /** @return array{path: string, name: string, version: string, size: int}|null */
    public static function find(string $kind): ?array
    {
        $build = self::BUILDS[$kind] ?? null;
        if ($build === null) {
            return null;
        }
        $best = null;
        foreach (glob(BASE_PATH . '/releases/' . $build['prefix'] . '*.apk') ?: [] as $path) {
            $version = substr(basename($path), strlen($build['prefix']), -4);
            if (!preg_match('/^\d+(\.\d+)*$/', $version)) {
                continue;
            }
            if ($best === null || version_compare($version, $best['version'], '>')) {
                $best = ['path' => $path, 'name' => basename($path), 'version' => $version, 'size' => (int) filesize($path)];
            }
        }
        return $best;
    }

    /** @return array<string, array{label: string, hint: string, file: array{path: string, name: string, version: string, size: int}|null}> */
    public static function all(): array
    {
        $apps = [];
        foreach (self::BUILDS as $kind => $build) {
            $apps[$kind] = ['label' => $build['label'], 'hint' => $build['hint'], 'file' => self::find($kind)];
        }
        return $apps;
    }
}

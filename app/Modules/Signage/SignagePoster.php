<?php

declare(strict_types=1);

namespace App\Modules\Signage;

use RuntimeException;

/**
 * A saved preview frame and length for an uploaded video.
 *
 * Browsers decode the frame once and send it back, so later page views show a
 * small JPEG instead of streaming the whole video through PHP. Shared hosting
 * limits disk I/O for every site on the account; repeated preview streams
 * slowed Nexus and its sibling sites. Files are keyed by the media revision,
 * so a replaced video never reuses an old frame. No database change is needed.
 */
final class SignagePoster
{
    public const MAX_BYTES = 400 * 1024;

    /** Revision used in preview URLs; changes whenever the stored file changes. */
    public static function revision(array $item): string
    {
        return substr(hash('sha256', (string) $item['stored_filename']), 0, 16);
    }

    /** @return array{url:string,duration:float,width:int,height:int}|null */
    public static function find(array $item): ?array
    {
        if (($item['type'] ?? '') !== 'video') {
            return null;
        }
        $base = self::base((int) $item['id'], self::revision($item));
        if (!is_file($base . '.jpg') || !is_file($base . '.json')) {
            return null;
        }
        $meta = json_decode((string) @file_get_contents($base . '.json'), true);
        if (!is_array($meta)) {
            return null;
        }
        return [
            'url' => url('/signage/preview/poster/' . (int) $item['id'], ['v' => self::revision($item)]),
            'duration' => (float) ($meta['duration'] ?? 0),
            'width' => (int) ($meta['width'] ?? 0),
            'height' => (int) ($meta['height'] ?? 0),
        ];
    }

    /** "1:05" / "1:02:05", matching the badge the browser draws. */
    public static function clock(float $seconds): string
    {
        $total = (int) floor($seconds);
        $hours = intdiv($total, 3600);
        $minutes = intdiv($total % 3600, 60);
        $rest = str_pad((string) ($total % 60), 2, '0', STR_PAD_LEFT);
        return $hours > 0 ? $hours . ':' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT) . ':' . $rest : $minutes . ':' . $rest;
    }

    public static function path(array $item): string
    {
        return self::base((int) $item['id'], self::revision($item)) . '.jpg';
    }

    /** Validates a browser-captured JPEG frame and its metadata, then saves both. */
    public static function store(array $item, string $dataUrl, float $duration, int $width, int $height): void
    {
        if (!preg_match('#\Adata:image/jpeg;base64,([A-Za-z0-9+/=]+)\z#', $dataUrl, $matches)) {
            throw new RuntimeException('The preview must be a JPEG image.');
        }
        $jpeg = base64_decode($matches[1], true);
        if ($jpeg === false || $jpeg === '' || strlen($jpeg) > self::MAX_BYTES) {
            throw new RuntimeException('The preview image is too large.');
        }
        $info = @getimagesizefromstring($jpeg);
        if ($info === false || ($info[2] ?? null) !== IMAGETYPE_JPEG || $info[0] > 1280 || $info[1] > 1280) {
            throw new RuntimeException('The preview image is not valid.');
        }
        if (!is_finite($duration) || $duration <= 0 || $duration > 86400 || $width < 1 || $height < 1 || $width > 16384 || $height > 16384) {
            throw new RuntimeException('The video details are not valid.');
        }
        $directory = self::directory();
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('The server could not save the preview.');
        }
        foreach (glob($directory . '/' . (int) $item['id'] . '-*') ?: [] as $old) {
            @unlink($old);
        }
        $base = self::base((int) $item['id'], self::revision($item));
        $meta = json_encode(['duration' => round($duration, 3), 'width' => $width, 'height' => $height]);
        // Write the image last-but-one and the metadata last: find() needs both.
        if (!self::write($base . '.jpg', $jpeg) || !self::write($base . '.json', (string) $meta)) {
            throw new RuntimeException('The server could not save the preview.');
        }
    }

    public static function deleteFor(int $contentId): void
    {
        foreach (glob(self::directory() . '/' . $contentId . '-*') ?: [] as $file) {
            @unlink($file);
        }
    }

    private static function write(string $path, string $contents): bool
    {
        $temporary = $path . '.tmp';
        return @file_put_contents($temporary, $contents, LOCK_EX) !== false && @rename($temporary, $path);
    }

    private static function base(int $id, string $revision): string
    {
        return self::directory() . '/' . $id . '-' . $revision;
    }

    private static function directory(): string
    {
        return BASE_PATH . '/storage/signage/posters';
    }
}

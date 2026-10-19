<?php

declare(strict_types=1);

namespace App\Modules\Signage;

use RuntimeException;

/** Files stay outside the document root; player routes check access first. */
final class SignageStorage
{
    /** @return array{stored_filename:string,original_filename:string,mime_type:string,size_bytes:int} */
    public static function storeUpload(array $file, string $type = 'video'): array
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if (!is_int($error) || $error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file exceeds the server upload limit.',
                UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please choose the file again.',
                UPLOAD_ERR_NO_FILE => 'Choose a file to upload.',
                default => 'The server could not receive this upload. Check its storage and upload settings.',
            });
        }
        $temporary = $file['tmp_name'] ?? null;
        $original = $file['name'] ?? null;
        if (!is_string($temporary) || !is_uploaded_file($temporary) || !is_string($original)) {
            throw new RuntimeException('Choose a file using the upload form.');
        }
        $original = basename(str_replace('\\', '/', $original));
        $original = preg_replace('/[\x00-\x1f\x7f]/', '', $original) ?? '';
        $size = filesize($temporary);
        if ($size === false || $size < 1) {
            throw new RuntimeException('This file is empty.');
        }
        if ($size > self::maxUploadBytes()) {
            throw new RuntimeException('The file exceeds the upload limit shown on this page.');
        }
        $media = self::inspect($temporary, $original, $type);
        $directory = self::directory();
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('The media folder is not writable. Check the server storage permissions.');
        }
        do {
            $filename = bin2hex(random_bytes(24)) . '.' . $media['extension'];
        } while (file_exists($directory . '/' . $filename));
        if (!move_uploaded_file($temporary, self::path($filename))) {
            throw new RuntimeException('The server could not save this file. Check available storage and permissions.');
        }
        @chmod(self::path($filename), 0640);
        return [
            'stored_filename' => $filename,
            'original_filename' => mb_substr($original, 0, 200),
            'mime_type' => $media['mime_type'],
            'size_bytes' => $size,
        ];
    }

    /** Inspect actual bytes as well as the extension before accepting uploaded media. */
    public static function inspect(string $path, string $original, string $type): array
    {
        if (!class_exists(\finfo::class)) {
            throw new RuntimeException('Enable PHP fileinfo on the server before uploading media.');
        }
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if ($type === 'image') {
            $image = @getimagesize($path);
            if (!in_array($extension, ['png', 'jpg', 'jpeg'], true) || !is_array($image)
                || !in_array($mime, ['image/png', 'image/jpeg'], true) || $image['mime'] !== $mime
                || ($extension === 'png') !== ($mime === 'image/png')
                || $image[0] < 1 || $image[1] < 1 || $image[0] > 16384 || $image[1] > 16384
                || $image[0] * $image[1] > 40000000) {
                throw new RuntimeException('Choose a valid PNG or JPG image, up to 40 megapixels and 16,384 pixels per side.');
            }
            return ['extension' => $mime === 'image/png' ? 'png' : 'jpg', 'mime_type' => $mime];
        }
        if ($type !== 'video' || !in_array($extension, ['mp4', 'mov'], true)) {
            throw new RuntimeException('Choose an MP4 or MOV video.');
        }
        $size = (int) filesize($path);
        $handle = fopen($path, 'rb');
        $header = $handle === false ? false : fread($handle, 64);
        if ($handle !== false) {
            fclose($handle);
        }
        $boxSize = is_string($header) && strlen($header) >= 12 ? unpack('Nsize', substr($header, 0, 4))['size'] : 0;
        $brand = is_string($header) ? substr($header, 8, 4) : '';
        // Older QuickTime files can omit ftyp, but must still contain a complete
        // movie and media container. Modern MOV files use the qt brand.
        $quickTime = $extension === 'mov' && $mime === 'video/quicktime';
        $validBrand = is_string($header) && substr($header, 4, 4) === 'ftyp'
            && $boxSize >= 16 && $boxSize <= $size
            && in_array($brand, ['isom', 'iso2', 'iso3', 'iso4', 'iso5', 'iso6', 'mp41', 'mp42', 'avc1', 'M4V ', 'MSNV', 'dash', 'qt  '], true);
        if (!in_array($mime, ['video/mp4', 'application/mp4', 'video/quicktime'], true)
            || (!$validBrand && !$quickTime) || !self::hasVideoBoxes($path, $size)) {
            throw new RuntimeException('This file is not a valid MP4 or MOV video.');
        }
        return ['extension' => $extension, 'mime_type' => $extension === 'mov' ? 'video/quicktime' : 'video/mp4'];
    }

    public static function delete(string $filename): void
    {
        $path = self::path($filename);
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('The server could not remove the media file.');
        }
    }

    public static function maxUploadBytes(): int
    {
        $limits = [max(1, min(250, (int) config('signage.max_upload_mb', 250))) * 1024 * 1024];
        foreach (['upload_max_filesize', 'post_max_size'] as $key) {
            $bytes = self::iniBytes((string) ini_get($key));
            if ($bytes > 0) {
                // Leave space for multipart boundaries and ordinary form fields.
                if ($key === 'post_max_size') $bytes = max(1, $bytes - 1048576);
                $limits[] = $bytes;
            }
        }
        return min($limits);
    }

    public static function path(string $filename): string
    {
        if (!preg_match('/\A[a-f0-9]{48}\.(mp4|mov|png|jpg)\z/', $filename)) {
            throw new RuntimeException('Invalid stored media filename.');
        }
        $path = self::directory() . '/' . $filename;
        if (is_link($path)) {
            throw new RuntimeException('Invalid stored media file.');
        }
        return $path;
    }

    private static function directory(): string
    {
        return BASE_PATH . '/storage/signage/media';
    }

    /** Check container boundaries without decoding or buffering the video. */
    private static function hasVideoBoxes(string $path, int $fileSize): bool
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $offset = 0;
        $foundMovie = false;
        $foundMedia = false;
        try {
            for ($boxes = 0; $offset < $fileSize && $boxes < 10000; $boxes++) {
                if ($fileSize - $offset < 8 || fseek($handle, $offset) !== 0) {
                    return false;
                }
                $header = fread($handle, 8);
                if ($header === false || strlen($header) !== 8) {
                    return false;
                }
                $box = unpack('Nsize/a4type', $header);
                $size = $box['size'];
                $headerSize = 8;
                if ($size === 1) {
                    $extended = fread($handle, 8);
                    if ($extended === false || strlen($extended) !== 8) {
                        return false;
                    }
                    $parts = unpack('Nhigh/Nlow', $extended);
                    // Upload limits keep files far below the signed integer limit.
                    if ($parts['high'] > intdiv($fileSize, 4294967296)) {
                        return false;
                    }
                    $size = (int) ($parts['high'] * 4294967296 + $parts['low']);
                    $headerSize = 16;
                } elseif ($size === 0) {
                    $size = $fileSize - $offset;
                }
                if ($size < $headerSize || $size > $fileSize - $offset) {
                    return false;
                }
                if ($box['type'] === 'moov' && $size > $headerSize) {
                    $foundMovie = true;
                }
                if ($box['type'] === 'mdat' && $size > $headerSize) {
                    $foundMedia = true;
                }
                $offset += $size;
            }
            return $offset === $fileSize && $foundMovie && $foundMedia;
        } finally {
            fclose($handle);
        }
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || !preg_match('/\A([0-9]+(?:\.[0-9]+)?)\s*([KMG]?)\z/i', $value, $matches)) {
            return 0;
        }
        $factor = match (strtoupper($matches[2] ?? '')) {
            'G' => 1024 ** 3,
            'M' => 1024 ** 2,
            'K' => 1024,
            default => 1,
        };
        return (int) ((float) $matches[1] * $factor);
    }
}

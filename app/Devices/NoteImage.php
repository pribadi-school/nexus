<?php

declare(strict_types=1);

namespace App\Devices;

use RuntimeException;

/**
 * One optional picture on a device note (a photo of the fault, the label, the
 * cabling). Stored outside the document root and served to signed-in users only.
 */
final class NoteImage
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    /** Accepted image types and the extension stored for each. */
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** True when the form carried a file (so an empty file field is not an error). */
    public static function submitted(array $file): bool
    {
        return ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    /** Validates an uploaded image and stores it; returns the new file name. */
    public static function store(array $file): string
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if (!is_int($error) || $error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The image is too large (2 MB at most).',
                UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Choose the image again.',
                default => 'The server could not receive this image. Check its storage settings.',
            });
        }
        $temporary = $file['tmp_name'] ?? null;
        if (!is_string($temporary) || !is_uploaded_file($temporary)) {
            throw new RuntimeException('Choose an image using the note form.');
        }
        $size = filesize($temporary);
        if ($size === false || $size === 0) {
            throw new RuntimeException('This image file is empty.');
        }
        if ($size > self::MAX_BYTES) {
            throw new RuntimeException('The image is too large (2 MB at most). Try a smaller or lower-quality one.');
        }
        if (!class_exists(\finfo::class)) {
            throw new RuntimeException('Enable PHP fileinfo on the server before uploading images.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporary);
        // getimagesize() reads the image header, so a renamed non-image fails too.
        $info = @getimagesize($temporary);
        if (!isset(self::TYPES[$mime]) || $info === false || ($info['mime'] ?? '') !== $mime) {
            throw new RuntimeException('Use a JPG, PNG or WebP image.');
        }

        $directory = self::directory();
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('The image folder is not writable. Check the server storage permissions.');
        }
        do {
            $filename = bin2hex(random_bytes(20)) . '.' . self::TYPES[$mime];
        } while (file_exists($directory . '/' . $filename));
        if (!move_uploaded_file($temporary, self::path($filename))) {
            throw new RuntimeException('The server could not save this image. Check available storage and permissions.');
        }
        @chmod(self::path($filename), 0640);
        return $filename;
    }

    public static function delete(?string $filename): void
    {
        if ($filename === null || $filename === '') {
            return;
        }
        $path = self::path($filename);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function mime(string $filename): string
    {
        return (string) array_search(pathinfo($filename, PATHINFO_EXTENSION), self::TYPES, true);
    }

    public static function path(string $filename): string
    {
        if (!preg_match('/\A[a-f0-9]{40}\.(jpg|png|webp)\z/', $filename)) {
            throw new RuntimeException('Invalid stored image filename.');
        }
        $path = self::directory() . '/' . $filename;
        if (is_link($path)) {
            throw new RuntimeException('Invalid stored image file.');
        }
        return $path;
    }

    private static function directory(): string
    {
        return BASE_PATH . '/storage/private/note-images';
    }
}

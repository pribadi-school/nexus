<?php

declare(strict_types=1);

namespace App\Modules\Bell;

use RuntimeException;

/**
 * Bell sounds: three built-in ones that ship with the Windows app, and MP3 or
 * WAV files uploaded here. Uploads stay outside the document root.
 */
final class BellSounds
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    /** Built-in sounds (key => name). The files are bell-pc/sounds/<key>.wav. */
    public const BUILTIN = ['bel-sekolah' => 'Bel sekolah', 'denting' => 'Denting', 'peringatan' => 'Peringatan'];

    public static function builtinPath(string $key): string
    {
        if (!isset(self::BUILTIN[$key])) {
            throw new RuntimeException('Unknown built-in sound.');
        }
        return BASE_PATH . '/bell-pc/sounds/' . $key . '.wav';
    }

    /** @return array{stored_filename:string,original_filename:string,mime_type:string,size_bytes:int,sha256:string} */
    public static function store(array $file): array
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if (!is_int($error) || $error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is larger than the server allows (10 MB at most).',
                UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Choose the file again.',
                UPLOAD_ERR_NO_FILE => 'Choose an MP3 or WAV file first.',
                default => 'The server could not receive this file. Check its storage settings.',
            });
        }
        $temporary = $file['tmp_name'] ?? null;
        $original = $file['name'] ?? null;
        if (!is_string($temporary) || !is_uploaded_file($temporary) || !is_string($original)) {
            throw new RuntimeException('Choose a file using the upload form.');
        }
        $original = preg_replace('/[\x00-\x1f\x7f]/', '', basename(str_replace('\\', '/', $original))) ?? '';
        $size = filesize($temporary);
        if ($size === false || $size < 1) {
            throw new RuntimeException('This file is empty.');
        }
        if ($size > self::MAX_BYTES) {
            throw new RuntimeException('The file is too large (10 MB at most).');
        }
        $type = self::detect($temporary);
        if ($type === null) {
            throw new RuntimeException('Use an MP3 or WAV file.');
        }
        [$extension, $mime] = $type;

        $directory = self::directory();
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('The sound folder is not writable. Check the server storage permissions.');
        }
        $hash = hash_file('sha256', $temporary);
        do {
            $filename = bin2hex(random_bytes(20)) . '.' . $extension;
        } while (file_exists($directory . '/' . $filename));
        if (!move_uploaded_file($temporary, self::path($filename))) {
            throw new RuntimeException('The server could not save this file. Check available storage and permissions.');
        }
        @chmod(self::path($filename), 0640);
        return [
            'stored_filename' => $filename,
            'original_filename' => mb_substr($original, 0, 200),
            'mime_type' => $mime,
            'size_bytes' => (int) $size,
            'sha256' => (string) $hash,
        ];
    }

    /**
     * Looks at the file's own bytes, not its name: WAV starts with RIFF....WAVE,
     * MP3 with an ID3 tag or an MPEG frame header.
     *
     * @return array{0:string,1:string}|null  [extension, mime]
     */
    public static function detect(string $path): ?array
    {
        $head = (string) file_get_contents($path, false, null, 0, 12);
        if (strlen($head) >= 12 && str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WAVE') {
            return ['wav', 'audio/wav'];
        }
        if (str_starts_with($head, 'ID3') || (strlen($head) >= 2 && ord($head[0]) === 0xFF && (ord($head[1]) & 0xE0) === 0xE0)) {
            return ['mp3', 'audio/mpeg'];
        }
        return null;
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
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'wav' ? 'audio/wav' : 'audio/mpeg';
    }

    public static function path(string $filename): string
    {
        if (!preg_match('/\A[a-f0-9]{40}\.(mp3|wav)\z/', $filename)) {
            throw new RuntimeException('Invalid stored sound filename.');
        }
        $path = self::directory() . '/' . $filename;
        if (is_link($path)) {
            throw new RuntimeException('Invalid stored sound file.');
        }
        return $path;
    }

    private static function directory(): string
    {
        return BASE_PATH . '/storage/bell/sounds';
    }
}

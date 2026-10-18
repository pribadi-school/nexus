<?php

declare(strict_types=1);

namespace App\Modules\Signage;

use App\Core\Validator;

final class SignageValidation
{
    /** @return array{0:array<string,mixed>,1:array<string,string>} */
    public static function screen(array $input): array
    {
        $validator = new Validator($input, [
            'name' => 'Screen name',
            'orientation' => 'Orientation', 'fit' => 'Video fit',
        ]);
        $data = $validator->validate([
            'name' => ['required', 'max:160'],
            'orientation' => ['required', 'in:landscape,portrait'],
            'fit' => ['required', 'in:contain,cover'],
        ]);
        foreach (['muted', 'enabled'] as $field) {
            $value = $input[$field] ?? null;
            if ($value !== null && !in_array($value, ['0', '1'], true)) {
                $validator->addError($field, 'Choose a valid setting.');
            }
            $data[$field] = $value === '1' ? 1 : 0;
        }
        return [$data, $validator->errors()];
    }

    /** @return array{0:array<string,mixed>,1:array<string,string>} */
    public static function content(array $input, ?string $existingType = null): array
    {
        if ($existingType !== null) {
            $input['type'] = $existingType;
        }
        $validator = new Validator($input, ['name' => 'Content name', 'type' => 'Content type', 'source_url' => 'Website address']);
        $data = $validator->validate([
            'name' => ['required', 'max:160'],
            'type' => ['required', 'in:video,website'],
            'source_url' => ['max:2048'],
        ]);
        if ($data['type'] === 'website') {
            $url = $data['source_url'];
            if (!is_string($url) || !self::websiteUrl($url)) {
                $validator->addError('source_url', 'Enter a full website address beginning with https:// or http://.');
            }
        } else {
            $data['source_url'] = null;
        }
        return [$data, $validator->errors()];
    }

    /** Only browser navigation is allowed; the server never fetches this address. */
    public static function websiteUrl(string $url): bool
    {
        if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = parse_url($url);
        return is_array($parts)
            && in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true)
            && !empty($parts['host'])
            && !isset($parts['user']) && !isset($parts['pass']);
    }

    /** @return array{0:array<string,mixed>,1:array<string,string>} */
    public static function playlist(array $input): array
    {
        $validator = new Validator($input, ['name' => 'Playlist name', 'description' => 'Description']);
        $data = $validator->validate([
            'name' => ['required', 'max:160'],
            'description' => ['max:500'],
        ]);
        return [$data, $validator->errors()];
    }

    /** @return array{0:array<string,mixed>,1:array<string,string>} */
    public static function playlistItem(array $input, bool $requireContent = true): array
    {
        if (!isset($input['duration_seconds']) || $input['duration_seconds'] === '') {
            $input['duration_seconds'] = '30';
        }
        $validator = new Validator($input, ['content_id' => 'Content', 'duration_seconds' => 'Website duration']);
        $rules = ['duration_seconds' => ['required', 'int', 'min_value:5', 'max_value:86400']];
        if ($requireContent) {
            $rules['content_id'] = ['required', 'int', 'min_value:1', 'max_value:4294967295', 'exists:signage_content'];
        }
        $data = $validator->validate($rules);
        return [$data, $validator->errors()];
    }
}

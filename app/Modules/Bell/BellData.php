<?php

declare(strict_types=1);

namespace App\Modules\Bell;

/**
 * Pure helpers for bell data: validation, day sets and the CSV format.
 * Nothing here touches the database, so it is easy to test.
 */
final class BellData
{
    /** ISO weekday number => [English short, Indonesian short, Indonesian long]. */
    public const DAYS = [
        1 => ['Mon', 'Sen', 'Senin'], 2 => ['Tue', 'Sel', 'Selasa'], 3 => ['Wed', 'Rab', 'Rabu'],
        4 => ['Thu', 'Kam', 'Kamis'], 5 => ['Fri', 'Jum', 'Jumat'], 6 => ['Sat', 'Sab', 'Sabtu'], 7 => ['Sun', 'Min', 'Minggu'],
    ];

    public const COLORS = ['blue' => 'Blue', 'orange' => 'Orange', 'green' => 'Green', 'purple' => 'Purple'];

    public const CSV_HEADER = ['Waktu', 'Hari', 'File suara', 'Label', 'Aktif', 'Dua lantai'];

    /** "7:05", "07:05" or "07:05:09" => "07:05:09" (null when invalid). */
    public static function time(string $value): ?string
    {
        if (!preg_match('/\A(\d{1,2}):(\d{2})(?::(\d{2}))?\z/', trim($value), $m)) {
            return null;
        }
        [$h, $i, $s] = [(int) $m[1], (int) $m[2], (int) ($m[3] ?? 0)];
        return $h > 23 || $i > 59 || $s > 59 ? null : sprintf('%02d:%02d:%02d', $h, $i, $s);
    }

    /**
     * Weekday digits from a form (array of 1-7) => the stored string ("12345").
     *
     * @param mixed $days
     */
    public static function daysString(mixed $days): ?string
    {
        $set = [];
        foreach ((array) $days as $day) {
            if (is_numeric($day) && (int) $day >= 1 && (int) $day <= 7) {
                $set[(int) $day] = true;
            }
        }
        ksort($set);
        return $set === [] ? null : implode('', array_keys($set));
    }

    /** @return list<int> */
    public static function daysList(string $days): array
    {
        return array_map('intval', str_split($days));
    }

    /** "12345" => "Sen,Sel,Rab,Kam,Jum" for the CSV. */
    public static function daysCsv(string $days): string
    {
        return implode(',', array_map(static fn (int $d): string => self::DAYS[$d][1], self::daysList($days)));
    }

    /** Parses "Sen,Sel", "Mon Tue", "Sen-Jum", "1,2,3" or "setiap hari" into "12345". */
    public static function parseDays(string $text): ?string
    {
        $text = mb_strtolower(trim($text));
        if ($text === '') {
            return null;
        }
        if (in_array($text, ['setiap hari', 'tiap hari', 'every day', 'daily', 'all'], true)) {
            return '1234567';
        }
        $names = [];
        foreach (self::DAYS as $n => [$en, $id, $long]) {
            foreach ([$en, $id, $long, (string) $n] as $alias) {
                $names[mb_strtolower($alias)] = $n;
            }
        }
        if (preg_match('/\A([a-z0-9]+)\s*-\s*([a-z0-9]+)\z/u', $text, $m) && isset($names[$m[1]], $names[$m[2]])) {
            $to = $names[$m[2]];
            $set = [];
            for ($d = $names[$m[1]]; count($set) < 7; $d = $d % 7 + 1) {
                $set[] = $d;
                if ($d === $to) {
                    break;
                }
            }
            return self::daysString($set);
        }
        $set = [];
        foreach (preg_split('/[\s,;\/]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            if (!isset($names[$token])) {
                return null;
            }
            $set[] = $names[$token];
        }
        return self::daysString($set);
    }

    /**
     * Validates one schedule row from a form.
     *
     * @param array<string,mixed> $input
     * @param list<int> $soundIds uploaded sound ids that exist
     * @return array{0:array<string,mixed>,1:array<string,string>} [data, errors]
     */
    public static function validateEntry(array $input, array $soundIds): array
    {
        $errors = [];
        $time = self::time((string) ($input['ring_time'] ?? ''));
        if ($time === null) {
            $errors['ring_time'] = 'Enter a time such as 07:00:00.';
        }
        $days = self::daysString($input['days'] ?? []);
        if ($days === null) {
            $errors['days'] = 'Choose at least one day.';
        }
        $sound = (string) ($input['sound_ref'] ?? '');
        if (!self::validSound($sound, $soundIds)) {
            $errors['sound_ref'] = 'Choose a sound.';
        }
        $label = trim((string) ($input['label'] ?? ''));
        if (mb_strlen($label) > 120) {
            $errors['label'] = 'Keep the label to 120 characters.';
        }
        return [[
            'ring_time' => $time ?? '00:00:00', 'days' => $days ?? '', 'sound_ref' => $sound, 'label' => $label,
            'enabled' => !empty($input['enabled']) ? 1 : 0, 'both_floors' => !empty($input['both_floors']) ? 1 : 0,
        ], $errors];
    }

    /** @param list<int> $soundIds */
    public static function validSound(string $ref, array $soundIds): bool
    {
        if (str_starts_with($ref, 'builtin:')) {
            return isset(BellSounds::BUILTIN[substr($ref, 8)]);
        }
        return preg_match('/\Asound:(\d+)\z/', $ref, $m) === 1 && in_array((int) $m[1], $soundIds, true);
    }

    /**
     * One zone profile as Excel-friendly CSV: UTF-8 with a byte-order mark and
     * semicolon separators (what Indonesian Excel expects).
     *
     * @param list<array<string,mixed>> $entries
     * @param array<string,string> $soundNames sound_ref => display name
     */
    public static function toCsv(array $entries, array $soundNames): string
    {
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, self::CSV_HEADER, ';');
        foreach ($entries as $entry) {
            fputcsv($handle, [
                (string) $entry['ring_time'], self::daysCsv((string) $entry['days']),
                $soundNames[(string) $entry['sound_ref']] ?? (string) $entry['sound_ref'],
                (string) $entry['label'], $entry['enabled'] ? 'Ya' : 'Tidak', $entry['both_floors'] ? 'Ya' : 'Tidak',
            ], ';');
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);
        return "\xEF\xBB\xBF" . $csv;
    }

    /**
     * Reads a CSV made by toCsv() (or edited in Excel). Returns the valid rows
     * and one message per rejected line; nothing is saved here.
     *
     * @param array<string,string> $soundRefs lower-case sound name => sound_ref
     * @return array{0:list<array<string,mixed>>,1:list<string>}
     */
    public static function fromCsv(string $csv, array $soundRefs): array
    {
        $csv = preg_replace('/\A\xEF\xBB\xBF/', '', $csv) ?? $csv;
        $firstLine = strtok($csv, "\n") ?: '';
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, $csv);
        rewind($handle);
        $rows = [];
        $errors = [];
        $line = 0;
        while (($cells = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            if (count(array_filter($cells, static fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $cells = array_pad(array_map(static fn ($c) => trim((string) $c), $cells), 6, '');
            if ($line === 1 && self::time($cells[0]) === null) {
                continue; // header row
            }
            $time = self::time($cells[0]);
            $days = self::parseDays($cells[1]);
            $sound = $soundRefs[mb_strtolower($cells[2])] ?? null;
            if ($time === null) {
                $errors[] = "Line $line: \"{$cells[0]}\" is not a time.";
            } elseif ($days === null) {
                $errors[] = "Line $line: could not read the days \"{$cells[1]}\".";
            } elseif ($sound === null) {
                $errors[] = "Line $line: there is no sound named \"{$cells[2]}\".";
            } elseif (mb_strlen($cells[3]) > 120) {
                $errors[] = "Line $line: the label is longer than 120 characters.";
            } else {
                $rows[] = [
                    'ring_time' => $time, 'days' => $days, 'sound_ref' => $sound, 'label' => $cells[3],
                    'enabled' => self::yes($cells[4], true) ? 1 : 0, 'both_floors' => self::yes($cells[5], false) ? 1 : 0,
                ];
            }
        }
        fclose($handle);
        return [$rows, $errors];
    }

    private static function yes(string $value, bool $default): bool
    {
        $value = mb_strtolower($value);
        if ($value === '') {
            return $default;
        }
        return in_array($value, ['ya', 'y', 'yes', '1', 'true', 'aktif', 'x'], true);
    }
}

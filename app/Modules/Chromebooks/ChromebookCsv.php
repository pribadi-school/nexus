<?php

declare(strict_types=1);

namespace App\Modules\Chromebooks;

use App\Devices\DeviceService;
use App\Devices\Status;

/**
 * Export (CSV) and import (CSV or Excel .xlsx) of one academic year's
 * Chromebooks. Both use the same columns, so an exported file can be edited
 * and imported again; the school's own yearly lists (No, ID, Serial Number,
 * Nama, Email, ...) import as they are.
 *
 * Import is three steps: read() turns the file into rows of cells, plan()
 * says what each row would do (shown as a preview), apply() does it. Rows
 * are matched on asset ID, or on serial number when there is no asset ID.
 * Empty cells never clear anything.
 */
final class ChromebookCsv
{
    public const COLUMNS = ['asset_id', 'serial_number', 'student_name', 'student_email', 'model', 'status'];
    public const MAX_ROWS = 3000;

    /** Header names people are likely to use (English and Indonesian), mapped to our columns. */
    private const ALIASES = [
        'asset_id' => 'asset_id', 'asset id' => 'asset_id', 'asset' => 'asset_id', 'id' => 'asset_id', 'id aset' => 'asset_id', 'no aset' => 'asset_id',
        'serial' => 'serial_number', 'serial_number' => 'serial_number', 'serial number' => 'serial_number', 'sn' => 'serial_number',
        'seri' => 'serial_number', 'no seri' => 'serial_number', 'nomor seri' => 'serial_number',
        'student' => 'student_name', 'student_name' => 'student_name', 'student name' => 'student_name', 'name' => 'student_name',
        'nama' => 'student_name', 'nama siswa' => 'student_name', 'siswa' => 'student_name',
        'email' => 'student_email', 'e-mail' => 'student_email', 'student_email' => 'student_email', 'email siswa' => 'student_email',
        'model' => 'model',
        'status' => 'status',
    ];

    /** @param list<array<string, mixed>> $rows from ChromebookRepository::search() */
    public static function export(array $rows, ChromebookCategory $category): string
    {
        $out = fopen('php://temp', 'r+');
        // BOM so Excel opens the file as UTF-8.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [...self::COLUMNS, 'assigned_at', 'last_updated'], ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['asset_id'],
                $row['serial_number'],
                $row['student_name'],
                $row['student_email'],
                $row['model'],
                $category->statusLabel((string) $row['status']),
                $row['assigned_at'] ? format_datetime($row['assigned_at']) : '',
                format_datetime($row['updated_at']),
            ], ',', '"', '');
        }
        rewind($out);
        return (string) stream_get_contents($out);
    }

    /**
     * Reads an uploaded .csv or .xlsx file (first sheet) into rows of cells.
     *
     * @return list<list<string>>|string the rows, or an error to show
     */
    public static function read(string $path, string $name): array|string
    {
        $content = (string) file_get_contents($path);
        if (str_starts_with($content, "PK\x03\x04")) {
            return self::readXlsx($path);
        }
        if (preg_match('/\.(xls|xlsx|ods)$/i', $name)) {
            return 'This file could not be read. Save it as an Excel workbook (.xlsx) or as CSV and try again.';
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $lines = preg_split('/\r\n|\n|\r/', trim($content)) ?: [];
        if ($lines === [] || trim($lines[0]) === '') {
            return 'The file is empty.';
        }
        // Excel in Indonesian / European settings saves with semicolons.
        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        return array_map(
            static fn (string $line): array => array_map(static fn ($v): string => (string) $v, str_getcsv($line, $delimiter, '"', '')),
            $lines,
        );
    }

    /** @return list<list<string>>|string */
    private static function readXlsx(string $path): array|string
    {
        if (!class_exists(\ZipArchive::class) || !function_exists('simplexml_load_string')) {
            return 'This server cannot read Excel files. Save the sheet as CSV and import that instead.';
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return 'This Excel file could not be opened. Save it again (or as CSV) and try again.';
        }
        try {
            $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
            $xml = static function (string $entry) use ($zip): ?\SimpleXMLElement {
                $raw = $zip->getFromName($entry);
                return $raw === false ? null : (simplexml_load_string($raw, \SimpleXMLElement::class, LIBXML_NONET) ?: null);
            };

            $shared = [];
            if ($strings = $xml('xl/sharedStrings.xml')) {
                foreach ($strings->children($ns)->si as $si) {
                    // Rich text keeps its pieces in several <t> elements.
                    $shared[] = implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]') ?: []));
                }
            }

            // The first sheet in the workbook's order, via its relationship.
            $book = $xml('xl/workbook.xml');
            $rels = $xml('xl/_rels/workbook.xml.rels');
            $first = $book?->children($ns)->sheets->sheet[0] ?? null;
            if ($first === null || $rels === null) {
                return 'This Excel file has no sheets.';
            }
            $rid = (string) $first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $target = null;
            foreach ($rels->xpath('//*[local-name()="Relationship"]') ?: [] as $rel) {
                if ((string) $rel->attributes()['Id'] === $rid) {
                    $target = ltrim((string) $rel->attributes()['Target'], '/');
                }
            }
            $sheet = $target === null ? null : $xml(str_starts_with($target, 'xl/') ? $target : 'xl/' . $target);
            if ($sheet === null) {
                return 'The first sheet of this Excel file could not be read.';
            }

            $rows = [];
            foreach ($sheet->children($ns)->sheetData->row as $row) {
                $cells = [];
                foreach ($row->children($ns)->c as $c) {
                    // Inside a namespaced walk, $c['r'] reads nothing: plain attributes need attributes().
                    $attr = $c->attributes();
                    preg_match('/^[A-Z]+/', (string) $attr['r'], $m);
                    $col = 0;
                    foreach (str_split($m[0] ?? 'A') as $ch) {
                        $col = $col * 26 + ord($ch) - 64;
                    }
                    $type = (string) $attr['t'];
                    $value = match ($type) {
                        's' => $shared[(int) $c->children($ns)->v] ?? '',
                        'inlineStr' => implode('', array_map('strval', $c->xpath('.//*[local-name()="t"]') ?: [])),
                        default => (string) $c->children($ns)->v,
                    };
                    $cells[$col - 1] = $value;
                }
                if ($cells !== []) {
                    $line = array_fill(0, max(array_keys($cells)) + 1, '');
                    foreach ($cells as $i => $v) {
                        $line[$i] = $v;
                    }
                    $rows[] = $line;
                }
            }
            return $rows === [] ? 'The first sheet of this Excel file is empty.' : $rows;
        } finally {
            $zip->close();
        }
    }

    /**
     * Works out what each row would do when imported into $year.
     *
     * @param list<list<string>> $table from read()
     * @return array{columns:list<string>, rows:list<array<string, mixed>>, error:?string}
     *   each row: line, asset_id, serial_number, student_name, student_email, model, status,
     *   action (add|update|same|error), changes (list<string>), message
     */
    public static function plan(array $table, int $year, ChromebookRepository $repo): array
    {
        // The header is the first row (of the first ten) that names a column we know.
        $map = [];
        $headerAt = null;
        foreach (array_slice($table, 0, 10) as $i => $cells) {
            $found = [];
            foreach ($cells as $col => $name) {
                $key = self::ALIASES[mb_strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? ''))] ?? null;
                if ($key !== null && !in_array($key, $found, true)) {
                    $found[$col] = $key;
                }
            }
            if (in_array('asset_id', $found, true) || in_array('serial_number', $found, true)) {
                [$map, $headerAt] = [$found, $i];
                break;
            }
        }
        if ($headerAt === null) {
            return ['columns' => [], 'rows' => [], 'error' => 'No header row found. One of the first rows must name the columns, including an asset ID (ID) or serial number column. Download the example to see the layout.'];
        }
        if (count($table) - $headerAt - 1 > self::MAX_ROWS) {
            return ['columns' => [], 'rows' => [], 'error' => 'The file has more than ' . self::MAX_ROWS . ' rows. Split it into smaller files.'];
        }

        $rows = [];
        $seenAsset = [];
        $seenSerial = [];
        foreach (array_slice($table, $headerAt + 1) as $n => $cells) {
            $value = static function (string $column) use ($map, $cells): ?string {
                $i = array_search($column, $map, true);
                if ($i === false) {
                    return null;
                }
                $v = trim(preg_replace('/\s+/u', ' ', (string) ($cells[$i] ?? '')) ?? '');
                return $v === '' ? null : $v;
            };
            $student = $value('student_name');
            if ($student !== null) {
                // The 2026 list ends each name with the registration number ("… REG-1140").
                $student = trim(preg_replace('/\s*\bREG-?\d+\s*$/i', '', $student) ?? $student);
                $student = $student === '' ? null : $student;
            }
            $row = [
                'line' => $headerAt + $n + 2,
                'asset_id' => $value('asset_id'),
                'serial_number' => $value('serial_number'),
                'student_name' => $student,
                'student_email' => $value('student_email') !== null ? mb_strtolower($value('student_email')) : null,
                'model' => $value('model'),
                'status' => null,
                'action' => 'error',
                'changes' => [],
                'message' => '',
            ];
            // Numbered but otherwise empty rows (the "No" column filled down) are not rows.
            if ($row['asset_id'] === null && $row['serial_number'] === null && $row['student_name'] === null && $row['student_email'] === null) {
                continue;
            }

            $status = $value('status');
            if ($status !== null) {
                $row['status'] = match (mb_strtolower($status)) {
                    'normal', '-', 'unmarked', 'ok', 'ok (legacy)', 'running well', 'good', 'baik' => Status::NORMAL,
                    'reported', 'problem', 'has problem', 'error' => Status::PROBLEM,
                    'fixed', 'repaired', 'fix' => Status::FIXED,
                    'borrowed' => Status::BORROWED,
                    'broken', 'rusak' => Status::BROKEN,
                    'replaced' => Status::REPLACED,
                    default => null,
                };
            }
            $assetYear = $row['asset_id'] !== null && preg_match('/^(\d{4})-/', $row['asset_id'], $m) ? (int) $m[1] : null;
            $assetKey = $row['asset_id'] !== null ? mb_strtolower($row['asset_id']) : null;
            $serialKey = $row['serial_number'] !== null ? mb_strtolower($row['serial_number']) : null;

            $error = match (true) {
                $row['asset_id'] === null && $row['serial_number'] === null => 'No asset ID or serial number.',
                $row['asset_id'] !== null && mb_strlen($row['asset_id']) > 20 => 'Asset ID is too long.',
                $assetYear !== null && $assetYear !== $year => "Asset ID is from $assetYear, but this import is for $year.",
                $assetKey !== null && isset($seenAsset[$assetKey]) => 'Same asset ID as line ' . $seenAsset[$assetKey] . '.',
                $row['serial_number'] !== null && mb_strlen($row['serial_number']) > 64 => 'Serial number is too long.',
                $serialKey !== null && isset($seenSerial[$serialKey]) => 'Same serial as line ' . $seenSerial[$serialKey] . '.',
                $row['student_name'] !== null && mb_strlen($row['student_name']) > 120 => 'Student name is too long.',
                $row['student_email'] !== null && filter_var($row['student_email'], FILTER_VALIDATE_EMAIL) === false => "Email \"{$row['student_email']}\" is not an email address.",
                $row['model'] !== null && mb_strlen($row['model']) > 100 => 'Model is too long.',
                $status !== null && $row['status'] === null => "Status \"$status\" must be Normal, Problem, Fixed, Borrowed, Replaced or Broken.",
                default => null,
            };
            if ($assetKey !== null) {
                $seenAsset[$assetKey] ??= $row['line'];
            }
            if ($serialKey !== null) {
                $seenSerial[$serialKey] ??= $row['line'];
            }
            if ($error !== null) {
                $row['message'] = $error;
                $rows[] = $row;
                continue;
            }

            $byAsset = $row['asset_id'] !== null ? $repo->findByAsset($row['asset_id']) : null;
            $bySerial = $row['serial_number'] !== null ? $repo->findBySerial($row['serial_number']) : null;
            $existing = $byAsset ?? $bySerial;
            $error = match (true) {
                $byAsset !== null && $bySerial !== null && $byAsset['id'] !== $bySerial['id']
                    => "Serial is already on Chromebook {$bySerial['asset_id']}.",
                $existing !== null && (int) $existing['academic_year'] !== $year
                    => 'This Chromebook is in the ' . ChromebookCategory::yearLabel((int) $existing['academic_year'])
                        . ' list, not ' . ChromebookCategory::yearLabel($year) . '.',
                $byAsset === null && $bySerial !== null && $bySerial['asset_id'] !== null && $row['asset_id'] !== null
                    => "Serial is already on Chromebook {$bySerial['asset_id']}.",
                default => null,
            };
            if ($error !== null) {
                $row['message'] = $error;
                $rows[] = $row;
                continue;
            }

            if ($existing === null) {
                $row['action'] = 'add';
                $row['message'] = 'New Chromebook';
            } else {
                $row['id'] = (int) $existing['id'];
                $differs = static fn (string $key): bool => $row[$key] !== null && $row[$key] !== $existing[$key];
                foreach (['asset_id' => 'asset ID', 'serial_number' => 'serial', 'model' => 'model', 'status' => 'status'] as $key => $label) {
                    if ($differs($key)) {
                        $row['changes'][] = $label;
                    }
                }
                if ($differs('student_name')) {
                    $row['changes'][] = 'student';
                    $row['previous'] = $existing['student_name'] ?? 'nobody';
                } elseif ($differs('student_email')) {
                    $row['changes'][] = 'email';
                }
                $row['action'] = $row['changes'] === [] ? 'same' : 'update';
                $row['message'] = $row['changes'] === [] ? 'No changes' : 'Changes ' . implode(', ', $row['changes']);
            }
            $rows[] = $row;
        }

        return ['columns' => array_values($map), 'rows' => $rows, 'error' => $rows === [] ? 'The file has no rows below the header.' : null];
    }

    /**
     * Applies a plan from plan(). Everything happens in one transaction: if
     * one row fails, nothing is saved.
     *
     * @param list<array<string, mixed>> $rows
     * @return array{added:int, updated:int}
     */
    public static function apply(array $rows, int $year, ChromebookCategory $category, ChromebookRepository $repo, ?int $userId): array
    {
        $added = 0;
        $updated = 0;
        db()->transaction(function () use ($rows, $year, $category, $repo, $userId, &$added, &$updated): void {
            $service = new DeviceService();
            foreach ($rows as $row) {
                if ($row['action'] === 'add') {
                    $repo->create($category, [
                        'academic_year' => $year,
                        'asset_id' => $row['asset_id'],
                        'serial_number' => $row['serial_number'],
                        'model' => $row['model'],
                        'status' => $row['status'] ?? Status::NORMAL,
                        'student_name' => $row['student_name'],
                        'student_email' => $row['student_email'],
                    ], $userId);
                    $added++;
                } elseif ($row['action'] === 'update') {
                    $current = $repo->find((int) $row['id']);
                    if ($current === null) {
                        continue;
                    }
                    if (array_intersect(['asset ID', 'serial', 'model'], $row['changes']) !== []) {
                        $repo->update($current, [
                            'academic_year' => (int) $current['academic_year'],
                            'asset_id' => $row['asset_id'] ?? $current['asset_id'],
                            'serial_number' => $row['serial_number'] ?? $current['serial_number'],
                            'model' => $row['model'] ?? $current['model'],
                        ], $userId);
                    }
                    if (in_array('status', $row['changes'], true)) {
                        $service->changeStatus($category, $current, (string) $row['status'], $userId);
                    }
                    if (in_array('student', $row['changes'], true)) {
                        $repo->assign($current, $row['student_name'], $row['student_email'], $userId);
                    } elseif (in_array('email', $row['changes'], true)) {
                        $repo->assign($current, $current['student_name'], $row['student_email'], $userId);
                    }
                    $updated++;
                }
            }
        });
        return ['added' => $added, 'updated' => $updated];
    }
}

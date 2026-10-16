<?php

declare(strict_types=1);

namespace App\Modules\Chromebooks;

use App\Devices\DeviceService;
use App\Devices\Status;

/**
 * CSV export and import. Both use the same columns, so an exported file can
 * be edited in Excel / Google Sheets and imported again.
 *
 * Import is two steps: plan() reads the file and says what each row would do
 * (shown as a preview), apply() does it. Rows are matched on serial number.
 */
final class ChromebookCsv
{
    public const COLUMNS = ['serial_number', 'student_name', 'class', 'model', 'status'];
    public const MAX_ROWS = 3000;

    /** Header names people are likely to use, mapped to our columns. */
    private const ALIASES = [
        'serial' => 'serial_number', 'serial_number' => 'serial_number', 'serial number' => 'serial_number', 'sn' => 'serial_number',
        'student' => 'student_name', 'student_name' => 'student_name', 'student name' => 'student_name', 'name' => 'student_name', 'nama' => 'student_name',
        'class' => 'class', 'class_name' => 'class', 'kelas' => 'class',
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
                $row['serial_number'],
                $row['student_name'],
                $row['class_name'],
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
     * Reads an uploaded file and works out what each row would do.
     *
     * @return array{columns:list<string>, rows:list<array<string, mixed>>, error:?string}
     *   each row: line, serial_number, student_name, class_id, class_name, model, status,
     *   action (add|update|same|error), changes (list<string>), message
     */
    public static function plan(string $content, ChromebookRepository $repo): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $lines = preg_split('/\r\n|\n|\r/', trim($content)) ?: [];
        if ($lines === [] || trim($lines[0]) === '') {
            return ['columns' => [], 'rows' => [], 'error' => 'The file is empty.'];
        }
        // Excel in Indonesian / European settings saves with semicolons.
        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';

        $map = [];
        foreach (str_getcsv($lines[0], $delimiter, '"', '') as $i => $name) {
            $key = self::ALIASES[strtolower(trim((string) $name))] ?? null;
            if ($key !== null && !in_array($key, $map, true)) {
                $map[$i] = $key;
            }
        }
        if (!in_array('serial_number', $map, true)) {
            return ['columns' => [], 'rows' => [], 'error' => 'The first row must name the columns, including serial_number. Download the example to see the layout.'];
        }
        if (count($lines) - 1 > self::MAX_ROWS) {
            return ['columns' => [], 'rows' => [], 'error' => 'The file has more than ' . self::MAX_ROWS . ' rows. Split it into smaller files.'];
        }

        $classes = [];
        foreach (db()->all('SELECT id, name FROM classes') as $class) {
            $classes[mb_strtolower($class['name'])] = ['id' => (int) $class['id'], 'name' => $class['name']];
        }

        $rows = [];
        $seen = [];
        foreach (array_slice($lines, 1) as $n => $line) {
            if (trim(str_replace($delimiter, '', $line)) === '') {
                continue;
            }
            $cells = str_getcsv($line, $delimiter, '"', '');
            $value = static function (string $column) use ($map, $cells): ?string {
                $i = array_search($column, $map, true);
                if ($i === false) {
                    return null;
                }
                $v = trim((string) ($cells[$i] ?? ''));
                return $v === '' ? null : $v;
            };
            $has = static fn (string $column): bool => in_array($column, $map, true);

            $row = [
                'line' => $n + 2,
                'serial_number' => $value('serial_number'),
                'student_name' => $value('student_name'),
                'class_id' => null,
                'class_name' => $value('class'),
                'model' => $value('model'),
                'status' => null,
                'action' => 'error',
                'changes' => [],
                'message' => '',
            ];
            $rows[] = &$row;

            $status = $value('status');
            if ($status !== null) {
                $row['status'] = match (strtolower($status)) {
                    'ok', 'running well', 'good', 'baik' => Status::OK,
                    'problem', 'has problem', 'rusak', 'broken' => Status::PROBLEM,
                    default => null,
                };
            }
            if ($row['class_name'] !== null && isset($classes[mb_strtolower($row['class_name'])])) {
                $class = $classes[mb_strtolower($row['class_name'])];
                [$row['class_id'], $row['class_name']] = [$class['id'], $class['name']];
            }

            $error = match (true) {
                $row['serial_number'] === null => 'No serial number.',
                mb_strlen($row['serial_number']) > 64 => 'Serial number is too long.',
                isset($seen[mb_strtolower($row['serial_number'])]) => 'Same serial as line ' . $seen[mb_strtolower($row['serial_number'])] . '.',
                $row['student_name'] !== null && mb_strlen($row['student_name']) > 120 => 'Student name is too long.',
                $row['model'] !== null && mb_strlen($row['model']) > 100 => 'Model is too long.',
                $row['class_name'] !== null && $row['class_id'] === null => "Class \"{$row['class_name']}\" is not in the class list.",
                $status !== null && $row['status'] === null => "Status \"$status\" is not OK or Has problem.",
                default => null,
            };
            if ($row['serial_number'] !== null) {
                $seen[mb_strtolower($row['serial_number'])] ??= $row['line'];
            }
            if ($error !== null) {
                $row['message'] = $error;
                unset($row);
                continue;
            }

            $existing = $repo->findBySerial($row['serial_number']);
            if ($existing === null) {
                $row['action'] = 'add';
                $row['message'] = 'New Chromebook';
            } else {
                $row['id'] = (int) $existing['id'];
                if ($row['model'] !== null && $row['model'] !== $existing['model']) {
                    $row['changes'][] = 'model';
                }
                if ($row['status'] !== null && $row['status'] !== $existing['status']) {
                    $row['changes'][] = 'status';
                }
                // Only columns that are in the file can change the assignment.
                $student = $has('student_name') ? $row['student_name'] : $existing['student_name'];
                $classId = $has('class') ? $row['class_id'] : ($existing['class_id'] === null ? null : (int) $existing['class_id']);
                [$row['student_name'], $row['class_id']] = [$student, $classId];
                if (!$has('class')) {
                    $row['class_name'] = $existing['class_name'];
                }
                if ($student !== $existing['student_name'] || $classId !== ($existing['class_id'] === null ? null : (int) $existing['class_id'])) {
                    $row['changes'][] = 'assignment';
                    $row['previous'] = trim(($existing['student_name'] ?? 'nobody') . ($existing['class_name'] ? " ({$existing['class_name']})" : ''));
                }
                $row['action'] = $row['changes'] === [] ? 'same' : 'update';
                $row['message'] = $row['changes'] === [] ? 'No changes' : 'Changes ' . implode(', ', $row['changes']);
            }
            unset($row);
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
    public static function apply(array $rows, ChromebookCategory $category, ChromebookRepository $repo, ?int $userId): array
    {
        $added = 0;
        $updated = 0;
        db()->transaction(function () use ($rows, $category, $repo, $userId, &$added, &$updated): void {
            $service = new DeviceService();
            foreach ($rows as $row) {
                if ($row['action'] === 'add') {
                    $repo->create($category, [
                        'serial_number' => $row['serial_number'],
                        'model' => $row['model'],
                        'status' => $row['status'] ?? Status::OK,
                        'student_name' => $row['student_name'],
                        'class_id' => $row['class_id'],
                    ], $userId);
                    $added++;
                } elseif ($row['action'] === 'update') {
                    $current = $repo->find((int) $row['id']);
                    if ($current === null) {
                        continue;
                    }
                    if (in_array('model', $row['changes'], true)) {
                        $repo->update($current, ['serial_number' => $current['serial_number'], 'model' => $row['model']], $userId);
                    }
                    if (in_array('status', $row['changes'], true)) {
                        $service->changeStatus($category, $current, (string) $row['status'], $userId);
                    }
                    if (in_array('assignment', $row['changes'], true)) {
                        $repo->assign($current, $row['student_name'], $row['class_id'], $userId);
                    }
                    $updated++;
                }
            }
        });
        return ['added' => $added, 'updated' => $updated];
    }
}

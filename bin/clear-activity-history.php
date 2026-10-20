<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Devices\ActivityHistoryCleanup;

try {
    $options = array_slice($argv, 1);
    $restore = null;
    foreach ($options as $option) {
        if (str_starts_with($option, '--restore=')) $restore = substr($option, 10);
        elseif ($option !== '--apply') throw new RuntimeException('Use --apply to clear history, or --restore=BACKUP --apply to restore. Without flags, this only previews.');
    }
    $maintenance = new ActivityHistoryCleanup();
    if ($restore !== null) {
        if (!in_array('--apply', $options, true)) throw new RuntimeException('Restore requires --apply.');
        echo json_encode(['restored_history_entries' => $maintenance->restore($restore)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
    } else {
        echo json_encode($maintenance->run(in_array('--apply', $options, true)), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}

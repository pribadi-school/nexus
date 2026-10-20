<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Modules\Dashboard\DashboardActivityVisibility;

try {
    $options = array_slice($argv, 1);
    foreach ($options as $option) {
        if (!in_array($option, ['--apply', '--reset'], true)) throw new RuntimeException('Use --apply to hide older dashboard activity, or --reset --apply to show it again. No flags previews only.');
    }
    $visibility = new DashboardActivityVisibility();
    if (in_array('--reset', $options, true)) {
        if (!in_array('--apply', $options, true)) throw new RuntimeException('Reset requires --apply.');
        $visibility->reset();
        echo "All dashboard activity is visible again. Device history was unchanged.\n";
    } else {
        echo json_encode($visibility->configure(in_array('--apply', $options, true)), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}

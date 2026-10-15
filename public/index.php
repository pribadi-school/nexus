<?php

declare(strict_types=1);

// Front controller: every request that is not a real file lands here.

// PHP's built-in dev server (bin/dev.ps1) asks this script about every URL;
// returning false lets it serve real files such as CSS, fonts and icons.
if (PHP_SAPI === 'cli-server') {
    $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    if ($path !== '/' && is_file(__DIR__ . $path)) {
        return false;
    }
}

$app = require dirname(__DIR__) . '/app/bootstrap.php';
$app->run();

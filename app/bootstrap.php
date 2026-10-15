<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Config;
use App\Core\Env;
use App\Core\ErrorHandler;
use App\Core\Session;

define('BASE_PATH', dirname(__DIR__));

// PSR-4 style autoloader: App\Foo\Bar -> app/Foo/Bar.php. No Composer needed.
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/helpers.php';

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

// Everything inside PHP and MySQL runs in UTC; views convert to the school's
// time zone (config app.timezone) when they print a time.
date_default_timezone_set('UTC');

ErrorHandler::register((bool) config('app.debug'), BASE_PATH . '/storage/logs/app.log');

if (PHP_SAPI !== 'cli') {
    Session::start();
}

return new App(require BASE_PATH . '/app/routes.php');

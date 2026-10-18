<?php

declare(strict_types=1);

// Applies database/migrations/*.sql that the database has not seen yet: the
// same files you import by hand in phpMyAdmin on the server.
//
//   php bin/migrate.php            apply pending migrations
//   php bin/migrate.php --seed     ...then load the sample data if there are no devices yet
//   php bin/migrate.php --status   list migrations and whether they are applied

use App\Core\Migrations;

require dirname(__DIR__) . '/app/bootstrap.php';

$args = array_slice($argv, 1);
$pdo = db()->pdo();

/** Runs a .sql file statement by statement (statements end with ";" at a line end). */
$runFile = static function (string $file) use ($pdo): void {
    $sql = (string) file_get_contents($file);
    $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (preg_split('/;\s*(\r?\n|$)/', $sql) ?: [] as $statement) {
        if (trim($statement) !== '') {
            $pdo->exec($statement);
        }
    }
};

if (in_array('--status', $args, true)) {
    // Connect first: Migrations::applied() treats a failed connection as
    // "nothing applied", which would wrongly list every migration as pending.
    try {
        db()->value('SELECT 1');
    } catch (Throwable $e) {
        fwrite(STDERR, 'Cannot connect to the database: ' . $e->getMessage() . "\n");
        exit(1);
    }
    $applied = Migrations::applied();
    foreach (Migrations::available() as $version) {
        printf("  [%s] %s\n", in_array($version, $applied, true) ? 'x' : ' ', $version);
    }
    exit(0);
}

$pending = Migrations::pending();
if ($pending === []) {
    echo "Database is up to date.\n";
}
foreach ($pending as $version) {
    echo "Applying $version ... ";
    $runFile(BASE_PATH . "/database/migrations/$version.sql");
    echo "done\n";
}

if (in_array('--seed', $args, true)) {
    // StudyTouch boards and CCTV cameras come from migrations (real data), so only Chromebooks count.
    if ((int) db()->value("SELECT COUNT(*) FROM devices WHERE category = 'chromebook'") === 0) {
        echo 'Loading sample data ... ';
        $runFile(BASE_PATH . '/database/seeds/sample_data.sql');
        echo "done\n";
    } else {
        echo "Devices already exist; sample data not loaded.\n";
    }
}

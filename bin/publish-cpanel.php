<?php

declare(strict_types=1);

// Run from cPanel Terminal after pulling code. No database or .env is needed.
// Copies only public assets, with a front controller pointing at this checkout.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (PHP_VERSION_ID < 80200) {
    fwrite(STDERR, "Nexus needs PHP 8.2 or newer. Select the host's PHP 8.2+ CLI.\n");
    exit(1);
}

$root = dirname(__DIR__);
$source = $root . '/public';
$target = $argv[1] ?? '/home/pribadisch/public_html/nexus';

try {
    if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
        throw new RuntimeException('Cannot create the website directory.');
    }
    $target = realpath($target);
    if ($target === false) {
        throw new RuntimeException('Cannot resolve the website directory.');
    }
    $normalized = static fn (string $path): string => strtolower(str_replace('\\', '/', rtrim($path, '/\\')));
    $targetPath = $normalized($target);
    $sourcePath = $normalized($source);
    if ($targetPath === $sourcePath || str_starts_with($sourcePath, $targetPath . '/')
        || str_starts_with($targetPath, $sourcePath . '/')) {
        throw new RuntimeException('Choose a separate website directory, outside the source public folder.');
    }

    $copy = static function (string $from, string $to) use (&$copy): void {
        if (is_link($from) || is_link($to)) {
            throw new RuntimeException('Refusing to publish through a symbolic link: ' . $to);
        }
        if (is_dir($from)) {
            if (!is_dir($to) && !mkdir($to, 0755, true) && !is_dir($to)) {
                throw new RuntimeException('Cannot create asset directory: ' . $to);
            }
            foreach (new DirectoryIterator($from) as $entry) {
                if (!$entry->isDot()) {
                    $copy($entry->getPathname(), $to . '/' . $entry->getFilename());
                }
            }
        } elseif (!copy($from, $to)) {
            throw new RuntimeException('Cannot copy public asset: ' . $to);
        }
    };

    // Explicit allowlist: never copy the repository, database, .env or media.
    foreach (['assets', 'favicon.ico', 'manifest.webmanifest'] as $entry) {
        $copy($source . '/' . $entry, $target . '/' . $entry);
    }

    $write = static function (string $path, string $content): void {
        if (is_link($path) || file_put_contents($path, $content, LOCK_EX) === false) {
            throw new RuntimeException('Cannot write website file: ' . $path);
        }
    };
    $htaccessPath = $target . '/.htaccess';
    if (is_link($htaccessPath)) {
        throw new RuntimeException('The website .htaccess must not be a symbolic link.');
    }
    $existing = is_file($htaccessPath) ? file_get_contents($htaccessPath) : '';
    if ($existing === false) {
        throw new RuntimeException('Cannot read the existing website .htaccess.');
    }
    $start = '# BEGIN NEXUS PUBLIC';
    $end = '# END NEXUS PUBLIC';
    $existing = preg_replace('/^# BEGIN NEXUS PUBLIC\R.*?^# END NEXUS PUBLIC\R?/ms', '', $existing);
    $existing = preg_replace('/^# BEGIN NEXUS UPLOADS\R.*?^# END NEXUS UPLOADS\R?/ms', '', $existing ?? '');
    $rules = file_get_contents($source . '/.htaccess');
    if ($existing === null || $rules === false) {
        throw new RuntimeException('Cannot prepare website rewrite rules.');
    }
    // Upload settings come last so an older host-managed 64M setting cannot win.
    preg_match('/^# BEGIN NEXUS UPLOADS\R.*?^# END NEXUS UPLOADS\R?/ms', $rules, $uploads);
    $rules = preg_replace('/^# BEGIN NEXUS UPLOADS\R.*?^# END NEXUS UPLOADS\R?/ms', '', $rules);
    if ($rules === null || $uploads === []) throw new RuntimeException('Cannot prepare upload settings.');
    // Keep the host's PHP handler, other settings and ACME challenge directory.
    $write($htaccessPath, $start . "\n" . rtrim($rules) . "\n" . $end . "\n" . trim($existing) . "\n\n" . $uploads[0]);

    $userIniPath = $target . '/.user.ini';
    if (is_link($userIniPath)) throw new RuntimeException('The website .user.ini must not be a symbolic link.');
    $userIni = is_file($userIniPath) ? file_get_contents($userIniPath) : '';
    $uploadIni = file_get_contents($source . '/.user.ini');
    if ($userIni === false || $uploadIni === false) throw new RuntimeException('Cannot read PHP upload settings.');
    $userIni = preg_replace('/^; BEGIN NEXUS UPLOADS\R.*?^; END NEXUS UPLOADS\R?/ms', '', $userIni);
    if ($userIni === null) throw new RuntimeException('Cannot prepare PHP upload settings.');
    $write($userIniPath, rtrim($userIni) . "\n\n; BEGIN NEXUS UPLOADS\n" . rtrim($uploadIni) . "\n; END NEXUS UPLOADS\n");
    $write($target . '/index.php', "<?php\ndeclare(strict_types=1);\n\n// Application code stays outside public_html.\nrequire " . var_export($source . '/index.php', true) . ";\n");

    echo "Public website files published to: $target\n";
    echo "Application, .env, database exports and uploaded videos remain in: $root\n";
    echo "Keep the domain's document root set to the public website directory above.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Publish failed: ' . $error->getMessage() . "\n");
    exit(1);
}

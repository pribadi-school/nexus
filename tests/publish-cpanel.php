<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

$target = BASE_PATH . '/.dev/publish-upload-check-' . bin2hex(random_bytes(6));
mkdir($target, 0755, true);
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('Failed: ' . $message);
    $checks++;
};
try {
    $handler = '<FilesMatch "\\.php$">' . "\n  SetHandler application/x-httpd-alt-php82___lsphp\n</FilesMatch>\n";
    file_put_contents($target . '/.htaccess', $handler . "<IfModule LiteSpeed>\n  php_value upload_max_filesize 64M\n</IfModule>\n");
    file_put_contents($target . '/.user.ini', "date.timezone=Asia/Jakarta\nupload_max_filesize=64M\n");
    $previous = null;
    for ($run = 0; $run < 2; $run++) {
        $process = proc_open([PHP_BINARY, BASE_PATH . '/bin/publish-cpanel.php', $target],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, BASE_PATH, null, ['bypass_shell' => true]);
        if (!is_resource($process)) throw new RuntimeException('Could not start the publisher.');
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
        $check(proc_close($process) === 0, 'publisher succeeds: ' . $error);
        $htaccess = file_get_contents($target . '/.htaccess');
        $userIni = file_get_contents($target . '/.user.ini');
        $settings = parse_ini_file($target . '/.user.ini');
        $check(str_contains($htaccess, $handler), 'existing PHP handler is preserved');
        $check(strpos($htaccess, 'php_value upload_max_filesize 500M') > strpos($htaccess, 'php_value upload_max_filesize 64M'), 'managed upload limit overrides the earlier 64M cap');
        $check(substr_count($htaccess, '# BEGIN NEXUS UPLOADS') === 1, 'managed htaccess upload settings occur once');
        $check($settings['upload_max_filesize'] === '500M' && $settings['post_max_size'] === '512M', 'CGI upload settings allow 500 MB plus form overhead');
        $check($settings['date.timezone'] === 'Asia/Jakarta', 'unrelated PHP settings are preserved');
        $check($settings['display_errors'] === '' && $settings['log_errors'] === '1', 'startup warnings are logged without corrupting JSON');
        $check(str_contains($htaccess, '<Files ".user.ini">'), 'PHP configuration is denied over HTTP');
        if ($previous !== null) $check($previous === [$htaccess, $userIni], 'repeated deployment keeps configuration stable');
        $previous = [$htaccess, $userIni];
    }
} finally {
    // Delete only the unique test directory created above, without following links.
    $remove = static function (string $path) use (&$remove): void {
        if (!is_dir($path) || is_link($path)) { unlink($path); return; }
        foreach (new DirectoryIterator($path) as $entry) if (!$entry->isDot()) $remove($entry->getPathname());
        rmdir($path);
    };
    if (str_starts_with($target, BASE_PATH . '/.dev/publish-upload-check-')) $remove($target);
}
echo "Passed $checks cPanel publishing checks. Temporary website removed.\n";

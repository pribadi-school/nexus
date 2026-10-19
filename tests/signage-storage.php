<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Modules\Signage\SignageStorage;
use App\Modules\Signage\SignageValidation;
$checks = 0; $files = [];
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('Failed: ' . $message);
    $checks++;
};
$check(SignageStorage::maxUploadBytes() <= 250 * 1048576, 'upload enforcement is capped at 250 MB even with an older environment setting');
try {
    foreach (['0.5' => 30, '2.5' => 150, '0.083333' => 5, '1440' => 86400] as $minutes => $seconds) {
        [$data, $errors] = SignageValidation::playlistItem(['duration_minutes' => (string) $minutes], false);
        $check($errors === [] && (int) $data['duration_seconds'] === $seconds, 'decimal minutes converted to seconds');
    }
    foreach (['0', '-1', '0.01', '1440.1', 'NaN', ['bad']] as $minutes) {
        [, $errors] = SignageValidation::playlistItem(['duration_minutes' => $minutes], false);
        $check(isset($errors['duration_seconds']), 'invalid minute durations rejected');
    }
    $png = BASE_PATH . '/public/assets/brand/app-icon-192.png';
    $check(SignageStorage::inspect($png, 'poster.PNG', 'image')['mime_type'] === 'image/png', 'PNG bytes accepted');
    $jpeg = tempnam(sys_get_temp_dir(), 'nexus-jpg-'); $files[] = $jpeg;
    file_put_contents($jpeg, base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAMCAgMCAgMDAwMEAwMEBQgFBQQEBQoHBwYIDAoMDAsKCwsNDhIQDQ4RDgsLEBYQERMUFRUVDA8XGBYUGBIUFRT/2wBDAQMEBAUEBQkFBQkUDQsNFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBT/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD8qqKKKAP/2Q=='));
    foreach (['poster.jpg', 'poster.JPEG'] as $name) {
        $check(SignageStorage::inspect($jpeg, $name, 'image') === ['extension' => 'jpg', 'mime_type' => 'image/jpeg'], 'JPG and JPEG accepted');
    }
    foreach ([['poster.jpg', 'image'], ['poster.svg', 'image'], ['poster.mp4', 'video']] as [$name, $type]) {
        try { SignageStorage::inspect($png, $name, $type); throw new LogicException('Mismatched media accepted'); }
        catch (RuntimeException $error) { $check(!($error instanceof LogicException), 'extension, type and bytes agree'); }
    }
    $box = static fn (string $type, string $body): string => pack('N', strlen($body) + 8) . $type . $body;
    foreach (['mp4' => 'isom', 'mov' => 'qt  '] as $ext => $brand) {
        $fixture = tempnam(sys_get_temp_dir(), 'nexus-media-'); $files[] = $fixture;
        file_put_contents($fixture, $box('ftyp', $brand . pack('N', 0) . $brand) . $box('moov', 'fixture') . $box('mdat', 'fixture'));
        $expectedMime = $ext === 'mov' ? 'video/quicktime' : 'video/mp4';
        $check(SignageStorage::inspect($fixture, 'sample.' . $ext, 'video')['mime_type'] === $expectedMime, strtoupper($ext) . ' container accepted');
        file_put_contents($fixture, $box('ftyp', $brand . pack('N', 0) . $brand) . $box('moov', 'fixture'));
        try { SignageStorage::inspect($fixture, 'sample.' . $ext, 'video'); throw new LogicException('Incomplete video accepted'); }
        catch (RuntimeException $error) { $check(!($error instanceof LogicException), 'incomplete container rejected'); }
    }
    $form = partial('Signage::content-form', ['item' => null, 'values' => ['type' => 'image'], 'errors' => [], 'uploadLimitBytes' => 500000]);
    $check(str_contains($form, '>Video</option>') && str_contains($form, '>Image</option>') && str_contains($form, '.mov'), 'content choices render');
    $row = partial('Signage::_editor-item', ['entry' => ['id' => 1, 'item_id' => 1, 'type' => 'image', 'name' => 'Poster', 'stored_filename' => str_repeat('a', 48) . '.png', 'duration_seconds' => 150], 'saved' => true, 'position' => 1, 'base' => '/signage/screens/1']);
    $check(str_contains($row, 'value="2.5"') && str_contains($row, '<img'), 'image playlist renders minutes and thumbnail');
    $row = partial('Signage::_editor-item', ['entry' => ['id' => 1, 'item_id' => 1, 'type' => 'video', 'name' => 'Clip', 'stored_filename' => str_repeat('a', 48) . '.mov', 'duration_seconds' => 120], 'saved' => true, 'position' => 1, 'base' => '/signage/screens/1']);
    $check(!str_contains($row, 'data-item-duration') && str_contains($row, 'data-video-minutes'), 'video playlist uses its file duration without an editable field');
} finally { foreach ($files as $file) if (is_file($file)) unlink($file); }
echo "Passed $checks upload storage checks. No database access required.\n";

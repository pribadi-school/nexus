<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Request;
use App\Modules\Signage\SignageController;

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('Failed: ' . $message);
    $checks++;
};
$controller = new SignageController();
foreach ([
    [['type' => 'unknown'], 'type'],
    [['name' => 'Poster', 'type' => 'image'], 'file'],
    [['name' => 'Clip', 'type' => 'video'], 'file'],
    [['name' => 'Website', 'type' => 'website', 'source_url' => 'javascript:alert(1)'], 'source_url'],
] as [$body, $field]) {
    $response = $controller->storeContent(new Request('POST', '/signage/content', [], $body, ['HTTP_X_REQUESTED_WITH' => 'fetch']));
    $data = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
    $check($response->status === 422 && isset($data['errors'][$field]) && !isset($response->headers['Location']), 'popup errors stay in JSON without redirecting');
}
$html = partial('Signage::_content-dialog', ['uploadLimitBytes' => 500000]);
$check(str_contains($html, '<dialog') && str_contains($html, 'aria-labelledby="content-dialog-title"'), 'popup is embedded and labelled');
$check(!str_contains($html, '<h1') && !str_contains($html, 'back-link'), 'popup excludes the full page header');
preg_match_all('/\bid="([^"]+)"/', $html, $ids);
$check(count($ids[1]) === count(array_unique($ids[1])) && in_array('content-dialog-type', $ids[1]), 'popup fields have unique prefixed IDs');
$check(!str_contains($html, 'name="name"'), 'popup has no separate content name input');
$inputMethod = new ReflectionMethod(SignageController::class, 'contentInput');
$input = $inputMethod->invoke($controller, new Request('POST', '/', [], ['type' => 'video', 'name' => 'Ignore manual name'], [], [
    'file' => ['name' => 'C:\\fakepath\\School event.mov', 'error' => UPLOAD_ERR_OK],
]));
$check($input['name'] === 'School event.mov', 'content uses the filename including extension');
$input = $inputMethod->invoke($controller, new Request('POST', '/', [], ['name' => 'Ignore manual name'], []), ['type' => 'image', 'original_filename' => 'Poster.png']);
$check($input['name'] === 'Poster.png', 'editing without a replacement keeps the original filename');
$input = $inputMethod->invoke($controller, new Request('POST', '/', [], ['type' => 'website', 'source_url' => 'https://example.com/display'], []));
$check($input['name'] === 'https://example.com/display', 'website names follow their address');
$response = $controller->storeContent(new Request('POST', '/signage/content', [], ['name' => '', 'type' => 'video'], []));
$check($response->status === 422 && str_contains($response->body, '<!doctype html>'), 'ordinary form submissions still render a usable page');
echo "Passed $checks content popup checks. No database access required.\n";

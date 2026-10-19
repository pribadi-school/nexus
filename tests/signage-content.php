<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Request;
use App\Core\Response;
use App\Modules\Signage\SignageController;
use App\Modules\Signage\SignageValidation;

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
$headersMethod = new ReflectionMethod(Response::class, 'securityHeaders');
$headers = $headersMethod->invoke(new Response('photo', 200, ['Cache-Control' => 'private, max-age=31536000, immutable']));
$check($headers['Cache-Control'] === 'private, max-age=31536000, immutable', 'versioned private photos retain their caching header');
$check($headersMethod->invoke(Response::html(''))['Cache-Control'] === 'no-store', 'ordinary pages retain no-store');
$check(SignageValidation::screenTargets(['2', '1', '2']) === [2, 1], 'multiple TV selections are deduplicated');
foreach ([null, [], '1', ['0'], ['-1'], [['1']], ['4294967296'], array_fill(0, 1001, '1')] as $targets) {
    try { SignageValidation::screenTargets($targets); throw new LogicException('Invalid TVs accepted'); }
    catch (InvalidArgumentException) { $check(true, 'invalid TV selections rejected'); }
}
$response = $controller->addContentToScreens(new Request('POST', '/', [], [], ['HTTP_X_REQUESTED_WITH' => 'fetch']));
$check($response->status === 422 && str_contains($response->body, 'Select at least one TV'), 'empty selection stays in the menu without a redirect');
$card = partial('Signage::_content-card', [
    'entry' => ['id' => 1, 'name' => 'Poster.png', 'type' => 'image', 'stored_filename' => str_repeat('a', 48) . '.png', 'size_bytes' => 10, 'used_count' => 1, 'updated_at' => gmdate('Y-m-d H:i:s')],
    'screens' => [['id' => 1, 'name' => 'First TV'], ['id' => 2, 'name' => 'Second TV']], 'assignedScreens' => [1],
]);
$check(str_contains($card, 'name="screen_ids[]" value="1" checked disabled') && str_contains($card, 'name="screen_ids[]" value="2"'), 'existing TVs are checked and new TVs selectable');
$check(str_contains($card, 'data-content-delete data-confirm=') && str_contains($card, 'Add to selected TVs'), 'menu exposes batch add and confirmed deletion');
echo "Passed $checks content popup checks. No database access required.\n";

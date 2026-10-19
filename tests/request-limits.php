<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Modules\Signage\SignageStorage;

final class UploadLimitProbe
{
    public static int $calls = 0;
    public function receive(Request $request): Response { self::$calls++; return Response::json(['ok' => true]); }
}
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('Failed: ' . $message);
    $checks++;
};
$router = new Router();
$router->post('/upload', [UploadLimitProbe::class, 'receive'], public: true);
$limit = ini_parse_quantity((string) ini_get('post_max_size'));
$token = Csrf::token();
$make = static fn (string $length, array $body = []): Request => new Request('POST', '/upload', [], $body, ['CONTENT_LENGTH' => $length]);
$normal = $router->dispatch($make('100', ['_csrf' => $token]));
$check($normal->status === 200 && UploadLimitProbe::$calls === 1, 'ordinary valid uploads reach the handler');
if ($limit > 0) {
    $check(!$make((string) $limit)->exceedsPostLimit(), 'exact request limit is allowed');
    foreach ([[], ['_csrf' => $token], ['_csrf' => 'wrong']] as $body) {
        try { $router->dispatch($make((string) ($limit + 1), $body)); throw new LogicException('Oversized body accepted'); }
        catch (HttpException $error) { $check($error->status === 413 && !str_contains($error->getMessage(), 'expired'), 'discarded form reports size, not expiry'); }
    }
    $check(UploadLimitProbe::$calls === 1, 'rejected uploads never reach mutation handlers');
    $check(SignageStorage::maxUploadBytes() <= max(1, $limit - 1048576), 'displayed file limit reserves multipart overhead');
} else {
    $check(!$make('9999999999')->exceedsPostLimit(), 'unlimited request configuration is respected');
}
foreach ([[], ['_csrf' => 'wrong'], ['_csrf' => ['malformed']]] as $body) {
    try { $router->dispatch($make('100', $body)); throw new LogicException('Invalid CSRF accepted'); }
    catch (HttpException $error) { $check($error->status === 419, 'normal-size requests still require a valid CSRF token'); }
}
echo "Passed $checks request-limit and CSRF checks. No database or uploaded files used.\n";

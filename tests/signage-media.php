<?php
declare(strict_types=1);

// Local-only integration checks; fixture files and database changes are cleaned up.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Request;
use App\Core\HttpException;
use App\Modules\Signage\SignageController;
use App\Modules\Signage\SignagePlayerController;
use App\Modules\Signage\SignageRepository;
use App\Modules\Signage\SignageStorage;
use App\Modules\Signage\SignageValidation;

if (config('app.env') !== 'local') { fwrite(STDERR, "Local database only.\n"); exit(1); }
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('Failed: ' . $message);
    $checks++;
};
$files = [];
$failure = null;
$pdo = db()->pdo();
$pdo->beginTransaction();
try {
    $png = BASE_PATH . '/public/assets/brand/app-icon-192.png';
    [, $errors] = SignageValidation::content(['name' => 'Poster', 'type' => 'image']);
    $check($errors === [], 'image content validates');
    $repo = new SignageRepository();
    $filename = bin2hex(random_bytes(24)) . '.png';
    $path = SignageStorage::path($filename); $files[] = $path;
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0770, true);
    copy($png, $path);
    $image = $repo->createContent(['name' => 'Image regression', 'type' => 'image', 'source_url' => null,
        'stored_filename' => $filename, 'original_filename' => 'poster.png', 'mime_type' => 'image/png', 'size_bytes' => filesize($path)]);
    $screenId = $repo->createScreenWithPlaylist(['name' => 'Media test screen', 'orientation' => 'landscape', 'fit' => 'contain', 'muted' => 1, 'enabled' => 1]);
    $screen = $repo->screen($screenId); $playlist = (int) $screen['playlist_id'];
    $repo->addPlaylistItem($playlist, $image, 120);
    $items = $repo->playlistItems($playlist);
    $check((int) $items[0]['duration_seconds'] === 120, 'image duration retained on add');
    $repo->savePlaylistItems($playlist, [['item_id' => (int) $items[0]['item_id'], 'content_id' => $image, 'duration_seconds' => 150]], $repo->playlistRevision($playlist));
    $check((int) $repo->playlistItems($playlist)[0]['duration_seconds'] === 150, 'image duration retained on draft save');
    $controller = new SignagePlayerController();
    $request = new Request('HEAD', '/', [], [], []); $request->params = ['token' => $screen['token'], 'id' => (string) $image];
    $manifest = json_decode($controller->manifest($request)->body, true, 512, JSON_THROW_ON_ERROR);
    $check($manifest['items'][0]['type'] === 'image' && $manifest['items'][0]['size_bytes'] === filesize($path)
        && $manifest['items'][0]['duration_seconds'] === 150 && str_contains($manifest['items'][0]['url'], '/media/'), 'image manifest supports timed offline downloads');
    $response = $controller->media($request);
    $check($response->status === 200 && $response->headers['Content-Type'] === 'image/png', 'token-authorized image response');
    $range = new Request('GET', '/', [], [], ['HTTP_RANGE' => 'bytes=0-9']); $range->params = $request->params;
    $response = $controller->media($range);
    $check($response->status === 206 && $response->headers['Content-Length'] === '10', 'media range response preserved');
    $range->params['id'] = (string) ($image + 100000);
    try { $controller->media($range); throw new LogicException('Unassigned media accepted'); }
    catch (HttpException $error) { $check($error->status === 404, 'unassigned media blocked'); }
    $request->params['id'] = (string) $screenId;
    $html = (new SignageController())->editScreen($request)->body;
    $check(str_contains($html, 'Duration (minutes)') && str_contains($html, 'value="2.5"') && str_contains($html, 'data-playlist-duration'), 'screen renders editable minutes and playlist total');
    $html = (new SignageController())->createContent(new Request('GET', '/', [], [], []))->body;
    $check(str_contains($html, '>Video</option>') && str_contains($html, '>Image</option>') && str_contains($html, '.mov'), 'upload form offers Video and Image');
    $response = (new SignageController())->storeContent(new Request('POST', '/signage/content', [], [
        'name' => 'Popup regression', 'type' => 'website', 'source_url' => 'https://example.com', 'screen_id' => (string) $screenId,
    ], ['HTTP_X_REQUESTED_WITH' => 'fetch']));
    $data = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
    $check($response->status === 201 && str_contains($data['card_html'], 'https://example.com') && str_contains($data['editor_html'], 'data-library-add'), 'popup creation returns a library card and screen item without redirecting');
} catch (Throwable $error) { $failure = $error; }
finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    foreach ($files as $file) if (is_file($file)) unlink($file);
}
if ($failure !== null) { fwrite(STDERR, $failure->getMessage() . "\n"); exit(1); }
echo "Passed $checks media checks. All test rows and files cleaned up.\n";

<?php

declare(strict_types=1);

// Run with PHP CLI and the local development INI. All test rows are rolled back.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Signage\SignagePlayerController;
use App\Modules\Signage\SignageRepository;
use App\Modules\Signage\SignageStorage;
use App\Modules\Signage\SignageValidation;

if (config('app.env') !== 'local') {
    fwrite(STDERR, "Refusing to run: APP_ENV must be local.\n");
    exit(1);
}
if (db()->value("SELECT 1 FROM schema_migrations WHERE version = '006_signage'") === null) {
    fwrite(STDERR, "Apply migration 006_signage to the local database first.\n");
    exit(1);
}

$checks = 0;
$check = static function (bool $condition, string $description) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('Failed: ' . $description);
    }
    $checks++;
};
$notFound = static function (callable $action, string $description) use ($check): void {
    try {
        $action();
    } catch (HttpException $error) {
        $check($error->status === 404, $description . ' returns 404');
        return;
    }
    throw new RuntimeException('Failed: ' . $description . ' was accepted');
};
$request = static function (string $token, string $method = 'GET', array $query = []): Request {
    $request = new Request($method, '/signage/player/' . $token . '/manifest', $query, [], []);
    $request->params = ['token' => $token];
    return $request;
};
$manifest = static function (Request $request): array {
    return json_decode((new SignagePlayerController())->manifest($request)->body, true, 512, JSON_THROW_ON_ERROR);
};

$tables = ['signage_content', 'signage_playlists', 'signage_playlist_items', 'signage_screens'];
$before = [];
foreach ($tables as $table) {
    $before[$table] = (int) db()->value("SELECT COUNT(*) FROM $table");
}

$pdo = db()->pdo();
$pdo->beginTransaction();
$failure = null;
try {
    foreach ([
        'https://example.com/display?x=1' => true,
        'http://192.168.1.10/display' => true,
        'javascript:alert(1)' => false,
        'data:text/html,example' => false,
        '//example.com/display' => false,
        'ftp://example.com/video.mp4' => false,
        'https://user:password@example.com' => false,
        "https://example.com/\r\nX-Header: value" => false,
    ] as $address => $valid) {
        $check(SignageValidation::websiteUrl($address) === $valid, 'website URL scheme and structure validation');
    }
    [, $errors] = SignageValidation::playlistItem(['duration_seconds' => '4'], false);
    $check(isset($errors['duration_seconds']), 'website duration lower bound');
    [, $errors] = SignageValidation::playlistItem(['duration_seconds' => '86401'], false);
    $check(isset($errors['duration_seconds']), 'website duration upper bound');
    [, $errors] = SignageValidation::screen(['name' => ['bad'], 'orientation' => 'diagonal', 'fit' => 'contain', 'muted' => ['1']]);
    $check(isset($errors['name'], $errors['orientation'], $errors['muted']), 'malformed form values rejected');
    try {
        SignageStorage::path('../outside.mp4');
        throw new LogicException('Unsafe media path was accepted.');
    } catch (RuntimeException $error) {
        $check(!($error instanceof LogicException), 'stored filename cannot traverse directories');
    }

    $repository = new SignageRepository();
    $first = $repository->createContent(['name' => 'Regression website A', 'type' => 'website', 'source_url' => 'https://example.com/a']);
    $second = $repository->createContent(['name' => 'Regression website B', 'type' => 'website', 'source_url' => 'https://example.com/b']);
    $video = $repository->createContent([
        'name' => 'Regression video', 'type' => 'video', 'source_url' => null,
        'stored_filename' => str_repeat('a', 48) . '.mp4', 'original_filename' => 'test.mp4',
        'mime_type' => 'video/mp4', 'size_bytes' => 100,
    ]);
    $playlist = $repository->createPlaylist(['name' => 'Regression playlist A', 'description' => null]);
    $other = $repository->createPlaylist(['name' => 'Regression playlist B', 'description' => null]);
    $repository->addPlaylistItem($playlist, $first, 15);
    $repository->addPlaylistItem($playlist, $second, 25);
    $repository->addPlaylistItem($playlist, $video, 40);
    $items = $repository->playlistItems($playlist);
    $itemIds = array_map('intval', array_column($items, 'item_id'));
    $check(array_map('intval', array_column($items, 'id')) === [$first, $second, $video], 'playlist append order');
    $check((int) $items[2]['duration_seconds'] === 30, 'MP4 duration is independent of website timer');
    $repository->movePlaylistItem($playlist, $itemIds[2], 'up');
    $check(array_map('intval', array_column($repository->playlistItems($playlist), 'id')) === [$first, $video, $second], 'playlist moves preserve content order');
    $repository->updatePlaylistItem($playlist, $itemIds[0], 45);
    $check((int) $repository->playlistItems($playlist)[0]['duration_seconds'] === 45, 'website duration update');
    $notFound(fn () => $repository->movePlaylistItem($other, $itemIds[0], 'down'), 'foreign playlist item move');
    $notFound(fn () => $repository->updatePlaylistItem($other, $itemIds[0], 90), 'foreign playlist item duration update');
    $notFound(fn () => $repository->deletePlaylistItem($other, $itemIds[0]), 'foreign playlist item removal');
    try {
        $repository->deleteContent($first);
        throw new LogicException('Used content deletion was accepted.');
    } catch (InvalidArgumentException) {
        $check($repository->contentItem($first) !== null, 'used content is preserved');
    }

    $screenData = [
        'name' => 'Regression screen', 'location' => 'Test location', 'playlist_id' => $playlist,
        'orientation' => 'landscape', 'fit' => 'contain', 'muted' => 1, 'enabled' => 1,
    ];
    $screenId = $repository->createScreen($screenData);
    $token = $repository->screen($screenId)['token'];
    $check(strlen($token) === 64 && $repository->screenByToken($token) !== null, 'screen token creation');
    $initial = $manifest($request($token, 'HEAD'));
    $check(count($initial['items']) === 3 && $initial['screen']['enabled'], 'public manifest has assigned playlist');
    $check($repository->screen($screenId)['last_seen_at'] === null, 'HEAD request does not report screen heartbeat');
    $manifest($request($token, 'GET', ['item' => (string) $itemIds[0], 'error' => 'Test error']));
    $screen = $repository->screen($screenId);
    $check($screen['last_seen_at'] !== null && (int) $screen['last_content_id'] === $first && $screen['last_error'] === 'Test error', 'heartbeat records only valid assigned item');
    $manifest($request($token, 'GET', ['item' => '4294967295']));
    $check($repository->screen($screenId)['last_content_id'] === null, 'unknown heartbeat item is ignored');

    $repository->updateContent($video, ['stored_filename' => str_repeat('b', 48) . '.mp4']);
    $replaced = $manifest($request($token, 'HEAD'));
    $check($replaced['revision'] !== $initial['revision'] && $replaced['items'][1]['url'] !== $initial['items'][1]['url'], 'video replacement changes manifest revision and media URL');
    $unassignedMedia = $request($token);
    $unassignedMedia->params['id'] = (string) $first;
    $notFound(fn () => (new SignagePlayerController())->media($unassignedMedia), 'website cannot use video media route');

    $screenData['enabled'] = 0;
    $repository->updateScreen($screenId, $screenData);
    $paused = $manifest($request($token, 'HEAD'));
    $check(!$paused['screen']['enabled'] && $paused['items'] === [], 'paused screen does not receive playable content');
    $disabledMedia = $request($token);
    $disabledMedia->params['id'] = (string) $video;
    $notFound(fn () => (new SignagePlayerController())->media($disabledMedia), 'paused screen media access');
    $repository->rotateScreenToken($screenId);
    $check($repository->screenByToken($token) === null, 'old screen token revoked');
    $notFound(fn () => $manifest($request($token)), 'revoked screen manifest');
    $newToken = $repository->screen($screenId)['token'];
    $check($newToken !== $token && $repository->screenByToken($newToken) !== null, 'replacement screen token works');

    $repository->deletePlaylistItem($playlist, $itemIds[0]);
    $remaining = $repository->playlistItems($playlist);
    $check(array_map('intval', array_column($remaining, 'sort_order')) === [0, 1], 'removal keeps playlist positions contiguous');
    $repository->deleteContent($first);
    $check($repository->contentItem($first) === null, 'unused content deletion');
    $repository->deletePlaylist($playlist);
    $check($repository->screen($screenId)['playlist_id'] === null && $repository->playlistItems($playlist) === [], 'playlist deletion unassigns screen and removes playlist items');

    $headersMethod = new ReflectionMethod(Response::class, 'securityHeaders');
    $adminHeaders = $headersMethod->invoke(Response::html(''));
    $playerHeaders = $headersMethod->invoke(Response::playerHtml(''));
    $check($adminHeaders['X-Frame-Options'] === 'DENY' && str_contains($adminHeaders['Content-Security-Policy'], "frame-ancestors 'none'"), 'admin pages retain framing protection');
    $check(!isset($playerHeaders['X-Frame-Options']) && str_contains($playerHeaders['Content-Security-Policy'], 'frame-src https: http:') && $playerHeaders['Referrer-Policy'] === 'no-referrer', 'player alone supports framing and hides its token in referrers');
} catch (Throwable $error) {
    $failure = $error;
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

foreach ($tables as $table) {
    $after = (int) db()->value("SELECT COUNT(*) FROM $table");
    if ($after !== $before[$table]) {
        fwrite(STDERR, "Failed: test rows were not rolled back in $table.\n");
        exit(1);
    }
}
if ($failure !== null) {
    fwrite(STDERR, $failure->getMessage() . "\n");
    exit(1);
}
echo "Passed $checks signage regression checks. All test rows rolled back; no media files created.\n";

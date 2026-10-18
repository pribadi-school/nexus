<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\HttpException;
use App\Core\Request;
use App\Modules\Signage\SignageController;
use App\Modules\Signage\SignageRepository;
use App\Modules\Signage\SignageValidation;

if (config('app.env') !== 'local') { fwrite(STDERR, "Local database only.\n"); exit(1); }
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('Failed: ' . $message);
    $checks++;
};
$failure = null;
$tables = ['signage_content', 'signage_screens', 'signage_playlists', 'signage_playlist_items'];
$before = [];
foreach ($tables as $table) $before[$table] = db()->value("SELECT COUNT(*) FROM $table");
$pdo = db()->pdo();
$pdo->beginTransaction();
try {
    $repo = new SignageRepository();
    $controller = new SignageController();
    $screenData = ['name' => 'Editor regression TV', 'location' => null, 'orientation' => 'landscape', 'fit' => 'contain', 'muted' => 1, 'enabled' => 1];
    $website = $repo->createContent(['name' => 'Editor website', 'type' => 'website', 'source_url' => 'https://example.com']);
    $video = $repo->createContent(['name' => 'Editor video', 'type' => 'video', 'stored_filename' => str_repeat('c', 48) . '.mp4', 'original_filename' => 'test.mp4', 'mime_type' => 'video/mp4', 'size_bytes' => 100, 'source_url' => null]);
    $old = $repo->createPlaylist(['name' => 'Legacy shared', 'description' => null]);
    $repo->addPlaylistItem($old, $website, 17);
    $repo->addPlaylistItem($old, $video, 30);
    $oldItems = $repo->playlistItems($old);
    $first = $repo->createScreen($screenData + ['playlist_id' => $old]);
    $second = $repo->createScreen(array_replace($screenData, ['name' => 'Other TV', 'playlist_id' => $old]));
    $token = $repo->screen($first)['token'];
    $private = $repo->screenPlaylistId($first);
    $check($private !== $old && (int) $repo->screen($second)['playlist_id'] === $old, 'legacy shared playlist split');
    $copy = $repo->playlistItems($private);
    $check(array_column($copy, 'id') === array_column($oldItems, 'id') && array_column($copy, 'duration_seconds') === array_column($oldItems, 'duration_seconds'), 'legacy content, ordering and website timing retained');
    $check($repo->screenPlaylistId($first) === $private, 'private playlist initialization idempotent');
    $check($repo->screen($first)['token'] === $token, 'existing TV token retained');
    $fresh = $repo->createScreenWithPlaylist($screenData + ['playlist_id' => $old]);
    $freshId = $repo->screenPlaylistId($fresh);
    $check($freshId !== $old && $freshId !== $private && $repo->playlistItems($freshId) === [], 'new screen gets own empty playlist even with submitted assignment');
    [$validated] = SignageValidation::screen($screenData + ['playlist_id' => (string) $old]);
    $check(!array_key_exists('playlist_id', $validated), 'form cannot reassign another screen playlist');
    $blank = $repo->createScreen($screenData + ['playlist_id' => null]);
    $check($repo->screenPlaylistId($blank) > 0, 'older unassigned screen initialized');

    $revision = $repo->playlistRevision($private);
    $draft = [
        ['item_id' => (int) $copy[1]['item_id'], 'content_id' => $video, 'duration_seconds' => 30],
        ['item_id' => (int) $copy[0]['item_id'], 'content_id' => $website, 'duration_seconds' => 42],
        ['item_id' => null, 'content_id' => $website, 'duration_seconds' => 55],
    ];
    $repo->withScreenPlaylist($first, fn (int $id) => $repo->savePlaylistItems($id, $draft, $revision));
    $saved = $repo->playlistItems($private);
    $check(array_map('intval', array_column($saved, 'id')) === [$video, $website, $website], 'draft reorder and repeated content saved');
    $check((int) $saved[0]['item_id'] === (int) $copy[1]['item_id'] && (int) $saved[1]['item_id'] === (int) $copy[0]['item_id'], 'existing playlist item IDs retained');
    $check(array_map('intval', array_column($saved, 'duration_seconds')) === [30, 42, 55], 'website timing saved per occurrence');
    $check(array_column($repo->playlistItems($old), 'duration_seconds') === array_column($oldItems, 'duration_seconds'), 'other TV content unchanged');
    $reject = static function (array $entries, string $expectedRevision) use ($repo, $private, $check): void {
        $beforeRevision = $repo->playlistRevision($private);
        try { $repo->savePlaylistItems($private, $entries, $expectedRevision); throw new LogicException('Invalid draft accepted'); }
        catch (InvalidArgumentException | HttpException $error) {
            $check($repo->playlistRevision($private) === $beforeRevision, 'invalid draft leaves playlist unchanged');
        }
    };
    $reject($draft, $revision);
    $currentRevision = $repo->playlistRevision($private);
    $reject([['item_id' => (int) $oldItems[0]['item_id'], 'content_id' => $website, 'duration_seconds' => 30]], $currentRevision);
    $reject([$draft[0], $draft[0]], $currentRevision);
    $reject([['item_id' => null, 'content_id' => 4294967295, 'duration_seconds' => 30]], $currentRevision);
    $reject([['item_id' => null, 'content_id' => $website, 'duration_seconds' => 4]], $currentRevision);
    $reject([['item_id' => [], 'content_id' => $website, 'duration_seconds' => 30]], $currentRevision);
    $reject(array_fill(0, 1001, ['item_id' => null, 'content_id' => $website, 'duration_seconds' => 30]), $currentRevision);

    $request = new Request('POST', '/signage/screens/' . $fresh . '/items', [], ['content_id' => (string) $website, 'duration_seconds' => '30', 'from_library' => '1'], []);
    $request->params['id'] = (string) $fresh;
    $response = $controller->addPlaylistItem($request);
    $check($response->headers['Location'] === '/signage' && count($repo->playlistItems($freshId)) === 1, 'content menu adds directly to selected TV and returns to library');
    $request = new Request('POST', '/signage/screens/' . $first . '/playlist', [], ['playlist_items' => '[]', 'revision' => $currentRevision], ['HTTP_X_REQUESTED_WITH' => 'fetch']);
    $request->params['id'] = (string) $first;
    $response = $controller->saveScreenPlaylist($request);
    $check($response->status === 200 && $repo->playlistItems($private) === [], 'screen bulk endpoint can clear own playlist');
    $check(count($repo->playlistItems($old)) === 2 && count($repo->playlistItems($freshId)) === 1, 'bulk clear leaves other TVs unchanged');
    $request = new Request('GET', '/signage/screens/' . $second . '/edit', [], [], []);
    $request->params['id'] = (string) $second;
    $html = $controller->editScreen($request)->body;
    $check(str_contains($html, 'data-screen-editor') && str_contains($html, 'data-screen-library') && !str_contains($html, 'name="playlist_id"'), 'screen contains library and playlist without assignment picker');
    $html = $controller->content(new Request('GET', '/signage', [], [], []))->body;
    $check(str_contains($html, 'popovertarget="content-menu-') && str_contains($html, 'Add to TV playlist'), 'content kebab menu includes direct TV action');
    $tabs = partial('Signage::_tabs', ['tab' => 'screens']);
    $check(!str_contains($tabs, '/signage/playlists'), 'separate playlists tab removed');
    $repo->deleteScreen($fresh);
    $check($repo->playlist($freshId) === null && $repo->contentItem($website) !== null, 'deleting screen removes private playlist but keeps library content');
} catch (Throwable $error) { $failure = $error; }
finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
foreach ($tables as $table) {
    if (db()->value("SELECT COUNT(*) FROM $table") !== $before[$table]) { fwrite(STDERR, "Rollback failed.\n"); exit(1); }
}
if ($failure) { fwrite(STDERR, $failure->getMessage() . "\n"); exit(1); }
echo "Passed $checks screen playlist checks. All changes rolled back.\n";

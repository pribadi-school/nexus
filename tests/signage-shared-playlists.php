<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Request;
use App\Modules\Signage\SignageController;
use App\Modules\Signage\SignageRepository;

if (config('app.env') !== 'local') { fwrite(STDERR, "Local database only.\n"); exit(1); }
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('Failed: ' . $message);
    $checks++;
};
$throws = static function (callable $fn): bool {
    try { $fn(); return false; } catch (InvalidArgumentException | App\Core\HttpException) { return true; }
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
    $screenData = ['name' => 'Shared test A', 'location' => null, 'orientation' => 'landscape', 'fit' => 'contain', 'muted' => 1, 'enabled' => 1];
    $site = $repo->createContent(['name' => 'Shared site', 'type' => 'website', 'source_url' => 'https://example.com']);
    $site2 = $repo->createContent(['name' => 'Shared site two', 'type' => 'website', 'source_url' => 'https://example.org']);
    $a = $repo->createScreenWithPlaylist($screenData);
    $b = $repo->createScreenWithPlaylist(array_replace($screenData, ['name' => 'Shared test B']));
    $ownA = $repo->screenPlaylistId($a);
    $ownB = $repo->screenPlaylistId($b);
    $repo->addPlaylistItem($ownA, $site, 40);
    $ids = static fn (int $playlist): array => array_map('intval', array_column($repo->playlistItems($playlist), 'id'));

    // Share A's own playlist, then let B play it.
    $shared = $repo->shareScreenPlaylist($a, 'Morning test');
    $check($shared === $ownA && $repo->screenPlaylistId($a) === $shared, 'sharing keeps the TV on the same playlist, items intact');
    $check($ids($shared) === [$site], 'shared playlist keeps its items');
    $check($throws(fn () => $repo->shareScreenPlaylist($a, 'Another')), 'a TV already on a shared playlist cannot share it again');
    $check($throws(fn () => $repo->createSharedPlaylist('morning test')) || $throws(fn () => $repo->createSharedPlaylist('Morning test')), 'duplicate shared names are refused');
    $repo->assignScreenPlaylist($b, $shared);
    $check((int) $repo->screen($b)['playlist_id'] === $shared, 'TV B plays the shared playlist');
    $check($repo->playlist($ownB) === null, 'its abandoned private playlist is removed');
    $check($repo->screenPlaylistId($b) === $shared && $repo->screenPlaylistId($a) === $shared, 'editing a shared playlist is never split into a copy');

    // An edit from either TV changes the one list both play.
    $repo->withScreenPlaylist($b, fn (int $id) => $repo->addPlaylistItem($id, $site2, 30));
    $check($ids($shared) === [$site, $site2] && $ids($repo->screenPlaylistId($a)) === [$site, $site2], 'an edit from one TV reaches the other');
    $listed = $repo->sharedPlaylist($shared);
    $check(array_column($listed['screens'], 'id') === [$a, $b] && (int) $listed['item_count'] === 2, 'playlist lists the TVs using it');

    // Leaving a shared playlist keeps a private copy; the shared one is untouched.
    $repo->assignScreenPlaylist($b, null);
    $private = (int) $repo->screen($b)['playlist_id'];
    $check($private !== $shared && $ids($private) === [$site, $site2] && !(bool) $repo->playlist($private)['is_shared'], 'leaving a shared playlist keeps a private copy');
    $repo->withScreenPlaylist($b, fn (int $id) => $repo->addPlaylistItem($id, $site, 30));
    $check($ids($shared) === [$site, $site2], 'editing the private copy leaves the shared playlist alone');

    // Deleting rules.
    $check($throws(fn () => $repo->deleteSharedPlaylist($shared)), 'a playlist used by a TV cannot be deleted');
    $repo->deleteScreen($a);
    $check($repo->playlist($shared) !== null, 'deleting a TV never deletes the shared playlist');
    $repo->deleteSharedPlaylist($shared);
    $check($repo->playlist($shared) === null, 'an unused shared playlist can be deleted');
    $check($throws(fn () => $repo->deleteSharedPlaylist($private)), 'private playlists cannot be deleted through the shared route');

    // Copy, rename.
    $one = $repo->createSharedPlaylist('Lunch test');
    $repo->withSharedPlaylist($one, fn (int $id) => $repo->addPlaylistItem($id, $site, 20));
    $copy = $repo->duplicateSharedPlaylist($one, 'Lunch test copy');
    $check($ids($copy) === [$site] && (bool) $repo->playlist($copy)['is_shared'], 'copy has the same content and is shared');
    $repo->renameSharedPlaylist($copy, 'Dinner test');
    $check($repo->playlist($copy)['name'] === 'Dinner test' && $throws(fn () => $repo->renameSharedPlaylist($copy, 'Lunch test')), 'rename works and refuses a taken name');

    // Pages and endpoints.
    $page = $controller->playlists(new Request('GET', '/signage/playlists', [], [], []))->body;
    $check(str_contains($page, 'Lunch test') && str_contains($page, 'Dinner test') && str_contains($page, 'href="/signage/playlists"'), 'Playlists tab lists shared playlists');
    $edit = new Request('GET', '/signage/playlists/' . $one . '/edit', [], [], []); $edit->params['id'] = (string) $one;
    $html = $controller->editPlaylist($edit)->body;
    $check(str_contains($html, 'action="/signage/playlists/' . $one . '/playlist"') && str_contains($html, 'data-screen-editor') && str_contains($html, 'start this playlist.'), 'shared playlist page reuses the editor with its own addresses');
    $add = new Request('POST', '/signage/playlists/' . $one . '/items', [], ['content_id' => (string) $site2, 'duration_seconds' => '30'], []); $add->params['id'] = (string) $one;
    $controller->addPlaylistItem($add);
    $check($ids($one) === [$site, $site2], 'adding through the shared playlist route edits that playlist');
    $screenPage = new Request('GET', '/signage/screens/' . $b . '/edit', [], [], []); $screenPage->params['id'] = (string) $b;
    $html = $controller->editScreen($screenPage)->body;
    $check(str_contains($html, 'id="assign-playlist"') && str_contains($html, 'Lunch test') && str_contains($html, 'Save as shared playlist'), 'TV page offers the shared playlists and saving its own');
    $assign = new Request('POST', '/signage/screens/' . $b . '/assign-playlist', [], ['playlist_id' => (string) $one], []); $assign->params['id'] = (string) $b;
    $controller->assignScreenPlaylist($assign);
    $check((int) $repo->screen($b)['playlist_id'] === $one, 'assign endpoint points the TV at the playlist');
    $html = $controller->editScreen($screenPage)->body;
    $check(str_contains($html, 'Playing the shared playlist') && !str_contains($html, 'Save as shared playlist'), 'TV page explains it is on a shared playlist');
} catch (Throwable $error) { $failure = $error; }
finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
foreach ($tables as $table) {
    if (db()->value("SELECT COUNT(*) FROM $table") !== $before[$table]) { fwrite(STDERR, "Rollback failed.\n"); exit(1); }
}
if ($failure) { fwrite(STDERR, $failure->getMessage() . "\n" . $failure->getFile() . ':' . $failure->getLine() . "\n"); exit(1); }
echo "Passed $checks shared playlist checks. All changes rolled back.\n";

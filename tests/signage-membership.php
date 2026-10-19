<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Request;
use App\Modules\Signage\SignageController;
use App\Modules\Signage\SignageRepository;

if (config('app.env') !== 'local') { fwrite(STDERR, "Local database only.\n"); exit(1); }
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('Failed: ' . $label);
    $checks++;
};
$pdo = db()->pdo();
$pdo->beginTransaction();
try {
    $repo = new SignageRepository();
    $content = $repo->createContent(['name' => 'Membership test', 'type' => 'website', 'source_url' => 'https://example.com/content']);
    $other = $repo->createContent(['name' => 'Other content', 'type' => 'website', 'source_url' => 'https://example.com/other']);
    $data = ['name' => 'Membership TV', 'orientation' => 'landscape', 'fit' => 'contain', 'muted' => 1, 'enabled' => 1];
    $first = $repo->createScreenWithPlaylist($data);
    $second = $repo->createScreenWithPlaylist($data);
    $third = $repo->createScreenWithPlaylist($data);
    $p1 = $repo->screenPlaylistId($first); $p2 = $repo->screenPlaylistId($second); $p3 = $repo->screenPlaylistId($third);
    foreach ([[$other, 12], [$content, 31], [$content, 47], [$other, 15]] as [$id, $duration]) $repo->addPlaylistItem($p1, $id, $duration);
    $repo->addPlaylistItem($p2, $content, 135);
    $retained = $repo->playlistItems($p2);
    $revision = $repo->playlistRevision($p1);
    $result = $repo->updateContentScreens($content, [$second, $third], [$first, $second]);
    $check($result === ['added' => 1, 'removed' => 1], 'one operation adds and removes membership');
    $items = $repo->playlistItems($p1);
    $check(array_map('intval', array_column($items, 'id')) === [$other, $other], 'unchecking removes every occurrence of that content from the TV');
    $check(array_map('intval', array_column($items, 'duration_seconds')) === [12, 15], 'other items retain their durations');
    $check(array_map('intval', array_column($items, 'sort_order')) === [0, 1], 'removal normalizes ordering');
    $check($repo->playlistRevision($p1) !== $revision, 'TV manifest revision changes after removal');
    $check($repo->playlistItems($p2) === $retained, 'retained membership preserves timing and item identity');
    $check((int) $repo->playlistItems($p3)[0]['duration_seconds'] === 30, 'new membership uses default duration');
    $before = $repo->playlistRevision($p2);
    try { $repo->updateContentScreens($content, [4294967295], [$second]); throw new LogicException('Missing TV accepted'); }
    catch (InvalidArgumentException) { $check($repo->playlistRevision($p2) === $before, 'invalid target rolls back the whole operation'); }
    $result = $repo->updateContentScreens($content, [$second], [$second]);
    $check($result === ['added' => 0, 'removed' => 0] && $repo->playlistItems($p3) !== [], 'unchanged boxes do not overwrite concurrent changes on other TVs');
    $legacy = $repo->createPlaylist(['name' => 'Shared legacy', 'description' => null]);
    $repo->addPlaylistItem($legacy, $content, 99);
    $legacyFirst = $repo->createScreen($data + ['playlist_id' => $legacy]);
    $legacySecond = $repo->createScreen($data + ['playlist_id' => $legacy]);
    $repo->updateContentScreens($content, [], [$legacyFirst]);
    $check($repo->playlistItems($repo->screenPlaylistId($legacyFirst)) === [], 'unchecking a legacy TV clears its private copy');
    $check(count($repo->playlistItems($legacy)) === 1 && (int) $repo->screen($legacySecond)['playlist_id'] === $legacy, 'other TV keeps its legacy shared content');
    $result = $repo->updateContentScreens($content, [], [$second, $third]);
    $check($result === ['added' => 0, 'removed' => 2] && $repo->playlistItems($p2) === [] && $repo->playlistItems($p3) === [], 'all visible memberships can be unchecked');
    $before = $repo->playlistRevision($p2);
    $check($repo->updateContentScreens($content, [], [$second, $third]) === ['added' => 0, 'removed' => 0]
        && $repo->playlistRevision($p2) === $before, 'retrying a removal is idempotent');
    $repo->addContentToScreens($content, [$second]);
    $request = new Request('POST', '/', [], ['membership_mode' => 'sync', 'original_screen_ids' => [(string) $second]], ['HTTP_X_REQUESTED_WITH' => 'fetch']);
    $request->params['id'] = (string) $content;
    $response = (new SignageController())->addContentToScreens($request);
    $body = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
    $check($response->status === 200 && $body['screen_ids'] === [$legacySecond] && $body['used_count'] === 1
        && str_contains($body['message'], 'Removed from 1 TV'), 'empty checked selection removes just the original TV and returns accurate usage');
    $check($repo->contentItem($content) !== null, 'playlist removal keeps the library content');
    echo "Playlist membership checks: $checks passed.\n";
} finally { $pdo->rollBack(); }

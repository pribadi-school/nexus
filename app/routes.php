<?php

declare(strict_types=1);

use App\Auth\AuthController;
use App\Core\Router;
use App\Devices\DeviceController;
use App\Modules\Bell\BellApiController;
use App\Modules\Bell\BellController;
use App\Modules\Dashboard\DashboardController;
use App\Modules\Signage\SignageController;
use App\Modules\Signage\SignagePlayerController;
use App\Modules\Signage\SignageAppController;
use App\Modules\Signage\SignagePairingController;
use App\Modules\Signage\SignageScheduleController;
use App\Modules\Signage\SignageTvControlController;

// Every route needs a signed-in person unless marked public: true.
// Device categories add their own routes from their Category class.

$router = new Router();

// Sign-in (one shared password + your name)
$router->get('/login', [AuthController::class, 'showLogin'], public: true);
$router->post('/login', [AuthController::class, 'login'], public: true);
$router->post('/logout', [AuthController::class, 'logout'], public: true);

// Dashboard
$router->get('/', [DashboardController::class, 'index']);
$router->get('/activity', [DashboardController::class, 'activity']);
$router->get('/search', [DashboardController::class, 'search']);

// Any device, whatever its category: forwards to the category's own page,
// and the shared status switch and notes log post here.
$router->get('/devices/{id}', [DeviceController::class, 'show']);
$router->post('/devices/{id}/status', [DeviceController::class, 'status']);
$router->post('/devices/{id}/notes', [DeviceController::class, 'addNote']);
$router->get('/devices/notes/{id}/image', [DeviceController::class, 'noteImage']);

// Device categories (config/modules.php)
categories()->registerRoutes($router);

// Signage manages displays and content separately from the device inventory.
$router->get('/signage', [SignageController::class, 'content']);
$router->get('/signage/screens', [SignageController::class, 'index']);
$router->get('/signage/app/download/{kind}', [SignageAppController::class, 'download']);
$router->get('/signage/pair', [SignagePairingController::class, 'show']);
$router->post('/signage/pair', [SignagePairingController::class, 'claim']);
// Native TVs authenticate polling with a generated device secret, not an admin session.
$router->post('/signage/api/pairing/start', [SignagePairingController::class, 'start'], public: true, csrf: false);
$router->post('/signage/api/pairing/poll', [SignagePairingController::class, 'poll'], public: true, csrf: false);
$router->get('/signage/screens/create', [SignageController::class, 'createScreen']);
$router->post('/signage/screens', [SignageController::class, 'storeScreen']);
$router->get('/signage/screens/{id}/edit', [SignageController::class, 'editScreen']);
$router->get('/signage/screens/{id}/settings', [SignageController::class, 'screenSettings']);
$router->get('/signage/screens/{id}/control', [SignageController::class, 'screenControl']);
$router->post('/signage/screens/{id}', [SignageController::class, 'updateScreen']);
$router->post('/signage/screens/{id}/delete', [SignageController::class, 'deleteScreen']);
$router->post('/signage/screens/{id}/rotate-token', [SignageController::class, 'rotateScreenToken']);
$router->get('/signage/screens/{id}/tv-control', [SignageTvControlController::class, 'show']);
$router->post('/signage/screens/{id}/tv-control', [SignageTvControlController::class, 'queue']);
$router->get('/signage/screens/{id}/tv-control/{command}/screenshot', [SignageTvControlController::class, 'screenshot']);
// The helper PC authenticates with SIGNAGE_TV_AGENT_TOKEN, not a session.
$router->post('/signage/api/tv-agent/poll', [SignageTvControlController::class, 'poll'], public: true, csrf: false);
$router->post('/signage/api/tv-agent/result', [SignageTvControlController::class, 'result'], public: true, csrf: false);
$router->get('/signage/screens/{id}/photo', [SignageController::class, 'screenPhoto']);
$router->post('/signage/screens/{id}/photo', [SignageController::class, 'uploadScreenPhoto']);
$router->post('/signage/screens/{id}/photo/delete', [SignageController::class, 'deleteScreenPhoto']);

$router->get('/signage/content', [SignageController::class, 'content']);
$router->get('/signage/content/create', [SignageController::class, 'createContent']);
$router->post('/signage/content', [SignageController::class, 'storeContent']);
$router->get('/signage/content/{id}/edit', [SignageController::class, 'editContent']);
$router->post('/signage/content/{id}', [SignageController::class, 'updateContent']);
$router->post('/signage/content/{id}/delete', [SignageController::class, 'deleteContent']);
$router->post('/signage/content/{id}/screens', [SignageController::class, 'addContentToScreens']);

// Older bookmarks lead to the screen that owns the playlist.
$router->get('/signage/schedules', [SignageScheduleController::class, 'index']);
$router->get('/signage/schedules/create', [SignageScheduleController::class, 'create']);
$router->post('/signage/schedules', [SignageScheduleController::class, 'store']);
$router->get('/signage/schedules/{id}/edit', [SignageScheduleController::class, 'edit']);
$router->post('/signage/schedules/{id}', [SignageScheduleController::class, 'update']);
$router->post('/signage/schedules/{id}/toggle', [SignageScheduleController::class, 'toggle']);
$router->post('/signage/schedules/{id}/delete', [SignageScheduleController::class, 'delete']);
$router->get('/signage/playlists', [SignageController::class, 'playlists']);
$router->get('/signage/playlists/create', [SignageController::class, 'playlists']);
$router->get('/signage/playlists/{id}/edit', [SignageController::class, 'editPlaylist']);
$router->post('/signage/playlists', [SignageController::class, 'storePlaylist']);
$router->get('/signage/playlists/{id}/settings', [SignageController::class, 'playlistSettings']);
$router->post('/signage/playlists/{id}', [SignageController::class, 'updatePlaylist']);
$router->post('/signage/playlists/{id}/duplicate', [SignageController::class, 'duplicatePlaylist']);
$router->post('/signage/playlists/{id}/delete', [SignageController::class, 'deletePlaylist']);
$router->post('/signage/playlists/{id}/playlist', [SignageController::class, 'saveScreenPlaylist']);
$router->post('/signage/playlists/{id}/items', [SignageController::class, 'addPlaylistItem']);
$router->post('/signage/playlists/{id}/items/{item}', [SignageController::class, 'updatePlaylistItem']);
$router->post('/signage/playlists/{id}/items/{item}/move', [SignageController::class, 'movePlaylistItem']);
$router->post('/signage/playlists/{id}/items/{item}/delete', [SignageController::class, 'deletePlaylistItem']);
$router->post('/signage/screens/{id}/assign-playlist', [SignageController::class, 'assignScreenPlaylist']);
$router->post('/signage/screens/{id}/share-playlist', [SignageController::class, 'shareScreenPlaylist']);
$router->post('/signage/screens/{id}/playlist', [SignageController::class, 'saveScreenPlaylist']);
$router->post('/signage/screens/{id}/items', [SignageController::class, 'addPlaylistItem']);
$router->post('/signage/screens/{id}/items/{item}', [SignageController::class, 'updatePlaylistItem']);
$router->post('/signage/screens/{id}/items/{item}/move', [SignageController::class, 'movePlaylistItem']);
$router->post('/signage/screens/{id}/items/{item}/delete', [SignageController::class, 'deletePlaylistItem']);

// Preview stays signed in. TV links use an unguessable, revocable screen token.
$router->get('/signage/playlists/{id}/preview', [SignagePlayerController::class, 'preview']);
$router->get('/signage/playlists/{id}/preview/manifest', [SignagePlayerController::class, 'previewManifest']);
$router->get('/signage/preview/media/{id}', [SignagePlayerController::class, 'previewMedia']);
$router->get('/signage/preview/poster/{id}', [SignagePlayerController::class, 'poster']);
$router->post('/signage/preview/poster/{id}', [SignagePlayerController::class, 'savePoster']);
$router->get('/signage/player/{token}', [SignagePlayerController::class, 'player'], public: true);
$router->get('/signage/player/{token}/manifest', [SignagePlayerController::class, 'manifest'], public: true);
$router->get('/signage/player/{token}/media/{id}', [SignagePlayerController::class, 'media'], public: true);

// Bell: two independent floors (zones). The bell PC downloads the schedule
// through the public, key-protected API and rings from its own copy.
$router->get('/bell', [BellController::class, 'index']);
$router->get('/bell/zones/{id}', [BellController::class, 'zone']);
$router->post('/bell/zones/{id}/active-profile', [BellController::class, 'activate']);
$router->get('/bell/zones/{id}/settings', [BellController::class, 'settings']);
$router->post('/bell/zones/{id}/settings', [BellController::class, 'saveSettings']);
$router->post('/bell/zones/{id}/profiles', [BellController::class, 'createProfile']);
$router->post('/bell/zones/{id}/holidays', [BellController::class, 'addHoliday']);
$router->post('/bell/holidays/{id}/delete', [BellController::class, 'deleteHoliday']);
$router->post('/bell/profiles/{id}', [BellController::class, 'renameProfile']);
$router->post('/bell/profiles/{id}/delete', [BellController::class, 'deleteProfile']);
$router->post('/bell/profiles/{id}/copy', [BellController::class, 'copyProfile']);
$router->get('/bell/profiles/{id}/entries/create', [BellController::class, 'createEntry']);
$router->post('/bell/profiles/{id}/entries', [BellController::class, 'storeEntry']);
$router->get('/bell/profiles/{id}/export.csv', [BellController::class, 'exportCsv']);
$router->post('/bell/profiles/{id}/import', [BellController::class, 'importCsv']);
$router->get('/bell/entries/{id}/edit', [BellController::class, 'editEntry']);
$router->post('/bell/entries/{id}', [BellController::class, 'updateEntry']);
$router->post('/bell/entries/{id}/toggle', [BellController::class, 'toggleEntry']);
$router->post('/bell/entries/{id}/delete', [BellController::class, 'deleteEntry']);
$router->post('/bell/entries/{id}/copy', [BellController::class, 'copyEntry']);
$router->get('/bell/backup.json', [BellController::class, 'backup']);
$router->get('/bell/sounds', [BellController::class, 'sounds']);
$router->post('/bell/sounds', [BellController::class, 'uploadSound']);
$router->get('/bell/sounds/builtin/{key}', [BellController::class, 'builtinFile']);
$router->get('/bell/sounds/{id}/file', [BellController::class, 'soundFile']);
$router->post('/bell/sounds/{id}/delete', [BellController::class, 'deleteSound']);
$router->get('/bell/pc', [BellController::class, 'pc']);
$router->post('/bell/pc/key', [BellController::class, 'createKey']);
$router->post('/bell/pc/key/revoke', [BellController::class, 'revokeKey']);
$router->post('/bell/api/sync', [BellApiController::class, 'sync'], public: true, csrf: false);
$router->get('/bell/api/sounds/{id}', [BellApiController::class, 'sound'], public: true);

return $router;

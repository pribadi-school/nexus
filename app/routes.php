<?php

declare(strict_types=1);

use App\Auth\AuthController;
use App\Core\Router;
use App\Devices\DeviceController;
use App\Modules\Dashboard\DashboardController;
use App\Modules\Signage\SignageController;
use App\Modules\Signage\SignagePlayerController;
use App\Modules\Signage\SignagePairingController;
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

// Device categories (config/modules.php)
categories()->registerRoutes($router);

// Signage manages displays and content separately from the device inventory.
$router->get('/signage', [SignageController::class, 'content']);
$router->get('/signage/screens', [SignageController::class, 'index']);
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
$router->get('/signage/playlists', [SignageController::class, 'playlists']);
$router->get('/signage/playlists/create', [SignageController::class, 'playlists']);
$router->get('/signage/playlists/{id}/edit', [SignageController::class, 'editPlaylist']);
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

return $router;

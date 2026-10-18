<?php

declare(strict_types=1);

use App\Auth\AuthController;
use App\Core\Router;
use App\Devices\DeviceController;
use App\Modules\Dashboard\DashboardController;

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

return $router;

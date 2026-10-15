<?php

declare(strict_types=1);

namespace App\Devices;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * Temporary page for a category whose screens are not built yet. Each module
 * swaps this for its own controller as it is built.
 */
final class ComingSoonController
{
    public function show(Request $request): Response
    {
        $category = categories()->forPath($request->path) ?? throw HttpException::notFound();
        $counts = (new DeviceRepository())->countsByCategory()[$category->key()];

        return Response::html(View::render('devices/coming-soon', [
            'category' => $category,
            'counts' => $counts,
        ], 'layouts/app', $category->name()));
    }
}

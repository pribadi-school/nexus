<?php

declare(strict_types=1);

namespace App\Devices;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

final class DeviceController
{
    /** GET /devices/{id}: forwards to the device's page in its own category. */
    public function show(Request $request): Response
    {
        $device = (new DeviceRepository())->find((int) $request->param('id'))
            ?? throw HttpException::notFound('That device does not exist or was deleted.');
        $category = categories()->find((string) $device['category'])
            ?? throw HttpException::notFound('That device belongs to a category that is turned off.');

        return Response::redirect($category->detailPath((int) $device['id']));
    }
}

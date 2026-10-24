<?php

declare(strict_types=1);

namespace App\Modules\Signage;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

/** GET /signage/app/download/{kind} : the Android player APK, for signed-in people only. */
final class SignageAppController
{
    public function download(Request $request): Response
    {
        $file = SignageApp::find((string) $request->param('kind'));
        if ($file === null) {
            throw HttpException::notFound('That player app is not available yet.');
        }
        // A few MB, but nothing here needs the session lock held while it is sent.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $path = $file['path'];
        return Response::stream(static function () use ($path): void {
            readfile($path);
        }, 200, [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Content-Disposition' => 'attachment; filename="' . $file['name'] . '"',
            'Content-Length' => (string) $file['size'],
            'Cache-Control' => 'private, no-store',
        ]);
    }
}

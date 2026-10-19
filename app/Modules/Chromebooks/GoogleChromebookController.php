<?php
declare(strict_types=1);

namespace App\Modules\Chromebooks;

use App\Auth\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

final class GoogleChromebookController
{
    public static function client(): GoogleAdminClient
    {
        return new GoogleAdminClient(config('google'));
    }

    /** No Google request here: detail pages render immediately from the cache. */
    public static function viewData(array $chromebook): array
    {
        $client = self::client();
        $service = new GoogleDeviceService($client);
        return [
            'chromebook' => $chromebook,
            'googleConfigured' => $client->configured(),
            'googleCache' => $service->cached($chromebook),
            'googleCanMove' => $client->configured() && config('google.ou_password_hash') !== '',
            'googleMoves' => $client->configured() ? $service->moves((int) $chromebook['id']) : [],
        ];
    }

    public function setup(Request $request): Response
    {
        return Response::html(View::render('Chromebooks::google-setup', [
            'googleConfigured' => self::client()->configured(),
            'googleCanMove' => config('google.ou_password_hash') !== '',
        ], 'layouts/app', 'Google Admin connection · Chromebooks'));
    }

    public function refresh(Request $request): Response
    {
        $device = $this->device($request);
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        set_time_limit(75);
        try {
            (new GoogleDeviceService(self::client()))->refresh($device, $request->input('force') === '1');
            if (!$request->isFetch()) return Response::redirect('/chromebooks/' . $device['id'] . '#google-admin');
            return Response::json(['html' => View::partial('Chromebooks::_google-details', self::viewData($device))]);
        } catch (GoogleApiError $e) {
            if ($request->isFetch()) return Response::json(['error' => $e->getMessage()], 422);
            throw new HttpException(422, $e->getMessage());
        }
    }

    public function orgUnits(Request $request): Response
    {
        $device = $this->device($request);
        if (!self::client()->configured() || config('google.ou_password_hash') === '') throw new HttpException(403, 'OU changes are not enabled.');
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        set_time_limit(75);
        try {
            $units = self::client()->orgUnits();
            if ($request->isFetch()) return Response::json(['units' => $units]);
            return Response::html(View::render('Chromebooks::google-ou', self::viewData($device) + ['units' => $units], 'layouts/app', 'Change OU · Chromebooks'));
        } catch (GoogleApiError $e) { throw new HttpException(422, $e->getMessage()); }
    }

    public function move(Request $request): Response
    {
        $device = $this->device($request);
        $hash = (string) config('google.ou_password_hash');
        if (!self::client()->configured() || $hash === '') throw new HttpException(403, 'OU changes are not enabled.');
        $userId = Auth::id();
        if ($userId === null) throw new HttpException(403, 'Sign in before changing an OU.');
        $window = gmdate('Y-m-d H:i:s', time() - 900);
        if ((int) db()->value('SELECT COUNT(*) FROM google_management_attempts WHERE ip = ? AND attempted_at > ?', [$request->ip(), $window]) >= 5) {
            throw new HttpException(429, 'Too many wrong management passwords. Wait 15 minutes before trying again.');
        }
        $password = $request->body['management_password'] ?? '';
        if (!is_string($password) || strlen($password) > 1024 || !password_verify($password, $hash)) {
            db()->insert('google_management_attempts', ['ip' => $request->ip(), 'attempted_at' => Database::now()]);
            db()->run('DELETE FROM google_management_attempts WHERE attempted_at <= ?', [$window]);
            throw new HttpException(403, 'The management password is not correct.');
        }
        if ($request->input('confirm') !== '1') throw new HttpException(422, 'Confirm that you want to change this Chromebook’s OU and policies.');
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        set_time_limit(90);
        try {
            $service = new GoogleDeviceService(self::client());
            $service->move($device, (string) $request->input('org_unit'), (string) $request->input('expected_ou'), $userId);
            // A failed follow-up read must not imply the accepted move failed.
            try { $service->refresh($device, true); } catch (GoogleApiError) {}
            if (!$request->isFetch()) return Response::redirect('/chromebooks/' . $device['id'] . '#google-admin');
            return Response::json([
                'message' => 'Google accepted the OU change. Policies apply when the Chromebook next synchronizes.',
                'html' => View::partial('Chromebooks::_google-details', self::viewData($device)),
            ]);
        } catch (GoogleApiError $e) { throw new HttpException(422, $e->getMessage()); }
    }

    private function device(Request $request): array
    {
        return (new ChromebookRepository())->find((int) $request->param('id'))
            ?? throw HttpException::notFound('That Chromebook does not exist or was deleted.');
    }
}

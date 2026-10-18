<?php

declare(strict_types=1);

namespace App\Modules\Signage;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use InvalidArgumentException;

final class SignagePairingController
{
    public function show(Request $request): Response
    {
        return $this->form(['code' => '', 'screen_id' => $request->query('screen', '')]);
    }

    public function claim(Request $request): Response
    {
        $values = ['code' => $request->input('code', ''), 'screen_id' => $request->input('screen_id', '')];
        $errors = [];
        if (!preg_match('/^[0-9]{6}$/D', $values['code'])) {
            $errors['code'] = 'Enter the six-digit code shown on the TV.';
        }
        if (!preg_match('/^[1-9][0-9]{0,9}$/D', $values['screen_id']) || (int) $values['screen_id'] > 4294967295) {
            $errors['screen_id'] = 'Choose an existing screen.';
        }
        if ($errors !== []) {
            return $this->form($values, $errors);
        }
        try {
            $screen = (new SignagePairingRepository())->claim($values['code'], (int) $values['screen_id']);
        } catch (InvalidArgumentException $error) {
            return $this->form($values, ['code' => $error->getMessage()]);
        }
        Session::flash('success', 'Android TV paired with ' . $screen['name'] . '. Its playlist will download automatically.');
        return Response::redirect('/signage/screens/' . $screen['id'] . '/edit');
    }

    public function start(Request $request): Response
    {
        $this->releaseSession();
        try {
            if (array_key_exists('device_name', $request->body) && !is_string($request->body['device_name'])) {
                throw new HttpException(422, 'Use a device name of 1 to 160 characters.');
            }
            $result = (new SignagePairingRepository())->start(
                $request->input('device_key', ''), $request->input('device_name', 'Android TV'), $request->ip(),
            );
            return Response::json($result);
        } catch (HttpException $error) {
            return $this->apiError($error);
        }
    }

    public function poll(Request $request): Response
    {
        $this->releaseSession();
        try {
            return Response::json((new SignagePairingRepository())->poll(
                $request->input('device_key', ''), $request->input('code', ''),
            ));
        } catch (HttpException $error) {
            return $this->apiError($error);
        }
    }

    /** @param array<string,mixed> $values @param array<string,string> $errors */
    private function form(array $values, array $errors = []): Response
    {
        return Response::html(View::render('Signage::pair', [
            'values' => $values, 'errors' => $errors, 'screens' => (new SignageRepository())->screens(),
            'tab' => 'screens',
        ], 'layouts/app', 'Pair Android TV'), $errors === [] ? 200 : 422);
    }

    private function apiError(HttpException $error): Response
    {
        $response = Response::json(['status' => $error->status === 410 ? 'expired' : 'error', 'error' => $error->getMessage()], $error->status);
        if ($error->status === 429) {
            $response->headers['Retry-After'] = (string) SignagePairingRepository::LIFETIME_SECONDS;
        }
        return $response;
    }

    private function releaseSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}

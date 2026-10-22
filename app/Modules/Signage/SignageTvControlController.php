<?php

declare(strict_types=1);

namespace App\Modules\Signage;

use App\Auth\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use RuntimeException;

/** TV control buttons for signed-in people, and the API the helper PC uses. */
final class SignageTvControlController
{
    private SignageTvControl $control;

    public function __construct()
    {
        $this->control = new SignageTvControl();
    }

    /** GET /signage/screens/{id}/tv-control : the card, refreshed while actions run. */
    public function show(Request $request): Response
    {
        $screen = $this->screen($request);
        $this->releaseSession();
        return Response::json(['html' => $this->card($screen)]);
    }

    /** POST /signage/screens/{id}/tv-control : queue one fixed action. */
    public function queue(Request $request): Response
    {
        $screen = $this->screen($request);
        $action = (string) $request->input('action', '');
        try {
            $this->control->queue((int) $screen['id'], $action, Auth::id());
        } catch (RuntimeException $error) {
            if ($request->isFetch()) return Response::json(['error' => $error->getMessage()], 422);
            Session::flash('error', $error->getMessage());
            return Response::redirect('/signage/screens/' . $screen['id'] . '/edit#tv-control');
        }
        $message = SignageTvControl::ACTIONS[$action] . ' sent. The helper PC runs it within about 10 seconds.';
        if ($request->isFetch()) {
            $this->releaseSession();
            return Response::json(['message' => $message, 'html' => $this->card($screen)]);
        }
        Session::flash('success', $message);
        return Response::redirect('/signage/screens/' . $screen['id'] . '/edit#tv-control');
    }

    /** GET /signage/screens/{id}/tv-control/{command}/screenshot */
    public function screenshot(Request $request): Response
    {
        $screen = $this->screen($request);
        $this->releaseSession();
        $command = $this->control->command((int) $screen['id'], (int) $request->param('command'));
        $path = $command !== null && $command['screenshot_filename'] !== null
            ? SignageTvControl::screenshotPath((string) $command['screenshot_filename']) : '';
        if ($path === '' || !is_file($path)) {
            throw HttpException::notFound('This screenshot is no longer available.');
        }
        return new Response((string) file_get_contents($path), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /** POST /signage/api/tv-agent/poll : helper check-in; returns actions to run. */
    public function poll(Request $request): Response
    {
        $this->releaseSession();
        $this->authorize($request);
        $name = trim((string) $request->input('name', 'helper'));
        if (!preg_match('/^[A-Za-z0-9 ._-]{1,60}$/D', $name)) {
            return Response::json(['error' => 'Use a helper name of 1 to 60 letters, digits, spaces, dots or dashes.'], 422);
        }
        return Response::json(['commands' => $this->control->poll(
            $name, (string) $request->input('screens', ''), mb_substr((string) $request->input('version', ''), 0, 20),
        )]);
    }

    /** POST /signage/api/tv-agent/result : the helper reports an action's outcome. */
    public function result(Request $request): Response
    {
        $this->releaseSession();
        $this->authorize($request);
        try {
            $this->control->finish(
                (int) $request->input('id', '0'), $request->input('ok') === '1',
                (string) $request->input('message', ''), $request->body['screenshot'] ?? null,
            );
        } catch (RuntimeException $error) {
            return Response::json(['error' => $error->getMessage()], 409);
        }
        return Response::json(['ok' => true]);
    }

    /** @param array<string,mixed> $screen */
    private function card(array $screen): string
    {
        return View::partial('Signage::_tv-control', ['screen' => $screen]);
    }

    private function authorize(Request $request): void
    {
        // A custom header: some cPanel PHP setups drop Authorization.
        $token = SignageTvControl::token();
        $sent = (string) $request->header('X-Nexus-Agent-Token');
        if ($token === '' || $sent === '' || !hash_equals($token, $sent)) {
            throw new HttpException(403, 'TV control is not enabled for this helper.');
        }
    }

    /** @return array<string,mixed> */
    private function screen(Request $request): array
    {
        if (!SignageTvControl::enabled()) {
            throw HttpException::notFound('TV control is not set up.');
        }
        return (new SignageRepository())->screen((int) $request->param('id'))
            ?? throw HttpException::notFound('That screen does not exist.');
    }

    private function releaseSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}

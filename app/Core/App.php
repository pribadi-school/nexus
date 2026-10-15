<?php

declare(strict_types=1);

namespace App\Core;

use App\Auth\Auth;
use Throwable;

final class App
{
    public function __construct(private Router $router)
    {
    }

    public function run(): void
    {
        $request = Request::fromGlobals();

        try {
            $response = $this->router->dispatch($request);
        } catch (HttpException $e) {
            $response = $this->errorPage($request, $e->status, $e->getMessage());
        } catch (Throwable $e) {
            ErrorHandler::log($e);
            $response = ErrorHandler::page($e);
        }

        $response->send();
    }

    private function errorPage(Request $request, int $status, string $message): Response
    {
        if ($request->isFetch()) {
            return Response::json(['error' => $message], $status);
        }

        $titles = [
            403 => 'No access',
            404 => 'Page not found',
            405 => 'Not allowed',
            419 => 'Form expired',
        ];
        $title = $titles[$status] ?? 'Something went wrong';
        $layout = Auth::user() !== null ? 'layouts/app' : 'layouts/auth';

        return Response::html(
            View::render('errors/error', compact('status', 'title', 'message'), $layout, $title),
            $status,
        );
    }
}

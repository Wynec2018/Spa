<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\SecurityHeadersMiddleware;
use Throwable;

final class App
{
    public static function run(): void
    {
        $root = dirname(__DIR__, 2);
        $config = require $root . '/config/app.php';

        date_default_timezone_set((string) ($config['timezone'] ?? 'UTC'));

        try {
            $request = Request::fromGlobals();
            $router = new Router(require $root . '/routes/web.php');
            $response = $router->dispatch($request);
        } catch (Throwable $exception) {
            $debug = (bool) ($config['debug'] ?? false);
            $message = $debug
                ? '<pre>' . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>'
                : '<p>No pudimos cargar la pagina. Intenta nuevamente.</p>';

            $response = Response::html(
                '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>No disponible</title></head><body><main><h1>No disponible</h1>' . $message . '<p><a href="/">Volver al inicio</a></p></main></body></html>',
                500
            );
        }

        header_remove('X-Powered-By');
        SecurityHeadersMiddleware::apply($response)->send();
    }
}

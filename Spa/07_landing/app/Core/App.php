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
        self::loadEnvFile($root);
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

    private static function loadEnvFile(string $root): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $loaded = true;
        $path = $root . '/.env';

        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            $separator = strpos($line, '=');

            if ($separator === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separator));
            $value = trim(substr($line, $separator + 1));

            if ($key === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
                continue;
            }

            if (getenv($key) !== false) {
                continue;
            }

            $value = self::normalizeEnvValue($value);

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    private static function normalizeEnvValue(string $value): string
    {
        $length = strlen($value);

        if ($length >= 2) {
            $quote = $value[0];

            if (($quote === '"' || $quote === "'") && $value[$length - 1] === $quote) {
                return substr($value, 1, -1);
            }
        }

        $comment = strpos($value, ' #');

        if ($comment !== false) {
            return rtrim(substr($value, 0, $comment));
        }

        return $value;
    }
}

<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Router
{
    public function __construct(private array $routes)
    {
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if (!is_array($route) || count($route) !== 3) {
                continue;
            }

            [$method, $path, $action] = $route;

            if (strtoupper((string) $method) !== $request->method()) {
                continue;
            }

            if (!$this->matches((string) $path, $request->path())) {
                continue;
            }

            return $this->runAction((string) $action, $request);
        }

        return Response::html(
            '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>Pagina no encontrada</title></head><body><main><h1>Pagina no encontrada</h1><p><a href="/">Volver al inicio</a></p></main></body></html>',
            404
        );
    }

    private function matches(string $routePath, string $requestPath): bool
    {
        return rtrim($routePath, '/') === rtrim($requestPath, '/');
    }

    private function runAction(string $action, Request $request): Response
    {
        if (!str_contains($action, '@')) {
            throw new RuntimeException('Accion de ruta invalida');
        }

        [$controllerClass, $method] = explode('@', $action, 2);

        if (!class_exists($controllerClass)) {
            throw new RuntimeException('Controlador no encontrado');
        }

        $controller = new $controllerClass();
        $response = $controller->$method($request);

        if (!$response instanceof Response) {
            throw new RuntimeException('El controlador no devolvio una respuesta');
        }

        return $response;
    }
}

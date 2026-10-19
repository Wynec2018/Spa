<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int, array{string, string, callable|array}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable|array $handler): void
    {
        $regex = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern);
        $this->routes[] = [$method, '#^' . $regex . '$#', $handler];
    }

    public function dispatch(Request $request): never
    {
        foreach ($this->routes as [$method, $regex, $handler]) {
            if ($request->method !== $method || !preg_match($regex, $request->path, $m)) {
                continue;
            }
            $request->setParams(array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY));
            if (is_array($handler)) {
                [$class, $action] = $handler;
                (new $class())->{$action}($request);
            } else {
                $handler($request);
            }
        }
        // method/URI sin ruta registrada: mismo 404 que antes
        Response::fail(404, 'Recurso no encontrado');
    }
}

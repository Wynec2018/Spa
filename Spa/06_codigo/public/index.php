<?php
declare(strict_types=1);

use App\Core\Request;
use App\Core\Router;

require __DIR__ . '/../app/Core/bootstrap.php';

$app = require dirname(__DIR__) . '/config/app.php';
ini_set('display_errors', $app['debug'] ? '1' : '0');

header('Content-Type: application/json; charset=' . $app['charset']);

$request = new Request();

// php -S con router: deja pasar los ficheros estáticos reales de /assets
if (PHP_SAPI === 'cli-server' && $request->path !== '/' && is_file(__DIR__ . $request->path)) {
    return false;
}

$router = new Router();
foreach (require dirname(__DIR__) . '/routes/api.php' as [$method, $pattern, $handler]) {
    $router->add($method, $pattern, $handler);
}
// CP-FRONT-01: rutas web (pantallas HTML)
foreach (require dirname(__DIR__) . '/routes/web.php' as [$method, $pattern, $handler]) {
    $router->add($method, $pattern, $handler);
}
$router->dispatch($request);

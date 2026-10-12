<?php

declare(strict_types=1);

use App\Controllers\Api\AuthController;
use App\Controllers\Api\CategoriaController;
use App\Controllers\Api\ProductoController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

// registro de endpoints: GET /api/v1/health (CP-API-02)
return [
    ['GET', '/api/v1/health', function (Request $request): never {
        if (!Database::ping()) {
            Response::fail(500, 'Error de base de datos'); // sin stack trace
        }
        Response::json(200, ['success' => true, 'data' => ['status' => 'ok', 'db' => 'up', 'php' => PHP_VERSION]]);
    }],
    ['POST', '/api/v1/auth/login', [AuthController::class, 'login']],
    ['POST', '/api/v1/auth/logout', [AuthController::class, 'logout']],
    ['GET', '/api/v1/auth/me', [AuthController::class, 'me']],
    ['GET', '/api/v1/categorias', [CategoriaController::class, 'index']],
    ['POST', '/api/v1/categorias', [CategoriaController::class, 'store']],
    ['GET', '/api/v1/categorias/{id}', [CategoriaController::class, 'show']],
    ['PUT', '/api/v1/categorias/{id}', [CategoriaController::class, 'update']],
    ['DELETE', '/api/v1/categorias/{id}', [CategoriaController::class, 'destroy']],
    ['GET', '/api/v1/productos', [ProductoController::class, 'index']],
    ['POST', '/api/v1/productos', [ProductoController::class, 'store']],
    ['GET', '/api/v1/productos/{id}', [ProductoController::class, 'show']],
    ['PUT', '/api/v1/productos/{id}', [ProductoController::class, 'update']],
    ['DELETE', '/api/v1/productos/{id}', [ProductoController::class, 'destroy']],
];

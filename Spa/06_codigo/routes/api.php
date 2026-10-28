<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ReservationController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Middleware\AuthenticateSession;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

$sessionMiddleware = [
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
];

Route::prefix('auth')->middleware($sessionMiddleware)->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

Route::prefix('reservas')->middleware([
    ...$sessionMiddleware,
    AuthenticateSession::class,
])->group(function () {
    Route::get('/', [ReservationController::class, 'index']);
    Route::post('/', [ReservationController::class, 'store']);
    Route::patch('/{id}/estado', [ReservationController::class, 'updateState']);
    Route::get('/{id}', [ReservationController::class, 'show']);
});

Route::get('/servicios', [ServiceController::class, 'index']);
Route::post('/servicios', [ServiceController::class, 'store']);
Route::get('/servicios/{id}', [ServiceController::class, 'show']);
Route::put('/servicios/{id}', [ServiceController::class, 'update']);
Route::delete('/servicios/{id}', [ServiceController::class, 'destroy']);

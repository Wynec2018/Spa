<?php

use App\Exceptions\ApiException;
use App\Exceptions\DuplicateServiceException;
use App\Support\ApiResponse;
use App\Support\RequestId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $e->getStatusCode();
            $code = match ($status) {
                404 => 'NOT_FOUND',
                405 => 'METHOD_NOT_ALLOWED',
                429 => 'TOO_MANY_REQUESTS',
                default => 'HTTP_ERROR',
            };
            $message = match ($status) {
                404 => 'El recurso no existe.',
                405 => 'El método HTTP no está permitido para esta ruta.',
                429 => 'Demasiadas peticiones.',
                default => 'La petición no pudo completarse.',
            };

            return ApiResponse::error(
                RequestId::from($request),
                new ApiException($status, $code, $message),
                $e->getHeaders(),
            );
        });

        $exceptions->render(function (ApiException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e->status >= 500) {
                report($e);
            }

            return ApiResponse::error(RequestId::from($request), $e);
        });

        $exceptions->render(function (DuplicateServiceException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(RequestId::from($request), ApiException::duplicateService());
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            report($e);

            return ApiResponse::internalError(RequestId::from($request));
        });
    })->create();

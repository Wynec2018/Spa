<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

final class Response
{
    public static function json(int $status, array $body): never
    {
        http_response_code($status);
        echo json_encode($body, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function fail(int $status, string $message): never
    {
        self::json($status, ['success' => false, 'error' => $message]);
    }

    /**
     * CP-BACK-01: patrón reutilizable. Ejecuta la acción del controller y
     * traduce excepciones a respuestas HTTP (RuntimeException → 400/404,
     * Throwable → 500 sin stack trace). Lo usan Auth/Categoria/Producto.
     */
    public static function execute(callable $action, int $status): never
    {
        try {
            $data = $action();
        } catch (RuntimeException $e) {
            $code = (int) $e->getCode();
            self::fail($code >= 400 ? $code : 400, $e->getMessage());
        } catch (Throwable $e) {
            error_log($e->getMessage());
            self::fail(500, 'Error de base de datos');
        }
        self::json($status, ['success' => true, 'data' => $data]);
    }
}

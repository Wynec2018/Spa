<?php
declare(strict_types=1);

namespace App\Core;

// CP-BACK-03: inicio único de sesión con hardening mínimo de cookie
final class Session
{
    private static bool $started = false;

    public static function name(): string
    {
        /** @var array{name: string} $cfg */
        $cfg = require dirname(__DIR__, 2) . '/config/session.php';
        return $cfg['name'];
    }

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }
        /** @var array{name: string, lifetime: int, httponly: bool, samesite: string, strict: bool} $cfg */
        $cfg = require dirname(__DIR__, 2) . '/config/session.php';
        session_name($cfg['name']);
        ini_set('session.use_strict_mode', $cfg['strict'] ? '1' : '0');
        session_set_cookie_params([
            'lifetime' => $cfg['lifetime'],
            'path' => '/',
            'httponly' => $cfg['httponly'],
            'samesite' => $cfg['samesite'],
        ]);
        session_start();
        self::$started = true;
    }

    // CP-BACK-04: destruye la sesión si existe; sin cookie no hace nada (idempotente)
    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (!isset($_COOKIE[self::name()])) {
                return;
            }
            self::start();
        }
        $_SESSION = [];
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $p['path'],
            'domain' => $p['domain'],
            'secure' => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'],
        ]);
        session_destroy();
        self::$started = false;
    }
}

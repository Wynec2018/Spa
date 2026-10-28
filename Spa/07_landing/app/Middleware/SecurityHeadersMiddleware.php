<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Response;

final class SecurityHeadersMiddleware
{
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'X-Permitted-Cross-Domain-Policies' => 'none',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(), payment=(), usb=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Cross-Origin-Resource-Policy' => 'same-origin',
        'Cache-Control' => 'no-store, max-age=0',
        'Content-Security-Policy' => "default-src 'self'; img-src 'self' data: https:; style-src 'self'; script-src 'self'; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; frame-src 'none'; media-src 'none'; worker-src 'none'",
    ];

    public static function apply(Response $response): Response
    {
        foreach (self::HEADERS as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}

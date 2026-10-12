<?php
declare(strict_types=1);

namespace App\Core;

// CP-FRONT-01: renderiza una View con su layout. extract() solo inyecta datos
// de presentación; la View nunca ve SQL/PDO (regla de estructura_frontend.md).
final class View
{
    public static function render(string $template, array $data = [], string $layout = 'layouts/auth'): never
    {
        extract($data, EXTR_SKIP);
        $views = dirname(__DIR__, 2) . '/app/Views/';
        ob_start();
        require $views . $template . '.php';
        $content = ob_get_clean();
        require $views . $layout . '.php';
        exit;
    }
}

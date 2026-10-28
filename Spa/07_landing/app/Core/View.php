<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    public function __construct(private string $viewsPath)
    {
    }

    public function render(string $template, array $data = []): string
    {
        $file = $this->viewsPath . '/' . str_replace('.', '/', $template) . '.php';

        if (!is_file($file)) {
            throw new RuntimeException('Vista no encontrada');
        }

        extract($data, EXTR_SKIP);
        ob_start();

        try {
            require $file;
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }

        $content = ob_get_clean();

        return $content === false ? '' : $content;
    }

    public function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function asset(string $path): string
    {
        $relative = ltrim($path, '/');
        $file = dirname(__DIR__, 2) . '/public/' . $relative;

        if (!is_file($file)) {
            return $relative;
        }

        return $relative . '?v=' . filemtime($file);
    }
}

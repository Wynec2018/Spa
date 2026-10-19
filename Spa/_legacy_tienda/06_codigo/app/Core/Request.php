<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    public readonly string $method;
    public readonly string $path;

    private array $params = [];
    private ?array $body = null;
    private bool $bodyParsed = false;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    }

    public function body(): ?array
    {
        if (!$this->bodyParsed) {
            $raw = file_get_contents('php://input');
            // CP-INT-04: solo se acepta JSON con Content-Type application/json
            // (bloquea payloads de formularios/fetch cross-site → defensa CSRF en profundidad)
            $ct = $_SERVER['CONTENT_TYPE'] ?? '';
            if ($raw !== '' && stripos($ct, 'application/json') !== 0) {
                throw new \RuntimeException('Content-Type debe ser application/json', 415);
            }
            $decoded = $raw === '' ? null : json_decode($raw, true);
            $this->body = is_array($decoded) ? $decoded : null;
            $this->bodyParsed = true;
        }
        return $this->body;
    }

    public function param(string $name): string
    {
        return $this->params[$name] ?? '';
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }
}

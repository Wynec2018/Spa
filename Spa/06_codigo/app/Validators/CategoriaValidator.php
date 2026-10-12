<?php
declare(strict_types=1);

namespace App\Validators;

use App\Models\Categoria;
use RuntimeException;

final class CategoriaValidator
{
    /** @return int id validado */
    public function validateId(string $raw): int
    {
        if (!ctype_digit($raw) || (int) $raw < 1) {
            throw new RuntimeException('ID inválido', 400);
        }
        return (int) $raw;
    }

    /**
     * @param array|null $body cuerpo JSON ya decodificado (null = inválido)
     * @return array{nombre: string, categoria_padre_id: int|null}
     */
    public function validateBody(?array $body): array
    {
        if ($body === null) {
            throw new RuntimeException('Cuerpo JSON inválido', 400);
        }
        $nombre = $body['nombre'] ?? null;
        if (!is_string($nombre) || trim($nombre) === '' || mb_strlen($nombre, 'UTF-8') > Categoria::MAX_NOMBRE) {
            throw new RuntimeException('nombre: obligatorio, string, máximo ' . Categoria::MAX_NOMBRE . ' caracteres', 400);
        }
        $padre = $body['categoria_padre_id'] ?? null;
        if ($padre !== null && (!is_int($padre) || $padre < 1)) {
            throw new RuntimeException('categoria_padre_id: entero mayor 0 o null', 400);
        }
        return ['nombre' => $nombre, 'categoria_padre_id' => $padre];
    }
}

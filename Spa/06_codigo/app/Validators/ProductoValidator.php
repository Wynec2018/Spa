<?php
declare(strict_types=1);

namespace App\Validators;

use App\Models\Producto;
use RuntimeException;

final class ProductoValidator
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
     * CP-BACK-06: POST /api/v1/productos
     * @param array|null $body cuerpo JSON ya decodificado (null = inválido)
     * @return array{sku: string, nombre: string, categoria_id: int, unidad: string}
     */
    public function validateBody(?array $body): array
    {
        if ($body === null) {
            throw new RuntimeException('Cuerpo JSON inválido', 400);
        }
        $sku = $body['sku'] ?? null;
        if (!is_string($sku) || trim($sku) === '' || mb_strlen($sku, 'UTF-8') > Producto::MAX_SKU) {
            throw new RuntimeException('sku: obligatorio, string, máximo ' . Producto::MAX_SKU . ' caracteres', 400);
        }
        $nombre = $body['nombre'] ?? null;
        if (!is_string($nombre) || trim($nombre) === '' || mb_strlen($nombre, 'UTF-8') > Producto::MAX_NOMBRE) {
            throw new RuntimeException('nombre: obligatorio, string, máximo ' . Producto::MAX_NOMBRE . ' caracteres', 400);
        }
        $categoriaId = $body['categoria_id'] ?? null;
        if (!is_int($categoriaId) || $categoriaId < 1) {
            throw new RuntimeException('categoria_id: entero mayor 0', 400);
        }
        $unidad = $body['unidad'] ?? null;
        if (!is_string($unidad) || trim($unidad) === '' || mb_strlen($unidad, 'UTF-8') > Producto::MAX_UNIDAD) {
            throw new RuntimeException('unidad: obligatoria, string, máximo ' . Producto::MAX_UNIDAD . ' caracteres', 400);
        }
        return ['sku' => trim($sku), 'nombre' => $nombre, 'categoria_id' => $categoriaId, 'unidad' => $unidad];
    }
}

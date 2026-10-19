<?php
declare(strict_types=1);

namespace App\Models;

final class Producto
{
    public const TABLE = 'cat_producto';
    public const MAX_SKU = 64;
    public const MAX_NOMBRE = 150;
    public const MAX_UNIDAD = 32;

    public function __construct(
        public readonly int $id,
        public readonly string $sku,
        public readonly string $nombre,
        public readonly int $categoriaId,
        public readonly string $unidad,
        public readonly string $estado,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['sku'],
            (string) $row['nombre'],
            (int) $row['categoria_id'],
            (string) $row['unidad'],
            (string) $row['estado'],
        );
    }

    /** forma exacta de la respuesta JSON del contrato */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'nombre' => $this->nombre,
            'categoria_id' => $this->categoriaId,
            'unidad' => $this->unidad,
            'estado' => $this->estado,
        ];
    }
}

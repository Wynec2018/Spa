<?php
declare(strict_types=1);

namespace App\Models;

final class Categoria
{
    public const TABLE = 'cat_categoria';
    public const MAX_NOMBRE = 100;

    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
        public readonly ?int $categoriaPadreId,
        public readonly string $estado,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['nombre'],
            $row['categoria_padre_id'] === null ? null : (int) $row['categoria_padre_id'],
            (string) $row['estado'],
        );
    }

    /** forma exacta de la respuesta JSON del contrato */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'categoria_padre_id' => $this->categoriaPadreId,
            'estado' => $this->estado,
        ];
    }
}

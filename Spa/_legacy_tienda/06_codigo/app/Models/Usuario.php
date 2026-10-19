<?php
declare(strict_types=1);

namespace App\Models;

final class Usuario
{
    public const TABLE = 'auth_usuario';
    public const MAX_EMAIL = 254;

    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
        public readonly string $email,
        public readonly string $estado,
        public readonly string $hashPassword,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['nombre'],
            (string) $row['email'],
            (string) $row['estado'],
            (string) $row['hash_password'],
        );
    }

    /** datos mínimos de sesión: nunca expone hash ni estado */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'email' => $this->email,
        ];
    }
}

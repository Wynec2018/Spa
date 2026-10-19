<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\Categoria;
use PDO;

final class CategoriaRepository
{
    private const COLUMNS = 'id, nombre, categoria_padre_id, estado';

    /** @return list<Categoria> */
    public function findAll(): array
    {
        $rows = Database::conn()
            ->query('SELECT ' . self::COLUMNS . ' FROM ' . Categoria::TABLE . ' ORDER BY id')
            ->fetchAll();
        return array_map(Categoria::fromRow(...), $rows);
    }

    public function findById(int $id): ?Categoria
    {
        $q = Database::conn()->prepare('SELECT ' . self::COLUMNS . ' FROM ' . Categoria::TABLE . ' WHERE id = :id');
        $q->execute(['id' => $id]);
        $row = $q->fetch();
        return $row === false ? null : Categoria::fromRow($row);
    }

    public function exists(int $id): bool
    {
        $q = Database::conn()->prepare('SELECT 1 FROM ' . Categoria::TABLE . ' WHERE id = :id');
        $q->execute(['id' => $id]);
        return (bool) $q->fetchColumn();
    }

    // ponytail: user 1 fijo hasta implementar auth (workflow 04); audit no se expone
    public function insert(string $nombre, ?int $categoriaPadreId): int
    {
        $q = Database::conn()->prepare(
            'INSERT INTO ' . Categoria::TABLE . ' (nombre, categoria_padre_id, estado, user_create, user_update, user_created_at, user_update_at)
             VALUES (:nombre, :padre, "activo", 1, 1, NOW(), NOW())'
        );
        $q->execute(['nombre' => $nombre, 'padre' => $categoriaPadreId]);
        return (int) Database::conn()->lastInsertId();
    }

    // ponytail: user 1 fijo hasta auth (workflow 04)
    public function update(int $id, string $nombre, ?int $categoriaPadreId): void
    {
        $q = Database::conn()->prepare(
            'UPDATE ' . Categoria::TABLE . ' SET nombre = :nombre, categoria_padre_id = :padre,
             user_update = 1, user_update_at = NOW() WHERE id = :id'
        );
        $q->execute(['nombre' => $nombre, 'padre' => $categoriaPadreId, 'id' => $id]);
    }

    // CP-API-07: borrado lógico; nunca DELETE FROM (FK RESTRICT + auditoría)
    public function deactivate(int $id): void
    {
        $q = Database::conn()->prepare(
            'UPDATE ' . Categoria::TABLE . " SET estado = 'inactivo', user_update = 1, user_update_at = NOW() WHERE id = :id"
        );
        $q->execute(['id' => $id]);
    }
}

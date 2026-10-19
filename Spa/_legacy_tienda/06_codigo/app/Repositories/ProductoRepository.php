<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\Producto;

final class ProductoRepository
{
    private const COLUMNS = 'id, sku, nombre, categoria_id, unidad, estado';

    /** @return list<Producto> */
    public function findAll(): array
    {
        $rows = Database::conn()
            ->query('SELECT ' . self::COLUMNS . ' FROM ' . Producto::TABLE . ' ORDER BY id')
            ->fetchAll();
        return array_map(Producto::fromRow(...), $rows);
    }

    public function findById(int $id): ?Producto
    {
        $q = Database::conn()->prepare('SELECT ' . self::COLUMNS . ' FROM ' . Producto::TABLE . ' WHERE id = :id');
        $q->execute(['id' => $id]);
        $row = $q->fetch();
        return $row === false ? null : Producto::fromRow($row);
    }

    // ponytail: excludeId evita el falso positivo del propio producto en PUT
    public function existsSku(string $sku, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM ' . Producto::TABLE . ' WHERE sku = :sku';
        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude';
        }
        $q = Database::conn()->prepare($sql);
        $params = ['sku' => $sku];
        if ($excludeId !== null) {
            $params['exclude'] = $excludeId;
        }
        $q->execute($params);
        return (bool) $q->fetchColumn();
    }

    // ponytail: user 1 fijo hasta implementar auth (workflow 04); estado lo pone el DEFAULT 'activo'
    public function insert(string $sku, string $nombre, int $categoriaId, string $unidad): int
    {
        $q = Database::conn()->prepare(
            'INSERT INTO ' . Producto::TABLE . ' (sku, nombre, categoria_id, unidad, estado, user_create, user_update, user_created_at, user_update_at)
             VALUES (:sku, :nombre, :categoria_id, :unidad, "activo", 1, 1, NOW(), NOW())'
        );
        $q->execute(['sku' => $sku, 'nombre' => $nombre, 'categoria_id' => $categoriaId, 'unidad' => $unidad]);
        return (int) Database::conn()->lastInsertId();
    }

    // CP-BACK-07; ponytail: user 1 fijo hasta implementar auth (workflow 04)
    public function update(int $id, string $sku, string $nombre, int $categoriaId, string $unidad): void
    {
        $q = Database::conn()->prepare(
            'UPDATE ' . Producto::TABLE . ' SET sku = :sku, nombre = :nombre, categoria_id = :categoria_id, unidad = :unidad,
             user_update = 1, user_update_at = NOW() WHERE id = :id'
        );
        $q->execute(['sku' => $sku, 'nombre' => $nombre, 'categoria_id' => $categoriaId, 'unidad' => $unidad, 'id' => $id]);
    }

    // CP-BACK-08: borrado lógico; nunca DELETE FROM (FK RESTRICT + auditoría), idempotente
    public function deactivate(int $id): void
    {
        $q = Database::conn()->prepare(
            'UPDATE ' . Producto::TABLE . " SET estado = 'inactivo', user_update = 1, user_update_at = NOW() WHERE id = :id"
        );
        $q->execute(['id' => $id]);
    }
}

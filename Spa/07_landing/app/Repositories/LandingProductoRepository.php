<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

// SELECT de landing_producto únicamente.
final class LandingProductoRepository
{
    /**
     * Una sola consulta (sin N+1): productos visibles con su categoría.
     * @return array<int,array<string,mixed>>
     */
    public function visibles(): array
    {
        $sql = 'SELECT p.id, p.nombre, p.precio, p.precio_anterior, p.imagen_url,
                       p.etiqueta, p.orden,
                       c.id AS categoria_id, c.nombre AS categoria_nombre, c.orden AS categoria_orden
                  FROM landing_producto p
                  JOIN landing_categoria c ON c.id = p.categoria_id
                 WHERE p.visible = 1 AND c.visible = 1
                 ORDER BY c.orden, c.id, p.orden, p.id';
        return Database::pdo()->query($sql)->fetchAll();
    }
}

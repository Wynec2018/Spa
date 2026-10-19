<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

// SELECT de landing_categoria únicamente.
final class LandingCategoriaRepository
{
    /** @return array<int,array<string,mixed>> */
    public function visibles(): array
    {
        $sql = 'SELECT id, nombre, slug, descripcion, imagen_url
                  FROM landing_categoria
                 WHERE visible = 1
                 ORDER BY orden, id';
        $stmt = Database::pdo()->query($sql);
        return $stmt->fetchAll();
    }

    public function contarVisibles(): int
    {
        return (int) Database::pdo()
            ->query('SELECT COUNT(*) FROM landing_categoria WHERE visible = 1')
            ->fetchColumn();
    }
}

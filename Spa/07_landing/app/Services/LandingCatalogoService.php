<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\LandingCategoriaRepository;
use App\Repositories\LandingProductoRepository;

// Orquestación de catálogo. Sin SQL/PDO.
final class LandingCatalogoService
{
    public function __construct(
        private readonly LandingCategoriaRepository $categorias = new LandingCategoriaRepository(),
        private readonly LandingProductoRepository $productos = new LandingProductoRepository(),
    ) {
    }

    /** @return array<int,array<string,mixed>> */
    public function categoriasVisibles(): array
    {
        return $this->categorias->visibles();
    }

    /**
     * Productos agrupados por categoría, ya agrupados en PHP
     * desde una única consulta (sin N+1).
     * @return array<int,array{categoria:array<string,mixed>,productos:array<int,array<string,mixed>>}>}
     */
    public function productosPorCategoria(): array
    {
        $grupos = [];
        foreach ($this->productos->visibles() as $p) {
            $key = (int) $p['categoria_id'];
            if (!isset($grupos[$key])) {
                $grupos[$key] = [
                    'categoria' => [
                        'id' => $key,
                        'nombre' => $p['categoria_nombre'],
                        'orden' => (int) $p['categoria_orden'],
                    ],
                    'productos' => [],
                ];
            }
            $grupos[$key]['productos'][] = $p;
        }
        ksort($grupos);
        return array_values($grupos);
    }
}

<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Producto;
use App\Repositories\CategoriaRepository;
use App\Repositories\ProductoRepository;
use RuntimeException;

final class ProductoService
{
    public function __construct(
        private readonly ProductoRepository $repository = new ProductoRepository(),
        private readonly CategoriaRepository $categorias = new CategoriaRepository(),
    ) {
    }

    /** @return list<array> */
    public function list(): array
    {
        return array_map(static fn (Producto $p): array => $p->toArray(), $this->repository->findAll());
    }

    public function get(int $id): array
    {
        $producto = $this->repository->findById($id);
        if ($producto === null) {
            throw new RuntimeException('Producto no encontrado', 404);
        }
        return $producto->toArray();
    }

    /**
     * CP-BACK-06: crear producto
     * @param array{sku: string, nombre: string, categoria_id: int, unidad: string} $data
     */
    public function create(array $data): array
    {
        if (!$this->categorias->exists($data['categoria_id'])) {
            throw new RuntimeException('categoria_id no existe', 404);
        }
        // ponytail: pre-check de SKU; la restricción UNIQUE uq_cat_producto_sku es la garantía real
        if ($this->repository->existsSku($data['sku'])) {
            throw new RuntimeException('sku ya existe', 409);
        }
        $id = $this->repository->insert($data['sku'], $data['nombre'], $data['categoria_id'], $data['unidad']);
        return $this->get($id);
    }

    /**
     * CP-BACK-07: PUT /api/v1/productos/{id} — mismas reglas que create + producto existente
     * @param array{sku: string, nombre: string, categoria_id: int, unidad: string} $data
     */
    public function update(int $id, array $data): array
    {
        if ($this->repository->findById($id) === null) {
            throw new RuntimeException('Producto no encontrado', 404);
        }
        if (!$this->categorias->exists($data['categoria_id'])) {
            throw new RuntimeException('categoria_id no existe', 404);
        }
        // ponytail: excludeId en el pre-check; la UNIQUE uq_cat_producto_sku es la garantía real
        if ($this->repository->existsSku($data['sku'], $id)) {
            throw new RuntimeException('sku ya existe', 409);
        }
        $this->repository->update($id, $data['sku'], $data['nombre'], $data['categoria_id'], $data['unidad']);
        return $this->get($id);
    }

    /** CP-BACK-08: inactivación lógica, idempotente (doble DELETE → 200) */
    public function deactivate(int $id): array
    {
        if ($this->repository->findById($id) === null) {
            throw new RuntimeException('Producto no encontrado', 404);
        }
        $this->repository->deactivate($id);
        return $this->get($id);
    }
}

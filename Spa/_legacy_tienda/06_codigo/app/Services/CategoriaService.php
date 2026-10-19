<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Categoria;
use App\Repositories\CategoriaRepository;
use RuntimeException;

final class CategoriaService
{
    public function __construct(private readonly CategoriaRepository $repository = new CategoriaRepository())
    {
    }

    /** @return list<array> */
    public function list(): array
    {
        return array_map(static fn (Categoria $c): array => $c->toArray(), $this->repository->findAll());
    }

    public function get(int $id): array
    {
        $categoria = $this->repository->findById($id);
        if ($categoria === null) {
            throw new RuntimeException('Categoría no encontrada', 404);
        }
        return $categoria->toArray();
    }

    /** @param array{nombre: string, categoria_padre_id: int|null} $data */
    public function create(array $data): array
    {
        $padre = $data['categoria_padre_id'];
        if ($padre !== null && !$this->repository->exists($padre)) {
            throw new RuntimeException('categoria_padre_id no existe', 404);
        }
        $id = $this->repository->insert($data['nombre'], $padre);
        return $this->get($id);
    }

    /** @param array{nombre: string, categoria_padre_id: int|null} $data */
    public function update(int $id, array $data): array
    {
        if (!$this->repository->exists($id)) {
            throw new RuntimeException('Categoría no encontrada', 404);
        }
        $padre = $data['categoria_padre_id'];
        if ($padre !== null) {
            if ($padre === $id) {
                throw new RuntimeException('categoria_padre_id no puede ser la misma categoría (auto-padre)', 400);
            }
            if (!$this->repository->exists($padre)) {
                throw new RuntimeException('categoria_padre_id no existe', 404);
            }
        }
        $this->repository->update($id, $data['nombre'], $padre);
        return $this->get($id);
    }

    /** CP-API-07: inactivación lógica, idempotente (doble DELETE → 200) */
    public function deactivate(int $id): array
    {
        if (!$this->repository->exists($id)) {
            throw new RuntimeException('Categoría no encontrada', 404);
        }
        $this->repository->deactivate($id);
        return $this->get($id);
    }
}

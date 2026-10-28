<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DuplicateServiceException;
use App\Support\DatabaseContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class ServiceRepository
{
    public function paginate(array $filters, string $branchId, string $requestId): array
    {
        return DatabaseContext::run(function () use ($filters, $branchId): array {
            $base = DB::table('catalog.service')->where('branch_id', $branchId);

            if ($filters['estado'] !== null) {
                $base->where('estado', $filters['estado']);
            }

            if ($filters['q'] !== null) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']);
                $base->whereRaw("nombre ILIKE ? ESCAPE '\\'", ['%'.$escaped.'%']);
            }

            $total = (clone $base)->count();
            $rows = $base->orderBy($filters['sort'], $filters['order'])
                ->forPage($filters['page'], $filters['per_page'])
                ->get();

            return ['rows' => $rows, 'total' => (int) $total];
        }, branchId: $branchId, correlationId: $requestId);
    }

    public function find(string $id, string $branchId, string $requestId): ?object
    {
        return DatabaseContext::run(
            fn (): ?object => DB::table('catalog.service')
                ->where('branch_id', $branchId)
                ->where('id', $id)
                ->first(),
            branchId: $branchId,
            correlationId: $requestId,
        );
    }

    public function create(array $data, string $branchId, string $requestId): object
    {
        try {
            return DatabaseContext::run(function () use ($data, $branchId): object {
                $id = (string) Str::uuid();

                DB::table('catalog.service')->insert([
                    'id' => $id,
                    'branch_id' => $branchId,
                    'nombre' => $data['nombre'],
                    'duracion_min' => $data['duracion_min'],
                    'precio' => $data['precio'],
                ]);

                $row = DB::table('catalog.service')
                    ->where('branch_id', $branchId)
                    ->where('id', $id)
                    ->first();

                if ($row === null) {
                    throw new RuntimeException('No se pudo leer el servicio creado.');
                }

                return $row;
            }, branchId: $branchId, correlationId: $requestId);
        } catch (QueryException $e) {
            $this->throwIfUnique($e);

            throw $e;
        }
    }

    public function update(string $id, array $data, string $branchId, string $requestId): ?object
    {
        try {
            return DatabaseContext::run(function () use ($id, $data, $branchId): ?object {
                $exists = DB::table('catalog.service')
                    ->where('branch_id', $branchId)
                    ->where('id', $id)
                    ->exists();

                if (! $exists) {
                    return null;
                }

                DB::table('catalog.service')
                    ->where('branch_id', $branchId)
                    ->where('id', $id)
                    ->update([
                        'nombre' => $data['nombre'],
                        'duracion_min' => $data['duracion_min'],
                        'precio' => $data['precio'],
                        'updated_at' => now(),
                    ]);

                return DB::table('catalog.service')
                    ->where('branch_id', $branchId)
                    ->where('id', $id)
                    ->first();
            }, branchId: $branchId, correlationId: $requestId);
        } catch (QueryException $e) {
            $this->throwIfUnique($e);

            throw $e;
        }
    }

    public function deactivate(string $id, string $branchId, string $requestId): ?object
    {
        return DatabaseContext::run(function () use ($id, $branchId): ?object {
            $row = DB::table('catalog.service')
                ->where('branch_id', $branchId)
                ->where('id', $id)
                ->first();

            if ($row === null || $row->estado === 'inactivo') {
                return $row;
            }

            DB::table('catalog.service')
                ->where('branch_id', $branchId)
                ->where('id', $id)
                ->update([
                    'estado' => 'inactivo',
                    'updated_at' => now(),
                ]);

            return DB::table('catalog.service')
                ->where('branch_id', $branchId)
                ->where('id', $id)
                ->first();
        }, branchId: $branchId, correlationId: $requestId);
    }

    private function throwIfUnique(QueryException $e): void
    {
        if ($e->getCode() === '23505' || ($e->errorInfo[0] ?? null) === '23505') {
            throw new DuplicateServiceException('Ya existe un servicio con ese nombre en la sucursal.', 0, $e);
        }
    }
}

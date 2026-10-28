<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\ApiException;
use App\Support\DatabaseContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class ReservationRepository
{
    public function create(
        array $data,
        string $branchId,
        string $requestId,
        string $userId,
    ): object {
        try {
            return DatabaseContext::run(function () use ($data, $branchId): object {
                $client = DB::table('catalog.client')
                    ->where('branch_id', $branchId)
                    ->where('id', $data['client_id'])
                    ->first();

                if ($client === null) {
                    throw ApiException::clientNotFound();
                }

                $service = DB::table('catalog.service')
                    ->where('branch_id', $branchId)
                    ->where('id', $data['service_id'])
                    ->sharedLock()
                    ->first();

                if ($service === null) {
                    throw ApiException::serviceNotFound();
                }

                if ($service->estado !== 'activo') {
                    throw ApiException::serviceInactive();
                }

                $resourceIds = array_values(array_unique([$data['cama_id'], $data['trabajadora_id']]));
                $existing = DB::table('catalog.resource')
                    ->where('branch_id', $branchId)
                    ->whereIn('id', $resourceIds)
                    ->get()
                    ->keyBy('id');

                if (! $existing->has($data['cama_id']) || ! $existing->has($data['trabajadora_id'])) {
                    throw ApiException::resourceNotFound();
                }

                $locked = DB::table('catalog.resource')
                    ->where('branch_id', $branchId)
                    ->whereIn('id', $resourceIds)
                    ->orderBy('id')
                    ->lock('for update skip locked')
                    ->get()
                    ->keyBy('id');

                if (! $locked->has($data['cama_id']) || ! $locked->has($data['trabajadora_id'])) {
                    throw ApiException::resourceUnavailable();
                }

                $cama = $locked->get($data['cama_id']);
                $trabajadora = $locked->get($data['trabajadora_id']);

                if ($cama->tipo !== 'cama' || $trabajadora->tipo !== 'trabajadora') {
                    $details = [];

                    if ($cama->tipo !== 'cama') {
                        $details['cama_id'][] = 'Debe referenciar un recurso de tipo cama.';
                    }

                    if ($trabajadora->tipo !== 'trabajadora') {
                        $details['trabajadora_id'][] = 'Debe referenciar un recurso de tipo trabajadora.';
                    }

                    throw ApiException::validation($details);
                }

                if ($cama->estado === 'no_disponible' || $trabajadora->estado !== 'libre') {
                    throw ApiException::resourceUnavailable();
                }

                $inicio = CarbonImmutable::parse($data['inicio'], 'UTC')->utc();
                $fin = $inicio->addMinutes((int) $service->duracion_min);
                $id = (string) Str::uuid();

                DB::table('booking.booking')->insert([
                    'id' => $id,
                    'branch_id' => $branchId,
                    'client_id' => $data['client_id'],
                    'service_id' => $data['service_id'],
                    'cama_id' => $data['cama_id'],
                    'trabajadora_id' => $data['trabajadora_id'],
                    'inicio' => $inicio->format('Y-m-d H:i:sP'),
                    'fin' => $fin->format('Y-m-d H:i:sP'),
                    'estado' => 'pendiente',
                ]);

                $row = $this->baseQuery($branchId)
                    ->where('b.id', $id)
                    ->select($this->columns())
                    ->first();

                if ($row === null) {
                    throw new RuntimeException('No se pudo leer la reserva creada.');
                }

                return $row;
            }, branchId: $branchId, userId: $userId, correlationId: $requestId, isolation: 'repeatable read');
        } catch (QueryException $e) {
            $sqlState = $e->getCode() !== '' ? $e->getCode() : ($e->errorInfo[0] ?? '');

            if (in_array($sqlState, ['23P01', '40001', '40P01'], true)) {
                throw ApiException::reservationConflict();
            }

            throw $e;
        }
    }

    public function cancel(
        string $id,
        string $branchId,
        string $requestId,
        string $userId,
    ): ?object {
        try {
            return DatabaseContext::run(function () use ($id, $branchId): ?object {
                $booking = DB::table('booking.booking')
                    ->where('branch_id', $branchId)
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                if ($booking === null) {
                    return null;
                }

                if ($booking->estado !== 'cancelada') {
                    if ($booking->estado !== 'pendiente') {
                        throw ApiException::invalidStateTransition();
                    }

                    DB::table('booking.booking')
                        ->where('branch_id', $branchId)
                        ->where('id', $id)
                        ->update([
                            'estado' => 'cancelada',
                            'updated_at' => now(),
                        ]);
                }

                $row = $this->baseQuery($branchId)
                    ->where('b.id', $id)
                    ->select($this->columns())
                    ->first();

                if ($row === null) {
                    throw new RuntimeException('No se pudo leer la reserva cancelada.');
                }

                return $row;
            }, branchId: $branchId, userId: $userId, correlationId: $requestId, isolation: 'repeatable read');
        } catch (QueryException $e) {
            $sqlState = $e->getCode() !== '' ? $e->getCode() : ($e->errorInfo[0] ?? '');

            if (in_array($sqlState, ['40001', '40P01'], true)) {
                throw ApiException::reservationConflict();
            }

            throw $e;
        }
    }

    public function paginate(
        array $filters,
        string $branchId,
        string $requestId,
        string $userId,
    ): array {
        return DatabaseContext::run(function () use ($filters, $branchId): array {
            $query = $this->baseQuery($branchId);

            if ($filters['desde'] !== null) {
                $query->where('b.inicio', '>=', $filters['desde']);
            }

            if ($filters['hasta'] !== null) {
                $query->where('b.inicio', '<', $filters['hasta']);
            }

            if ($filters['estado'] !== null) {
                $query->where('b.estado', $filters['estado']);
            }

            $total = (int) (clone $query)->count();

            $rows = $query
                ->select($this->columns())
                ->orderByDesc('b.inicio')
                ->orderByDesc('b.id')
                ->forPage($filters['page'], $filters['per_page'])
                ->get();

            return ['rows' => $rows, 'total' => $total];
        }, $branchId, $userId, $requestId);
    }

    public function formOptions(
        string $branchId,
        string $requestId,
        string $userId,
    ): array {
        return DatabaseContext::run(function () use ($branchId): array {
            return [
                'clients' => DB::table('catalog.client')
                    ->where('branch_id', $branchId)
                    ->select('id', 'nombre', 'contacto')
                    ->orderBy('nombre')
                    ->get(),
                'services' => DB::table('catalog.service')
                    ->where('branch_id', $branchId)
                    ->where('estado', 'activo')
                    ->select('id', 'nombre', 'duracion_min')
                    ->orderBy('nombre')
                    ->get(),
                'camas' => DB::table('catalog.resource')
                    ->where('branch_id', $branchId)
                    ->where('tipo', 'cama')
                    ->select('id', 'nombre', 'estado')
                    ->orderBy('nombre')
                    ->get(),
                'trabajadoras' => DB::table('catalog.resource')
                    ->where('branch_id', $branchId)
                    ->where('tipo', 'trabajadora')
                    ->select('id', 'nombre', 'estado')
                    ->orderBy('nombre')
                    ->get(),
            ];
        }, $branchId, $userId, $requestId);
    }

    public function find(
        string $id,
        string $branchId,
        string $requestId,
        string $userId,
    ): ?object {
        return DatabaseContext::run(function () use ($id, $branchId): ?object {
            return $this->baseQuery($branchId)
                ->where('b.id', $id)
                ->select($this->columns())
                ->first();
        }, $branchId, $userId, $requestId);
    }

    private function baseQuery(string $branchId): Builder
    {
        return DB::table('booking.booking as b')
            ->join('catalog.client as c', 'c.id', '=', 'b.client_id')
            ->join('catalog.service as s', 's.id', '=', 'b.service_id')
            ->join('catalog.resource as cama', 'cama.id', '=', 'b.cama_id')
            ->join('catalog.resource as trab', 'trab.id', '=', 'b.trabajadora_id')
            ->where('b.branch_id', $branchId);
    }

    private function columns(): array
    {
        return [
            'b.id',
            'b.inicio',
            'b.fin',
            'b.estado',
            'b.created_at',
            'b.updated_at',
            'c.id as client_id',
            'c.nombre as client_nombre',
            'c.contacto as client_contacto',
            's.id as service_id',
            's.nombre as service_nombre',
            's.duracion_min as service_duracion_min',
            's.precio as service_precio',
            'cama.id as cama_id',
            'cama.nombre as cama_nombre',
            'trab.id as trabajadora_id',
            'trab.nombre as trabajadora_nombre',
        ];
    }
}

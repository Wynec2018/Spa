<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\DatabaseContext;
use Illuminate\Support\Facades\DB;

final class AuthRepository
{
    public function findActiveBranchUser(string $contacto, string $branchId, string $requestId): ?object
    {
        return DatabaseContext::run(function () use ($contacto, $branchId): ?object {
            return DB::table('auth.app_user as u')
                ->join('core.branch as b', 'b.id', '=', 'u.branch_id')
                ->where('u.branch_id', $branchId)
                ->where('u.contacto', $contacto)
                ->where('b.estado', 'activa')
                ->select([
                    'u.id',
                    'u.branch_id',
                    'u.rol',
                    'u.nombre',
                    'u.contacto',
                    'u.hash_password',
                    'u.estado',
                ])
                ->first();
        }, $branchId, null, $requestId);
    }

    public function findActiveBranchUserById(
        string $id,
        string $branchId,
        string $requestId,
        ?string $actorUserId = null,
    ): ?object {
        return DatabaseContext::run(function () use ($id, $branchId): ?object {
            return DB::table('auth.app_user as u')
                ->join('core.branch as b', 'b.id', '=', 'u.branch_id')
                ->where('u.id', $id)
                ->where('u.branch_id', $branchId)
                ->where('u.estado', 'activo')
                ->where('b.estado', 'activa')
                ->select([
                    'u.id',
                    'u.branch_id',
                    'u.rol',
                    'u.nombre',
                    'u.contacto',
                ])
                ->first();
        }, $branchId, $actorUserId, $requestId);
    }

    public function updatePasswordHash(
        string $id,
        string $hash,
        string $branchId,
        string $requestId,
    ): void {
        DatabaseContext::run(function () use ($id, $hash): void {
            DB::table('auth.app_user')
                ->where('id', $id)
                ->update(['hash_password' => $hash]);
        }, $branchId, $id, $requestId);
    }
}

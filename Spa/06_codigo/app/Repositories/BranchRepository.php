<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\DatabaseContext;
use Illuminate\Support\Facades\DB;

final class BranchRepository
{
    public function find(string $branchId, string $requestId): ?object
    {
        return DatabaseContext::run(
            fn (): ?object => DB::table('core.branch')
                ->where('id', $branchId)
                ->select(['id', 'nombre'])
                ->first(),
            branchId: $branchId,
            correlationId: $requestId,
        );
    }
}

<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class DatabaseContext
{
    private const ISOLATION_LEVELS = ['read committed', 'repeatable read', 'serializable'];

    public static function run(
        Closure $callback,
        ?string $branchId = null,
        ?string $userId = null,
        ?string $correlationId = null,
        ?string $isolation = null,
    ): mixed {
        $branchId ??= (string) config('spa.branch_id');
        $correlationId ??= (string) Str::uuid();
        $level = $isolation === null ? null : strtolower(trim($isolation));

        if ($level !== null && ! in_array($level, self::ISOLATION_LEVELS, true)) {
            throw new InvalidArgumentException('Nivel de aislamiento no permitido.');
        }

        return DB::transaction(function () use ($callback, $branchId, $userId, $correlationId, $level) {
            if ($level !== null) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL '.strtoupper($level));
            }

            DB::selectOne("select set_config('app.branch_id', ?, true)", [$branchId]);
            DB::selectOne("select set_config('app.user_id', ?, true)", [$userId ?? '']);
            DB::selectOne("select set_config('app.correlation_id', ?, true)", [$correlationId]);

            return $callback($correlationId);
        });
    }
}

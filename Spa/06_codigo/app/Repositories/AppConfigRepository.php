<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\DatabaseContext;
use Illuminate\Support\Facades\DB;

final class AppConfigRepository
{
    public function value(string $key, string $branchId, string $requestId): mixed
    {
        return DatabaseContext::run(function () use ($key): mixed {
            $value = DB::table('core.app_config')->where('clave', $key)->value('valor');

            if (! is_string($value)) {
                return $value;
            }

            $decoded = json_decode($value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }, branchId: $branchId, correlationId: $requestId);
    }
}

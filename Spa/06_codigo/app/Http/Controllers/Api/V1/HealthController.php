<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Support\ApiResponse;
use App\Support\DatabaseContext;
use App\Support\RequestId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class HealthController
{
    public function __invoke(Request $request): JsonResponse
    {
        $requestId = RequestId::from($request);

        try {
            $data = DatabaseContext::run(function (): array {
                $row = DB::selectOne("select current_setting('app.branch_id', true) as branch_id");

                if ($row->branch_id !== (string) config('spa.branch_id')) {
                    throw new RuntimeException('El contexto RLS no coincide con la sucursal configurada.');
                }

                DB::selectOne('select 1 as ok');

                return ['status' => 'ok', 'db' => 'ok'];
            }, correlationId: $requestId);
        } catch (Throwable $e) {
            report($e);

            return ApiResponse::error($requestId, ApiException::serviceUnavailable([
                'status' => 'degraded',
                'db' => 'error',
            ]));
        }

        return ApiResponse::success($data);
    }
}

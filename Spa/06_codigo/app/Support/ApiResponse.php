<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function success(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
        ], $status, $headers);
    }

    public static function collection(array $data, array $meta): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => $meta,
        ]);
    }

    public static function error(string $requestId, ApiException $exception, array $headers = []): JsonResponse
    {
        $error = [
            'code' => $exception->errorCode,
            'message' => $exception->getMessage(),
        ];

        if ($exception->details !== null && $exception->details !== []) {
            $error['details'] = $exception->details;
        }

        $payload = ['success' => false];

        if ($exception->data !== null) {
            $payload['data'] = $exception->data;
        }

        $payload['error'] = $error;
        $payload['request_id'] = $requestId;

        return response()->json($payload, $exception->status, $headers);
    }

    public static function internalError(string $requestId): JsonResponse
    {
        return self::error($requestId, ApiException::internal());
    }
}

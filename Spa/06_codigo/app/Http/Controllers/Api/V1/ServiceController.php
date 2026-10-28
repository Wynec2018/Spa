<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Resources\ServiceResource;
use App\Repositories\ServiceRepository;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use App\Support\JsonBody;
use App\Support\RequestId;
use App\Support\RouteId;
use App\Validation\ServiceValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ServiceController
{
    public function __construct(private readonly ServiceRepository $services) {}

    public function index(Request $request): JsonResponse
    {
        $requestId = RequestId::from($request);
        $filters = ServiceValidator::query($request->query());
        $branchId = BranchContext::id();
        $result = $this->services->paginate($filters, $branchId, $requestId);
        $total = (int) $result['total'];

        return ApiResponse::collection(
            ServiceResource::collection($result['rows'])->resolve(),
            [
                'page' => $filters['page'],
                'per_page' => $filters['per_page'],
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $filters['per_page'])),
            ],
        );
    }

    public function store(Request $request): JsonResponse
    {
        $requestId = RequestId::from($request);
        $data = ServiceValidator::payload(JsonBody::parse($request));
        $branchId = BranchContext::id();
        $row = $this->services->create($data, $branchId, $requestId);

        return ApiResponse::success(
            (new ServiceResource($row))->resolve(),
            201,
            ['Location' => '/api/v1/servicios/'.$row->id],
        );
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $requestId = RequestId::from($request);
        RouteId::uuidV4($id);
        $data = ServiceValidator::payload(JsonBody::parse($request));
        $branchId = BranchContext::id();
        $row = $this->services->update($id, $data, $branchId, $requestId);

        if ($row === null) {
            throw ApiException::serviceNotFound();
        }

        return ApiResponse::success((new ServiceResource($row))->resolve());
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $requestId = RequestId::from($request);
        RouteId::uuidV4($id);
        $branchId = BranchContext::id();
        $row = $this->services->deactivate($id, $branchId, $requestId);

        if ($row === null) {
            throw ApiException::serviceNotFound();
        }

        return ApiResponse::success((new ServiceResource($row))->resolve());
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $requestId = RequestId::from($request);
        RouteId::uuidV4($id);
        $branchId = BranchContext::id();
        $row = $this->services->find($id, $branchId, $requestId);

        if ($row === null) {
            throw ApiException::serviceNotFound();
        }

        return ApiResponse::success((new ServiceResource($row))->resolve());
    }
}

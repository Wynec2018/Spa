<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Middleware\AuthenticateSession;
use App\Http\Resources\ReservationResource;
use App\Repositories\ReservationRepository;
use App\Support\ApiResponse;
use App\Support\JsonBody;
use App\Support\RouteId;
use App\Validation\ReservationQueryValidator;
use App\Validation\ReservationStateValidator;
use App\Validation\ReservationValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReservationController
{
    public function __construct(private readonly ReservationRepository $reservations) {}

    public function index(Request $request): JsonResponse
    {
        $context = $this->context($request);
        $filters = ReservationQueryValidator::query($request->query());
        $result = $this->reservations->paginate(
            $filters,
            $context['branch_id'],
            $context['request_id'],
            $context['user_id'],
        );
        $total = (int) $result['total'];

        return ApiResponse::collection(
            ReservationResource::collection($result['rows'])->resolve(),
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
        $context = $this->context($request);
        $data = ReservationValidator::payload(JsonBody::parse($request));
        $row = $this->reservations->create(
            $data,
            $context['branch_id'],
            $context['request_id'],
            $context['user_id'],
        );

        return ApiResponse::success(
            (new ReservationResource($row))->resolve(),
            201,
            ['Location' => '/api/v1/reservas/'.$row->id],
        );
    }

    public function updateState(Request $request, string $id): JsonResponse
    {
        $context = $this->context($request);
        RouteId::uuidV4($id);
        ReservationStateValidator::payload(JsonBody::parse($request));
        $row = $this->reservations->cancel(
            $id,
            $context['branch_id'],
            $context['request_id'],
            $context['user_id'],
        );

        if ($row === null) {
            throw ApiException::reservationNotFound();
        }

        return ApiResponse::success((new ReservationResource($row))->resolve());
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $context = $this->context($request);
        RouteId::uuidV4($id);
        $row = $this->reservations->find(
            $id,
            $context['branch_id'],
            $context['request_id'],
            $context['user_id'],
        );

        if ($row === null) {
            throw ApiException::reservationNotFound();
        }

        return ApiResponse::success((new ReservationResource($row))->resolve());
    }

    private function context(Request $request): array
    {
        $user = $request->attributes->get(AuthenticateSession::USER_ATTRIBUTE);

        return [
            'user_id' => (string) $user->id,
            'branch_id' => (string) $request->attributes->get(AuthenticateSession::BRANCH_ATTRIBUTE),
            'request_id' => (string) $request->attributes->get(AuthenticateSession::REQUEST_ID_ATTRIBUTE),
        ];
    }
}

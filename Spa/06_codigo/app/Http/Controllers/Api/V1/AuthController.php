<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Resources\AuthUserResource;
use App\Services\AuthService;
use App\Support\ApiResponse;
use App\Support\JsonBody;
use App\Support\RequestId;
use App\Support\SessionAuth;
use App\Validation\LoginValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController
{
    public function __construct(private readonly AuthService $auth) {}

    public function login(Request $request): JsonResponse
    {
        $requestId = RequestId::from($request);
        $data = LoginValidator::payload(JsonBody::parse($request));
        $user = $this->auth->attempt(
            $data['contacto'],
            $data['password'],
            $data['branch_id'],
            $requestId,
        );

        $request->session()->regenerate();
        $request->session()->put('auth_user_id', $user->id);
        $request->session()->put('auth_branch_id', $user->branch_id);
        $request->session()->put('auth_rol', $user->rol);

        return ApiResponse::success((new AuthUserResource($user))->resolve());
    }

    public function me(Request $request): JsonResponse
    {
        $requestId = RequestId::from($request);
        [$userId, $branchId] = SessionAuth::credentials($request);
        $user = $this->auth->current($userId, $branchId, $requestId);

        if ($user === null) {
            $request->session()->invalidate();
            throw ApiException::unauthenticated();
        }

        return ApiResponse::success((new AuthUserResource($user))->resolve());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::success(['message' => 'Sesión cerrada.']);
    }
}

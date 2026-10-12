<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Validators\AuthValidator;

final class AuthController
{
    public function __construct(
        private readonly AuthValidator $validator = new AuthValidator(),
        private readonly AuthService $service = new AuthService(),
    ) {
    }

    // CP-BACK-03: POST /api/v1/auth/login
    public function login(Request $request): never
    {
        Response::execute(function () use ($request): array {
            $data = $this->validator->validateLoginBody($request->body());
            return $this->service->login($data['email'], $data['password']);
        }, 200);
    }

    // CP-BACK-04: GET /api/v1/auth/me
    public function me(Request $request): never
    {
        Response::execute(fn (): array => $this->service->me(), 200);
    }

    // CP-BACK-04: POST /api/v1/auth/logout
    public function logout(Request $request): never
    {
        Response::execute(fn (): array => $this->service->logout(), 200);
    }
}

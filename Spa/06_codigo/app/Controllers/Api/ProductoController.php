<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\ProductoService;
use App\Validators\ProductoValidator;

final class ProductoController
{
    public function __construct(
        private readonly ProductoValidator $validator = new ProductoValidator(),
        private readonly ProductoService $service = new ProductoService(),
    ) {
    }

    // CP-BACK-05: GET /api/v1/productos
    public function index(Request $request): never
    {
        Response::execute(function (): array {
            AuthService::requireSession();
            return $this->service->list();
        }, 200);
    }

    // CP-BACK-05: GET /api/v1/productos/{id}
    public function show(Request $request): never
    {
        Response::execute(function () use ($request): array {
            AuthService::requireSession();
            $id = $this->validator->validateId($request->param('id'));
            return $this->service->get($id);
        }, 200);
    }

    // CP-BACK-06: POST /api/v1/productos
    public function store(Request $request): never
    {
        Response::execute(function () use ($request): array {
            AuthService::requireSession();
            $data = $this->validator->validateBody($request->body());
            return $this->service->create($data);
        }, 201);
    }

    // CP-BACK-07: PUT /api/v1/productos/{id}
    public function update(Request $request): never
    {
        Response::execute(function () use ($request): array {
            AuthService::requireSession();
            $id = $this->validator->validateId($request->param('id'));
            $data = $this->validator->validateBody($request->body());
            return $this->service->update($id, $data);
        }, 200);
    }

    // CP-BACK-08: DELETE lógico estado='inactivo'
    public function destroy(Request $request): never
    {
        Response::execute(function () use ($request): array {
            AuthService::requireSession();
            $id = $this->validator->validateId($request->param('id'));
            return $this->service->deactivate($id);
        }, 200);
    }
}

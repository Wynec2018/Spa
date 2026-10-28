<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Exceptions\ApiException;
use App\Exceptions\DuplicateServiceException;
use App\Repositories\AppConfigRepository;
use App\Repositories\BranchRepository;
use App\Repositories\ServiceRepository;
use App\Services\AuthService;
use App\Support\RequestId;
use App\Support\RouteId;
use App\Support\SessionAuth;
use App\Validation\ServiceValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class ServicePageController
{
    private const PER_PAGE = 10;

    public function index(
        Request $request,
        AuthService $auth,
        BranchRepository $branches,
        ServiceRepository $services,
        AppConfigRepository $config,
    ): View|RedirectResponse {
        $context = $this->context($request, $auth);

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        ['branchId' => $branchId, 'requestId' => $requestId, 'user' => $user] = $context;

        $q = $this->text($request->query('q'));
        $estado = $this->estado($request->query('estado'));
        $page = $this->page($request->query('page'));

        $result = $this->paginate($services, $branchId, $requestId, $q, $estado, $page);
        $total = max(0, (int) $result['total']);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));

        if ($page > $lastPage) {
            $page = $lastPage;
            $result = $this->paginate($services, $branchId, $requestId, $q, $estado, $page);
        }

        $branch = $branches->find($branchId, $requestId);

        return view('services.index', [
            'user' => $user,
            'branchName' => $branch?->nombre ?? 'Sede principal',
            'currency' => $this->currency($config, $branchId, $requestId),
            'services' => $result['rows'],
            'total' => $total,
            'page' => $page,
            'lastPage' => $lastPage,
            'perPage' => self::PER_PAGE,
            'from' => $total === 0 ? 0 : (($page - 1) * self::PER_PAGE) + 1,
            'to' => $total === 0 ? 0 : min($page * self::PER_PAGE, $total),
            'filters' => [
                'q' => $q,
                'estado' => $estado,
            ],
        ]);
    }

    public function create(
        Request $request,
        AuthService $auth,
        BranchRepository $branches,
        AppConfigRepository $config,
    ): View|RedirectResponse {
        $context = $this->context($request, $auth);

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        ['branchId' => $branchId, 'requestId' => $requestId, 'user' => $user] = $context;
        $branch = $branches->find($branchId, $requestId);

        return view('services.create', [
            'user' => $user,
            'branchName' => $branch?->nombre ?? 'Sede principal',
            'currency' => $this->currency($config, $branchId, $requestId),
        ]);
    }

    public function store(Request $request, AuthService $auth, ServiceRepository $services): RedirectResponse
    {
        $context = $this->context($request, $auth);

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        ['branchId' => $branchId, 'requestId' => $requestId] = $context;
        $data = $this->validatedPayload($request);

        try {
            $service = $services->create($data, $branchId, $requestId);
        } catch (DuplicateServiceException) {
            throw ValidationException::withMessages([
                'nombre' => 'Ya existe un servicio con ese nombre en la sede.',
            ]);
        }

        return redirect()
            ->route('services.index')
            ->with('status', 'Servicio “'.$service->nombre.'” creado.');
    }

    public function edit(
        Request $request,
        AuthService $auth,
        BranchRepository $branches,
        ServiceRepository $services,
        AppConfigRepository $config,
        string $id,
    ): View|RedirectResponse {
        $context = $this->context($request, $auth);

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        ['branchId' => $branchId, 'requestId' => $requestId, 'user' => $user] = $context;
        $service = $services->find($this->routeId($id), $branchId, $requestId);

        if ($service === null) {
            abort(404);
        }

        $branch = $branches->find($branchId, $requestId);

        return view('services.edit', [
            'user' => $user,
            'branchName' => $branch?->nombre ?? 'Sede principal',
            'currency' => $this->currency($config, $branchId, $requestId),
            'service' => $service,
        ]);
    }

    public function update(
        Request $request,
        AuthService $auth,
        ServiceRepository $services,
        string $id,
    ): RedirectResponse {
        $context = $this->context($request, $auth);

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        ['branchId' => $branchId, 'requestId' => $requestId] = $context;
        $id = $this->routeId($id);
        $data = $this->validatedPayload($request);

        try {
            $service = $services->update($id, $data, $branchId, $requestId);
        } catch (DuplicateServiceException) {
            throw ValidationException::withMessages([
                'nombre' => 'Ya existe un servicio con ese nombre en la sede.',
            ]);
        }

        if ($service === null) {
            abort(404);
        }

        return redirect()
            ->route('services.index')
            ->with('status', 'Servicio “'.$service->nombre.'” actualizado.');
    }

    public function deactivate(
        Request $request,
        AuthService $auth,
        ServiceRepository $services,
        string $id,
    ): RedirectResponse {
        $context = $this->context($request, $auth);

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        ['branchId' => $branchId, 'requestId' => $requestId] = $context;
        $service = $services->deactivate($this->routeId($id), $branchId, $requestId);

        if ($service === null) {
            abort(404);
        }

        return redirect()
            ->route('services.index')
            ->with('status', 'Servicio “'.$service->nombre.'” inactivado.');
    }

    private function validatedPayload(Request $request): array
    {
        $payload = $request->only(['nombre', 'duracion_min', 'precio']);

        if (is_string($payload['precio'] ?? null)) {
            $price = trim($payload['precio']);

            if (preg_match('/^\d{1,10},\d{1,2}$/', $price) === 1) {
                $payload['precio'] = str_replace(',', '.', $price);
            }
        }

        try {
            return ServiceValidator::payload($payload);
        } catch (ApiException $e) {
            if (in_array($e->status, [400, 422], true)) {
                throw ValidationException::withMessages($this->validationMessages($e));
            }

            throw $e;
        }
    }

    private function routeId(string $id): string
    {
        try {
            return RouteId::uuidV4($id);
        } catch (ApiException) {
            abort(404);
        }
    }

    private function context(Request $request, AuthService $auth): array|RedirectResponse
    {
        try {
            [$userId, $branchId] = SessionAuth::credentials($request);
        } catch (ApiException) {
            return redirect()->route('login');
        }

        $requestId = RequestId::from($request);
        $user = $auth->current($userId, $branchId, $requestId);

        if ($user === null) {
            $request->session()->invalidate();

            return redirect()->route('login');
        }

        return [
            'branchId' => $branchId,
            'requestId' => $requestId,
            'user' => $user,
        ];
    }

    private function currency(AppConfigRepository $config, string $branchId, string $requestId): string
    {
        $currency = $config->value('moneda', $branchId, $requestId);

        return is_string($currency) && preg_match('/^[A-Za-z]{3}$/', $currency) === 1
            ? strtoupper($currency)
            : 'BOB';
    }

    private function validationMessages(ApiException $e): array
    {
        $messages = [];

        foreach ($e->details ?? [] as $field => $errors) {
            if (is_array($errors) && $errors !== []) {
                $messages[$field] = implode(' ', array_map('strval', $errors));
            }
        }

        return $messages === [] ? ['nombre' => $e->getMessage()] : $messages;
    }

    private function paginate(
        ServiceRepository $services,
        string $branchId,
        string $requestId,
        string $q,
        ?string $estado,
        int $page,
    ): array {
        return $services->paginate([
            'estado' => $estado,
            'q' => $q === '' ? null : $q,
            'page' => $page,
            'per_page' => self::PER_PAGE,
            'sort' => 'nombre',
            'order' => 'asc',
        ], $branchId, $requestId);
    }

    private function text(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return mb_substr(trim($value), 0, 120);
    }

    private function estado(mixed $value): ?string
    {
        return is_string($value) && in_array($value, ['activo', 'inactivo'], true) ? $value : null;
    }

    private function page(mixed $value): int
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
            return 1;
        }

        return max(1, (int) $value);
    }
}

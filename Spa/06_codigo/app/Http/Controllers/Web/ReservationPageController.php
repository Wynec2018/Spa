<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Exceptions\ApiException;
use App\Repositories\BranchRepository;
use App\Repositories\ReservationRepository;
use App\Services\AuthService;
use App\Support\RequestId;
use App\Support\RouteId;
use App\Support\SessionAuth;
use App\Validation\ReservationValidator;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class ReservationPageController
{
    private const PER_PAGE = 10;

    private const ESTADOS = ['pendiente', 'confirmada', 'cancelada', 'expirada', 'atendida'];

    public function index(
        Request $request,
        AuthService $auth,
        BranchRepository $branches,
        ReservationRepository $reservations,
    ): View|RedirectResponse {
        $context = $this->context($request, $auth);

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        ['branchId' => $branchId, 'requestId' => $requestId, 'user' => $user] = $context;

        $fecha = $this->fecha($request->query('fecha'));
        $estado = $this->estado($request->query('estado'));
        $page = $this->page($request->query('page'));

        $result = $this->paginate($reservations, $branchId, $requestId, $user->id, $fecha, $estado, $page);
        $total = max(0, (int) $result['total']);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));

        if ($page > $lastPage) {
            $page = $lastPage;
            $result = $this->paginate($reservations, $branchId, $requestId, $user->id, $fecha, $estado, $page);
        }

        $branch = $branches->find($branchId, $requestId);
        $today = CarbonImmutable::now('UTC')->startOfDay();

        return view('reservations.index', [
            'user' => $user,
            'branchName' => $branch?->nombre ?? 'Sede principal',
            'reservations' => $result['rows'],
            'total' => $total,
            'page' => $page,
            'lastPage' => $lastPage,
            'perPage' => self::PER_PAGE,
            'from' => $total === 0 ? 0 : (($page - 1) * self::PER_PAGE) + 1,
            'to' => $total === 0 ? 0 : min($page * self::PER_PAGE, $total),
            'filters' => [
                'fecha' => $fecha->format('Y-m-d'),
                'estado' => $estado,
            ],
            'today' => $today->format('Y-m-d'),
        ]);
    }

    public function create(
        Request $request,
        AuthService $auth,
        BranchRepository $branches,
        ReservationRepository $reservations,
    ): View|RedirectResponse {
        $context = $this->context($request, $auth);

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        ['branchId' => $branchId, 'requestId' => $requestId, 'user' => $user] = $context;
        $branch = $branches->find($branchId, $requestId);

        return view('reservations.create', [
            'user' => $user,
            'branchName' => $branch?->nombre ?? 'Sede principal',
            'options' => $reservations->formOptions($branchId, $requestId, $user->id),
        ]);
    }

    public function store(
        Request $request,
        AuthService $auth,
        ReservationRepository $reservations,
    ): RedirectResponse {
        $context = $this->context($request, $auth);

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        ['branchId' => $branchId, 'requestId' => $requestId, 'user' => $user] = $context;
        $data = $this->validatedPayload($request);

        try {
            $reservation = $reservations->create($data, $branchId, $requestId, $user->id);
        } catch (ApiException $e) {
            $messages = $this->reservationMessages($e);

            if ($messages !== []) {
                throw ValidationException::withMessages($messages);
            }

            throw $e;
        }

        $inicio = CarbonImmutable::parse($reservation->inicio)->utc();
        $fin = CarbonImmutable::parse($reservation->fin)->utc();

        return redirect()
            ->route('reservations.index', ['fecha' => $inicio->format('Y-m-d')])
            ->with(
                'status',
                'Reserva de '.$reservation->client_nombre.' creada para el '
                .$inicio->format('d/m/Y').' de '.$inicio->format('H:i')
                .' a '.$fin->format('H:i').' UTC.',
            );
    }

    public function cancel(
        Request $request,
        AuthService $auth,
        ReservationRepository $reservations,
        string $id,
    ): RedirectResponse {
        $context = $this->context($request, $auth);

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        ['branchId' => $branchId, 'requestId' => $requestId, 'user' => $user] = $context;

        try {
            $reservation = $reservations->cancel(
                $this->routeId($id),
                $branchId,
                $requestId,
                $user->id,
            );
        } catch (ApiException $e) {
            if ($e->errorCode === 'INVALID_STATE_TRANSITION') {
                return $this->redirectToIndex($request)
                    ->with('error', 'Solo se pueden cancelar reservas pendientes.');
            }

            throw $e;
        }

        if ($reservation === null) {
            abort(404);
        }

        $inicio = CarbonImmutable::parse($reservation->inicio)->utc();
        $fin = CarbonImmutable::parse($reservation->fin)->utc();

        return $this->redirectToIndex($request)
            ->with(
                'status',
                'Reserva de '.$reservation->client_nombre.' cancelada para el '
                .$inicio->format('d/m/Y').' de '.$inicio->format('H:i')
                .' a '.$fin->format('H:i').' UTC.',
            );
    }

    private function redirectToIndex(Request $request): RedirectResponse
    {
        $query = ['fecha' => $this->fecha($request->input('fecha'))->format('Y-m-d')];
        $estado = $this->estado($request->input('estado'));

        if ($estado !== null) {
            $query['estado'] = $estado;
        }

        $page = $this->page($request->input('page'));

        if ($page > 1) {
            $query['page'] = $page;
        }

        return redirect()->route('reservations.index', $query);
    }

    private function routeId(string $id): string
    {
        try {
            return RouteId::uuidV4($id);
        } catch (ApiException) {
            abort(404);
        }
    }

    private function paginate(
        ReservationRepository $reservations,
        string $branchId,
        string $requestId,
        string $userId,
        CarbonImmutable $fecha,
        ?string $estado,
        int $page,
    ): array {
        $desde = $fecha->startOfDay();
        $hasta = $desde->addDay();

        return $reservations->paginate([
            'desde' => $desde->format('Y-m-d H:i:sP'),
            'hasta' => $hasta->format('Y-m-d H:i:sP'),
            'estado' => $estado,
            'page' => $page,
            'per_page' => self::PER_PAGE,
        ], $branchId, $requestId, $userId);
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

    private function validatedPayload(Request $request): array
    {
        $payload = $request->only(['client_id', 'service_id', 'cama_id', 'trabajadora_id', 'inicio']);

        if (is_string($payload['inicio'] ?? null)) {
            $inicio = trim($payload['inicio']);

            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $inicio) === 1) {
                $inicio .= ':00Z';
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $inicio) === 1) {
                $inicio .= 'Z';
            }

            $payload['inicio'] = $inicio;
        }

        try {
            return ReservationValidator::payload($payload);
        } catch (ApiException $e) {
            if (in_array($e->status, [400, 422], true)) {
                throw ValidationException::withMessages($this->validationMessages($e));
            }

            throw $e;
        }
    }

    private function reservationMessages(ApiException $e): array
    {
        return match ($e->errorCode) {
            'CLIENT_NOT_FOUND' => [
                'client_id' => 'El cliente ya no existe en la sede. Elige otro.',
            ],
            'SERVICE_NOT_FOUND' => [
                'service_id' => 'El servicio ya no existe en la sede. Elige otro.',
            ],
            'SERVICE_INACTIVE' => [
                'service_id' => 'El servicio ya no está activo. Elige otro.',
            ],
            'RESOURCE_NOT_FOUND' => [
                'reserva' => 'La cama o la trabajadora ya no existe en la sede. Actualiza la página y elige otras.',
            ],
            'RESOURCE_UNAVAILABLE' => [
                'reserva' => 'La cama o la trabajadora ya no está disponible. Actualiza la página y elige otras.',
            ],
            'RESERVATION_CONFLICT' => [
                'reserva' => 'La cama o la trabajadora ya tiene una reserva en esa franja. Elige otro horario o recursos.',
            ],
            default => [],
        };
    }

    private function validationMessages(ApiException $e): array
    {
        $messages = [];

        foreach ($e->details ?? [] as $field => $errors) {
            if (is_array($errors) && $errors !== []) {
                $messages[$field] = implode(' ', array_map('strval', $errors));
            }
        }

        return $messages === [] ? ['reserva' => $e->getMessage()] : $messages;
    }

    private function fecha(mixed $value): CarbonImmutable
    {
        $today = CarbonImmutable::now('UTC')->startOfDay();

        if (! is_string($value) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($value), $parts) !== 1) {
            return $today;
        }

        $year = (int) $parts[1];
        $month = (int) $parts[2];
        $day = (int) $parts[3];

        return checkdate($month, $day, $year)
            ? CarbonImmutable::create($year, $month, $day, 0, 0, 0, 'UTC')
            : $today;
    }

    private function estado(mixed $value): ?string
    {
        return is_string($value) && in_array($value, self::ESTADOS, true) ? $value : null;
    }

    private function page(mixed $value): int
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
            return 1;
        }

        return max(1, (int) $value);
    }
}

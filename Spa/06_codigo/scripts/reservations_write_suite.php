<?php

declare(strict_types=1);

use App\Exceptions\ApiException;
use App\Repositories\ReservationRepository;
use App\Support\DatabaseContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

$base = rtrim((string) (getenv('API_BASE_URL') ?: 'http://127.0.0.1:8000'), '/');
$api = $base.'/api/v1';
$branchId = trim((string) config('spa.branch_id'));
$superPassword = (string) (getenv('DEMO_SUPER_PASSWORD') ?: '');
$checks = 0;
$failures = [];
$createdId = null;

$clientId = '55555555-5555-4555-8555-555555555501';
$serviceId = '55555555-5555-4555-8555-555555555502';
$inactiveServiceId = '55555555-5555-4555-8555-555555555540';
$existingCamaId = '55555555-5555-4555-8555-555555555503';
$existingWorkerId = '55555555-5555-4555-8555-555555555504';
$existingBookingId = '66666666-6666-4666-8666-666666666601';
$successCamaId = '55555555-5555-4555-8555-555555555520';
$successWorkerId = '55555555-5555-4555-8555-555555555521';
$camaConflictWorkerId = '55555555-5555-4555-8555-555555555522';
$workerConflictCamaId = '55555555-5555-4555-8555-555555555525';
$workerConflictExistingCamaId = '55555555-5555-4555-8555-555555555523';
$workerConflictExistingWorkerId = '55555555-5555-4555-8555-555555555524';
$workerConflictBookingId = '66666666-6666-4666-8666-666666666610';
$inactiveCamaId = '55555555-5555-4555-8555-555555555526';
$inactiveWorkerId = '55555555-5555-4555-8555-555555555527';
$unavailableCamaId = '55555555-5555-4555-8555-555555555528';
$unavailableWorkerId = '55555555-5555-4555-8555-555555555529';
$missingClientCamaId = '55555555-5555-4555-8555-555555555530';
$missingClientWorkerId = '55555555-5555-4555-8555-555555555531';
$occupiedWorkerCamaId = '55555555-5555-4555-8555-555555555532';

function check(string $label, bool $ok, mixed $detail = null): void
{
    global $checks, $failures;

    $checks++;

    if (! $ok) {
        $failures[] = $label.($detail === null ? '' : ' | '.summarize($detail));
    }
}

function summarize(mixed $value): string
{
    if (is_string($value)) {
        return $value;
    }

    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $encoded === false ? gettype($value) : mb_substr($encoded, 0, 700);
}

function request(
    string $method,
    string $url,
    ?array $body,
    ?string $cookieJar,
): array {
    $curl = curl_init($url);
    $headers = ['Accept: application/json'];

    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 10,
    ];

    if ($cookieJar !== null) {
        $options[CURLOPT_COOKIEJAR] = $cookieJar;
        $options[CURLOPT_COOKIEFILE] = $cookieJar;
    }

    curl_setopt_array($curl, $options);

    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    $raw = curl_exec($curl);

    if ($raw === false) {
        $error = curl_error($curl);
        curl_close($curl);
        throw new RuntimeException('cURL: '.$error);
    }

    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    curl_close($curl);

    return [
        'status' => $status,
        'headers' => substr($raw, 0, $headerSize),
        'body' => substr($raw, $headerSize),
        'json' => json_decode(substr($raw, $headerSize), true),
    ];
}

function errorCode(array $response): ?string
{
    return $response['json']['error']['code'] ?? null;
}

if ($branchId === '' || $superPassword === '') {
    fwrite(STDERR, 'Faltan APP_BRANCH_ID o DEMO_SUPER_PASSWORD.'.PHP_EOL);
    exit(2);
}

$userId = null;

DatabaseContext::run(function () use (
    $branchId,
    $clientId,
    $serviceId,
    $inactiveServiceId,
    $existingCamaId,
    $existingWorkerId,
    $existingBookingId,
    $successCamaId,
    $successWorkerId,
    $camaConflictWorkerId,
    $workerConflictCamaId,
    $workerConflictExistingCamaId,
    $workerConflictExistingWorkerId,
    $workerConflictBookingId,
    $inactiveCamaId,
    $inactiveWorkerId,
    $unavailableCamaId,
    $unavailableWorkerId,
    $missingClientCamaId,
    $missingClientWorkerId,
    $occupiedWorkerCamaId,
    &$userId,
): void {
    $userId = DB::table('auth.app_user')
        ->where('branch_id', $branchId)
        ->where('contacto', 'super@spa.local')
        ->value('id');

    if ($userId === null) {
        throw new RuntimeException('Falta el usuario demo super@spa.local.');
    }

    if (! DB::table('catalog.client')->where('id', $clientId)->exists()) {
        DB::statement(
            "INSERT INTO catalog.client (id, branch_id, nombre, documento_cifrado, documento_hash, contacto, created_at, updated_at)
             VALUES (?, ?, ?, decode(?, 'hex'), ?, ?, now(), now())",
            [
                $clientId,
                $branchId,
                'Cliente CP-BACK-06',
                bin2hex(random_bytes(16)),
                hash('sha256', 'cp-back-06-document'),
                'cliente.cpback06@spa.local',
            ],
        );
    }

    if (! DB::table('catalog.service')->where('id', $serviceId)->exists()) {
        DB::table('catalog.service')->insert([
            'id' => $serviceId,
            'branch_id' => $branchId,
            'nombre' => 'Servicio CP-BACK-06',
            'duracion_min' => 60,
            'precio' => 100.00,
            'estado' => 'activo',
        ]);
    }

    if (! DB::table('catalog.service')->where('id', $inactiveServiceId)->exists()) {
        DB::table('catalog.service')->insert([
            'id' => $inactiveServiceId,
            'branch_id' => $branchId,
            'nombre' => 'Servicio inactivo CP-BACK-06',
            'duracion_min' => 30,
            'precio' => 50.00,
            'estado' => 'inactivo',
        ]);
    }

    $resources = [
        [$existingCamaId, 'cama', 'Cama CP-BACK-06 existente'],
        [$existingWorkerId, 'trabajadora', 'Trabajadora CP-BACK-06 existente'],
        [$successCamaId, 'cama', 'Cama CP-BACK-06 alta'],
        [$successWorkerId, 'trabajadora', 'Trabajadora CP-BACK-06 alta'],
        [$camaConflictWorkerId, 'trabajadora', 'Trabajadora CP-BACK-06 conflicto cama'],
        [$workerConflictCamaId, 'cama', 'Cama CP-BACK-06 conflicto trabajadora'],
        [$workerConflictExistingCamaId, 'cama', 'Cama CP-BACK-06 trabajadora existente'],
        [$workerConflictExistingWorkerId, 'trabajadora', 'Trabajadora CP-BACK-06 conflicto'],
        [$inactiveCamaId, 'cama', 'Cama CP-BACK-06 servicio inactivo'],
        [$inactiveWorkerId, 'trabajadora', 'Trabajadora CP-BACK-06 servicio inactivo'],
        [$unavailableCamaId, 'cama', 'Cama CP-BACK-06 no disponible'],
        [$unavailableWorkerId, 'trabajadora', 'Trabajadora CP-BACK-06 no disponible'],
        [$missingClientCamaId, 'cama', 'Cama CP-BACK-06 cliente ausente'],
        [$missingClientWorkerId, 'trabajadora', 'Trabajadora CP-BACK-06 cliente ausente'],
        [$occupiedWorkerCamaId, 'cama', 'Cama CP-BACK-06 trabajadora ocupada'],
    ];

    foreach ($resources as [$id, $tipo, $nombre]) {
        if (! DB::table('catalog.resource')->where('id', $id)->exists()) {
            DB::table('catalog.resource')->insert([
                'id' => $id,
                'branch_id' => $branchId,
                'tipo' => $tipo,
                'nombre' => $nombre,
                'estado' => 'libre',
            ]);
        }
    }

    DB::table('booking.booking')
        ->where('branch_id', $branchId)
        ->whereIn('cama_id', [$successCamaId, $workerConflictCamaId, $workerConflictExistingCamaId])
        ->whereIn('estado', ['pendiente', 'confirmada'])
        ->update(['estado' => 'cancelada', 'updated_at' => now()]);

    DB::table('booking.booking')
        ->where('branch_id', $branchId)
        ->whereIn('trabajadora_id', [$successWorkerId, $camaConflictWorkerId])
        ->whereIn('estado', ['pendiente', 'confirmada'])
        ->update(['estado' => 'cancelada', 'updated_at' => now()]);

    DB::table('catalog.resource')->where('branch_id', $branchId)->whereIn('id', [
        $existingCamaId,
        $existingWorkerId,
        $successCamaId,
        $successWorkerId,
        $camaConflictWorkerId,
        $workerConflictCamaId,
        $workerConflictExistingCamaId,
        $workerConflictExistingWorkerId,
        $inactiveCamaId,
        $inactiveWorkerId,
        $unavailableWorkerId,
        $missingClientCamaId,
        $missingClientWorkerId,
        $occupiedWorkerCamaId,
    ])->update(['estado' => 'libre']);

    DB::table('catalog.resource')->where('id', $unavailableCamaId)->update(['estado' => 'no_disponible']);

    $ensureBooking = function (string $id, string $camaId, string $trabajadoraId, string $inicio, string $fin) use ($branchId, $clientId, $serviceId): void {
        $exists = DB::table('booking.booking')->where('id', $id)->exists();

        if ($exists) {
            DB::table('booking.booking')->where('id', $id)->update([
                'branch_id' => $branchId,
                'client_id' => $clientId,
                'service_id' => $serviceId,
                'cama_id' => $camaId,
                'trabajadora_id' => $trabajadoraId,
                'inicio' => $inicio,
                'fin' => $fin,
                'estado' => 'pendiente',
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('booking.booking')->insert([
            'id' => $id,
            'branch_id' => $branchId,
            'client_id' => $clientId,
            'service_id' => $serviceId,
            'cama_id' => $camaId,
            'trabajadora_id' => $trabajadoraId,
            'inicio' => $inicio,
            'fin' => $fin,
            'estado' => 'pendiente',
        ]);
    };

    $ensureBooking($existingBookingId, $existingCamaId, $existingWorkerId, '2030-01-15T10:00:00Z', '2030-01-15T11:00:00Z');
    $ensureBooking($workerConflictBookingId, $workerConflictExistingCamaId, $workerConflictExistingWorkerId, '2030-04-01T10:00:00Z', '2030-04-01T11:00:00Z');

    DB::table('catalog.resource')->where('id', $workerConflictExistingWorkerId)->update(['estado' => 'libre']);
}, $branchId, null, (string) Str::uuid());

$inContext = function (Closure $callback) use ($branchId, &$userId): mixed {
    return DatabaseContext::run($callback, $branchId, (string) $userId, (string) Str::uuid());
};

$body = static fn (array $overrides = []): array => array_merge([
    'client_id' => $clientId,
    'service_id' => $serviceId,
    'cama_id' => $successCamaId,
    'trabajadora_id' => $successWorkerId,
    'inicio' => '2030-03-01T10:00:00Z',
], $overrides);

$cookieJar = tempnam(sys_get_temp_dir(), 'spa_reservations_write_');
$anonymousJar = tempnam(sys_get_temp_dir(), 'spa_reservations_write_anon_');

try {
    $login = request('POST', $api.'/auth/login', [
        'contacto' => 'super@spa.local',
        'password' => $superPassword,
        'branch_id' => $branchId,
    ], $cookieJar);

    check('login para crear reservas responde 200', $login['status'] === 200, $login);

    $anonymous = request('POST', $api.'/reservas', $body(), $anonymousJar);

    check(
        'crear sin sesion responde 401',
        $anonymous['status'] === 401 && errorCode($anonymous) === 'UNAUTHENTICATED',
        $anonymous,
    );

    $missingField = request('POST', $api.'/reservas', [
        'service_id' => $serviceId,
        'cama_id' => $successCamaId,
        'trabajadora_id' => $successWorkerId,
        'inicio' => '2030-03-01T10:00:00Z',
    ], $cookieJar);

    check(
        'cuerpo incompleto responde 422',
        $missingField['status'] === 422 && errorCode($missingField) === 'VALIDATION_ERROR',
        $missingField,
    );

    $extraField = request('POST', $api.'/reservas', $body(['fin' => '2030-03-01T11:00:00Z']), $cookieJar);

    check(
        'campo desconocido responde 400',
        $extraField['status'] === 400 && errorCode($extraField) === 'INVALID_REQUEST',
        $extraField,
    );

    $invalidId = request('POST', $api.'/reservas', $body(['client_id' => 'no-es-uuid']), $cookieJar);

    check(
        'UUID invalido responde 422',
        $invalidId['status'] === 422 && errorCode($invalidId) === 'VALIDATION_ERROR',
        $invalidId,
    );

    $invalidDate = request('POST', $api.'/reservas', $body(['inicio' => '2030-03-01 10:00']), $cookieJar);

    check(
        'fecha sin zona horaria responde 422',
        $invalidDate['status'] === 422 && errorCode($invalidDate) === 'VALIDATION_ERROR',
        $invalidDate,
    );

    $missingClient = request('POST', $api.'/reservas', $body([
        'client_id' => '88888888-8888-4888-8888-888888888888',
        'cama_id' => $missingClientCamaId,
        'trabajadora_id' => $missingClientWorkerId,
        'inicio' => '2030-08-01T10:00:00Z',
    ]), $cookieJar);

    check(
        'cliente inexistente responde 404',
        $missingClient['status'] === 404 && errorCode($missingClient) === 'CLIENT_NOT_FOUND',
        $missingClient,
    );

    $inactiveService = request('POST', $api.'/reservas', $body([
        'service_id' => $inactiveServiceId,
        'cama_id' => $inactiveCamaId,
        'trabajadora_id' => $inactiveWorkerId,
        'inicio' => '2030-08-02T10:00:00Z',
    ]), $cookieJar);

    check(
        'servicio inactivo responde 409',
        $inactiveService['status'] === 409 && errorCode($inactiveService) === 'SERVICE_INACTIVE',
        $inactiveService,
    );

    $missingResource = request('POST', $api.'/reservas', $body([
        'cama_id' => '99999999-9999-4999-8999-999999999999',
        'trabajadora_id' => $camaConflictWorkerId,
        'inicio' => '2030-08-03T10:00:00Z',
    ]), $cookieJar);

    check(
        'recurso inexistente responde 404',
        $missingResource['status'] === 404 && errorCode($missingResource) === 'RESOURCE_NOT_FOUND',
        $missingResource,
    );

    $missingService = request('POST', $api.'/reservas', $body([
        'service_id' => '77777777-7777-4777-8777-777777777777',
        'inicio' => '2030-08-07T10:00:00Z',
    ]), $cookieJar);

    check(
        'servicio inexistente responde 404',
        $missingService['status'] === 404 && errorCode($missingService) === 'SERVICE_NOT_FOUND',
        $missingService,
    );

    $wrongType = request('POST', $api.'/reservas', $body([
        'cama_id' => $successWorkerId,
        'trabajadora_id' => $successCamaId,
        'inicio' => '2030-08-04T10:00:00Z',
    ]), $cookieJar);

    check(
        'recurso con tipo incorrecto responde 422',
        $wrongType['status'] === 422 && errorCode($wrongType) === 'VALIDATION_ERROR',
        $wrongType,
    );

    $unavailableCama = request('POST', $api.'/reservas', $body([
        'cama_id' => $unavailableCamaId,
        'trabajadora_id' => $unavailableWorkerId,
        'inicio' => '2030-08-05T10:00:00Z',
    ]), $cookieJar);

    check(
        'cama no disponible responde 409',
        $unavailableCama['status'] === 409 && errorCode($unavailableCama) === 'RESOURCE_UNAVAILABLE',
        $unavailableCama,
    );

    $occupiedWorker = request('POST', $api.'/reservas', $body([
        'cama_id' => $occupiedWorkerCamaId,
        'trabajadora_id' => $existingWorkerId,
        'inicio' => '2030-08-06T10:00:00Z',
    ]), $cookieJar);

    check(
        'trabajadora ocupada responde 409',
        $occupiedWorker['status'] === 409 && errorCode($occupiedWorker) === 'RESOURCE_UNAVAILABLE',
        $occupiedWorker,
    );

    $countBeforeConflicts = $inContext(
        fn (): int => DB::table('booking.booking')->where('branch_id', $branchId)->count(),
    );

    $camaConflict = request('POST', $api.'/reservas', $body([
        'cama_id' => $existingCamaId,
        'trabajadora_id' => $camaConflictWorkerId,
        'inicio' => '2030-01-15T10:30:00Z',
    ]), $cookieJar);

    check(
        'conflicto de cama responde 409',
        $camaConflict['status'] === 409 && errorCode($camaConflict) === 'RESERVATION_CONFLICT',
        $camaConflict,
    );

    $workerConflict = request('POST', $api.'/reservas', $body([
        'cama_id' => $workerConflictCamaId,
        'trabajadora_id' => $workerConflictExistingWorkerId,
        'inicio' => '2030-04-01T10:30:00Z',
    ]), $cookieJar);

    check(
        'conflicto de trabajadora responde 409',
        $workerConflict['status'] === 409 && errorCode($workerConflict) === 'RESERVATION_CONFLICT',
        $workerConflict,
    );

    $countAfterConflicts = $inContext(
        fn (): int => DB::table('booking.booking')->where('branch_id', $branchId)->count(),
    );
    $freeWorkers = $inContext(fn (): array => DB::table('catalog.resource')
        ->whereIn('id', [$camaConflictWorkerId, $workerConflictExistingWorkerId])
        ->pluck('estado', 'id')
        ->all());

    check(
        'conflictos no dejan filas ni recursos parciales',
        $countBeforeConflicts === $countAfterConflicts
            && ($freeWorkers[$camaConflictWorkerId] ?? null) === 'libre'
            && ($freeWorkers[$workerConflictExistingWorkerId] ?? null) === 'libre',
        ['antes' => $countBeforeConflicts, 'despues' => $countAfterConflicts, 'recursos' => $freeWorkers],
    );

    $repository = $app->make(ReservationRepository::class);
    $otherBranch = '44444444-4444-4444-8444-444444444444';

    try {
        $repository->create([
            'client_id' => $clientId,
            'service_id' => $serviceId,
            'cama_id' => $successCamaId,
            'trabajadora_id' => $successWorkerId,
            'inicio' => '2030-09-01 10:00:00+00:00',
        ], $otherBranch, (string) Str::uuid(), (string) $userId);

        check('RLS impide crear con cliente de otra sucursal', false, 'se creo una reserva');
    } catch (ApiException $e) {
        check(
            'RLS impide crear con cliente de otra sucursal',
            $e->status === 404 && $e->errorCode === 'CLIENT_NOT_FOUND',
            $e->errorCode,
        );
    } catch (Throwable $e) {
        check('RLS impide crear con cliente de otra sucursal', false, $e->getMessage());
    }

    $success = request('POST', $api.'/reservas', $body(), $cookieJar);
    $createdId = $success['json']['data']['id'] ?? null;

    check('alta de reserva responde 201', $success['status'] === 201, $success);
    check(
        'alta devuelve Location',
        is_string($createdId) && str_contains($success['headers'], 'Location: /api/v1/reservas/'.$createdId),
        $success['headers'],
    );
    check(
        'alta responde pendiente y calcula fin',
        ($success['json']['data']['estado'] ?? null) === 'pendiente'
            && ($success['json']['data']['inicio'] ?? null) === '2030-03-01T10:00:00Z'
            && ($success['json']['data']['fin'] ?? null) === '2030-03-01T11:00:00Z'
            && ($success['json']['data']['cliente']['id'] ?? null) === $clientId
            && ($success['json']['data']['servicio']['id'] ?? null) === $serviceId
            && ($success['json']['data']['cama']['id'] ?? null) === $successCamaId
            && ($success['json']['data']['trabajadora']['id'] ?? null) === $successWorkerId,
        $success['json'],
    );
    check(
        'alta no expone campos internos',
        ! str_contains($success['body'], 'branch_id') && ! str_contains($success['body'], 'hash_password'),
        $success['body'],
    );

    $workerState = $inContext(fn (): ?string => DB::table('catalog.resource')->where('id', $successWorkerId)->value('estado'));
    $camaState = $inContext(fn (): ?string => DB::table('catalog.resource')->where('id', $successCamaId)->value('estado'));
    $auditExists = $inContext(fn (): bool => DB::table('audit.audit_log')
        ->where('entidad', 'booking')
        ->where('entidad_id', $createdId)
        ->where('accion', 'INSERT')
        ->exists());

    check(
        'alta ocupa la trabajadora y audita',
        $workerState === 'ocupada' && $auditExists,
        ['trabajadora' => $workerState, 'auditoria' => $auditExists],
    );
    check(
        'el estado de la cama no se sincroniza',
        $camaState === 'libre',
        ['cama' => $camaState],
    );

    if (is_string($createdId)) {
        $detail = request('GET', $api.'/reservas/'.$createdId, null, $cookieJar);

        check(
            'detalle de la reserva creada responde 200',
            $detail['status'] === 200 && ($detail['json']['data']['id'] ?? null) === $createdId,
            $detail,
        );
    }
} finally {
    if (is_string($createdId) && isset($inContext)) {
        $inContext(function () use ($createdId): void {
            DB::table('booking.booking')
                ->where('id', $createdId)
                ->whereIn('estado', ['pendiente', 'confirmada'])
                ->update(['estado' => 'cancelada', 'updated_at' => now()]);
        });
    }

    @unlink($cookieJar);
    @unlink($anonymousJar);
}

if ($failures !== []) {
    fwrite(STDERR, "Fallaron {$checks} comprobaciones: ".count($failures).PHP_EOL);

    foreach ($failures as $failure) {
        fwrite(STDERR, ' - '.$failure.PHP_EOL);
    }

    exit(1);
}

echo "Reservas (alta): {$checks}/{$checks} comprobaciones OK".PHP_EOL;

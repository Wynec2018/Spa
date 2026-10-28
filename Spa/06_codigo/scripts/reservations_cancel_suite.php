<?php

declare(strict_types=1);

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

$clientId = '55555555-5555-4555-8555-555555555501';
$serviceId = '55555555-5555-4555-8555-555555555502';
$cancelCamaId = '55555555-5555-4555-8555-555555555540';
$cancelWorkerId = '55555555-5555-4555-8555-555555555541';
$alreadyCamaId = '55555555-5555-4555-8555-555555555542';
$alreadyWorkerId = '55555555-5555-4555-8555-555555555543';
$expiredCamaId = '55555555-5555-4555-8555-555555555544';
$expiredWorkerId = '55555555-5555-4555-8555-555555555545';
$cancelBookingId = '66666666-6666-4666-8666-666666666620';
$alreadyBookingId = '66666666-6666-4666-8666-666666666621';
$expiredBookingId = '66666666-6666-4666-8666-666666666622';

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
    array|object|null $body,
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
    $cancelCamaId,
    $cancelWorkerId,
    $alreadyCamaId,
    $alreadyWorkerId,
    $expiredCamaId,
    $expiredWorkerId,
    $cancelBookingId,
    $alreadyBookingId,
    $expiredBookingId,
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
                'Cliente CP-BACK-07',
                bin2hex(random_bytes(16)),
                hash('sha256', 'cp-back-07-document'),
                'cliente.cpback07@spa.local',
            ],
        );
    }

    if (! DB::table('catalog.service')->where('id', $serviceId)->exists()) {
        DB::table('catalog.service')->insert([
            'id' => $serviceId,
            'branch_id' => $branchId,
            'nombre' => 'Servicio CP-BACK-07',
            'duracion_min' => 60,
            'precio' => 100.00,
            'estado' => 'activo',
        ]);
    }

    foreach ([
        [$cancelCamaId, 'cama', 'Cama CP-BACK-07 cancelar'],
        [$cancelWorkerId, 'trabajadora', 'Trabajadora CP-BACK-07 cancelar'],
        [$alreadyCamaId, 'cama', 'Cama CP-BACK-07 ya cancelada'],
        [$alreadyWorkerId, 'trabajadora', 'Trabajadora CP-BACK-07 ya cancelada'],
        [$expiredCamaId, 'cama', 'Cama CP-BACK-07 expirada'],
        [$expiredWorkerId, 'trabajadora', 'Trabajadora CP-BACK-07 expirada'],
    ] as [$id, $tipo, $nombre]) {
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
        ->whereIn('cama_id', [$cancelCamaId, $alreadyCamaId, $expiredCamaId])
        ->whereIn('estado', ['pendiente', 'confirmada'])
        ->update(['estado' => 'cancelada', 'updated_at' => now()]);

    DB::table('catalog.resource')->whereIn('id', [$cancelWorkerId, $alreadyWorkerId, $expiredWorkerId])->update(['estado' => 'libre']);

    $ensureBooking = function (string $id, string $camaId, string $trabajadoraId, string $inicio, string $fin, string $estado) use ($branchId, $clientId, $serviceId): void {
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
                'estado' => $estado,
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
            'estado' => $estado,
        ]);
    };

    $ensureBooking($alreadyBookingId, $alreadyCamaId, $alreadyWorkerId, '2030-10-02T10:00:00Z', '2030-10-02T11:00:00Z', 'cancelada');
    $ensureBooking($expiredBookingId, $expiredCamaId, $expiredWorkerId, '2030-10-03T10:00:00Z', '2030-10-03T11:00:00Z', 'expirada');
    $ensureBooking($cancelBookingId, $cancelCamaId, $cancelWorkerId, '2030-10-01T10:00:00Z', '2030-10-01T11:00:00Z', 'pendiente');
    DB::table('catalog.resource')->whereIn('id', [$alreadyWorkerId, $expiredWorkerId])->update(['estado' => 'libre']);
}, $branchId, null, (string) Str::uuid());

$inContext = function (Closure $callback) use ($branchId, &$userId): mixed {
    return DatabaseContext::run($callback, $branchId, (string) $userId, (string) Str::uuid());
};

$cookieJar = tempnam(sys_get_temp_dir(), 'spa_reservations_cancel_');
$anonymousJar = tempnam(sys_get_temp_dir(), 'spa_reservations_cancel_anon_');

try {
    $login = request('POST', $api.'/auth/login', [
        'contacto' => 'super@spa.local',
        'password' => $superPassword,
        'branch_id' => $branchId,
    ], $cookieJar);

    check('login para cancelar reservas responde 200', $login['status'] === 200, $login);

    $anonymous = request('PATCH', $api.'/reservas/'.$cancelBookingId.'/estado', ['estado' => 'cancelada'], $anonymousJar);

    check(
        'cancelar sin sesion responde 401',
        $anonymous['status'] === 401 && errorCode($anonymous) === 'UNAUTHENTICATED',
        $anonymous,
    );

    $invalidId = request('PATCH', $api.'/reservas/no-es-uuid/estado', ['estado' => 'cancelada'], $cookieJar);

    check(
        'cancelar con UUID invalido responde 400',
        $invalidId['status'] === 400 && errorCode($invalidId) === 'INVALID_ID',
        $invalidId,
    );

    $missingBooking = request('PATCH', $api.'/reservas/77777777-7777-4777-8777-777777777777/estado', ['estado' => 'cancelada'], $cookieJar);

    check(
        'cancelar reserva inexistente responde 404',
        $missingBooking['status'] === 404 && errorCode($missingBooking) === 'RESERVATION_NOT_FOUND',
        $missingBooking,
    );

    $missingState = request('PATCH', $api.'/reservas/'.$cancelBookingId.'/estado', (object) [], $cookieJar);

    check(
        'cancelar sin estado responde 422',
        $missingState['status'] === 422 && errorCode($missingState) === 'VALIDATION_ERROR',
        $missingState,
    );

    $confirmAttempt = request('PATCH', $api.'/reservas/'.$cancelBookingId.'/estado', ['estado' => 'confirmada'], $cookieJar);

    check(
        'confirmar por esta ruta responde 422',
        $confirmAttempt['status'] === 422 && errorCode($confirmAttempt) === 'VALIDATION_ERROR',
        $confirmAttempt,
    );

    $extraField = request('PATCH', $api.'/reservas/'.$cancelBookingId.'/estado', [
        'estado' => 'cancelada',
        'motivo' => 'prueba',
    ], $cookieJar);

    check(
        'campo extra al cancelar responde 400',
        $extraField['status'] === 400 && errorCode($extraField) === 'INVALID_REQUEST',
        $extraField,
    );

    $invalidTransition = request('PATCH', $api.'/reservas/'.$expiredBookingId.'/estado', ['estado' => 'cancelada'], $cookieJar);
    $expiredState = $inContext(fn (): ?string => DB::table('booking.booking')->where('id', $expiredBookingId)->value('estado'));

    check(
        'cancelar una reserva expirada responde 409',
        $invalidTransition['status'] === 409 && errorCode($invalidTransition) === 'INVALID_STATE_TRANSITION',
        $invalidTransition,
    );
    check(
        'la transicion invalida no cambia el estado',
        $expiredState === 'expirada',
        ['estado' => $expiredState],
    );

    $repository = $app->make(ReservationRepository::class);
    $otherBranch = '44444444-4444-4444-8444-444444444444';
    $otherRow = $repository->cancel($cancelBookingId, $otherBranch, (string) Str::uuid(), (string) $userId);
    $pendingAfterOtherBranch = $inContext(fn (): ?string => DB::table('booking.booking')->where('id', $cancelBookingId)->value('estado'));

    check(
        'RLS impide cancelar desde otra sucursal',
        $otherRow === null && $pendingAfterOtherBranch === 'pendiente',
        ['fila' => $otherRow, 'estado' => $pendingAfterOtherBranch],
    );

    $auditBeforeCancel = $inContext(fn (): int => DB::table('audit.audit_log')
        ->where('entidad', 'booking')
        ->where('entidad_id', $cancelBookingId)
        ->where('accion', 'UPDATE')
        ->count());

    $cancelled = request('PATCH', $api.'/reservas/'.$cancelBookingId.'/estado', ['estado' => 'cancelada'], $cookieJar);
    $cancelledState = $inContext(fn (): ?string => DB::table('booking.booking')->where('id', $cancelBookingId)->value('estado'));
    $workerState = $inContext(fn (): ?string => DB::table('catalog.resource')->where('id', $cancelWorkerId)->value('estado'));
    $auditAfterCancel = $inContext(fn (): int => DB::table('audit.audit_log')
        ->where('entidad', 'booking')
        ->where('entidad_id', $cancelBookingId)
        ->where('accion', 'UPDATE')
        ->count());

    check('cancelar una reserva pendiente responde 200', $cancelled['status'] === 200, $cancelled);
    check(
        'la reserva queda cancelada',
        ($cancelled['json']['data']['estado'] ?? null) === 'cancelada' && $cancelledState === 'cancelada',
        ['respuesta' => $cancelled['json'], 'estado' => $cancelledState],
    );
    check(
        'cancelar libera a la trabajadora',
        $workerState === 'libre',
        ['trabajadora' => $workerState],
    );
    check(
        'cancelar audita la actualizacion',
        $auditAfterCancel > $auditBeforeCancel,
        ['antes' => $auditBeforeCancel, 'despues' => $auditAfterCancel],
    );

    $auditBeforeSecond = $inContext(fn (): int => DB::table('audit.audit_log')
        ->where('entidad', 'booking')
        ->where('entidad_id', $cancelBookingId)
        ->where('accion', 'UPDATE')
        ->count());

    $second = request('PATCH', $api.'/reservas/'.$cancelBookingId.'/estado', ['estado' => 'cancelada'], $cookieJar);
    $auditAfterSecond = $inContext(fn (): int => DB::table('audit.audit_log')
        ->where('entidad', 'booking')
        ->where('entidad_id', $cancelBookingId)
        ->where('accion', 'UPDATE')
        ->count());
    $workerAfterSecond = $inContext(fn (): ?string => DB::table('catalog.resource')->where('id', $cancelWorkerId)->value('estado'));

    check(
        'cancelar dos veces es idempotente',
        $second['status'] === 200
            && ($second['json']['data']['estado'] ?? null) === 'cancelada'
            && $auditBeforeSecond === $auditAfterSecond
            && $workerAfterSecond === 'libre',
        ['respuesta' => $second['json'], 'auditoria' => [$auditBeforeSecond, $auditAfterSecond], 'trabajadora' => $workerAfterSecond],
    );

    $detail = request('GET', $api.'/reservas/'.$cancelBookingId, null, $cookieJar);

    check(
        'el detalle refleja la cancelacion',
        $detail['status'] === 200 && ($detail['json']['data']['estado'] ?? null) === 'cancelada',
        $detail,
    );
} finally {
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

echo "Reservas (cancelacion): {$checks}/{$checks} comprobaciones OK".PHP_EOL;

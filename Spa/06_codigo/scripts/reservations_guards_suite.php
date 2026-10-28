<?php

declare(strict_types=1);

use App\Support\DatabaseContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

$base = rtrim((string) (getenv('API_BASE_URL') ?: 'http://127.0.0.1:8000'), '/');
$api = $base.'/api/v1';
$branchId = trim((string) config('spa.branch_id'));
$superPassword = (string) (getenv('DEMO_SUPER_PASSWORD') ?: '');
$otherBranchId = '44444444-4444-4444-8444-444444444444';
$checks = 0;
$failures = [];

$clientId = '55555555-5555-4555-8555-555555555501';
$serviceId = '55555555-5555-4555-8555-555555555502';
$camaId = '55555555-5555-4555-8555-555555555550';
$workerId = '55555555-5555-4555-8555-555555555551';
$bookingId = '66666666-6666-4666-8666-666666666630';

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

function request(string $method, string $url, ?array $body, ?string $cookieJar): array
{
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
    $camaId,
    $workerId,
    $bookingId,
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
                'Cliente CP-BACK-08',
                bin2hex(random_bytes(16)),
                hash('sha256', 'cp-back-08-document'),
                'cliente.cpback08@spa.local',
            ],
        );
    }

    if (! DB::table('catalog.service')->where('id', $serviceId)->exists()) {
        DB::table('catalog.service')->insert([
            'id' => $serviceId,
            'branch_id' => $branchId,
            'nombre' => 'Servicio CP-BACK-08',
            'duracion_min' => 60,
            'precio' => 100.00,
            'estado' => 'activo',
        ]);
    }

    foreach ([[$camaId, 'cama', 'Cama CP-BACK-08'], [$workerId, 'trabajadora', 'Trabajadora CP-BACK-08']] as [$id, $tipo, $nombre]) {
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
        ->where('cama_id', $camaId)
        ->whereIn('estado', ['pendiente', 'confirmada'])
        ->update(['estado' => 'cancelada', 'updated_at' => now()]);

    DB::table('catalog.resource')->where('id', $workerId)->update(['estado' => 'libre']);

    $exists = DB::table('booking.booking')->where('id', $bookingId)->exists();

    if ($exists) {
        DB::table('booking.booking')->where('id', $bookingId)->update([
            'branch_id' => $branchId,
            'client_id' => $clientId,
            'service_id' => $serviceId,
            'cama_id' => $camaId,
            'trabajadora_id' => $workerId,
            'inicio' => '2030-11-01T10:00:00Z',
            'fin' => '2030-11-01T11:00:00Z',
            'estado' => 'pendiente',
            'updated_at' => now(),
        ]);
    } else {
        DB::table('booking.booking')->insert([
            'id' => $bookingId,
            'branch_id' => $branchId,
            'client_id' => $clientId,
            'service_id' => $serviceId,
            'cama_id' => $camaId,
            'trabajadora_id' => $workerId,
            'inicio' => '2030-11-01T10:00:00Z',
            'fin' => '2030-11-01T11:00:00Z',
            'estado' => 'pendiente',
        ]);
    }
}, $branchId, null, (string) Str::uuid());

$inContext = function (Closure $callback) use ($branchId, &$userId): mixed {
    return DatabaseContext::run($callback, $branchId, (string) $userId, (string) Str::uuid());
};

$cookieJar = tempnam(sys_get_temp_dir(), 'spa_reservations_guard_');

try {
    $login = request('POST', $api.'/auth/login', [
        'contacto' => 'super@spa.local',
        'password' => $superPassword,
        'branch_id' => $branchId,
    ], $cookieJar);

    check('login para guardas responde 200', $login['status'] === 200, $login);

    $beforeState = $inContext(fn (): ?string => DB::table('booking.booking')->where('id', $bookingId)->value('estado'));
    $beforeWorker = $inContext(fn (): ?string => DB::table('catalog.resource')->where('id', $workerId)->value('estado'));

    check(
        'fixture de guarda en pendiente con trabajadora ocupada',
        $beforeState === 'pendiente' && $beforeWorker === 'ocupada',
        ['estado' => $beforeState, 'trabajadora' => $beforeWorker],
    );

    $confirm = request('PATCH', $api.'/reservas/'.$bookingId.'/estado', ['estado' => 'confirmada'], $cookieJar);
    $afterConfirmState = $inContext(fn (): ?string => DB::table('booking.booking')->where('id', $bookingId)->value('estado'));

    check(
        'la API rechaza confirmada sin pago',
        $confirm['status'] === 422 && errorCode($confirm) === 'VALIDATION_ERROR',
        $confirm,
    );
    check(
        'el rechazo de confirmada no modifica la reserva',
        $afterConfirmState === 'pendiente',
        ['estado' => $afterConfirmState],
    );

    $attend = request('PATCH', $api.'/reservas/'.$bookingId.'/estado', ['estado' => 'atendida'], $cookieJar);

    check(
        'la API rechaza atendida',
        $attend['status'] === 422 && errorCode($attend) === 'VALIDATION_ERROR',
        $attend,
    );

    $triggerError = null;

    try {
        DatabaseContext::run(function () use ($branchId, $bookingId): void {
            DB::table('booking.booking')
                ->where('branch_id', $branchId)
                ->where('id', $bookingId)
                ->update(['estado' => 'confirmada', 'updated_at' => now()]);
        }, $branchId, (string) $userId, (string) Str::uuid());
    } catch (QueryException $e) {
        $triggerError = $e;
    }

    $sqlState = $triggerError?->getCode() !== '' ? $triggerError?->getCode() : ($triggerError?->errorInfo[0] ?? '');
    $afterTriggerState = $inContext(fn (): ?string => DB::table('booking.booking')->where('id', $bookingId)->value('estado'));
    $afterTriggerWorker = $inContext(fn (): ?string => DB::table('catalog.resource')->where('id', $workerId)->value('estado'));

    check(
        'el trigger de BD rechaza confirmada sin pago',
        $triggerError !== null
            && ($sqlState === 'P0001' || str_contains($triggerError->getMessage(), 'RN-002')),
        ['error' => $triggerError?->getMessage(), 'sqlstate' => $sqlState],
    );
    check(
        'la BD no confirma ni libera la trabajadora',
        $afterTriggerState === 'pendiente' && $afterTriggerWorker === 'ocupada',
        ['estado' => $afterTriggerState, 'trabajadora' => $afterTriggerWorker],
    );

    $otherBranchCounts = DatabaseContext::run(fn (): array => [
        'booking' => DB::table('booking.booking')->where('id', $bookingId)->count(),
        'client' => DB::table('catalog.client')->where('id', $clientId)->count(),
        'service' => DB::table('catalog.service')->where('id', $serviceId)->count(),
        'resource' => DB::table('catalog.resource')->where('id', $camaId)->count(),
    ], $otherBranchId, (string) $userId, (string) Str::uuid());

    check(
        'RLS devuelve 0 filas con otra sucursal',
        array_sum($otherBranchCounts) === 0,
        $otherBranchCounts,
    );

    $sameBranchCount = $inContext(fn (): int => DB::table('booking.booking')->where('id', $bookingId)->count());

    check(
        'la misma sucursal sigue viendo su reserva',
        $sameBranchCount === 1,
        ['filas' => $sameBranchCount],
    );
} finally {
    @unlink($cookieJar);
}

if ($failures !== []) {
    fwrite(STDERR, "Fallaron {$checks} comprobaciones: ".count($failures).PHP_EOL);

    foreach ($failures as $failure) {
        fwrite(STDERR, ' - '.$failure.PHP_EOL);
    }

    exit(1);
}

echo "Reservas (guardas): {$checks}/{$checks} comprobaciones OK".PHP_EOL;

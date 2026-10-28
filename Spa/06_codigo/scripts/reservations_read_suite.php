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
$camaId = '55555555-5555-4555-8555-555555555503';
$workerResourceId = '55555555-5555-4555-8555-555555555504';
$pendingId = '66666666-6666-4666-8666-666666666601';
$cancelledId = '66666666-6666-4666-8666-666666666602';
$pendingStart = '2030-01-15T10:00:00Z';
$pendingEnd = '2030-01-15T11:00:00Z';
$cancelledStart = '2030-01-16T10:00:00Z';
$cancelledEnd = '2030-01-16T11:00:00Z';

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

function queryUrl(string $url, array $params): string
{
    return $url.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

if ($branchId === '' || $superPassword === '') {
    fwrite(STDERR, 'Faltan APP_BRANCH_ID o DEMO_SUPER_PASSWORD.'.PHP_EOL);
    exit(2);
}

$userId = null;

DatabaseContext::run(function () use ($branchId, $clientId, $serviceId, $camaId, $workerResourceId, $pendingId, $cancelledId, $pendingStart, $pendingEnd, $cancelledStart, $cancelledEnd, &$userId): void {
    $userId = DB::table('auth.app_user')
        ->where('branch_id', $branchId)
        ->where('contacto', 'super@spa.local')
        ->value('id');

    if ($userId === null) {
        throw new RuntimeException('Falta el usuario demo super@spa.local.');
    }

    $clientExists = DB::table('catalog.client')->where('id', $clientId)->exists();

    if (! $clientExists) {
        DB::statement(
            "INSERT INTO catalog.client (id, branch_id, nombre, documento_cifrado, documento_hash, contacto, created_at, updated_at)
             VALUES (?, ?, ?, decode(?, 'hex'), ?, ?, now(), now())",
            [
                $clientId,
                $branchId,
                'Cliente CP-BACK-05',
                bin2hex(random_bytes(16)),
                hash('sha256', 'cp-back-05-document'),
                'cliente.cpback05@spa.local',
            ],
        );
    }

    if (! DB::table('catalog.service')->where('id', $serviceId)->exists()) {
        DB::table('catalog.service')->insert([
            'id' => $serviceId,
            'branch_id' => $branchId,
            'nombre' => 'Servicio CP-BACK-05',
            'duracion_min' => 60,
            'precio' => 100.00,
            'estado' => 'activo',
        ]);
    }

    if (! DB::table('catalog.resource')->where('id', $camaId)->exists()) {
        DB::table('catalog.resource')->insert([
            'id' => $camaId,
            'branch_id' => $branchId,
            'tipo' => 'cama',
            'nombre' => 'Cama CP-BACK-05',
            'estado' => 'libre',
        ]);
    }

    if (! DB::table('catalog.resource')->where('id', $workerResourceId)->exists()) {
        DB::table('catalog.resource')->insert([
            'id' => $workerResourceId,
            'branch_id' => $branchId,
            'tipo' => 'trabajadora',
            'nombre' => 'Trabajadora CP-BACK-05',
            'estado' => 'libre',
        ]);
    }

    $bookings = [
        [$pendingId, $pendingStart, $pendingEnd, 'pendiente'],
        [$cancelledId, $cancelledStart, $cancelledEnd, 'cancelada'],
    ];

    foreach ($bookings as [$id, $inicio, $fin, $estado]) {
        if (DB::table('booking.booking')->where('id', $id)->exists()) {
            continue;
        }

        DB::table('booking.booking')->insert([
            'id' => $id,
            'branch_id' => $branchId,
            'client_id' => $clientId,
            'service_id' => $serviceId,
            'cama_id' => $camaId,
            'trabajadora_id' => $workerResourceId,
            'inicio' => $inicio,
            'fin' => $fin,
            'estado' => $estado,
        ]);
    }
}, $branchId, null, (string) Str::uuid());

$cookieJar = tempnam(sys_get_temp_dir(), 'spa_reservations_');

try {
    $login = request('POST', $api.'/auth/login', [
        'contacto' => 'super@spa.local',
        'password' => $superPassword,
        'branch_id' => $branchId,
    ], $cookieJar);

    check('login para reservas responde 200', $login['status'] === 200, $login);

    $list = request('GET', queryUrl($api.'/reservas', [
        'desde' => '2030-01-15T00:00:00Z',
        'hasta' => '2030-01-16T00:00:00Z',
        'per_page' => 10,
    ]), null, $cookieJar);

    $listRow = $list['json']['data'][0] ?? [];

    check('listado por rango responde 200', $list['status'] === 200, $list);
    check(
        'listado por rango devuelve la reserva pendiente',
        ($list['json']['meta']['total'] ?? null) === 1
            && ($list['json']['meta']['page'] ?? null) === 1
            && ($list['json']['meta']['per_page'] ?? null) === 10
            && ($listRow['id'] ?? null) === $pendingId
            && ($listRow['cliente']['id'] ?? null) === $clientId
            && ($listRow['servicio']['id'] ?? null) === $serviceId
            && ($listRow['cama']['id'] ?? null) === $camaId
            && ($listRow['trabajadora']['id'] ?? null) === $workerResourceId,
        $list['json'],
    );
    check(
        'listado serializa fechas y precio',
        ($listRow['inicio'] ?? null) === '2030-01-15T10:00:00Z'
            && ($listRow['fin'] ?? null) === '2030-01-15T11:00:00Z'
            && ($listRow['servicio']['precio'] ?? null) === '100.00',
        $listRow,
    );
    check(
        'listado no expone hash ni branch_id',
        ! str_contains($list['body'], 'hash_password')
            && ! str_contains($list['body'], 'branch_id'),
        $list['body'],
    );

    $cancelled = request('GET', queryUrl($api.'/reservas', [
        'desde' => '2030-01-16T00:00:00Z',
        'hasta' => '2030-01-17T00:00:00Z',
        'estado' => 'cancelada',
        'per_page' => 10,
    ]), null, $cookieJar);

    check(
        'filtro por estado cancelada',
        $cancelled['status'] === 200
            && ($cancelled['json']['meta']['total'] ?? null) === 1
            && ($cancelled['json']['data'][0]['id'] ?? null) === $cancelledId,
        $cancelled,
    );

    $paged = request('GET', queryUrl($api.'/reservas', [
        'desde' => '2030-01-15T00:00:00Z',
        'hasta' => '2030-01-17T00:00:00Z',
        'page' => 2,
        'per_page' => 1,
    ]), null, $cookieJar);

    check(
        'paginacion devuelve la segunda reserva',
        $paged['status'] === 200
            && ($paged['json']['meta']['total'] ?? null) === 2
            && ($paged['json']['meta']['page'] ?? null) === 2
            && ($paged['json']['meta']['per_page'] ?? null) === 1
            && ($paged['json']['data'][0]['id'] ?? null) === $pendingId,
        $paged,
    );

    $empty = request('GET', queryUrl($api.'/reservas', [
        'desde' => '2030-01-17T00:00:00Z',
        'hasta' => '2030-01-18T00:00:00Z',
    ]), null, $cookieJar);

    check(
        'rango sin reservas devuelve total 0',
        $empty['status'] === 200 && ($empty['json']['meta']['total'] ?? null) === 0,
        $empty,
    );

    $show = request('GET', $api.'/reservas/'.$pendingId, null, $cookieJar);

    check('detalle responde 200', $show['status'] === 200, $show);
    check(
        'detalle tiene relaciones y estado',
        ($show['json']['data']['id'] ?? null) === $pendingId
            && ($show['json']['data']['estado'] ?? null) === 'pendiente'
            && ($show['json']['data']['cliente']['nombre'] ?? null) === 'Cliente CP-BACK-05'
            && ($show['json']['data']['servicio']['nombre'] ?? null) === 'Servicio CP-BACK-05',
        $show['json'],
    );

    $missing = request('GET', $api.'/reservas/77777777-7777-4777-8777-777777777777', null, $cookieJar);

    check(
        'detalle inexistente responde 404',
        $missing['status'] === 404 && ($missing['json']['error']['code'] ?? null) === 'RESERVATION_NOT_FOUND',
        $missing,
    );

    $invalidId = request('GET', $api.'/reservas/no-es-uuid', null, $cookieJar);

    check(
        'detalle con UUID invalido responde 400',
        $invalidId['status'] === 400 && ($invalidId['json']['error']['code'] ?? null) === 'INVALID_ID',
        $invalidId,
    );

    $inverted = request('GET', queryUrl($api.'/reservas', [
        'desde' => '2030-01-16T00:00:00Z',
        'hasta' => '2030-01-15T00:00:00Z',
    ]), null, $cookieJar);

    check(
        'rango invertido responde 400',
        $inverted['status'] === 400 && ($inverted['json']['error']['code'] ?? null) === 'INVALID_REQUEST',
        $inverted,
    );

    $badFilter = request('GET', queryUrl($api.'/reservas', ['estado' => 'inventada']), null, $cookieJar);

    check(
        'estado invalido responde 400',
        $badFilter['status'] === 400 && ($badFilter['json']['error']['code'] ?? null) === 'INVALID_REQUEST',
        $badFilter,
    );

    $extraFilter = request('GET', queryUrl($api.'/reservas', ['foo' => 'bar']), null, $cookieJar);

    check(
        'filtro desconocido responde 400',
        $extraFilter['status'] === 400 && ($extraFilter['json']['error']['code'] ?? null) === 'INVALID_REQUEST',
        $extraFilter,
    );

    $anonymousJar = tempnam(sys_get_temp_dir(), 'spa_reservations_anon_');
    $anonymous = request('GET', $api.'/reservas', null, $anonymousJar);
    @unlink($anonymousJar);

    check(
        'listado sin sesion responde 401',
        $anonymous['status'] === 401 && ($anonymous['json']['error']['code'] ?? null) === 'UNAUTHENTICATED',
        $anonymous,
    );

    $repository = $app->make(ReservationRepository::class);
    $otherBranch = '44444444-4444-4444-8444-444444444444';
    $otherList = $repository->paginate([
        'desde' => null,
        'hasta' => null,
        'estado' => null,
        'page' => 1,
        'per_page' => 20,
    ], $otherBranch, (string) Str::uuid(), (string) $userId);
    $otherShow = $repository->find($pendingId, $otherBranch, (string) Str::uuid(), (string) $userId);

    check(
        'RLS aisla listado y detalle por sucursal',
        $otherList['total'] === 0 && $otherShow === null,
        ['total' => $otherList['total'], 'detalle' => $otherShow],
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

echo "Reservas (lectura): {$checks}/{$checks} comprobaciones OK".PHP_EOL;

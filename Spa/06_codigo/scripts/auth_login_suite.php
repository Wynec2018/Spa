<?php

declare(strict_types=1);

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
$workerPassword = (string) (getenv('DEMO_TRABAJADORA_PASSWORD') ?: '');
$checks = 0;
$failures = [];

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

    return $encoded === false ? gettype($value) : mb_substr($encoded, 0, 600);
}

function request(
    string $method,
    string $url,
    ?array $body,
    string $cookieJar,
    ?string $contentType = 'application/json',
): array {
    $curl = curl_init($url);

    $headers = ['Accept: application/json'];

    if ($contentType !== null) {
        $headers[] = 'Content-Type: '.$contentType;
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_TIMEOUT => 10,
    ]);

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

if ($branchId === '' || $superPassword === '' || $workerPassword === '') {
    fwrite(STDERR, 'Faltan APP_BRANCH_ID, DEMO_SUPER_PASSWORD o DEMO_TRABAJADORA_PASSWORD.'.PHP_EOL);
    exit(2);
}

$cookieJar = tempnam(sys_get_temp_dir(), 'spa_auth_');
$workerContact = 'trabajadora@spa.local';

try {
    $valid = request('POST', $api.'/auth/login', [
        'contacto' => 'super@spa.local',
        'password' => $superPassword,
        'branch_id' => $branchId,
    ], $cookieJar);

    check('login valido responde 200', $valid['status'] === 200, $valid);
    check(
        'login valido devuelve rol y datos seguros',
        ($valid['json']['success'] ?? false) === true
            && ($valid['json']['data']['rol'] ?? null) === 'superusuario'
            && ($valid['json']['data']['contacto'] ?? null) === 'super@spa.local',
        $valid['json'],
    );
    check('login valido crea cookie de sesion', stripos($valid['headers'], 'set-cookie:') !== false, $valid['headers']);
    check(
        'login no expone hash ni contrasena',
        ! str_contains($valid['body'], 'hash_password')
            && ! str_contains($valid['body'], '$argon2id$')
            && ! str_contains($valid['body'], $superPassword),
        $valid['body'],
    );

    $invalid = request('POST', $api.'/auth/login', [
        'contacto' => 'super@spa.local',
        'password' => $superPassword.'-incorrecta',
        'branch_id' => $branchId,
    ], $cookieJar);

    check(
        'credenciales invalidas responden 401',
        $invalid['status'] === 401 && ($invalid['json']['error']['code'] ?? null) === 'INVALID_CREDENTIALS',
        $invalid,
    );

    $wrongBranch = request('POST', $api.'/auth/login', [
        'contacto' => 'super@spa.local',
        'password' => $superPassword,
        'branch_id' => '44444444-4444-4444-8444-444444444444',
    ], $cookieJar);

    check(
        'sucursal incorrecta responde 401',
        $wrongBranch['status'] === 401 && ($wrongBranch['json']['error']['code'] ?? null) === 'INVALID_CREDENTIALS',
        $wrongBranch,
    );

    $badBranch = request('POST', $api.'/auth/login', [
        'contacto' => 'super@spa.local',
        'password' => $superPassword,
        'branch_id' => 'no-es-uuid',
    ], $cookieJar);

    check(
        'sucursal con formato invalido responde 422',
        $badBranch['status'] === 422 && ($badBranch['json']['error']['code'] ?? null) === 'VALIDATION_ERROR',
        $badBranch,
    );

    $extra = request('POST', $api.'/auth/login', [
        'contacto' => 'super@spa.local',
        'password' => $superPassword,
        'branch_id' => $branchId,
        'estado' => 'activo',
    ], $cookieJar);

    check(
        'campo extra responde 400',
        $extra['status'] === 400 && ($extra['json']['error']['code'] ?? null) === 'INVALID_REQUEST',
        $extra,
    );

    DatabaseContext::run(function () use ($branchId, $workerContact): void {
        DB::table('auth.app_user')
            ->where('branch_id', $branchId)
            ->where('contacto', $workerContact)
            ->update(['estado' => 'inactivo']);
    }, $branchId, null, (string) Str::uuid());

    try {
        $inactive = request('POST', $api.'/auth/login', [
            'contacto' => $workerContact,
            'password' => $workerPassword,
            'branch_id' => $branchId,
        ], $cookieJar);

        check(
            'usuario inactivo responde 403',
            $inactive['status'] === 403 && ($inactive['json']['error']['code'] ?? null) === 'USER_INACTIVE',
            $inactive,
        );
    } finally {
        DatabaseContext::run(function () use ($branchId, $workerContact): void {
            DB::table('auth.app_user')
                ->where('branch_id', $branchId)
                ->where('contacto', $workerContact)
                ->update(['estado' => 'activo']);
        }, $branchId, null, (string) Str::uuid());
    }
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

echo "Autenticacion: {$checks}/{$checks} comprobaciones OK".PHP_EOL;

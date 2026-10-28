<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

$base = rtrim((string) (getenv('API_BASE_URL') ?: 'http://127.0.0.1:8000'), '/');
$api = $base.'/api/v1';
$branchId = trim((string) config('spa.branch_id'));
$superPassword = (string) (getenv('DEMO_SUPER_PASSWORD') ?: '');
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
        'headers' => substr($raw, 0, $headerSize),
        'body' => substr($raw, $headerSize),
        'json' => json_decode(substr($raw, $headerSize), true),
    ];
}

if ($branchId === '' || $superPassword === '') {
    fwrite(STDERR, 'Faltan APP_BRANCH_ID o DEMO_SUPER_PASSWORD.'.PHP_EOL);
    exit(2);
}

$cookieJar = tempnam(sys_get_temp_dir(), 'spa_session_');

try {
    $login = request('POST', $api.'/auth/login', [
        'contacto' => 'super@spa.local',
        'password' => $superPassword,
        'branch_id' => $branchId,
    ], $cookieJar);

    check('login crea sesion', $login['status'] === 200, $login);
    check(
        'cookie es HttpOnly y SameSite=Lax',
        stripos($login['headers'], 'httponly') !== false
            && stripos($login['headers'], 'samesite=lax') !== false,
        $login['headers'],
    );

    $me = request('GET', $api.'/auth/me', null, $cookieJar);

    check('me autenticado responde 200', $me['status'] === 200, $me);
    check(
        'me devuelve identidad sin hash',
        ($me['json']['data']['contacto'] ?? null) === 'super@spa.local'
            && ($me['json']['data']['rol'] ?? null) === 'superusuario'
            && ! str_contains($me['body'], 'hash_password')
            && ! str_contains($me['body'], '$argon2id$'),
        $me['body'],
    );

    $anonymousJar = tempnam(sys_get_temp_dir(), 'spa_session_anon_');
    $anonymous = request('GET', $api.'/auth/me', null, $anonymousJar);
    @unlink($anonymousJar);

    check(
        'me sin sesion responde 401',
        $anonymous['status'] === 401 && ($anonymous['json']['error']['code'] ?? null) === 'UNAUTHENTICATED',
        $anonymous,
    );

    $logout = request('POST', $api.'/auth/logout', [], $cookieJar);

    check(
        'logout responde 200',
        $logout['status'] === 200 && ($logout['json']['data']['message'] ?? null) === 'Sesión cerrada.',
        $logout,
    );

    $afterLogout = request('GET', $api.'/auth/me', null, $cookieJar);

    check(
        'me despues de logout responde 401',
        $afterLogout['status'] === 401 && ($afterLogout['json']['error']['code'] ?? null) === 'UNAUTHENTICATED',
        $afterLogout,
    );

    $logoutAgain = request('POST', $api.'/auth/logout', [], $cookieJar);

    check('logout sin sesion es idempotente', $logoutAgain['status'] === 200, $logoutAgain);
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

echo "Sesion: {$checks}/{$checks} comprobaciones OK".PHP_EOL;

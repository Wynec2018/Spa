<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$base = rtrim((string) (getenv('API_BASE_URL') ?: 'http://127.0.0.1:8000'), '/');
$failures = [];
$checks = 0;

function runCommand(string $label, string $command, string $cwd): bool
{
    echo '== '.$label.' =='.PHP_EOL;

    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes, $cwd);

    if (! is_resource($process)) {
        fwrite(STDERR, 'No se pudo iniciar: '.$label.PHP_EOL);

        return false;
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($stdout !== '') {
        echo rtrim($stdout).PHP_EOL;
    }

    if ($stderr !== '') {
        fwrite(STDERR, rtrim($stderr).PHP_EOL);
    }

    if ($exitCode !== 0) {
        fwrite(STDERR, $label.' fallo con codigo '.$exitCode.PHP_EOL);

        return false;
    }

    return true;
}

if ((string) (getenv('DEMO_SUPER_PASSWORD') ?: '') === '' || (string) (getenv('DEMO_TRABAJADORA_PASSWORD') ?: '') === '') {
    fwrite(STDERR, 'Faltan DEMO_SUPER_PASSWORD o DEMO_TRABAJADORA_PASSWORD.'.PHP_EOL);
    exit(2);
}

$health = curl_init($base.'/api/v1/health');
curl_setopt_array($health, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 5,
]);
$healthBody = curl_exec($health);
$healthStatus = (int) curl_getinfo($health, CURLINFO_RESPONSE_CODE);
curl_close($health);

if ($healthBody === false || $healthStatus !== 200) {
    fwrite(STDERR, 'El servidor no responde 200 en '.$base.'/api/v1/health.'.PHP_EOL);
    exit(2);
}

$suites = [
    ['Servicios (CRUD)', 'api_crud_suite.php'],
    ['Autenticacion (login)', 'auth_login_suite.php'],
    ['Autenticacion (sesion)', 'auth_session_suite.php'],
    ['Reservas (lectura)', 'reservations_read_suite.php'],
    ['Reservas (alta)', 'reservations_write_suite.php'],
    ['Reservas (cancelacion)', 'reservations_cancel_suite.php'],
    ['Reservas (guardas)', 'reservations_guards_suite.php'],
];

foreach ($suites as [$label, $script]) {
    $checks++;
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__FILE__).DIRECTORY_SEPARATOR.$script);

    if (! runCommand($label, $command, $root)) {
        $failures[] = $label;
    }
}

$checks++;
$phpunit = escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.DIRECTORY_SEPARATOR.'artisan').' test';

if (! runCommand('PHPUnit', $phpunit, $root)) {
    $failures[] = 'PHPUnit';
}

$checks++;
$pint = escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pint').' --test';

if (! runCommand('Pint', $pint, $root)) {
    $failures[] = 'Pint';
}

if ($failures !== []) {
    fwrite(STDERR, 'Backend suite: '.count($failures).' de '.$checks.' bloques fallaron.'.PHP_EOL);

    foreach ($failures as $failure) {
        fwrite(STDERR, ' - '.$failure.PHP_EOL);
    }

    exit(1);
}

echo 'Backend suite: '.$checks.'/'.$checks.' bloques OK'.PHP_EOL;

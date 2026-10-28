<?php

declare(strict_types=1);

$repo = dirname(__DIR__, 3);
$envPath = $repo . '/Spa/06_codigo/.env';
$failures = [];

if (!is_file($envPath)) {
    echo '[1] DEMO_DATA: FAILED' . PHP_EOL;
    echo '  - Falta Spa/06_codigo/.env para verificar los datos demo' . PHP_EOL;
    exit(1);
}

$env = parse_ini_file($envPath, false, INI_SCANNER_RAW);
if (!is_array($env)) {
    echo '[1] DEMO_DATA: FAILED' . PHP_EOL;
    echo '  - Spa/06_codigo/.env no se pudo leer' . PHP_EOL;
    exit(1);
}

$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    $env['DB_HOST'] ?? '127.0.0.1',
    $env['DB_PORT'] ?? '5432',
    $env['DB_DATABASE'] ?? 'sistema'
);

try {
    $pdo = new PDO($dsn, $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $exception) {
    echo '[1] DEMO_DATA: FAILED' . PHP_EOL;
    echo '  - No se pudo conectar para verificar los datos demo' . PHP_EOL;
    exit(1);
}

$branchId = (string) ($env['APP_BRANCH_ID'] ?? '');
$servicios = [];
$camas = [];
$sede = null;
$moneda = null;

try {
    $pdo->beginTransaction();
    $pdo->exec('SET TRANSACTION READ ONLY');
    $stmt = $pdo->prepare("SELECT set_config('app.branch_id', :branch_id, true)");
    $stmt->execute(['branch_id' => $branchId]);

    $servicios = $pdo->query(
        "SELECT nombre, duracion_min, precio
         FROM catalog.service
         WHERE estado = 'activo'
         ORDER BY nombre"
    )->fetchAll();

    $camas = $pdo->query(
        "SELECT estado, COUNT(*)::int AS total
         FROM catalog.resource
         WHERE tipo = 'cama'
         GROUP BY estado
         ORDER BY estado"
    )->fetchAll();

    $stmt = $pdo->prepare('SELECT nombre, estado FROM core.branch WHERE id = :branch_id LIMIT 1');
    $stmt->execute(['branch_id' => $branchId]);
    $sede = $stmt->fetch();

    $moneda = $pdo->query(
        "SELECT valor FROM core.app_config WHERE clave = 'moneda' LIMIT 1"
    )->fetchColumn();

    $pdo->rollBack();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo '[1] DEMO_DATA: FAILED' . PHP_EOL;
    echo '  - Fallo la lectura de datos demo' . PHP_EOL;
    exit(1);
}

$totalCamas = 0;
$estadosValidos = ['libre', 'ocupada', 'no_disponible'];
foreach ($camas as $row) {
    $totalCamas += (int) $row['total'];
    if (!in_array($row['estado'], $estadosValidos, true)) {
        $failures[] = 'Estado de cama no valido: ' . $row['estado'];
    }
}

if ($servicios === []) {
    $failures[] = 'No hay servicios activos para la demo';
}
if ($totalCamas < 1) {
    $failures[] = 'No hay camas registradas para la demo';
}
if ($sede === false || $sede === null) {
    $failures[] = 'La sede configurada no existe';
} elseif ($sede['estado'] !== 'activa') {
    $failures[] = 'La sede configurada no esta activa';
}
if (trim((string) $moneda, '"') !== 'BOB') {
    $failures[] = 'core.app_config.moneda no es BOB';
}

foreach ($servicios as $servicio) {
    if (trim((string) $servicio['nombre']) === '') {
        $failures[] = 'Servicio activo sin nombre';
    }
    if ((int) $servicio['duracion_min'] <= 0) {
        $failures[] = 'Servicio con duracion invalida: ' . $servicio['nombre'];
    }
    if ((float) $servicio['precio'] < 0) {
        $failures[] = 'Servicio con precio invalido: ' . $servicio['nombre'];
    }
}

if ($failures !== []) {
    echo '[1] DEMO_DATA: FAILED' . PHP_EOL;
    foreach ($failures as $failure) {
        echo '  - ' . $failure . PHP_EOL;
    }
    exit(1);
}

echo '[0] DEMO_DATA: PASSED' . PHP_EOL;
echo '  Servicios activos: ' . count($servicios) . PHP_EOL;
echo '  Camas registradas: ' . $totalCamas . PHP_EOL;
foreach ($camas as $row) {
    echo '    - ' . $row['estado'] . ': ' . $row['total'] . PHP_EOL;
}
echo '  Sede: ' . $sede['nombre'] . ' (' . $sede['estado'] . ')' . PHP_EOL;
echo '  Moneda: BOB' . PHP_EOL;
echo '  Escrituras durante la verificacion: 0' . PHP_EOL;
echo '  Datos PII expuestos: 0' . PHP_EOL;
exit(0);

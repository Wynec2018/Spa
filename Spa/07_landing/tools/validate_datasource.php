<?php

declare(strict_types=1);

$repo = dirname(__DIR__, 3);
$envPath = $repo . '/Spa/06_codigo/.env';
$failures = [];

if (!is_file($envPath)) {
    echo '[1] DATASOURCE: FAILED' . PHP_EOL;
    echo '  - Falta Spa/06_codigo/.env para la prueba de acceso' . PHP_EOL;
    exit(1);
}

$env = parse_ini_file($envPath, false, INI_SCANNER_RAW);
if (!is_array($env)) {
    echo '[1] DATASOURCE: FAILED' . PHP_EOL;
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
    echo '[1] DATASOURCE: FAILED' . PHP_EOL;
    echo '  - No se pudo conectar para verificar el rol de solo lectura' . PHP_EOL;
    exit(1);
}

$role = $pdo->query(
    "SELECT rolname, rolcanlogin, rolsuper, rolcreaterole, rolbypassrls
     FROM pg_roles
     WHERE rolname = 'spa_readonly'"
)->fetch();

if ($role === false) {
    $failures[] = 'No existe el rol spa_readonly';
} else {
    if ($role['rolcanlogin'] !== true) {
        $failures[] = 'spa_readonly no puede iniciar sesion';
    }
    if ($role['rolsuper'] !== false) {
        $failures[] = 'spa_readonly es superusuario';
    }
    if ($role['rolcreaterole'] !== false) {
        $failures[] = 'spa_readonly puede crear roles';
    }
    if ($role['rolbypassrls'] !== false) {
        $failures[] = 'spa_readonly omite RLS';
    }
}

foreach (['core', 'catalog'] as $schema) {
    $hasUsage = (bool) $pdo->query(
        "SELECT has_schema_privilege('spa_readonly', " . $pdo->quote($schema) . ", 'USAGE')"
    )->fetchColumn();
    if (!$hasUsage) {
        $failures[] = 'spa_readonly sin USAGE en ' . $schema;
    }
}

$privileges = $pdo->query(
    "SELECT
        has_table_privilege('spa_readonly', 'catalog.service', 'SELECT') AS service_select,
        has_table_privilege('spa_readonly', 'catalog.service', 'INSERT') AS service_insert,
        has_table_privilege('spa_readonly', 'catalog.service', 'UPDATE') AS service_update,
        has_table_privilege('spa_readonly', 'catalog.service', 'DELETE') AS service_delete,
        has_table_privilege('spa_readonly', 'catalog.resource', 'SELECT') AS resource_select,
        has_table_privilege('spa_readonly', 'catalog.resource', 'INSERT') AS resource_insert,
        has_table_privilege('spa_readonly', 'catalog.resource', 'UPDATE') AS resource_update,
        has_table_privilege('spa_readonly', 'catalog.resource', 'DELETE') AS resource_delete,
        has_table_privilege('spa_readonly', 'core.branch', 'SELECT') AS branch_select,
        has_table_privilege('spa_readonly', 'core.branch', 'INSERT') AS branch_insert,
        has_table_privilege('spa_readonly', 'core.branch', 'UPDATE') AS branch_update,
        has_table_privilege('spa_readonly', 'core.branch', 'DELETE') AS branch_delete,
        has_table_privilege('spa_readonly', 'core.app_config', 'SELECT') AS config_select,
        has_table_privilege('spa_readonly', 'core.app_config', 'INSERT') AS config_insert,
        has_table_privilege('spa_readonly', 'core.app_config', 'UPDATE') AS config_update,
        has_table_privilege('spa_readonly', 'core.app_config', 'DELETE') AS config_delete"
)->fetch();

foreach (['service', 'resource', 'branch', 'config'] as $table) {
    if ($privileges[$table . '_select'] !== true) {
        $failures[] = 'spa_readonly sin SELECT en ' . $table;
    }
    foreach (['insert', 'update', 'delete'] as $write) {
        if ($privileges[$table . '_' . $write] !== false) {
            $failures[] = 'spa_readonly tiene ' . strtoupper($write) . ' en ' . $table;
        }
    }
}

$tableWrites = $pdo->query(
    "SELECT n.nspname AS schema_name, c.relname AS table_name,
        has_table_privilege('spa_readonly', format('%I.%I', n.nspname, c.relname), 'INSERT') AS can_insert,
        has_table_privilege('spa_readonly', format('%I.%I', n.nspname, c.relname), 'UPDATE') AS can_update,
        has_table_privilege('spa_readonly', format('%I.%I', n.nspname, c.relname), 'DELETE') AS can_delete
     FROM pg_class c
     JOIN pg_namespace n ON n.oid = c.relnamespace
     WHERE n.nspname IN ('core','auth','catalog','booking','payment','support','audit')
       AND c.relkind IN ('r','p')
     ORDER BY n.nspname, c.relname"
)->fetchAll();

foreach ($tableWrites as $row) {
    if ($row['can_insert'] || $row['can_update'] || $row['can_delete']) {
        $failures[] = 'spa_readonly con escritura en ' . $row['schema_name'] . '.' . $row['table_name'];
    }
}

$landingTables = [];
foreach ($pdo->query("SELECT table_schema, table_name FROM information_schema.tables")->fetchAll() as $row) {
    if (str_starts_with($row['table_name'], 'landing_')) {
        $landingTables[] = $row['table_schema'] . '.' . $row['table_name'];
    }
}
if ($landingTables !== []) {
    $failures[] = 'Existen tablas landing_*: ' . implode(', ', $landingTables);
}

$branchId = (string) ($env['APP_BRANCH_ID'] ?? '');
$counts = [
    'servicios' => 0,
    'camas' => 0,
    'sede' => 0,
    'config' => 0,
];

try {
    $pdo->beginTransaction();
    $pdo->exec('SET TRANSACTION READ ONLY');
    $stmt = $pdo->prepare("SELECT set_config('app.branch_id', :branch_id, true)");
    $stmt->execute(['branch_id' => $branchId]);

    $appliedBranch = (string) $pdo->query("SELECT current_setting('app.branch_id', true)")->fetchColumn();
    $transactionReadOnly = (string) $pdo->query('SHOW transaction_read_only')->fetchColumn();

    $counts['servicios'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM catalog.service WHERE estado = 'activo'"
    )->fetchColumn();
    $counts['camas'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM catalog.resource WHERE tipo = 'cama'"
    )->fetchColumn();
    $counts['sede'] = (int) $pdo->query(
        'SELECT COUNT(*) FROM core.branch WHERE id = ' . $pdo->quote($branchId)
    )->fetchColumn();
    $counts['config'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM core.app_config WHERE clave = 'moneda'"
    )->fetchColumn();

    $pdo->rollBack();

    if ($appliedBranch !== $branchId) {
        $failures[] = 'app.branch_id no se aplico con set_config';
    }
    if ($transactionReadOnly !== 'on') {
        $failures[] = 'La transaccion de prueba no quedo en solo lectura';
    }
    if ($counts['sede'] !== 1) {
        $failures[] = 'La sede configurada no es visible para RLS';
    }
    if ($counts['config'] < 1) {
        $failures[] = 'No se pudo leer core.app_config (moneda)';
    }
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $failures[] = 'Fallo la lectura con SET LOCAL/RLS';
}

$repositoryCode = (string) file_get_contents($repo . '/Spa/07_landing/app/Repositories/LandingRepository.php');
foreach (['auth.app_user', 'catalog.client', 'booking.booking', 'payment.payment'] as $forbiddenTable) {
    if (str_contains($repositoryCode, $forbiddenTable)) {
        $failures[] = 'LandingRepository referencia tabla no autorizada: ' . $forbiddenTable;
    }
}

try {
    require_once $repo . '/Spa/07_landing/app/Core/Database.php';
    require_once $repo . '/Spa/07_landing/app/Repositories/LandingRepository.php';

    $repository = new \App\Repositories\LandingRepository($pdo, $branchId);
    $servicios = $repository->serviciosActivos();
    $camas = $repository->resumenCamas();
    $sede = $repository->sede();
    $config = $repository->configPublica();

    if (!is_array($servicios) || !is_array($camas) || !is_array($sede) || !is_array($config)) {
        $failures[] = 'LandingRepository no devolvio las estructuras esperadas';
    }
} catch (Throwable $exception) {
    $failures[] = 'LandingRepository fallo al leer: ' . $exception->getMessage();
}

if ($failures !== []) {
    echo '[1] DATASOURCE: FAILED' . PHP_EOL;
    foreach ($failures as $failure) {
        echo '  - ' . $failure . PHP_EOL;
    }
    exit(1);
}

echo '[0] DATASOURCE: PASSED' . PHP_EOL;
echo '  Rol: spa_readonly (LOGIN, sin superuser/createrole/bypassrls)' . PHP_EOL;
echo '  Schemas: core/catalog USAGE' . PHP_EOL;
echo '  SELECT: catalog.service, catalog.resource, core.branch, core.app_config' . PHP_EOL;
echo '  Escrituras en spa_readonly: 0' . PHP_EOL;
echo '  Tablas landing_*: 0' . PHP_EOL;
echo '  Tablas PII referenciadas: 0' . PHP_EOL;
echo '  SET LOCAL app.branch_id: OK' . PHP_EOL;
echo '  Transaccion de lectura: READ ONLY' . PHP_EOL;
echo '  Servicios activos visibles: ' . $counts['servicios'] . PHP_EOL;
echo '  Camas visibles: ' . $counts['camas'] . PHP_EOL;
echo '  Repository: servicios/camas/sede/config OK' . PHP_EOL;
exit(0);

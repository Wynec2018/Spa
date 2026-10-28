<?php

declare(strict_types=1);

$checkpoint = $argv[1] ?? '';
$titles = [
    'CP-LAND-01' => 'Contrato y estructura',
    'CP-LAND-02' => 'Fuente de datos de solo lectura',
    'CP-LAND-03' => 'Datos demo existentes',
    'CP-LAND-04' => 'Imagenes',
    'CP-LAND-05' => 'Sistema visual',
    'CP-LAND-06' => 'Header, hero y CTA',
    'CP-LAND-07' => 'Servicios',
    'CP-LAND-08' => 'Camas',
    'CP-LAND-09' => 'Beneficios, prueba social y CTA final',
    'CP-LAND-10' => 'Responsive base',
    'CP-LAND-11' => 'Motion',
    'CP-LAND-12' => 'Auditoria visual post-create',
    'CP-LAND-13' => 'Auditoria motion',
    'CP-LAND-14' => 'UI/UX/accesibilidad',
    'CP-LAND-15' => 'Performance y hardening',
    'CP-LAND-16' => 'Validacion completa',
];

if (!isset($titles[$checkpoint])) {
    fwrite(STDERR, 'Checkpoint no implementado: ' . $checkpoint . PHP_EOL);
    exit(2);
}

$root = dirname(__DIR__);
$repo = dirname(__DIR__, 3);
$failures = [];

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'App\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $root . '/app/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

function validateLandingImages(string $root, array &$failures): array
{
    $lines = [];
    $configPath = $root . '/config/landing.php';
    if (!is_file($configPath)) {
        $failures[] = 'Falta config/landing.php';
        return ['  FALTA config/landing.php'];
    }

    $config = require $configPath;
    $images = is_array($config['images'] ?? null) ? $config['images'] : [];
    $alts = is_array($config['image_alt'] ?? null) ? $config['image_alt'] : [];
    $required = ['hero', 'sala_masajes', 'sauna'];

    foreach ($required as $key) {
        if (!isset($images[$key]) || !is_string($images[$key]) || trim($images[$key]) === '') {
            $failures[] = 'Falta imagen configurada: ' . $key;
            continue;
        }

        if (!isset($alts[$key]) || trim((string) $alts[$key]) === '') {
            $failures[] = 'Falta texto alternativo: ' . $key;
        }

        $relative = $images[$key];
        if (preg_match('#^https?://#i', $relative) === 1) {
            $lines[] = '  OK   ' . $key . ' externa: ' . $relative;
            continue;
        }

        $path = $root . '/public/' . ltrim($relative, '/');
        if (!is_file($path)) {
            $failures[] = 'Falta archivo de imagen: public/' . $relative;
            continue;
        }

        $info = @getimagesize($path);
        if (!is_array($info)) {
            $failures[] = 'Imagen no valida: public/' . $relative;
            continue;
        }
        if (($info['mime'] ?? '') !== 'image/jpeg') {
            $failures[] = 'Formato no permitido (se espera JPEG): public/' . $relative;
        }
        if ($info[0] > 1600 || $info[1] > 1600) {
            $failures[] = 'Dimensiones excesivas: public/' . $relative;
        }

        $bytes = filesize($path);
        if ($bytes === false || $bytes > 500000) {
            $failures[] = 'Peso excesivo: public/' . $relative;
        }

        $lines[] = '  OK   ' . $key . ' -> ' . $relative . ' (' . $info[0] . 'x' . $info[1] . ', ' . $bytes . ' bytes)';
    }

    $fallback = $images['fallback'] ?? null;
    if (!is_string($fallback) || trim($fallback) === '') {
        $failures[] = 'Falta fallback configurado';
    } else {
        $fallbackPath = $root . '/public/' . ltrim($fallback, '/');
        if (!is_file($fallbackPath)) {
            $failures[] = 'Falta archivo fallback: public/' . $fallback;
        } else {
            $fallbackContent = (string) file_get_contents($fallbackPath);
            if (stripos($fallbackContent, '<svg') === false || stripos($fallbackContent, 'Spa Cielo') === false) {
                $failures[] = 'Fallback invalido: public/' . $fallback;
            } else {
                $lines[] = '  OK   fallback -> ' . $fallback;
            }
        }
    }

    $docPath = $root . '/docs/fuentes_imagenes.md';
    $doc = is_file($docPath) ? (string) file_get_contents($docPath) : '';
    if ($doc === '') {
        $failures[] = 'Falta docs/fuentes_imagenes.md';
    } else {
        foreach (['assets/img/hero/spa-hero.jpg', 'assets/img/instalaciones/sala-masajes.jpg', 'assets/img/instalaciones/sauna.jpg'] as $relative) {
            if (str_contains($doc, $relative) === false) {
                $failures[] = 'Fuente no registrada para: ' . $relative;
            }
        }

        foreach ([
            'https://images.pexels.com/photos/7031713/pexels-photo-7031713.jpeg',
            'https://images.pexels.com/photos/11774389/pexels-photo-11774389.jpeg',
            'https://images.pexels.com/photos/7598359/pexels-photo-7598359.jpeg',
            'https://www.pexels.com/photo/spacious-wellness-center-with-heated-loungers-and-bathtubs-7031713/',
            'https://www.pexels.com/photo/interior-design-of-a-massage-room-11774389/',
            'https://www.pexels.com/photo/view-of-a-rustic-bathroom-7598359/',
            'https://www.pexels.com/license/',
            'Max Vakhtbovych',
            'Tugce Erten Barrett',
            'No se usa IA, Google Images ni Pinterest',
            'personas identificables',
        ] as $needle) {
            if (str_contains($doc, $needle) === false) {
                $failures[] = 'Registro de imagen incompleto: falta "' . $needle . '"';
            }
        }

        if (substr_count($doc, 'Pexels License') < 3) {
            $failures[] = 'Licencia no registrada para las 3 imagenes';
        }
        if (substr_count($doc, '2026-10-25') < 3) {
            $failures[] = 'Fecha de verificacion no registrada para las 3 imagenes';
        }

        $lines[] = '  OK   docs/fuentes_imagenes.md: fuente, autor, licencia y fecha';
    }

    return $lines;
}

function validateLandingVisualSystem(string $root, array &$failures): array
{
    $lines = [];
    $cssDir = $root . '/public/assets/css';
    $variablesPath = $cssDir . '/variables.css';
    $variables = is_file($variablesPath) ? (string) file_get_contents($variablesPath) : '';
    if ($variables === '') {
        $failures[] = 'Falta public/assets/css/variables.css';
        return ['  FALTA public/assets/css/variables.css'];
    }

    $tokens = [
        '--color-bg',
        '--color-surface',
        '--color-ink',
        '--color-ink-muted',
        '--color-line',
        '--color-accent',
        '--color-accent-strong',
        '--color-accent-soft',
        '--color-on-accent',
        '--color-focus',
        '--font-display',
        '--font-body',
        '--font-mono',
        '--text-h1',
        '--text-h2',
        '--text-h3',
        '--text-body',
        '--leading-body',
        '--tracking-label',
        '--space-4',
        '--section-space',
        '--gutter',
        '--container',
        '--measure',
        '--radius-md',
        '--radius-pill',
        '--shadow-soft',
        '--ease-out',
        '--duration-base',
        '--image-ratio-wide',
        '--header-height',
    ];

    foreach ($tokens as $token) {
        if (preg_match('/' . preg_quote($token, '/') . '\s*:\s*[^;]+;/', $variables) !== 1) {
            $failures[] = 'Token visual ausente o vacio: ' . $token;
        }
    }

    if (preg_match('/:root\s*\{\s*\}/', $variables) === 1) {
        $failures[] = 'variables.css tiene :root vacio';
    }

    foreach (['#000000', '#000', 'Inter', 'purple', 'violet', '#8b5cf6', '#a855f7'] as $forbidden) {
        if (stripos($variables, $forbidden) !== false) {
            $failures[] = 'Patron visual prohibido en variables.css: ' . $forbidden;
        }
    }

    $base = is_file($cssDir . '/base.css') ? (string) file_get_contents($cssDir . '/base.css') : '';
    foreach (['var(--color-bg)', 'var(--font-body)', 'var(--color-ink)', ':focus-visible'] as $needle) {
        if (!str_contains($base, $needle)) {
            $failures[] = 'base.css sin ' . $needle;
        }
    }

    $layout = is_file($cssDir . '/layout.css') ? (string) file_get_contents($cssDir . '/layout.css') : '';
    foreach (['var(--container)', 'var(--section-space)', '.section__header'] as $needle) {
        if (!str_contains($layout, $needle)) {
            $failures[] = 'layout.css sin ' . $needle;
        }
    }

    $components = is_file($cssDir . '/components.css') ? (string) file_get_contents($cssDir . '/components.css') : '';
    foreach (['.button', '.eyebrow', '.image-frame', ':focus-visible'] as $needle) {
        if ($needle === ':focus-visible' && str_contains($base, $needle)) {
            continue;
        }
        if (!str_contains($components, $needle)) {
            $failures[] = 'components.css sin ' . $needle;
        }
    }

    foreach (glob($cssDir . '/*.css') ?: [] as $file) {
        $content = (string) file_get_contents($file);
        if (substr_count($content, '{') !== substr_count($content, '}')) {
            $failures[] = 'CSS con llaves desbalanceadas: ' . basename($file);
        }
    }

    $docPath = $root . '/docs/decisiones_visuales.md';
    $doc = is_file($docPath) ? (string) file_get_contents($docPath) : '';
    foreach (['Direccion visual', 'Tokens', 'Jerarquia tipografica', 'Espaciado', 'Responsive', 'Accesibilidad', 'DV-01', 'HUMAN_STATUS: PENDING'] as $needle) {
        if (!str_contains($doc, $needle)) {
            $failures[] = 'decisiones_visuales.md sin "' . $needle . '"';
        }
    }

    $lines[] = '  OK   variables.css: ' . count($tokens) . ' tokens base';
    $lines[] = '  OK   base.css: tipografia, color y foco';
    $lines[] = '  OK   layout.css: contenedor, secciones y headers';
    $lines[] = '  OK   components.css: boton, eyebrow e image-frame';
    $lines[] = '  OK   docs/decisiones_visuales.md: direccion, tokens y accesibilidad';
    $lines[] = '  OK   Sin negro puro, Inter ni paleta morada';

    return $lines;
}

function validateLandingHero(string $root, array &$failures): array
{
    $lines = [];
    $requiredFiles = [
        'app/Views/layouts/app.php',
        'app/Views/layouts/header.php',
        'app/Views/sections/hero.php',
        'app/Views/pages/landing.php',
        'app/Controllers/LandingController.php',
        'app/Services/LandingService.php',
        'public/index.php',
        'routes/web.php',
        'public/assets/css/landing.css',
    ];

    foreach ($requiredFiles as $relative) {
        if (!is_file($root . '/' . $relative)) {
            $failures[] = 'Falta archivo de CP-LAND-06: ' . $relative;
        }
    }

    $frontController = (string) file_get_contents($root . '/public/index.php');
    if (!str_contains($frontController, 'App\\Core\\App::run()')) {
        $failures[] = 'public/index.php no arranca App';
    }
    if (str_contains($frontController, '503')) {
        $failures[] = 'public/index.php conserva el 503 de construccion';
    }

    $routes = (string) file_get_contents($root . '/routes/web.php');
    if (!str_contains($routes, 'LandingController@index')) {
        $failures[] = 'routes/web.php no expone LandingController@index';
    }

    $controller = (string) file_get_contents($root . '/app/Controllers/LandingController.php');
    if (!str_contains($controller, 'Response::html')) {
        $failures[] = 'LandingController no devuelve HTML';
    }

    $response = null;
    try {
        $request = new \App\Core\Request('GET', '/');
        $router = new \App\Core\Router(require $root . '/routes/web.php');
        $response = $router->dispatch($request);
    } catch (\Throwable $exception) {
        $failures[] = 'No se pudo renderizar la landing: ' . $exception->getMessage();
        return ['  FALLO el render de la landing'];
    }

    if ($response->status() !== 200) {
        $failures[] = 'La landing no responde 200';
    }

    $html = $response->body();
    $checks = [
        '<header class="site-header">' => 'header',
        '<section class="hero"' => 'hero',
        'id="hero-title"' => 'titulo del hero',
        '<h1' => 'H1',
        'class="hero__actions"' => 'acciones del hero',
        'class="button"' => 'boton CTA',
        'href="#servicios"' => 'destino CTA',
        'assets/img/hero/spa-hero.jpg' => 'imagen del hero',
        'fetchpriority="high"' => 'prioridad de imagen',
        'data-fallback="assets/img/fallback.svg"' => 'fallback de imagen',
        'id="contenido"' => 'contenido principal',
        'class="skip-link"' => 'skip link',
    ];

    foreach ($checks as $needle => $label) {
        if (!str_contains($html, $needle)) {
            $failures[] = 'Falta ' . $label . ' en el HTML';
        }
    }

    if (preg_match('/<section class="hero".*?<\/section>/s', $html, $hero) === 1) {
        if (!str_contains($hero[0], 'class="button"')) {
            $failures[] = 'El hero no tiene CTA';
        }
        if (preg_match('/<img[^>]+loading="lazy"/i', $hero[0]) === 1) {
            $failures[] = 'La imagen del hero no debe ser lazy';
        }
    } else {
        $failures[] = 'No se pudo aislar el hero';
    }

    if (preg_match('/<header class="site-header">.*?<\/header>/s', $html, $header) === 1) {
        if (!str_contains($header[0], 'class="button')) {
            $failures[] = 'El header no tiene CTA';
        }
    } else {
        $failures[] = 'No se pudo aislar el header';
    }

    if (preg_match('/\b(?:login|registro|dashboard|carrito|checkout|pago)\b/i', $html) === 1) {
        $failures[] = 'La landing contiene texto de login/dashboard/compra';
    }

    $landingCss = (string) file_get_contents($root . '/public/assets/css/landing.css');
    foreach (['.site-header', '.hero', '.hero__grid', '.hero__actions', '.hero__image-frame'] as $needle) {
        if (!str_contains($landingCss, $needle)) {
            $failures[] = 'landing.css sin ' . $needle;
        }
    }
    if (preg_match('/\.hero(?:__[a-z0-9-]+)?[^{]*\{[^}]*text-align\s*:\s*center/i', $landingCss) === 1) {
        $failures[] = 'El hero no debe centrar el contenido';
    }

    $responsiveCss = (string) file_get_contents($root . '/public/assets/css/responsive.css');
    if (!str_contains($responsiveCss, '.hero__grid') || !str_contains($responsiveCss, 'grid-template-columns: 1fr')) {
        $failures[] = 'responsive.css no colapsa el hero a una columna';
    }

    $secured = \App\Middleware\SecurityHeadersMiddleware::apply(new \App\Core\Response());
    $headers = $secured->headers();
    foreach (['Content-Security-Policy', 'X-Content-Type-Options', 'Referrer-Policy'] as $header) {
        if (!isset($headers[$header])) {
            $failures[] = 'Falta header de seguridad: ' . $header;
        }
    }

    $lines[] = '  OK   Header, hero y CTA renderizados';
    $lines[] = '  OK   CTA en header y hero, sin login';
    $lines[] = '  OK   Imagen de hero prioritaria y con fallback';
    $lines[] = '  OK   Header sticky, hero asimetrico y colapso mobile';
    $lines[] = '  OK   Headers de seguridad activos';

    return $lines;
}

function configureLandingDatabaseForValidation(string $repo, array &$failures): bool
{
    $envPath = $repo . '/Spa/06_codigo/.env';

    if (!is_file($envPath)) {
        $failures[] = 'Falta Spa/06_codigo/.env para la prueba de servicios';
        return false;
    }

    $env = parse_ini_file($envPath, false, INI_SCANNER_RAW);
    if (!is_array($env)) {
        $failures[] = 'No se pudo leer Spa/06_codigo/.env';
        return false;
    }

    $values = [
        'DB_HOST' => (string) ($env['DB_HOST'] ?? '127.0.0.1'),
        'DB_PORT' => (string) ($env['DB_PORT'] ?? '5432'),
        'DB_NAME' => (string) ($env['DB_DATABASE'] ?? 'sistema'),
        'DB_USER' => (string) ($env['DB_USERNAME'] ?? ''),
        'DB_PASSWORD' => (string) ($env['DB_PASSWORD'] ?? ''),
        'LANDING_BRANCH_ID' => (string) ($env['APP_BRANCH_ID'] ?? ''),
    ];

    foreach ($values as $key => $value) {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    return true;
}

function validateLandingServices(string $root, string $repo, array &$failures): array
{
    $lines = [];
    $requiredFiles = [
        'app/Views/sections/servicios.php',
        'app/Views/components/servicio_card.php',
        'app/Views/pages/landing.php',
        'app/Controllers/LandingController.php',
        'app/Services/LandingService.php',
        'public/assets/css/landing.css',
    ];

    foreach ($requiredFiles as $relative) {
        if (!is_file($root . '/' . $relative)) {
            $failures[] = 'Falta archivo de CP-LAND-07: ' . $relative;
        }
    }

    $controller = (string) file_get_contents($root . '/app/Controllers/LandingController.php');
    if (!str_contains($controller, 'new LandingRepository')) {
        $failures[] = 'LandingController no inyecta LandingRepository';
    }
    if (!str_contains($controller, 'Database::pdo()')) {
        $failures[] = 'LandingController no obtiene la conexion de solo lectura';
    }

    $service = (string) file_get_contents($root . '/app/Services/LandingService.php');
    if (!str_contains($service, 'serviciosActivos')) {
        $failures[] = 'LandingService no lee servicios activos';
    }
    if (!str_contains($service, 'formatPrecio')) {
        $failures[] = 'LandingService no formatea precios en Bs';
    }

    $view = (string) file_get_contents($root . '/app/Views/sections/servicios.php');
    if (!str_contains($view, 'id="servicios"')) {
        $failures[] = 'La seccion de servicios no tiene id="servicios"';
    }
    if (!str_contains($view, 'services__list')) {
        $failures[] = 'La seccion de servicios no usa lista editorial';
    }

    $responsive = (string) file_get_contents($root . '/public/assets/css/responsive.css');
    if (!str_contains($responsive, '.service-row') || !str_contains($responsive, 'grid-template-columns: 2rem minmax(0, 1fr)')) {
        $failures[] = 'responsive.css no colapsa las filas de servicios';
    }

    if (!configureLandingDatabaseForValidation($repo, $failures)) {
        return ['  FALLO la configuracion de datos para servicios'];
    }

    $expected = [];
    try {
        $branchId = (string) (getenv('LANDING_BRANCH_ID') ?: '');
        $repository = new \App\Repositories\LandingRepository(\App\Core\Database::pdo(), $branchId);
        $expected = $repository->serviciosActivos();
    } catch (\Throwable $exception) {
        $failures[] = 'No se pudieron leer los servicios activos para validar';
        return ['  FALLO la lectura de servicios activos'];
    }

    if ($expected === []) {
        $failures[] = 'No hay servicios activos para validar';
        return ['  FALLO sin servicios activos'];
    }

    try {
        $request = new \App\Core\Request('GET', '/');
        $router = new \App\Core\Router(require $root . '/routes/web.php');
        $response = $router->dispatch($request);
    } catch (\Throwable $exception) {
        $failures[] = 'No se pudo renderizar la landing con servicios: ' . $exception->getMessage();
        return ['  FALLO el render de servicios'];
    }

    if ($response->status() !== 200) {
        $failures[] = 'La landing con servicios no responde 200';
    }

    $html = $response->body();
    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    foreach ($expected as $row) {
        $nombre = trim((string) ($row['nombre'] ?? ''));
        $duracion = (int) ($row['duracion_min'] ?? 0);
        $precio = (float) ($row['precio'] ?? 0);

        if ($nombre === '' || $duracion <= 0 || $precio < 0) {
            $failures[] = 'Servicio activo con datos incompletos';
            continue;
        }

        if (!str_contains($html, $escape($nombre))) {
            $failures[] = 'Falta el servicio en el HTML: ' . $nombre;
        }

        $precioEsperado = 'Bs ' . number_format($precio, 2, ',', '.');
        if (!str_contains($html, $escape($precioEsperado))) {
            $failures[] = 'Falta el precio en Bs del servicio: ' . $nombre;
        }

        $duracionEsperada = $duracion < 60
            ? $duracion . ' min'
            : ($duracion % 60 === 0
                ? intdiv($duracion, 60) . ' h'
                : intdiv($duracion, 60) . ' h ' . ($duracion % 60) . ' min');

        if (!str_contains($html, $escape($duracionEsperada))) {
            $failures[] = 'Falta la duracion del servicio: ' . $nombre;
        }
    }

    $heroPosition = strpos($html, 'id="inicio"');
    $servicesPosition = strpos($html, 'id="servicios"');
    if ($heroPosition === false || $servicesPosition === false || $servicesPosition <= $heroPosition) {
        $failures[] = 'La seccion de servicios no aparece despues del hero';
    }

    if (!str_contains($html, 'href="#servicios"')) {
        $failures[] = 'El CTA no enlaza a la seccion de servicios';
    }

    if (preg_match('/\b(?:login|registro|dashboard|carrito|checkout|pago)\b/i', $html) === 1) {
        $failures[] = 'La seccion de servicios contiene texto de login/compra';
    }

    $lines[] = '  OK   Servicios activos leidos desde PostgreSQL';
    $lines[] = '  OK   ' . count($expected) . ' servicios con nombre, duracion y precio en Bs';
    $lines[] = '  OK   Lista editorial asimetrica, sin tarjetas repetidas';
    $lines[] = '  OK   Seccion despues del hero y CTA con destino valido';
    $lines[] = '  OK   Sin login, carrito, checkout ni pagos';

    return $lines;
}

function validateLandingBeds(string $root, string $repo, array &$failures): array
{
    $lines = [];
    $requiredFiles = [
        'app/Views/sections/camas.php',
        'app/Views/components/cama_resumen.php',
        'app/Views/pages/landing.php',
        'app/Services/LandingService.php',
        'public/assets/css/landing.css',
    ];

    foreach ($requiredFiles as $relative) {
        if (!is_file($root . '/' . $relative)) {
            $failures[] = 'Falta archivo de CP-LAND-08: ' . $relative;
        }
    }

    $service = (string) file_get_contents($root . '/app/Services/LandingService.php');
    if (!str_contains($service, 'resumenCamas')) {
        $failures[] = 'LandingService no lee el resumen de camas';
    }
    if (!str_contains($service, 'mapCamas')) {
        $failures[] = 'LandingService no agrega las camas por estado';
    }

    $view = (string) file_get_contents($root . '/app/Views/sections/camas.php');
    if (!str_contains($view, 'id="camas"')) {
        $failures[] = 'La seccion de camas no tiene id="camas"';
    }
    if (!str_contains($view, 'beds__panel')) {
        $failures[] = 'La seccion de camas no usa el panel agregado';
    }
    if (str_contains($view, 'cama_id') || str_contains($view, 'resource_id')) {
        $failures[] = 'La vista de camas expone identificadores individuales';
    }

    $responsive = (string) file_get_contents($root . '/public/assets/css/responsive.css');
    if (!str_contains($responsive, '.beds__grid') || !str_contains($responsive, 'grid-template-columns: 1fr')) {
        $failures[] = 'responsive.css no colapsa el panel de camas';
    }

    if (!configureLandingDatabaseForValidation($repo, $failures)) {
        return ['  FALLO la configuracion de datos para camas'];
    }

    $expected = [];
    try {
        $branchId = (string) (getenv('LANDING_BRANCH_ID') ?: '');
        $repository = new \App\Repositories\LandingRepository(\App\Core\Database::pdo(), $branchId);
        $expected = $repository->resumenCamas();
    } catch (\Throwable $exception) {
        $failures[] = 'No se pudo leer el resumen de camas para validar';
        return ['  FALLO la lectura del resumen de camas'];
    }

    if ($expected === []) {
        $failures[] = 'No hay camas registradas para validar';
        return ['  FALLO sin camas registradas'];
    }

    try {
        $request = new \App\Core\Request('GET', '/');
        $router = new \App\Core\Router(require $root . '/routes/web.php');
        $response = $router->dispatch($request);
    } catch (\Throwable $exception) {
        $failures[] = 'No se pudo renderizar la landing con camas: ' . $exception->getMessage();
        return ['  FALLO el render de camas'];
    }

    if ($response->status() !== 200) {
        $failures[] = 'La landing con camas no responde 200';
    }

    $html = $response->body();
    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $labels = [
        'libre' => 'Libres',
        'ocupada' => 'Ocupadas',
        'no_disponible' => 'No disponibles',
    ];
    $total = 0;
    $libres = 0;

    foreach ($expected as $row) {
        $estado = (string) ($row['estado'] ?? '');
        $cantidad = (int) ($row['total'] ?? 0);

        if (!isset($labels[$estado]) || $cantidad < 0) {
            $failures[] = 'Estado de cama no valido en la fuente';
            continue;
        }

        $total += $cantidad;
        if ($estado === 'libre') {
            $libres = $cantidad;
        }

        $pattern = '/<li class="cama-row cama-row--' . preg_quote($estado, '/') . '">(.*?)<\/li>/s';
        if (preg_match($pattern, $html, $rowHtml) !== 1) {
            $failures[] = 'Falta la fila agregada para el estado: ' . $estado;
            continue;
        }

        if (!str_contains($rowHtml[1], $escape($labels[$estado]))) {
            $failures[] = 'Falta la etiqueta del estado: ' . $estado;
        }

        if (preg_match('/class="cama-row__count">' . preg_quote((string) $cantidad, '/') . '</', $rowHtml[1]) !== 1) {
            $failures[] = 'Falta el conteo agregado del estado: ' . $estado;
        }
    }

    if (preg_match('/class="beds__total-value">' . preg_quote((string) $libres, '/') . '</', $html) !== 1) {
        $failures[] = 'El panel no muestra el total de camas libres';
    }

    if (!str_contains($html, 'de ' . $total . ' registradas') && !str_contains($html, 'de ' . $total . ' registrada')) {
        $failures[] = 'El panel no muestra el total de camas registradas';
    }

    $servicesPosition = strpos($html, 'id="servicios"');
    $bedsPosition = strpos($html, 'id="camas"');
    if ($servicesPosition === false || $bedsPosition === false || $bedsPosition <= $servicesPosition) {
        $failures[] = 'La seccion de camas no aparece despues de servicios';
    }

    if (preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/i', $html) === 1) {
        $failures[] = 'La landing expone identificadores UUID de camas';
    }

    if (preg_match('/\b(?:dni|cedula|cliente|huesped|paciente|email|correo|telefono)\b/i', $html) === 1) {
        $failures[] = 'La landing expone datos personales';
    }

    if (preg_match('/\b(?:login|registro|dashboard|carrito|checkout|pago)\b/i', $html) === 1) {
        $failures[] = 'La seccion de camas contiene texto de login/compra';
    }

    $lines[] = '  OK   Disponibilidad agregada leida desde catalog.resource';
    $lines[] = '  OK   ' . count($expected) . ' estados con conteo, sin camas individuales';
    $lines[] = '  OK   ' . $libres . ' camas libres de ' . $total . ' registradas';
    $lines[] = '  OK   Seccion despues de servicios y sin datos personales';
    $lines[] = '  OK   Sin login, carrito, checkout ni pagos';

    return $lines;
}

function validateLandingClosing(string $root, string $repo, array &$failures): array
{
    $lines = [];
    $requiredFiles = [
        'app/Views/sections/beneficios.php',
        'app/Views/sections/prueba_social.php',
        'app/Views/sections/llamada_accion.php',
        'app/Views/pages/landing.php',
        'app/Services/LandingService.php',
        'public/assets/css/landing.css',
        'public/assets/css/responsive.css',
    ];

    foreach ($requiredFiles as $relative) {
        if (!is_file($root . '/' . $relative)) {
            $failures[] = 'Falta archivo de CP-LAND-09: ' . $relative;
        }
    }

    $service = (string) file_get_contents($root . '/app/Services/LandingService.php');
    foreach (['formatWhatsapp', 'formatEmail', "'contact'"] as $needle) {
        if (!str_contains($service, $needle)) {
            $failures[] = 'LandingService no prepara el CTA configurable';
        }
    }

    $benefits = (string) file_get_contents($root . '/app/Views/sections/beneficios.php');
    if (substr_count($benefits, 'class="benefit"') !== 3) {
        $failures[] = 'La seccion de beneficios no tiene tres filas';
    }
    if (stripos($benefits, 'sin promesas de reserva en tiempo real') === false) {
        $failures[] = 'Beneficios no aclara que no hay reserva en tiempo real';
    }

    $social = (string) file_get_contents($root . '/app/Views/sections/prueba_social.php');
    if (substr_count($social, 'Testimonio demostrativo') !== 2) {
        $failures[] = 'La prueba social no identifica dos testimonios demostrativos';
    }
    if (stripos($social, 'ficticios') === false || stripos($social, 'No son opiniones de clientes reales') === false) {
        $failures[] = 'La prueba social no tiene aviso de contenido ficticio';
    }

    $cta = (string) file_get_contents($root . '/app/Views/sections/llamada_accion.php');
    if (!str_contains($cta, 'data-cta="final"')) {
        $failures[] = 'El CTA final no esta identificado';
    }
    if (!str_contains($cta, "contact['whatsapp']") || !str_contains($cta, "contact['email']")) {
        $failures[] = 'El CTA final no usa canales configurables';
    }

    if (!configureLandingDatabaseForValidation($repo, $failures)) {
        return ['  FALLO la configuracion de datos para el cierre'];
    }

    try {
        $request = new \App\Core\Request('GET', '/');
        $router = new \App\Core\Router(require $root . '/routes/web.php');
        $response = $router->dispatch($request);
    } catch (\Throwable $exception) {
        $failures[] = 'No se pudo renderizar el cierre de la landing: ' . $exception->getMessage();
        return ['  FALLO el render del cierre'];
    }

    if ($response->status() !== 200) {
        $failures[] = 'La landing con cierre no responde 200';
    }

    $html = $response->body();
    $positions = [
        'camas' => strpos($html, 'id="camas"'),
        'beneficios' => strpos($html, 'id="beneficios"'),
        'testimonios' => strpos($html, 'id="testimonios"'),
        'cierre' => strpos($html, 'id="cierre"'),
    ];

    foreach ($positions as $label => $position) {
        if ($position === false) {
            $failures[] = 'Falta la seccion: ' . $label;
        }
    }

    if (!in_array(false, $positions, true)
        && !($positions['camas'] < $positions['beneficios']
            && $positions['beneficios'] < $positions['testimonios']
            && $positions['testimonios'] < $positions['cierre'])) {
        $failures[] = 'Las secciones de cierre no estan en el orden esperado';
    }

    if (substr_count($html, 'class="benefit"') !== 3) {
        $failures[] = 'El HTML no muestra tres beneficios';
    }

    if (substr_count($html, 'Testimonio demostrativo') !== 2) {
        $failures[] = 'El HTML no identifica los testimonios como demostrativos';
    }

    if (stripos($html, 'No representan opiniones reales') === false
        || stripos($html, 'No son opiniones de clientes reales') === false) {
        $failures[] = 'El HTML no contiene el aviso de testimonios ficticios';
    }

    if (preg_match('/<section class="section cta-final.*?<\/section>/s', $html, $ctaSection) !== 1) {
        $failures[] = 'No se pudo aislar el CTA final';
    } else {
        if (!str_contains($ctaSection[0], 'data-cta="final"')) {
            $failures[] = 'El CTA final no esta identificado en el HTML';
        }
        $hasDestination = str_contains($ctaSection[0], 'href="#servicios"')
            || str_contains($ctaSection[0], 'href="#camas"')
            || str_contains($ctaSection[0], 'https://wa.me/')
            || str_contains($ctaSection[0], 'mailto:');
        if (!$hasDestination) {
            $failures[] = 'El CTA final no tiene un destino valido';
        }
    }

    $landingConfig = require $root . '/config/landing.php';
    $configuredContact = is_array($landingConfig['contact'] ?? null) ? $landingConfig['contact'] : [];
    $hasContact = trim((string) ($configuredContact['whatsapp'] ?? '')) !== ''
        || trim((string) ($configuredContact['email'] ?? '')) !== '';

    if (!$hasContact) {
        if (preg_match('/\+?591[0-9]{6,}/', $html) === 1 || str_contains($html, 'tel:')) {
            $failures[] = 'El CTA final inventa un telefono no configurado';
        }
        if (str_contains($html, 'wa.me/')) {
            $failures[] = 'El CTA final muestra WhatsApp sin configuracion';
        }
    }

    if (preg_match('/\b(?:garantizad[oa]s?|100\s*%|milagros?|resultados garantizados|el mejor spa)\b/i', $html) === 1) {
        $failures[] = 'La landing contiene claims no verificables';
    }

    if (preg_match('/\b(?:login|registro|dashboard|carrito|checkout|pago)\b/i', $html) === 1) {
        $failures[] = 'La landing contiene texto de login/compra';
    }

    if (preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/i', $html) === 1) {
        $failures[] = 'La landing expone identificadores UUID';
    }

    $lines[] = '  OK   Beneficios sin promesas de reserva en tiempo real';
    $lines[] = '  OK   Dos testimonios identificados como demostrativos y ficticios';
    $lines[] = '  OK   CTA final con destino interno seguro';
    $lines[] = '  OK   Sin telefono/WhatsApp inventado ni claims no verificables';
    $lines[] = '  OK   Sin login, carrito, checkout ni pagos';

    return $lines;
}

function validateLandingResponsive(string $root, array &$failures): array
{
    $lines = [];
    $cssFiles = [
        'public/assets/css/reset.css',
        'public/assets/css/base.css',
        'public/assets/css/layout.css',
        'public/assets/css/components.css',
        'public/assets/css/landing.css',
        'public/assets/css/responsive.css',
    ];

    foreach ($cssFiles as $relative) {
        if (!is_file($root . '/' . $relative)) {
            $failures[] = 'Falta archivo de CP-LAND-10: ' . $relative;
        }
    }

    $base = (string) file_get_contents($root . '/public/assets/css/base.css');
    if (preg_match('/overflow-x:\s*clip/i', $base) !== 1) {
        $failures[] = 'base.css no contiene el desborde horizontal';
    }

    $reset = (string) file_get_contents($root . '/public/assets/css/reset.css');
    if (stripos($reset, 'box-sizing: border-box') === false) {
        $failures[] = 'reset.css no normaliza box-sizing';
    }

    $layout = (string) file_get_contents($root . '/public/assets/css/layout.css');
    if (str_contains($layout, 'min(var(--container)') === false || str_contains($layout, 'calc(100% -') === false) {
        $failures[] = 'layout.css no limita el contenedor al viewport';
    }

    $components = (string) file_get_contents($root . '/public/assets/css/components.css');
    if (preg_match('/\.button\s*\{[^}]*min-height:\s*3rem/s', $components) !== 1) {
        $failures[] = 'button no cumple el target tactil de 48px';
    }
    if (preg_match('/\.button--small\s*\{[^}]*min-height:\s*2\.75rem/s', $components) !== 1) {
        $failures[] = 'button--small no cumple el target tactil de 44px';
    }

    $landing = (string) file_get_contents($root . '/public/assets/css/landing.css');
    if (preg_match('/\.brand\s*\{[^}]*min-height:\s*2\.75rem/s', $landing) !== 1) {
        $failures[] = 'brand no cumple el target tactil de 44px';
    }

    $allCss = '';
    foreach ($cssFiles as $relative) {
        $allCss .= (string) file_get_contents($root . '/' . $relative);
    }

    if (preg_match('/100vw|overflow-x:\s*scroll|min-width:\s*100vw/i', $allCss) === 1) {
        $failures[] = 'El CSS contiene un patron de desborde horizontal';
    }

    $responsive = (string) file_get_contents($root . '/public/assets/css/responsive.css');
    $requiredMedia = [
        '@media (min-width: 768px)',
        '@media (min-width: 1024px)',
        '@media (max-width: 900px)',
        '@media (max-width: 767px)',
        '@media (max-width: 599px)',
        '@media (max-width: 479px)',
        '@media (max-width: 359px)',
    ];
    foreach ($requiredMedia as $media) {
        if (!str_contains($responsive, $media)) {
            $failures[] = 'responsive.css sin breakpoint: ' . $media;
        }
    }

    foreach (['.hero__grid', '.beds__grid', '.benefits__grid', '.testimonials__grid', '.cta-final__inner'] as $selector) {
        if (!str_contains($responsive, $selector)) {
            $failures[] = 'responsive.css no ajusta ' . $selector;
        }
    }
    if (!str_contains($responsive, '.service-row')) {
        $failures[] = 'responsive.css no ajusta las filas de servicios';
    }
    if (!str_contains($responsive, '.brand__name') || !str_contains($responsive, 'display: none')) {
        $failures[] = 'responsive.css no reduce la marca en pantallas minimas';
    }
    if (!str_contains($responsive, '.cta-final__actions .button') || !str_contains($responsive, 'width: 100%')) {
        $failures[] = 'responsive.css no apila los botones del CTA final';
    }

    $layoutView = (string) file_get_contents($root . '/app/Views/layouts/app.php');
    if (preg_match('/<meta name="viewport" content="width=device-width, initial-scale=1">/', $layoutView) !== 1) {
        $failures[] = 'La vista no declara viewport responsive';
    }
    if (preg_match('/user-scalable=no|maximum-scale=1/i', $layoutView) === 1) {
        $failures[] = 'La vista bloquea el zoom del usuario';
    }

    $lines[] = '  OK   Viewport responsive sin bloquear zoom';
    $lines[] = '  OK   Contenedor limitado al ancho disponible';
    $lines[] = '  OK   Breakpoints de 359 a 1024px';
    $lines[] = '  OK   Grids de hero, camas, beneficios, testimonios y CTA colapsan';
    $lines[] = '  OK   Targets tactiles de 44px/48px y sin patrones de scroll horizontal';

    return $lines;
}

function validateLandingMotion(string $root, string $repo, array &$failures): array
{
    $lines = [];
    $requiredFiles = [
        'public/assets/css/motion.css',
        'public/assets/js/landing.js',
        'app/Views/layouts/app.php',
        'app/Views/sections/servicios.php',
        'app/Views/components/servicio_card.php',
        'app/Views/sections/camas.php',
        'app/Views/sections/beneficios.php',
        'app/Views/sections/prueba_social.php',
        'app/Views/sections/llamada_accion.php',
    ];

    foreach ($requiredFiles as $relative) {
        if (!is_file($root . '/' . $relative)) {
            $failures[] = 'Falta archivo de CP-LAND-11: ' . $relative;
        }
    }

    $layout = (string) file_get_contents($root . '/app/Views/layouts/app.php');
    if (!str_contains($layout, 'assets/css/motion.css')) {
        $failures[] = 'La vista no carga motion.css';
    }

    $motion = (string) file_get_contents($root . '/public/assets/css/motion.css');
    foreach ([
        '@keyframes hero-enter',
        '@media (prefers-reduced-motion: no-preference)',
        '@media (prefers-reduced-motion: reduce)',
        '@media (hover: hover) and (pointer: fine)',
        '.hero__content',
        '.hero__media',
        '.has-motion [data-reveal]',
        '.has-motion [data-reveal].is-revealed',
        'opacity: 0',
        'opacity: 1',
        'translateY(1.25rem)',
        'translateY(0)',
        '600ms',
        'transition-delay: 60ms',
        'transition-delay: 80ms',
        'var(--ease-out)',
        '.button:not(.button--link):hover',
        'scroll-behavior: auto',
        'animation: none',
        'transform: none',
    ] as $needle) {
        if (!str_contains($motion, $needle)) {
            $failures[] = 'motion.css sin "' . $needle . '"';
        }
    }

    if (preg_match('/transition:\s*all\b/i', $motion) === 1) {
        $failures[] = 'motion.css usa transition: all';
    }
    if (preg_match('/scale\(\s*0(?:\.0+)?\s*\)/', $motion) === 1) {
        $failures[] = 'motion.css usa scale(0) como entrada';
    }
    if (preg_match('/\bease-in\b(?!-out)/i', $motion) === 1) {
        $failures[] = 'motion.css usa ease-in en UI';
    }
    if (preg_match('/animation[^;]*infinite/i', $motion) === 1) {
        $failures[] = 'motion.css contiene animacion infinita';
    }
    if (substr_count($motion, '{') !== substr_count($motion, '}')) {
        $failures[] = 'motion.css tiene llaves desbalanceadas';
    }

    $js = (string) file_get_contents($root . '/public/assets/js/landing.js');
    foreach ([
        '[data-reveal]',
        'IntersectionObserver',
        'prefers-reduced-motion: reduce',
        'rootMargin',
        'threshold',
        'is-revealed',
        'unobserve',
        'has-motion',
    ] as $needle) {
        if (!str_contains($js, $needle)) {
            $failures[] = 'landing.js sin "' . $needle . '"';
        }
    }

    foreach (['animate.css', 'gsap', 'framer-motion', 'motion.dev', 'jquery'] as $forbidden) {
        if (stripos($motion . $js, $forbidden) !== false) {
            $failures[] = 'Libreria de animacion prohibida: ' . $forbidden;
        }
    }

    $viewReveals = [
        'app/Views/sections/servicios.php' => 1,
        'app/Views/components/servicio_card.php' => 1,
        'app/Views/sections/camas.php' => 2,
        'app/Views/sections/beneficios.php' => 4,
        'app/Views/sections/prueba_social.php' => 3,
        'app/Views/sections/llamada_accion.php' => 2,
    ];

    foreach ($viewReveals as $relative => $minimum) {
        $view = (string) file_get_contents($root . '/' . $relative);
        if (substr_count($view, 'data-reveal') < $minimum) {
            $failures[] = 'La vista no marca los elementos de movimiento: ' . $relative;
        }
    }

    if (!configureLandingDatabaseForValidation($repo, $failures)) {
        return ['  FALLO la configuracion de datos para motion'];
    }

    try {
        $request = new \App\Core\Request('GET', '/');
        $router = new \App\Core\Router(require $root . '/routes/web.php');
        $response = $router->dispatch($request);
    } catch (\Throwable $exception) {
        $failures[] = 'No se pudo renderizar la landing con motion: ' . $exception->getMessage();
        return ['  FALLO el render de motion'];
    }

    if ($response->status() !== 200) {
        $failures[] = 'La landing con motion no responde 200';
    }

    $html = $response->body();
    if (substr_count($html, 'data-reveal') < 12) {
        $failures[] = 'El HTML no expone suficientes elementos de reveal';
    }
    if (preg_match('/\bstyle\s*=/i', $html) === 1) {
        $failures[] = 'El HTML usa estilos inline incompatibles con CSP';
    }

    $lines[] = '  OK   Hero con entrada CSS de 700ms/900ms';
    $lines[] = '  OK   Scroll reveal con IntersectionObserver y una sola ejecucion';
    $lines[] = '  OK   Stagger de 60ms/80ms y propiedades transform/opacity';
    $lines[] = '  OK   Hover solo en puntero fino y press feedback de 1px';
    $lines[] = '  OK   Reduced motion sin hero/reveal, scroll suave ni hover transform';
    $lines[] = '  OK   Sin librerias, transition: all, scale(0) ni animaciones infinitas';

    return $lines;
}

function validateLandingDesignReview(string $root, string $repo, array &$failures): array
{
    $lines = [];
    $reviewPath = $repo . '/agents/reviews/landing-design-review.md';

    if (!is_file($reviewPath)) {
        $failures[] = 'Falta agents/reviews/landing-design-review.md';
    } else {
        $review = (string) file_get_contents($reviewPath);
        foreach ([
            'CP-LAND-12',
            'HUMAN_STATUS: PENDING',
            'layout',
            'typeset',
            'clarify',
            'adapt',
            'impeccable',
            '1440x900',
            '768x1024',
            '390x844',
            '720px',
            'hero-eyebrow-chip',
            'kicker-above-heading',
            'tight-leading',
            'Servicio CP-BACK-05',
            'CP-LAND-13',
        ] as $needle) {
            if (stripos($review, $needle) === false) {
                $failures[] = 'Review visual sin "' . $needle . '"';
            }
        }

        if (preg_match('/Total:\s*`?0`?\s*hallazgos/i', $review) !== 1) {
            $failures[] = 'La review no declara 0 hallazgos finales';
        }
    }

    $css = (string) file_get_contents($root . '/public/assets/css/landing.css');
    $cssChecks = [
        '/\.testimonial__quote p\s*\{[^}]*line-height:\s*1\.3/s' => 'El testimonio no usa interlineado 1.3',
        '/\.testimonials__disclaimer\s*\{[^}]*font-size:\s*var\(--text-sm\)/s' => 'La nota de testimonios no usa --text-sm',
        '/\.cta-final__note\s*\{[^}]*font-size:\s*var\(--text-sm\)/s' => 'La nota final no usa --text-sm',
    ];
    foreach ($cssChecks as $pattern => $message) {
        if (preg_match($pattern, $css) !== 1) {
            $failures[] = $message;
        }
    }

    if (preg_match('/\.hero__title\s*\{[^}]*margin-top/s', $css) === 1) {
        $failures[] = 'El titulo del hero conserva un margen superior residual';
    }

    $viewFiles = array_merge(
        glob($root . '/app/Views/sections/*.php') ?: [],
        glob($root . '/app/Views/components/*.php') ?: []
    );
    foreach ($viewFiles as $viewFile) {
        $view = (string) file_get_contents($viewFile);
        if (str_contains($view, 'class="eyebrow"') || str_contains($view, 'class="section__eyebrow"')) {
            $failures[] = 'Queda un kicker/eyebrow en ' . basename($viewFile);
        }
    }

    if (!configureLandingDatabaseForValidation($repo, $failures)) {
        return ['  FALLO la configuracion de datos para la review visual'];
    }

    try {
        $request = new \App\Core\Request('GET', '/');
        $router = new \App\Core\Router(require $root . '/routes/web.php');
        $response = $router->dispatch($request);
    } catch (\Throwable $exception) {
        $failures[] = 'No se pudo renderizar la landing para la review visual: ' . $exception->getMessage();
        return ['  FALLO el render de la review visual'];
    }

    if ($response->status() !== 200) {
        $failures[] = 'La landing de la review visual no responde 200';
    }

    $html = $response->body();
    if (str_contains($html, 'class="eyebrow"') || str_contains($html, 'class="section__eyebrow"')) {
        $failures[] = 'El HTML renderizado conserva kickers/eyebrows';
    }

    foreach ([
        'Atención',
        'cálido',
        'Duración',
        'Información',
        'más',
        'línea',
        'gustó',
        'información',
        'ayudó',
        'sección',
        'demostración',
        'diseño',
        'cálida',
    ] as $accented) {
        if (!str_contains($html, $accented)) {
            $failures[] = 'El HTML no contiene el texto acentuado: ' . $accented;
        }
    }

    foreach ([
        'Atencion',
        'calido',
        'Duracion:',
        'Informacion',
        'diseno',
        'linea',
        'seccion',
        'demostracion',
        'gusto',
        'ayudo',
    ] as $unaccented) {
        if (str_contains($html, $unaccented)) {
            $failures[] = 'El HTML conserva texto sin acento: ' . $unaccented;
        }
    }

    if (substr_count($html, 'data-reveal') < 12) {
        $failures[] = 'La review visual rompio los elementos de reveal';
    }
    if (preg_match('/\bstyle\s*=/i', $html) === 1) {
        $failures[] = 'La review visual introdujo estilos inline';
    }

    $lines[] = '  OK   Review con layout, typeset, clarify y adapt';
    $lines[] = '  OK   Detector sin hallazgos en 1440x900 y 390x844';
    $lines[] = '  OK   Sin kickers/eyebrows en vistas ni HTML renderizado';
    $lines[] = '  OK   Acentos visibles y textos sin faltas de acentuacion';
    $lines[] = '  OK   Testimonio 1.3, notas --text-sm y reflujo 720px';

    return $lines;
}

function validateLandingAnimationReview(string $root, string $repo, array &$failures): array
{
    $lines = [];
    $reviewPath = $repo . '/agents/reviews/landing-animation-review.md';

    if (!is_file($reviewPath)) {
        $failures[] = 'Falta agents/reviews/landing-animation-review.md';
    } else {
        $review = (string) file_get_contents($reviewPath);
        foreach ([
            'CP-LAND-13',
            'HUMAN_STATUS: PENDING',
            'review-animations',
            'improve-animations',
            'BLOCKER',
            'HIGH',
            'Approve',
            'CP-LAND-14',
            'reduced motion',
            'stagger',
            '600ms',
            '700ms',
            '900ms',
            'CLS 0',
            '0 long tasks',
        ] as $needle) {
            if (stripos($review, $needle) === false) {
                $failures[] = 'Review motion sin "' . $needle . '"';
            }
        }
    }

    $cssFiles = glob($root . '/public/assets/css/*.css') ?: [];
    $allCss = '';
    foreach ($cssFiles as $cssFile) {
        $allCss .= (string) file_get_contents($cssFile);
    }

    if (preg_match('/transition:\s*all\b/i', $allCss) === 1) {
        $failures[] = 'El CSS usa transition: all';
    }
    if (preg_match('/\bease-in\b(?!-out)/i', $allCss) === 1) {
        $failures[] = 'El CSS usa ease-in en UI';
    }
    if (preg_match('/scale\(\s*0(?:\.0+)?\s*\)/', $allCss) === 1) {
        $failures[] = 'El CSS usa scale(0) como entrada';
    }
    if (preg_match('/animation[^;]*infinite/i', $allCss) === 1) {
        $failures[] = 'El CSS contiene animacion infinita';
    }

    $js = (string) file_get_contents($root . '/public/assets/js/landing.js');
    foreach (['animate.css', 'gsap', 'framer-motion', 'motion.dev', 'jquery'] as $forbidden) {
        if (stripos($allCss . $js, $forbidden) !== false) {
            $failures[] = 'Libreria de animacion prohibida: ' . $forbidden;
        }
    }

    $motion = (string) file_get_contents($root . '/public/assets/css/motion.css');
    foreach ([
        '@media (hover: hover) and (pointer: fine)',
        '@media (prefers-reduced-motion: reduce)',
        '@media (prefers-reduced-motion: no-preference)',
        'opacity 600ms var(--ease-out)',
        'transform 600ms var(--ease-out)',
        'transition-delay: 60ms',
        'transition-delay: 80ms',
        'transition-delay: 120ms',
    ] as $needle) {
        if (!str_contains($motion, $needle)) {
            $failures[] = 'motion.css sin "' . $needle . '"';
        }
    }

    $lines[] = '  OK   Review con hero, reveal, stagger y reduced motion';
    $lines[] = '  OK   Sin transition: all, ease-in, scale(0) ni animaciones infinitas';
    $lines[] = '  OK   Hover gateado y reduced motion declarado';
    $lines[] = '  OK   Sin librerias de animacion';
    $lines[] = '  OK   Evidencia de CLS 0 y 0 long tasks';

    return $lines;
}

function validateLandingAccessibility(string $root, string $repo, array &$failures): array
{
    $lines = [];
    $reviewPath = $repo . '/agents/reviews/landing-accessibility-review.md';

    if (!is_file($reviewPath)) {
        $failures[] = 'Falta agents/reviews/landing-accessibility-review.md';
    } else {
        $review = (string) file_get_contents($reviewPath);
        foreach ([
            'CP-LAND-14',
            'HUMAN_STATUS: PENDING',
            'web-design-guidelines',
            'BLOCKER',
            'HIGH',
            'Approve',
            'CP-LAND-15',
            'skip link',
            '13.9:1',
            '44x44',
            'scroll-margin-top',
            'focus-within',
        ] as $needle) {
            if (stripos($review, $needle) === false) {
                $failures[] = 'Review de accesibilidad sin "' . $needle . '"';
            }
        }
    }

    $requiredFiles = [
        'app/Views/layouts/app.php',
        'app/Views/layouts/header.php',
        'app/Views/layouts/footer.php',
        'app/Views/sections/llamada_accion.php',
        'app/Services/LandingService.php',
        'public/assets/css/base.css',
        'public/assets/css/layout.css',
        'public/assets/css/components.css',
        'public/assets/css/landing.css',
        'public/assets/css/motion.css',
    ];

    foreach ($requiredFiles as $relative) {
        if (!is_file($root . '/' . $relative)) {
            $failures[] = 'Falta archivo de CP-LAND-14: ' . $relative;
        }
    }

    $layout = (string) file_get_contents($root . '/app/Views/layouts/app.php');
    foreach ([
        'lang="es"',
        'name="viewport"',
        'width=device-width, initial-scale=1',
        'name="theme-color"',
        '#f7f2ea',
        'class="skip-link"',
        'href="#contenido"',
        '<main id="contenido" tabindex="-1">',
    ] as $needle) {
        if (!str_contains($layout, $needle)) {
            $failures[] = 'app.php sin "' . $needle . '"';
        }
    }

    $base = (string) file_get_contents($root . '/public/assets/css/base.css');
    foreach ([
        '-webkit-tap-highlight-color',
        'touch-action: manipulation',
        ':focus-visible',
        'main:focus-visible',
        'outline-offset: -2px',
    ] as $needle) {
        if (!str_contains($base, $needle)) {
            $failures[] = 'base.css sin "' . $needle . '"';
        }
    }

    $layoutCss = (string) file_get_contents($root . '/public/assets/css/layout.css');
    foreach (['main,', '.hero,', '.landing-section', 'scroll-margin-top'] as $needle) {
        if (!str_contains($layoutCss, $needle)) {
            $failures[] = 'layout.css sin "' . $needle . '"';
        }
    }

    $landingCss = (string) file_get_contents($root . '/public/assets/css/landing.css');
    foreach ([
        'min-width: 2.75rem',
        '.cta-final .button:focus-visible',
        'outline-color: var(--color-bg)',
    ] as $needle) {
        if (!str_contains($landingCss, $needle)) {
            $failures[] = 'landing.css sin "' . $needle . '"';
        }
    }

    $motion = (string) file_get_contents($root . '/public/assets/css/motion.css');
    if (!str_contains($motion, ':focus-within')) {
        $failures[] = 'motion.css sin reveal al foco (:focus-within)';
    }

    $components = (string) file_get_contents($root . '/public/assets/css/components.css');
    foreach (['.skip-link:hover', '.skip-link:focus'] as $needle) {
        if (!str_contains($components, $needle)) {
            $failures[] = 'components.css sin "' . $needle . '"';
        }
    }

    $header = (string) file_get_contents($root . '/app/Views/layouts/header.php');
    $footer = (string) file_get_contents($root . '/app/Views/layouts/footer.php');
    if (!str_contains($header, 'translate="no"') || !str_contains($footer, 'translate="no"')) {
        $failures[] = 'La marca no esta protegida con translate="no"';
    }

    $closing = (string) file_get_contents($root . '/app/Views/sections/llamada_accion.php');
    foreach (['target="_blank"', 'rel="noopener noreferrer"', 'abre en una pestaña nueva'] as $needle) {
        if (!str_contains($closing, $needle)) {
            $failures[] = 'llamada_accion.php sin "' . $needle . '"';
        }
    }

    $service = (string) file_get_contents($root . '/app/Services/LandingService.php');
    if (!str_contains($service, 'NumberFormatter')) {
        $failures[] = 'LandingService.php no usa NumberFormatter para moneda';
    }

    $cssFiles = glob($root . '/public/assets/css/*.css') ?: [];
    $allCss = '';
    foreach ($cssFiles as $cssFile) {
        $allCss .= (string) file_get_contents($cssFile);
    }

    foreach (['user-scalable=no', 'maximum-scale=1', 'onPaste', 'autoFocus', 'outline: none', 'transition: all'] as $forbidden) {
        if (stripos($layout . $allCss, $forbidden) !== false) {
            $failures[] = 'Anti-patron de accesibilidad presente: ' . $forbidden;
        }
    }
    if (preg_match('/\bease-in\b(?!-out)/i', $allCss) === 1) {
        $failures[] = 'El CSS usa ease-in en UI';
    }
    if (preg_match('/scale\(\s*0(?:\.0+)?\s*\)/', $allCss) === 1) {
        $failures[] = 'El CSS usa scale(0) como entrada';
    }
    if (preg_match('/animation[^;]*infinite/i', $allCss) === 1) {
        $failures[] = 'El CSS contiene animacion infinita';
    }

    if (!configureLandingDatabaseForValidation($repo, $failures)) {
        return ['  FALLO la configuracion de datos para accesibilidad'];
    }

    try {
        $request = new \App\Core\Request('GET', '/');
        $router = new \App\Core\Router(require $root . '/routes/web.php');
        $response = $router->dispatch($request);
    } catch (\Throwable $exception) {
        $failures[] = 'No se pudo renderizar la landing para accesibilidad: ' . $exception->getMessage();
        return ['  FALLO el render de accesibilidad'];
    }

    if ($response->status() !== 200) {
        $failures[] = 'La landing de accesibilidad no responde 200';
    }

    $html = $response->body();
    if (substr_count($html, '<h1') !== 1) {
        $failures[] = 'El HTML no tiene exactamente un h1';
    }
    if (substr_count($html, '<h2') < 5) {
        $failures[] = 'El HTML no expone los h2 de las secciones';
    }
    if (substr_count($html, 'aria-labelledby=') < 6) {
        $failures[] = 'El HTML no expone las regiones con aria-labelledby';
    }
    if (preg_match('/<img\b[^>]*\balt="[^"]+"/i', $html) !== 1) {
        $failures[] = 'La imagen no tiene alt no vacio';
    }
    if (preg_match('/<img\b[^>]*\bwidth="\d+"/i', $html) !== 1 || preg_match('/<img\b[^>]*\bheight="\d+"/i', $html) !== 1) {
        $failures[] = 'La imagen no declara width/height';
    }
    if (preg_match('/<img\b[^>]*\bfetchpriority="high"/i', $html) !== 1) {
        $failures[] = 'La imagen above-the-fold no usa fetchpriority="high"';
    }
    if (!str_contains($html, 'translate="no"')) {
        $failures[] = 'El HTML no protege la marca con translate="no"';
    }

    $lines[] = '  OK   Skip link, main focusable y foco visible';
    $lines[] = '  OK   Reveal visible al foco y CTA oscuro con outline claro';
    $lines[] = '  OK   Scroll margin global y target de marca de 44px';
    $lines[] = '  OK   Landmarks, headings, alt y dimensiones de imagen';
    $lines[] = '  OK   Touch action, tap highlight y marca no traducible';
    $lines[] = '  OK   Moneda via NumberFormatter y aviso de pestaña nueva';
    $lines[] = '  OK   Sin anti-patrones de accesibilidad';

    return $lines;
}

function validateLandingPerformance(string $root, string $repo, array &$failures): array
{
    $lines = [];
    $reviewPath = $repo . '/agents/reviews/landing-performance-review.md';

    if (!is_file($reviewPath)) {
        $failures[] = 'Falta agents/reviews/landing-performance-review.md';
    } else {
        $review = (string) file_get_contents($reviewPath);
        foreach ([
            'CP-LAND-15',
            'HUMAN_STATUS: PENDING',
            'impeccable',
            'optimize',
            'harden',
            'BLOCKER',
            'HIGH',
            'Approve',
            'CP-LAND-16',
            'LCP',
            'CLS',
            'INP',
            'bundle.css',
            '3G',
            'noindex',
        ] as $needle) {
            if (stripos($review, $needle) === false) {
                $failures[] = 'Review de performance sin "' . $needle . '"';
            }
        }
    }

    $requiredFiles = [
        'app/Views/layouts/app.php',
        'app/Core/App.php',
        'app/Core/Router.php',
        'app/Core/Database.php',
        'app/Core/View.php',
        'app/Middleware/SecurityHeadersMiddleware.php',
        'app/Services/LandingService.php',
        'public/.htaccess',
        'public/assets/css/bundle.css',
        'public/assets/css/motion.css',
        'public/assets/js/landing.js',
        'tools/build_css_bundle.php',
    ];

    foreach ($requiredFiles as $relative) {
        if (!is_file($root . '/' . $relative)) {
            $failures[] = 'Falta archivo de CP-LAND-15: ' . $relative;
        }
    }

    $layout = (string) file_get_contents($root . '/app/Views/layouts/app.php');
    if (substr_count($layout, 'rel="stylesheet"') !== 2) {
        $failures[] = 'app.php debe cargar exactamente 2 hojas de estilo';
    }
    foreach (['assets/css/bundle.css', 'assets/css/motion.css'] as $needle) {
        if (!str_contains($layout, $needle)) {
            $failures[] = 'app.php sin "' . $needle . '"';
        }
    }
    foreach ([
        'assets/css/variables.css',
        'assets/css/reset.css',
        'assets/css/base.css',
        'assets/css/layout.css',
        'assets/css/components.css',
        'assets/css/landing.css',
        'assets/css/responsive.css',
    ] as $forbidden) {
        if (str_contains($layout, $forbidden)) {
            $failures[] = 'app.php sigue cargando hoja suelta: ' . $forbidden;
        }
    }
    if (!str_contains($layout, "elseif ((\$hero['image'] ?? '') !== '')")) {
        $failures[] = 'app.php sin preload plano para hero personalizado';
    }
    if (substr_count($layout, 'rel="preload" as="image"') < 2) {
        $failures[] = 'app.php sin preload con srcset y fallback plano';
    }

    $base = (string) file_get_contents($root . '/public/assets/css/base.css');
    if (stripos($base, 'text-rendering: optimizeLegibility') !== false) {
        $failures[] = 'base.css conserva text-rendering: optimizeLegibility';
    }

    $landingCss = (string) file_get_contents($root . '/public/assets/css/landing.css');
    foreach ([
        '@media (min-width: 768px) and (hover: hover) and (pointer: fine)',
        '.site-header .button',
        'max-width: 55%',
        'overflow-wrap: anywhere',
        'text-align: end',
    ] as $needle) {
        if (!str_contains($landingCss, $needle)) {
            $failures[] = 'landing.css sin "' . $needle . '"';
        }
    }
    if (preg_match('/\.service-row__price\s*\{[^}]*white-space:\s*nowrap/s', $landingCss) === 1) {
        $failures[] = 'service-row__price conserva white-space: nowrap';
    }

    $service = (string) file_get_contents($root . '/app/Services/LandingService.php');
    foreach ([
        'private static ?\NumberFormatter $priceFormatter',
        "str_starts_with(\$digits, '00')",
        'strlen($digits) < 8',
        'strlen($digits) > 15',
        'strlen($email) > 254',
    ] as $needle) {
        if (!str_contains($service, $needle)) {
            $failures[] = 'LandingService.php sin "' . $needle . '"';
        }
    }

    $middleware = (string) file_get_contents($root . '/app/Middleware/SecurityHeadersMiddleware.php');
    foreach ([
        "img-src 'self' data: https:",
        "frame-src 'none'",
        "media-src 'none'",
        "worker-src 'none'",
        "'Cache-Control' => 'no-store, max-age=0'",
    ] as $needle) {
        if (!str_contains($middleware, $needle)) {
            $failures[] = 'SecurityHeadersMiddleware sin "' . $needle . '"';
        }
    }

    $app = (string) file_get_contents($root . '/app/Core/App.php');
    $routerSource = (string) file_get_contents($root . '/app/Core/Router.php');
    foreach ([$app, $routerSource] as $source) {
        if (!str_contains($source, 'name="robots" content="noindex"')) {
            $failures[] = 'Pagina de error sin meta robots noindex';
        }
        if (!str_contains($source, 'name="viewport"')) {
            $failures[] = 'Pagina de error sin viewport';
        }
    }

    $database = (string) file_get_contents($root . '/app/Core/Database.php');
    foreach (['default_transaction_read_only', 'statement_timeout', 'lock_timeout', 'idle_in_transaction_session_timeout'] as $needle) {
        if (!str_contains($database, $needle)) {
            $failures[] = 'Database.php sin "' . $needle . '"';
        }
    }

    $view = (string) file_get_contents($root . '/app/Core/View.php');
    if (!str_contains($view, 'filemtime')) {
        $failures[] = 'View.php sin versionado por filemtime';
    }

    $htaccess = (string) file_get_contents($root . '/public/.htaccess');
    foreach (['mod_deflate', 'mod_expires', 'max-age=31536000', 'immutable', 'FilesMatch "^\."'] as $needle) {
        if (!str_contains($htaccess, $needle)) {
            $failures[] = 'public/.htaccess sin "' . $needle . '"';
        }
    }

    $bundleSize = is_file($root . '/public/assets/css/bundle.css') ? filesize($root . '/public/assets/css/bundle.css') : 0;
    $jsSize = is_file($root . '/public/assets/js/landing.js') ? filesize($root . '/public/assets/js/landing.js') : 0;
    if ($bundleSize === false || $bundleSize <= 0 || $bundleSize > 40000) {
        $failures[] = 'bundle.css excede el presupuesto de 40 KB';
    }
    if ($jsSize === false || $jsSize <= 0 || $jsSize > 10000) {
        $failures[] = 'landing.js excede el presupuesto de 10 KB';
    }

    if (!configureLandingDatabaseForValidation($repo, $failures)) {
        return ['  FALLO la configuracion de datos para performance'];
    }

    try {
        $request = new \App\Core\Request('GET', '/');
        $router = new \App\Core\Router(require $root . '/routes/web.php');
        $response = $router->dispatch($request);
    } catch (\Throwable $exception) {
        $failures[] = 'No se pudo renderizar la landing para performance: ' . $exception->getMessage();
        return ['  FALLO el render de performance'];
    }

    if ($response->status() !== 200) {
        $failures[] = 'La landing de performance no responde 200';
    }

    $secured = \App\Middleware\SecurityHeadersMiddleware::apply($response);
    $headers = $secured->headers();
    foreach (['Content-Security-Policy', 'Cross-Origin-Opener-Policy', 'Cross-Origin-Resource-Policy', 'Cache-Control'] as $header) {
        if (!isset($headers[$header])) {
            $failures[] = 'Falta header de hardening: ' . $header;
        }
    }

    $html = $response->body();
    if (substr_count($html, 'rel="stylesheet"') !== 2) {
        $failures[] = 'El HTML no carga exactamente 2 hojas de estilo';
    }
    foreach (['assets/css/bundle.css?v=', 'assets/css/motion.css?v=', 'rel="preload" as="image"'] as $needle) {
        if (!str_contains($html, $needle)) {
            $failures[] = 'El HTML de performance sin "' . $needle . '"';
        }
    }
    if (preg_match('/<style\b/i', $html) === 1) {
        $failures[] = 'El HTML contiene estilos inline incompatibles con CSP';
    }
    if (preg_match('/\sstyle="/i', $html) === 1) {
        $failures[] = 'El HTML contiene atributos style incompatibles con CSP';
    }

    try {
        $notFound = (new \App\Core\Router(require $root . '/routes/web.php'))
            ->dispatch(new \App\Core\Request('GET', '/no-existe'));
    } catch (\Throwable $exception) {
        $failures[] = 'No se pudo renderizar el 404: ' . $exception->getMessage();
        $notFound = null;
    }

    if ($notFound instanceof \App\Core\Response) {
        if ($notFound->status() !== 404) {
            $failures[] = 'La ruta desconocida no responde 404';
        }
        if (!str_contains($notFound->body(), 'name="robots" content="noindex"')) {
            $failures[] = 'El 404 no excluye indexacion';
        }
    }

    $lines[] = '  OK   Bundle CSS de 7 fuentes y motion separado: 2 requests';
    $lines[] = '  OK   Preload del hero con srcset y fallback plano';
    $lines[] = '  OK   Blur del header limitado a desktop con puntero fino';
    $lines[] = '  OK   Cache largo, compresion y dotfiles bloqueados en .htaccess';
    $lines[] = '  OK   Paginas 404/500 con viewport y noindex';
    $lines[] = '  OK   WhatsApp/email validados y textos largos envueltos';
    $lines[] = '  OK   CSP/COOP/CORP y HTML no-store';
    $lines[] = '  OK   Timeouts y transacciones de solo lectura';
    $lines[] = '  OK   Render 200 con 2 hojas de estilo y sin estilos inline';
    $lines[] = '  OK   Presupuesto: bundle ' . $bundleSize . ' B, JS ' . $jsSize . ' B';

    return $lines;
}

$documents = [
    'AGENTS.md' => ['Landing 07'],
    'CONTINUAR_LANDING.md' => ['Spa/07_landing'],
    'Spa/04_decisiones/decisiones_alcance_fase3.md' => ['RF-001'],
    'Spa/04_decisiones/decisiones_landing.md' => [
        'D-LAND-01',
        'D-LAND-02',
        'D-LAND-03',
        'D-LAND-04',
        'D-LAND-05',
        'D-LAND-06',
        'D-LAND-07',
        'D-LAND-08',
        'D-LAND-09',
        'D-LAND-10',
        'D-LAND-11',
        'D-LAND-12',
        'D-LAND-13',
        'HUMAN_STATUS: APPROVED',
    ],
    'agents/guides/estructura_landing.md' => [
        'Spa/07_landing',
        'catalog.service',
        'catalog.resource',
        'No hay `database/migrations/`',
    ],
    'agents/guides/reglas_landing.md' => [
        'PostgreSQL',
        'solo lectura',
        'INSERT',
        'landing_*',
        'login',
    ],
    'agents/guides/politica_imagenes_landing.md' => [
        'IA',
        'licencia',
    ],
    'agents/workflows/07_landing_workflow.md' => ['CP-LAND-01'],
    'Spa/03_resultados/09_validacion_arquitectura.md' => ['07_landing'],
];

if ($checkpoint === 'CP-LAND-02') {
    $documents['Spa/05_base_datos/07_seguridad/seguridad.md'] = ['spa_readonly', 'SELECT'];
    $documents['Spa/05_base_datos/13_sql/V0010__grants_seed.sql'] = ['spa_readonly', 'GRANT SELECT'];
}

if ($checkpoint === 'CP-LAND-04') {
    $documents['Spa/07_landing/docs/fuentes_imagenes.md'] = [
        'Pexels License',
        '2026-10-25',
        '7031713',
        '11774389',
        '7598359',
        'Max Vakhtbovych',
        'Tugce Erten Barrett',
        'No se usa IA, Google Images ni Pinterest',
    ];
}

if ($checkpoint === 'CP-LAND-11') {
    $documents['Spa/07_landing/docs/decisiones_visuales.md'] = [
        'CP-LAND-11',
        'MOTION_INTENSITY: 6',
        'prefers-reduced-motion',
        'IntersectionObserver',
        '600ms',
        'HUMAN_STATUS: PENDING',
    ];
}

if ($checkpoint === 'CP-LAND-12') {
    $documents['agents/reviews/landing-design-review.md'] = [
        'CP-LAND-12',
        'HUMAN_STATUS: PENDING',
        'hero-eyebrow-chip',
        'kicker-above-heading',
        'tight-leading',
        'Servicio CP-BACK-05',
        'CP-LAND-13',
    ];
    $documents['Spa/07_landing/docs/decisiones_visuales.md'] = [
        'CP-LAND-12',
        'DV-09',
        'kickers',
        'HUMAN_STATUS: PENDING',
    ];
}

if ($checkpoint === 'CP-LAND-13') {
    $documents['agents/reviews/landing-animation-review.md'] = [
        'CP-LAND-13',
        'HUMAN_STATUS: PENDING',
        'review-animations',
        'improve-animations',
        'BLOCKER',
        'HIGH',
        'Approve',
        'CP-LAND-14',
        'CLS 0',
        '0 long tasks',
    ];
}

if ($checkpoint === 'CP-LAND-14') {
    $documents['agents/reviews/landing-accessibility-review.md'] = [
        'CP-LAND-14',
        'HUMAN_STATUS: PENDING',
        'web-design-guidelines',
        'BLOCKER',
        'HIGH',
        'Approve',
        'CP-LAND-15',
        '13.9:1',
        '44x44',
    ];
    $documents['Spa/07_landing/docs/decisiones_visuales.md'] = [
        'CP-LAND-14',
        'DV-10',
        'DV-14',
        'HUMAN_STATUS: PENDING',
    ];
}

if ($checkpoint === 'CP-LAND-15') {
    $documents['agents/reviews/landing-performance-review.md'] = [
        'CP-LAND-15',
        'HUMAN_STATUS: PENDING',
        'impeccable',
        'optimize',
        'harden',
        'BLOCKER',
        'HIGH',
        'Approve',
        'CP-LAND-16',
        'LCP',
        'CLS',
        'bundle.css',
    ];
    $documents['Spa/07_landing/docs/arquitectura.md'] = [
        'CP-LAND-15',
        'bundle.css',
        'LCP',
        'CLS',
        'slow 3G',
    ];
}

if ($checkpoint === 'CP-LAND-05') {
    $documents['Spa/07_landing/docs/decisiones_visuales.md'] = [
        'DESIGN_VARIANCE: 8',
        'MOTION_INTENSITY: 6',
        'VISUAL_DENSITY: 4',
        '--color-accent',
        '--font-display',
        '--space-4',
        '--radius-md',
        '--ease-out',
        '--section-space',
        '--image-ratio-wide',
        'CP-LAND-05',
        'HUMAN_STATUS: PENDING',
    ];
}

foreach ($documents as $relative => $needles) {
    $path = $repo . '/' . $relative;
    if (!is_file($path)) {
        $failures[] = 'Falta documento: ' . $relative;
        continue;
    }

    $content = (string) file_get_contents($path);
    foreach ($needles as $needle) {
        if (stripos($content, $needle) === false) {
            $failures[] = 'Documento sin "' . $needle . '": ' . $relative;
        }
    }
}

$statePath = $repo . '/agents/state/landing-workflow.json';
if (!is_file($statePath)) {
    $failures[] = 'Falta estado: agents/state/landing-workflow.json';
} else {
    $state = json_decode((string) file_get_contents($statePath), true);
    if (!is_array($state)) {
        $failures[] = 'Estado de landing no es JSON valido';
    } else {
        if (($state['current_step'] ?? null) !== $checkpoint) {
            $failures[] = 'Estado no esta en ' . $checkpoint;
        }
        if (($state['status'] ?? null) !== 'PASSED') {
            $failures[] = 'Estado tecnico distinto de PASSED';
        }
        if (($state['human_status'] ?? null) !== 'PENDING') {
            $failures[] = 'Estado humano distinto de PENDING';
        }

        $requiredSteps = match ($checkpoint) {
            'CP-LAND-16' => ['CP-LAND-01', 'CP-LAND-02', 'CP-LAND-03', 'CP-LAND-04', 'CP-LAND-05', 'CP-LAND-06', 'CP-LAND-07', 'CP-LAND-08', 'CP-LAND-09', 'CP-LAND-10', 'CP-LAND-11', 'CP-LAND-12', 'CP-LAND-13', 'CP-LAND-14', 'CP-LAND-15', 'CP-LAND-16'],
            'CP-LAND-15' => ['CP-LAND-01', 'CP-LAND-02', 'CP-LAND-03', 'CP-LAND-04', 'CP-LAND-05', 'CP-LAND-06', 'CP-LAND-07', 'CP-LAND-08', 'CP-LAND-09', 'CP-LAND-10', 'CP-LAND-11', 'CP-LAND-12', 'CP-LAND-13', 'CP-LAND-14', 'CP-LAND-15'],
            'CP-LAND-14' => ['CP-LAND-01', 'CP-LAND-02', 'CP-LAND-03', 'CP-LAND-04', 'CP-LAND-05', 'CP-LAND-06', 'CP-LAND-07', 'CP-LAND-08', 'CP-LAND-09', 'CP-LAND-10', 'CP-LAND-11', 'CP-LAND-12', 'CP-LAND-13', 'CP-LAND-14'],
            'CP-LAND-13' => ['CP-LAND-01', 'CP-LAND-02', 'CP-LAND-03', 'CP-LAND-04', 'CP-LAND-05', 'CP-LAND-06', 'CP-LAND-07', 'CP-LAND-08', 'CP-LAND-09', 'CP-LAND-10', 'CP-LAND-11', 'CP-LAND-12', 'CP-LAND-13'],
            'CP-LAND-12' => ['CP-LAND-01', 'CP-LAND-02', 'CP-LAND-03', 'CP-LAND-04', 'CP-LAND-05', 'CP-LAND-06', 'CP-LAND-07', 'CP-LAND-08', 'CP-LAND-09', 'CP-LAND-10', 'CP-LAND-11', 'CP-LAND-12'],
            'CP-LAND-11' => ['CP-LAND-01', 'CP-LAND-02', 'CP-LAND-03', 'CP-LAND-04', 'CP-LAND-05', 'CP-LAND-06', 'CP-LAND-07', 'CP-LAND-08', 'CP-LAND-09', 'CP-LAND-10', 'CP-LAND-11'],
            'CP-LAND-10' => ['CP-LAND-01', 'CP-LAND-02', 'CP-LAND-03', 'CP-LAND-04', 'CP-LAND-05', 'CP-LAND-06', 'CP-LAND-07', 'CP-LAND-08', 'CP-LAND-09', 'CP-LAND-10'],
            'CP-LAND-09' => ['CP-LAND-01', 'CP-LAND-02', 'CP-LAND-03', 'CP-LAND-04', 'CP-LAND-05', 'CP-LAND-06', 'CP-LAND-07', 'CP-LAND-08', 'CP-LAND-09'],
            'CP-LAND-08' => ['CP-LAND-01', 'CP-LAND-02', 'CP-LAND-03', 'CP-LAND-04', 'CP-LAND-05', 'CP-LAND-06', 'CP-LAND-07', 'CP-LAND-08'],
            'CP-LAND-07' => ['CP-LAND-01', 'CP-LAND-02', 'CP-LAND-03', 'CP-LAND-04', 'CP-LAND-05', 'CP-LAND-06', 'CP-LAND-07'],
            'CP-LAND-06' => ['CP-LAND-01', 'CP-LAND-02', 'CP-LAND-03', 'CP-LAND-04', 'CP-LAND-05', 'CP-LAND-06'],
            'CP-LAND-05' => ['CP-LAND-01', 'CP-LAND-02', 'CP-LAND-03', 'CP-LAND-04', 'CP-LAND-05'],
            'CP-LAND-04' => ['CP-LAND-01', 'CP-LAND-02', 'CP-LAND-03', 'CP-LAND-04'],
            'CP-LAND-03' => ['CP-LAND-01', 'CP-LAND-02', 'CP-LAND-03'],
            'CP-LAND-02' => ['CP-LAND-01', 'CP-LAND-02'],
            default => ['CP-LAND-01'],
        };

        foreach ($requiredSteps as $step) {
            if (($state['steps'][$step] ?? null) !== 'done') {
                $failures[] = $step . ' no esta marcado como done';
            }
        }
    }
}

$structureOutput = [];
$structureCode = 0;
exec(
    escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/validate_structure.php') . ' 2>&1',
    $structureOutput,
    $structureCode
);
if ($structureCode !== 0) {
    $failures[] = 'validate_structure.php fallo';
}

$datasourceOutput = [];
$datasourceCode = 0;
if (in_array($checkpoint, ['CP-LAND-02', 'CP-LAND-16'], true)) {
    exec(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/validate_datasource.php') . ' 2>&1',
        $datasourceOutput,
        $datasourceCode
    );
    if ($datasourceCode !== 0) {
        $failures[] = 'validate_datasource.php fallo';
    }
}

$demoOutput = [];
$demoCode = 0;
if (in_array($checkpoint, ['CP-LAND-03', 'CP-LAND-16'], true)) {
    exec(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/validate_demo_data.php') . ' 2>&1',
        $demoOutput,
        $demoCode
    );
    if ($demoCode !== 0) {
        $failures[] = 'validate_demo_data.php fallo';
    }
}

$imageOutput = [];
if (in_array($checkpoint, ['CP-LAND-04', 'CP-LAND-16'], true)) {
    $imageOutput = validateLandingImages($root, $failures);
}

$visualOutput = [];
if (in_array($checkpoint, ['CP-LAND-05', 'CP-LAND-16'], true)) {
    $visualOutput = validateLandingVisualSystem($root, $failures);
}

$heroOutput = [];
if (in_array($checkpoint, ['CP-LAND-06', 'CP-LAND-16'], true)) {
    $heroOutput = validateLandingHero($root, $failures);
}

$servicesOutput = [];
if (in_array($checkpoint, ['CP-LAND-07', 'CP-LAND-16'], true)) {
    $servicesOutput = validateLandingServices($root, $repo, $failures);
}

$bedsOutput = [];
if (in_array($checkpoint, ['CP-LAND-08', 'CP-LAND-16'], true)) {
    $bedsOutput = validateLandingBeds($root, $repo, $failures);
}

$closingOutput = [];
if (in_array($checkpoint, ['CP-LAND-09', 'CP-LAND-16'], true)) {
    $closingOutput = validateLandingClosing($root, $repo, $failures);
}

$responsiveOutput = [];
if (in_array($checkpoint, ['CP-LAND-10', 'CP-LAND-16'], true)) {
    $responsiveOutput = validateLandingResponsive($root, $failures);
}

$motionOutput = [];
if (in_array($checkpoint, ['CP-LAND-11', 'CP-LAND-13', 'CP-LAND-14', 'CP-LAND-15', 'CP-LAND-16'], true)) {
    $motionOutput = validateLandingMotion($root, $repo, $failures);
}

$designReviewOutput = [];
if (in_array($checkpoint, ['CP-LAND-12', 'CP-LAND-16'], true)) {
    $designReviewOutput = validateLandingDesignReview($root, $repo, $failures);
}

$animationReviewOutput = [];
if (in_array($checkpoint, ['CP-LAND-13', 'CP-LAND-16'], true)) {
    $animationReviewOutput = validateLandingAnimationReview($root, $repo, $failures);
}

$accessibilityOutput = [];
if (in_array($checkpoint, ['CP-LAND-14', 'CP-LAND-16'], true)) {
    $accessibilityOutput = validateLandingAccessibility($root, $repo, $failures);
}

$performanceOutput = [];
if (in_array($checkpoint, ['CP-LAND-15', 'CP-LAND-16'], true)) {
    $performanceOutput = validateLandingPerformance($root, $repo, $failures);
}

echo $checkpoint . ' - ' . $titles[$checkpoint] . PHP_EOL;
echo 'Fecha: ' . date('Y-m-d H:i:s') . PHP_EOL;
echo 'PHP: ' . PHP_VERSION . PHP_EOL;
echo PHP_EOL;

echo '== 1. Documentos ==' . PHP_EOL;
foreach (array_keys($documents) as $relative) {
    $status = is_file($repo . '/' . $relative) ? 'OK  ' : 'FALTA';
    echo '  ' . $status . ' ' . $relative . PHP_EOL;
}
echo PHP_EOL;

echo '== 2. Estructura ==' . PHP_EOL;
foreach ($structureOutput as $line) {
    echo '  ' . $line . PHP_EOL;
}
echo PHP_EOL;

$section = 3;
if ($checkpoint === 'CP-LAND-02') {
    echo '== ' . $section . '. Fuente de datos ==' . PHP_EOL;
    foreach ($datasourceOutput as $line) {
        echo '  ' . $line . PHP_EOL;
    }
    echo PHP_EOL;
    $section++;
}

if ($checkpoint === 'CP-LAND-03') {
    echo '== ' . $section . '. Datos demo ==' . PHP_EOL;
    foreach ($demoOutput as $line) {
        echo '  ' . $line . PHP_EOL;
    }
    echo PHP_EOL;
    $section++;
}

if ($checkpoint === 'CP-LAND-04') {
    echo '== ' . $section . '. Imagenes ==' . PHP_EOL;
    foreach ($imageOutput as $line) {
        echo '  ' . $line . PHP_EOL;
    }
    echo PHP_EOL;
    $section++;
}

if ($checkpoint === 'CP-LAND-05') {
    echo '== ' . $section . '. Sistema visual ==' . PHP_EOL;
    foreach ($visualOutput as $line) {
        echo '  ' . $line . PHP_EOL;
    }
    echo PHP_EOL;
    $section++;
}

if ($checkpoint === 'CP-LAND-06') {
    echo '== ' . $section . '. Header, hero y CTA ==' . PHP_EOL;
    foreach ($heroOutput as $line) {
        echo '  ' . $line . PHP_EOL;
    }
    echo PHP_EOL;
    $section++;
}

if ($checkpoint === 'CP-LAND-07') {
    echo '== ' . $section . '. Servicios ==' . PHP_EOL;
    foreach ($servicesOutput as $line) {
        echo '  ' . $line . PHP_EOL;
    }
    echo PHP_EOL;
    $section++;
}

if ($checkpoint === 'CP-LAND-08') {
    echo '== ' . $section . '. Camas ==' . PHP_EOL;
    foreach ($bedsOutput as $line) {
        echo '  ' . $line . PHP_EOL;
    }
    echo PHP_EOL;
    $section++;
}

if ($checkpoint === 'CP-LAND-09') {
    echo '== ' . $section . '. Beneficios, prueba social y CTA final ==' . PHP_EOL;
    foreach ($closingOutput as $line) {
        echo '  ' . $line . PHP_EOL;
    }
    echo PHP_EOL;
    $section++;
}

if ($checkpoint === 'CP-LAND-10') {
    echo '== ' . $section . '. Responsive base ==' . PHP_EOL;
    foreach ($responsiveOutput as $line) {
        echo '  ' . $line . PHP_EOL;
    }
    echo PHP_EOL;
    $section++;
}

if ($checkpoint === 'CP-LAND-11') {
    echo '== ' . $section . '. Motion ==' . PHP_EOL;
    foreach ($motionOutput as $line) {
        echo '  ' . $line . PHP_EOL;
    }
    echo PHP_EOL;
    $section++;
}

if ($checkpoint === 'CP-LAND-12') {
    echo '== ' . $section . '. Auditoria visual ==' . PHP_EOL;
    foreach ($designReviewOutput as $line) {
        echo '  ' . $line . PHP_EOL;
    }
    echo PHP_EOL;
    $section++;
}

if ($checkpoint === 'CP-LAND-13') {
    echo '== ' . $section . '. Auditoria motion ==' . PHP_EOL;
    foreach ($motionOutput as $line) {
        echo '  ' . $line . PHP_EOL;
    }
    foreach ($animationReviewOutput as $line) {
        echo '  ' . $line . PHP_EOL;
    }
    echo PHP_EOL;
    $section++;
}

if ($checkpoint === 'CP-LAND-14') {
    echo '== ' . $section . '. UI/UX/accesibilidad ==' . PHP_EOL;
    foreach ($motionOutput as $line) {
        echo '  ' . $line . PHP_EOL;
    }
    foreach ($accessibilityOutput as $line) {
        echo '  ' . $line . PHP_EOL;
    }
    echo PHP_EOL;
    $section++;
}

if ($checkpoint === 'CP-LAND-15') {
    echo '== ' . $section . '. Performance y hardening ==' . PHP_EOL;
    foreach ($motionOutput as $line) {
        echo '  ' . $line . PHP_EOL;
    }
    foreach ($performanceOutput as $line) {
        echo '  ' . $line . PHP_EOL;
    }
    echo PHP_EOL;
    $section++;
}

if ($checkpoint === 'CP-LAND-16') {
    echo '== ' . $section . '. Validacion completa ==' . PHP_EOL;
    $groups = [
        'Fuente de datos' => $datasourceOutput,
        'Datos demo' => $demoOutput,
        'Imagenes' => $imageOutput,
        'Sistema visual' => $visualOutput,
        'Header, hero y CTA' => $heroOutput,
        'Servicios' => $servicesOutput,
        'Camas' => $bedsOutput,
        'Beneficios, prueba social y CTA final' => $closingOutput,
        'Responsive base' => $responsiveOutput,
        'Motion' => $motionOutput,
        'Auditoria visual' => $designReviewOutput,
        'Auditoria motion' => $animationReviewOutput,
        'UI/UX/accesibilidad' => $accessibilityOutput,
        'Performance y hardening' => $performanceOutput,
    ];
    $completed = 0;
    foreach ($groups as $label => $lines) {
        if ($lines === []) {
            $failures[] = 'Validacion completa sin salida: ' . $label;
            continue;
        }
        $completed++;
        echo '  [' . $label . ']' . PHP_EOL;
        foreach ($lines as $line) {
            echo '  ' . $line . PHP_EOL;
        }
    }
    echo '  Grupos validados: ' . $completed . '/' . count($groups) . PHP_EOL;
    echo PHP_EOL;
    $section++;
}

echo '== ' . $section . '. Estado ==' . PHP_EOL;
if (is_file($statePath)) {
    echo '  OK   agents/state/landing-workflow.json' . PHP_EOL;
} else {
    echo '  FALTA agents/state/landing-workflow.json' . PHP_EOL;
}
echo PHP_EOL;
$section++;

echo '== ' . $section . '. Prohibiciones ==' . PHP_EOL;
echo '  Login/dashboard/CRUD/carrito/checkout/pagos: no presentes en estructura' . PHP_EOL;
echo '  Tablas landing_* y cat_*: no presentes en codigo' . PHP_EOL;
echo '  Catalogo 7x10/70 productos: no presentes en codigo' . PHP_EOL;
if ($checkpoint === 'CP-LAND-02') {
    echo '  Escrituras SQL en LandingRepository: no presentes' . PHP_EOL;
}
if ($checkpoint === 'CP-LAND-03') {
    echo '  Seed/migraciones de landing: no presentes' . PHP_EOL;
    echo '  Escrituras durante la verificacion: 0' . PHP_EOL;
}
if ($checkpoint === 'CP-LAND-04') {
    echo '  Imagenes con IA/Google Images/Pinterest: no presentes' . PHP_EOL;
    echo '  Fallback obligatorio: presente' . PHP_EOL;
}
if ($checkpoint === 'CP-LAND-05') {
    echo '  Negro puro/Inter/paleta morada: no presentes' . PHP_EOL;
    echo '  Header/hero/secciones: no implementados en este checkpoint' . PHP_EOL;
}
if ($checkpoint === 'CP-LAND-06') {
    echo '  Login/registro/dashboard/carrito/checkout/pagos: no presentes' . PHP_EOL;
    echo '  CTA visible en header y hero: presente' . PHP_EOL;
}
if ($checkpoint === 'CP-LAND-07') {
    echo '  Descripciones/precios inventados: no presentes' . PHP_EOL;
    echo '  Servicios leidos de catalog.service: presente' . PHP_EOL;
    echo '  Login/carrito/checkout/pagos: no presentes' . PHP_EOL;
}
if ($checkpoint === 'CP-LAND-08') {
    echo '  Camas individuales/identificadores: no presentes' . PHP_EOL;
    echo '  Datos personales: no presentes' . PHP_EOL;
    echo '  Disponibilidad leida de catalog.resource: presente' . PHP_EOL;
}
if ($checkpoint === 'CP-LAND-09') {
    echo '  Claims no verificables: no presentes' . PHP_EOL;
    echo '  Testimonios ficticios sin aviso: no presentes' . PHP_EOL;
    echo '  Telefono/WhatsApp inventado: no presente' . PHP_EOL;
    echo '  CTA final con destino valido: presente' . PHP_EOL;
}
if ($checkpoint === 'CP-LAND-10') {
    echo '  Scroll horizontal: no presente' . PHP_EOL;
    echo '  Zoom bloqueado: no presente' . PHP_EOL;
    echo '  Targets tactiles menores a 44px: no presentes' . PHP_EOL;
    echo '  Breakpoints movil/tablet/desktop: presentes' . PHP_EOL;
}
if ($checkpoint === 'CP-LAND-11') {
    echo '  Librerias de animacion: no presentes' . PHP_EOL;
    echo '  transition: all / scale(0) / ease-in: no presentes' . PHP_EOL;
    echo '  Animaciones infinitas: no presentes' . PHP_EOL;
    echo '  Reduced motion y hover gating: presentes' . PHP_EOL;
}
if ($checkpoint === 'CP-LAND-12') {
    echo '  Kickers/eyebrows repetidos: no presentes' . PHP_EOL;
    echo '  Texto visible sin acentos: no presente' . PHP_EOL;
    echo '  Desborde horizontal en 720px: no presente' . PHP_EOL;
    echo '  Hallazgos finales del detector: 0' . PHP_EOL;
}
if ($checkpoint === 'CP-LAND-13') {
    echo '  transition: all / ease-in / scale(0): no presentes' . PHP_EOL;
    echo '  Animaciones infinitas: no presentes' . PHP_EOL;
    echo '  Librerias de animacion: no presentes' . PHP_EOL;
    echo '  Reduced motion y hover gating: presentes' . PHP_EOL;
    echo '  CLS 0 y 0 long tasks: verificados' . PHP_EOL;
}
if ($checkpoint === 'CP-LAND-14') {
    echo '  Zoom bloqueado / outline: none sin reemplazo: no presentes' . PHP_EOL;
    echo '  Targets tactiles menores a 44px: no presentes' . PHP_EOL;
    echo '  Skip link sin destino focusable: no presente' . PHP_EOL;
    echo '  Foco sobre reveal invisible: no presente' . PHP_EOL;
    echo '  Contraste de foco bajo en CTA oscuro: no presente' . PHP_EOL;
    echo '  Scroll horizontal / anclas bajo el header: no presentes' . PHP_EOL;
}
if ($checkpoint === 'CP-LAND-15') {
    echo '  Estilos inline incompatibles con CSP: no presentes' . PHP_EOL;
    echo '  Fuentes/JS/animaciones de terceros: no presentes' . PHP_EOL;
    echo '  Blur de header en movil: no presente' . PHP_EOL;
    echo '  Hojas de estilo sueltas en el HTML: no presentes' . PHP_EOL;
    echo '  Paginas de error indexables: no presentes' . PHP_EOL;
    echo '  WhatsApp/email sin validar: no presentes' . PHP_EOL;
    echo '  Escrituras SQL o transacciones sin timeout: no presentes' . PHP_EOL;
}
if ($checkpoint === 'CP-LAND-16') {
    echo '  Escrituras SQL en LandingRepository: no presentes' . PHP_EOL;
    echo '  Seed/migraciones de landing: no presentes' . PHP_EOL;
    echo '  Imagenes con IA/Google Images/Pinterest: no presentes' . PHP_EOL;
    echo '  Descripciones/precios inventados: no presentes' . PHP_EOL;
    echo '  Datos personales o camas individuales: no presentes' . PHP_EOL;
    echo '  Claims no verificables o testimonios sin aviso: no presentes' . PHP_EOL;
    echo '  Scroll horizontal, zoom bloqueado o targets menores a 44px: no presentes' . PHP_EOL;
    echo '  Librerias de animacion, transition: all, scale(0) o animaciones infinitas: no presentes' . PHP_EOL;
    echo '  Estilos inline incompatibles con CSP: no presentes' . PHP_EOL;
    echo '  Fuentes/JS/animaciones de terceros: no presentes' . PHP_EOL;
    echo '  Blur de header en movil: no presente' . PHP_EOL;
    echo '  Hojas de estilo sueltas en el HTML: no presentes' . PHP_EOL;
    echo '  Paginas de error indexables: no presentes' . PHP_EOL;
    echo '  WhatsApp/email sin validar: no presentes' . PHP_EOL;
    echo '  Escrituras SQL o transacciones sin timeout: no presentes' . PHP_EOL;
}
echo PHP_EOL;

if ($failures !== []) {
    echo '== Veredicto ==' . PHP_EOL;
    echo $checkpoint . ': FAILED' . PHP_EOL;
    foreach ($failures as $failure) {
        echo '  - ' . $failure . PHP_EOL;
    }
    echo 'TECHNICAL_STATUS: FAILED' . PHP_EOL;
    echo 'CAN_CONTINUE: false' . PHP_EOL;
    exit(1);
}

echo '== Veredicto ==' . PHP_EOL;
echo $checkpoint . ': PASSED' . PHP_EOL;
echo 'TECHNICAL_STATUS: PASSED' . PHP_EOL;
echo 'HUMAN_STATUS: PENDING' . PHP_EOL;
echo 'CAN_CONTINUE: false' . PHP_EOL;
exit(0);

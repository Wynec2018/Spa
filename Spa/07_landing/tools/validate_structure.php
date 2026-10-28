<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$requiredFiles = [
    'README.md',
    '.env.example',
    '.gitignore',
    'composer.json',
    'app/Core/App.php',
    'app/Core/Database.php',
    'app/Core/Router.php',
    'app/Core/Request.php',
    'app/Core/Response.php',
    'app/Core/View.php',
    'app/Controllers/LandingController.php',
    'app/Repositories/LandingRepository.php',
    'app/Services/LandingService.php',
    'app/Middleware/SecurityHeadersMiddleware.php',
    'app/Views/layouts/app.php',
    'app/Views/layouts/header.php',
    'app/Views/layouts/footer.php',
    'app/Views/pages/landing.php',
    'app/Views/sections/hero.php',
    'app/Views/sections/servicios.php',
    'app/Views/sections/camas.php',
    'app/Views/sections/beneficios.php',
    'app/Views/sections/prueba_social.php',
    'app/Views/sections/llamada_accion.php',
    'app/Views/components/servicio_card.php',
    'app/Views/components/cama_resumen.php',
    'config/app.php',
    'config/database.php',
    'config/landing.php',
    'routes/web.php',
    'public/index.php',
    'public/.htaccess',
    'public/favicon.svg',
    'public/assets/css/variables.css',
    'public/assets/css/reset.css',
    'public/assets/css/base.css',
    'public/assets/css/layout.css',
    'public/assets/css/components.css',
    'public/assets/css/landing.css',
    'public/assets/css/motion.css',
    'public/assets/css/responsive.css',
    'public/assets/css/bundle.css',
    'public/assets/js/landing.js',
    'docs/brief_landing.md',
    'docs/arquitectura.md',
    'docs/decisiones_visuales.md',
    'docs/fuentes_imagenes.md',
    'docs/validacion_final.md',
    'tools/validate_structure.php',
    'tools/validate_checkpoint.php',
    'tools/build_css_bundle.php',
];

$requiredDirs = [
    'app/Core',
    'app/Controllers',
    'app/Repositories',
    'app/Services',
    'app/Middleware',
    'app/Views/layouts',
    'app/Views/pages',
    'app/Views/sections',
    'app/Views/components',
    'config',
    'routes',
    'public/assets/css',
    'public/assets/js',
    'public/assets/img/hero',
    'public/assets/img/servicios',
    'public/assets/img/instalaciones',
    'public/assets/img/iconos',
    'storage/logs',
    'storage/cache',
    'docs',
    'tests/Unit',
    'tests/Integration',
    'tests/Functional',
    'tests/fixtures',
    'tests/results',
    'tools',
];

foreach ($requiredFiles as $relative) {
    if (!is_file($root . '/' . $relative)) {
        $failures[] = 'Falta archivo: ' . $relative;
    }
}

foreach ($requiredDirs as $relative) {
    if (!is_dir($root . '/' . $relative)) {
        $failures[] = 'Falta carpeta: ' . $relative;
    }
}

foreach (['database', 'database/migrations', 'database/seeds', 'seeds', 'migrations', 'vendor'] as $relative) {
    if (is_dir($root . '/' . $relative)) {
        $failures[] = 'Carpeta prohibida: ' . $relative;
    }
}

if (is_file($root . '/package.json')) {
    $failures[] = 'package.json no permitido: la landing no usa Node';
}

$scanDirs = ['app', 'public', 'routes', 'config'];
$codeFiles = [];

foreach ($scanDirs as $scanDir) {
    $path = $root . '/' . $scanDir;
    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $extension = strtolower($file->getExtension());
        if (!in_array($extension, ['php', 'js', 'css'], true)) {
            continue;
        }

        $codeFiles[] = $file->getPathname();
    }
}

$prohibitedPatterns = [
    '/\b(?:mysqli|mysql:|jquery|bootstrap|tailwind|react|vue|angular)\b/i' => 'framework o motor prohibido',
    '/\bcat_(?:categoria|producto)\b/i' => 'tabla de tienda prohibida',
    '/\blanding_[a-z0-9_]+\b/' => 'tabla landing_* prohibida',
    '/\b(?:INSERT|UPDATE|DELETE)\b/i' => 'escritura SQL prohibida',
];

foreach ($codeFiles as $file) {
    $content = (string) file_get_contents($file);
    $relative = str_replace('\\', '/', substr($file, strlen($root) + 1));

    foreach ($prohibitedPatterns as $pattern => $label) {
        if (preg_match($pattern, $content) === 1) {
            $failures[] = $label . ': ' . $relative;
        }
    }
}

$frontController = (string) file_get_contents($root . '/public/index.php');
if (preg_match('/\b(?:PDO|SELECT|INSERT|UPDATE|DELETE)\b/i', $frontController) === 1) {
    $failures[] = 'public/index.php contiene SQL/PDO';
}
if (preg_match('/<style\b/i', $frontController) === 1) {
    $failures[] = 'public/index.php contiene CSS incrustado';
}

foreach (glob($root . '/app/Views/*/*.php') ?: [] as $view) {
    $content = (string) file_get_contents($view);
    $relative = str_replace('\\', '/', substr($view, strlen($root) + 1));

    if (preg_match('/\b(?:PDO|SELECT|INSERT|UPDATE|DELETE)\b/i', $content) === 1) {
        $failures[] = 'Vista con SQL/PDO: ' . $relative;
    }
    if (preg_match('/<style\b/i', $content) === 1) {
        $failures[] = 'Vista con CSS incrustado: ' . $relative;
    }
}

$repository = (string) file_get_contents($root . '/app/Repositories/LandingRepository.php');
if (preg_match('/\b(?:INSERT|UPDATE|DELETE)\b/i', $repository) === 1) {
    $failures[] = 'LandingRepository contiene escritura SQL';
}

$composer = (string) file_get_contents($root . '/composer.json');
$composerData = json_decode($composer, true);
if (!is_array($composerData)) {
    $failures[] = 'composer.json no es JSON valido';
} else {
    $composerText = strtolower($composer);
    foreach (['laravel', 'symfony', 'jquery', 'bootstrap', 'tailwind', 'react', 'vue', 'angular'] as $dependency) {
        if (str_contains($composerText, $dependency)) {
            $failures[] = 'Dependencia prohibida en composer.json: ' . $dependency;
        }
    }
}

$databaseConfig = (string) file_get_contents($root . '/config/database.php');
if (str_contains(strtolower($databaseConfig), 'pgsql') === false) {
    $failures[] = 'config/database.php no declara PostgreSQL';
}

$gitignore = (string) file_get_contents($root . '/.gitignore');
if (preg_match('/^\.env$/m', $gitignore) !== 1) {
    $failures[] = '.gitignore no excluye .env';
}

$landingConfig = (string) file_get_contents($root . '/config/landing.php');
if (preg_match('/\+?591[0-9]{6,}/', $landingConfig) === 1) {
    $failures[] = 'config/landing.php contiene un telefono boliviano inventado';
}

$bundleSources = [
    'public/assets/css/variables.css',
    'public/assets/css/reset.css',
    'public/assets/css/base.css',
    'public/assets/css/layout.css',
    'public/assets/css/components.css',
    'public/assets/css/landing.css',
    'public/assets/css/responsive.css',
];
$bundleParts = [];
foreach ($bundleSources as $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        $failures[] = 'Falta fuente del bundle CSS: ' . $relative;
        continue;
    }
    $bundleParts[] = (string) file_get_contents($path);
}

if (count($bundleParts) === count($bundleSources)) {
    $expectedHash = hash('sha256', implode("\n", $bundleParts));
    $bundlePath = $root . '/public/assets/css/bundle.css';
    $bundle = is_file($bundlePath) ? (string) file_get_contents($bundlePath) : '';

    if ($bundle === '') {
        $failures[] = 'Falta public/assets/css/bundle.css';
    } elseif (preg_match('/css-bundle-sha256:\s*([a-f0-9]{64})/', $bundle, $matches) !== 1) {
        $failures[] = 'bundle.css no declara css-bundle-sha256';
    } elseif (!hash_equals($expectedHash, $matches[1])) {
        $failures[] = 'bundle.css esta desactualizado; ejecuta tools/build_css_bundle.php';
    }
}

$allCode = '';
foreach ($codeFiles as $file) {
    $allCode .= (string) file_get_contents($file);
}
if (preg_match('/7\s*[x×]\s*10|70\s+productos/i', $allCode) === 1) {
    $failures[] = 'Codigo con catalogo 7x10/70 productos';
}

if ($failures !== []) {
    echo '[1] STRUCTURE: FAILED' . PHP_EOL;
    foreach ($failures as $failure) {
        echo '  - ' . $failure . PHP_EOL;
    }
    exit(1);
}

echo '[0] STRUCTURE: PASSED' . PHP_EOL;
echo '  Archivos requeridos: ' . count($requiredFiles) . PHP_EOL;
echo '  Carpetas requeridas: ' . count($requiredDirs) . PHP_EOL;
echo '  Archivos de codigo revisados: ' . count($codeFiles) . PHP_EOL;
exit(0);

<?php
declare(strict_types=1);

/* Front Controller (CP-LAND-06).
 * Composición únicamente: sin SQL/PDO, sin catálogo inline, sin CSS/JS inline.
 * CP-LAND-07..09 añadirán las secciones restantes entre hero y footer.
 */
$base = dirname(__DIR__);
$landing = require $base . '/config/landing.php';
$app = require $base . '/config/app.php';

require $base . '/app/Core/Database.php';
require $base . '/app/Repositories/LandingCategoriaRepository.php';
require $base . '/app/Repositories/LandingProductoRepository.php';
require $base . '/app/Services/LandingCatalogoService.php';

$service = new App\Services\LandingCatalogoService();

// CP-LAND-07: categorías (7) desde Repository/Service; degradación a estado vacío si falla la BD.
try {
    $categorias = $service->categoriasVisibles();
} catch (Throwable $e) {
    error_log('[landing] categorias: ' . $e->getMessage());
    $categorias = [];
}

// CP-LAND-08: productos (70) agrupados por categoría desde una única consulta.
try {
    $productosGrupo = $service->productosPorCategoria();
} catch (Throwable $e) {
    error_log('[landing] productos: ' . $e->getMessage());
    $productosGrupo = [];
}

http_response_code(200);
header('Content-Type: text/html; charset=UTF-8');

require $base . '/app/Views/layouts/header.php';
require $base . '/app/Views/sections/hero.php';
require $base . '/app/Views/sections/categorias.php'; // CP-LAND-07
require $base . '/app/Views/sections/productos.php'; // CP-LAND-08

// CP-LAND-09: números reales de los datos ya cargados (sin claims inventados).
$numeroCategorias = count($categorias);
$numeroProductos = array_sum(array_map(fn($g) => count($g['productos']), $productosGrupo));

require $base . '/app/Views/sections/beneficios.php';
require $base . '/app/Views/sections/prueba_social.php';
require $base . '/app/Views/sections/llamada_accion.php';
require $base . '/app/Views/layouts/footer.php';

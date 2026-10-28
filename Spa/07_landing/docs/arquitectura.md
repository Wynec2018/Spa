# Arquitectura - Landing Spa Cielo

## Flujo

```text
Request
  -> public/index.php
  -> App
  -> Router
  -> LandingController
  -> LandingService
  -> View
  -> HTML

Datos (CP-LAND-07/08)
  -> LandingService
  -> LandingRepository
  -> PostgreSQL / RLS (solo SELECT)
```

## Capas

| Capa | Archivo | Regla |
|---|---|---|
| Front controller | `public/index.php` | composicion; sin SQL/PDO |
| Router | `app/Core/Router.php` | rutas; sin SQL |
| Controller | `app/Controllers/LandingController.php` | coordina servicio y vista; sin SQL |
| Service | `app/Services/LandingService.php` | arma datos; sin SQL |
| Repository | `app/Repositories/LandingRepository.php` | unico lugar con SQL/PDO; solo SELECT |
| Views | `app/Views/**` | presentacion; sin SQL/PDO |
| Config | `config/**` | valores publicos; sin credenciales versionadas |

## Datos

- `catalog.service`: filas con `estado='activo'`.
- `catalog.resource`: filas con `tipo='cama'`, agrupadas por `estado`.
- `core.branch`: datos publicos de la sede.
- `core.app_config`: moneda y configuracion publica permitida.

## Imagenes

- Fuente, autor, licencia y fecha: `docs/fuentes_imagenes.md`.
- Assets locales en `public/assets/img/`.
- Fallback obligatorio: `public/assets/img/fallback.svg`.
- Sin imagenes de IA, Google Images ni Pinterest.
- Servicios sin imagen verificada se muestran como filas editoriales de texto.

## Reglas

- Runtime de solo lectura con el rol `spa_readonly`.
- Transacciones `READ ONLY` y `set_config('app.branch_id', ..., true)`.
- `SET LOCAL app.branch_id` en una transaccion de lectura para RLS.
- Sin `INSERT`, `UPDATE` ni `DELETE`.
- Sin tablas `landing_*` ni `cat_*`.
- Sin login, dashboard, CRUD, carrito, checkout ni pagos.
- Sin clientes, reservas, usuarios ni datos personales en la salida.
- La disponibilidad de camas es referencial.
- Motion en `public/assets/css/motion.css` y `public/assets/js/landing.js`:
  CSS e `IntersectionObserver`, sin librerias, con `prefers-reduced-motion`.

## Performance y hardening

- `CP-LAND-15`: bundle CSS generado por `tools/build_css_bundle.php` a partir de
  `variables`, `reset`, `base`, `layout`, `components`, `landing` y `responsive`.
  El HTML carga `bundle.css` + `motion.css` (2 requests); el bundle se valida por
  hash de frescura en `validate_structure.php`.
- Hero responsive con `srcset` de 640/960/1200/1400 px, `sizes`, preload con
  `imagesrcset` y fallback plano.
- CSS y JS versionados con `?v=<filemtime>`; assets con cache largo en `.htaccess`.
- HTML con `Cache-Control: no-store`; respuestas con CSP, COOP, CORP,
  `X-Permitted-Cross-Domain-Policies` y `Permissions-Policy`.
- `X-Powered-By` eliminado en runtime.
- Compresion y cache de estaticos declarados en `public/.htaccess` para Apache.
- Fallback de imagen tolera errores previos al registro del listener.
- CTA validado contra `javascript:`, URLs protocol-relative y valores vacios.
- WhatsApp de 8-15 digitos con prefijo `00` normalizado; email de hasta 254
  caracteres.
- Paginas 404/500 con `viewport`, `robots noindex` y enlace de retorno.
- Textos largos, precios grandes y correos largos envuelven sin desbordar, incluso
  al 200% de zoom.
- `LCP` y `CLS` medidos en `CP-LAND-15`: mediana `864 ms` desktop y `1704 ms`
  mobile `slow 3G`, `CLS` `0`; `INP` sin handlers propios y `~3 ms` de script.
  Evidencia en `agents/reviews/landing-performance-review.md` y
  `tests/results/CP-LAND-15.txt`.

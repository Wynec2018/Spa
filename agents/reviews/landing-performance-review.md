# Performance y hardening - Landing Spa Cielo

- Checkpoint: `CP-LAND-15`
- Fecha: 2026-10-26
- Skill: `impeccable` (optimize/harden)
- Alcance: landing publica completa; sin cambios en `Spa/06_codigo` ni en el esquema
- Estado: `HUMAN_STATUS: PENDING`

## Contexto

La landing es una pagina publica de conversion, sin login, formularios, carrito ni
pagos. La revision busca el cuello de botella real de esta interfaz, no optimizar
codigo que no se ejecuta. Se midio antes y despues, se revisaron condiciones de red
lenta (`slow 3G`), datos extremos, errores de base de datos, ausencia de JavaScript
y reduccion de movimiento.

## Evidencia

- PHP `8.3.35`; Chrome `156.0.8078.48`; Node `24.21.0`.
- Viewports: `320x568`, `390x844`, `768x1024` y `1440x900`.
- Servidor de prueba con compresion gzip equivalente a la declarada en
  `public/.htaccess`; HTML `10,859 B` (`2,890 B` gzip).
- Estructura: `validate_structure.php` pasa con `51` archivos requeridos y `37`
  archivos de codigo.
- Presupuesto: `bundle.css` `22,301 B` (`4,488 B` gzip), `motion.css` `1,686 B`
  (`490 B` gzip), `landing.js` `1,921 B` (`680 B` gzip), hero `67,016 B`.
- El HTML carga exactamente `2` hojas de estilo (`bundle.css` y `motion.css`) y
  `1` script; el bundle agrupa `7` fuentes CSS.
- Baseline: mobile `390x844` en `slow 3G` con `8` hojas CSS: LCP `2432 ms`,
  FCP `2024 ms`, CLS `0`, `1` long task de `138 ms`.
- Despues, headless sin GPU, `5` corridas desktop `1440x900`: LCP mediana
  `864 ms` (rango `828-876 ms`), FCP igual a LCP, CLS `0`.
- Despues, headless sin GPU, `3` corridas mobile `390x844` en `slow 3G`: LCP
  mediana `1704 ms` (rango `1644-1848 ms`), FCP igual a LCP, CLS `0`.
- Con GPU headless: LCP mediana `1148 ms` desktop y `2216 ms` mobile `slow 3G`;
  CLS `0`. La diferencia es del entorno de medicion, no del HTML.
- Long tasks: `0` en la mayoria de las corridas. Se observo un outlier de
  `375 ms` desktop y otro de `123 ms` mobile en headless por software; la
  `ScriptDuration` de la pagina fue `~3 ms`, por lo que el outlier no proviene
  de JavaScript de la aplicacion.
- `INP`: la landing no tiene handlers de interaccion propios; el unico JS corre
  al cargar (fallback de imagen y reveal). El riesgo de INP alto es bajo; el
  trabajo de script medido es `~3 ms` y no hay tareas largas atribuibles al
  codigo de la pagina.
- `edge.php`: `7/7` casos pasan (base caida, sede sin datos, sede con datos,
  contacto normalizado y CTA inseguro bloqueado, WhatsApp corto y email largo
  descartados, precio grande formateado, 404 con `noindex` y headers).
- Hardening responsive: sin scroll horizontal en `320`, `390`, `768` ni `1440`;
  texto al `200%` sin desborde; contenido extremo (nombre, precio, CTA y marca
  largos) sin desborde; todos los targets interactivos miden al menos `44x44px`.
- Reduced motion: `15/15` elementos visibles, `0` animaciones,
  `scroll-behavior: auto`. Sin JavaScript: `15/15` elementos visibles y sin
  scroll horizontal.
- Reveal: al llegar a `#servicios`, `4/4` elementos visibles de esa seccion
  (encabezado y `3` filas); el resto permanece oculto porque esta bajo el
  pliegue, no por un fallo del observer.

## Hallazgos y correcciones

| # | Severidad | Ubicacion | Antes | Correccion | Verificacion |
|---|---|---|---|---|---|
| 1 | HIGH | `app/Views/layouts/app.php` | `8` hojas CSS render-blocking | `bundle.css` de `7` fuentes + `motion.css` separado | `2` requests CSS; presupuesto `22,301 B` |
| 2 | HIGH | `app/Views/layouts/app.php:16-18` | Preload del hero solo con `href` | Preload con `imagesrcset`, `imagesizes` y fallback plano | Markup renderizado y recurso del hero preloaded |
| 3 | HIGH | `app/Middleware/SecurityHeadersMiddleware.php:20` | CSP permitia imagenes externas sin limite | `img-src 'self' data: https:`; `frame-src`, `media-src` y `worker-src` en `none` | Headers verificados en 200 y 404 |
| 4 | HIGH | `app/Core/App.php:30`, `app/Core/Router.php:36` | 404/500 sin viewport y potencialmente indexables | `viewport`, `robots noindex` y enlace de retorno | `edge.php` y validador confirman 404 con `noindex` |
| 5 | MEDIUM | `public/assets/css/landing.css` | Texto al `200%` desbordaba la fila de servicio | `overflow-wrap: anywhere` en cuerpo, nombre y meta | Sin desborde en `320`, `390`, `768` y `1440` |
| 6 | MEDIUM | `public/assets/css/landing.css` | `backdrop-filter` del header activo en movil | Solo `min-width: 768px` con `hover: hover` y `pointer: fine` | Sin blur en mobile; desktop conserva el efecto |
| 7 | MEDIUM | `public/assets/css/landing.css` | Precios y CTA largos podian desbordar | `max-width: 100%`, `overflow-wrap: anywhere` y `text-align: end` | Precio de `Bs 1.234.567.890,50` y CTA largo sin desborde |
| 8 | MEDIUM | `app/Services/LandingService.php:195-218` | WhatsApp y email se aceptaban sin rango | WhatsApp `8-15` digitos, prefijo `00` normalizado; email `<=254` | Casos invalidos descartados; CTA seguro |
| 9 | LOW | `app/Services/LandingService.php:12,239-245` | `NumberFormatter` se creaba por servicio | Formatter estatico cacheado | Salida `Bs 1.234.567.890,50` conservada |
| 10 | LOW | `public/assets/css/base.css` | `text-rendering: optimizeLegibility` forzaba trabajo de fuentes | Regla eliminada | Sin cambio visual; menos trabajo de texto |

No se encontro ningun `BLOCKER`.

## Checks que pasan

- Sin CSS ni JavaScript de terceros; sin fuentes externas.
- Sin estilos inline incompatibles con CSP.
- Cache largo e `immutable` para CSS/JS; `no-store` para HTML.
- Compresion gzip y bloqueo de dotfiles declarados en `public/.htaccess`.
- Timeouts de statement, lock e idle transaction; transacciones de solo lectura.
- `X-Powered-By` eliminado; COOP, CORP, `Permissions-Policy` y
  `X-Permitted-Cross-Domain-Policies` presentes.
- Fallback de imagen tolera errores previos al registro del listener.
- Sin scroll horizontal, sin zoom bloqueado y sin targets tactiles menores a
  `44px`.
- Reduced motion y navegacion sin JavaScript conservan todo el contenido visible.

## Veredicto

**Approve.** No quedan hallazgos `BLOCKER` ni `HIGH`; los `MEDIUM` y `LOW`
detectados se corrigieron. La landing queda dentro de los presupuestos de
`bundle.css` y JS, con LCP y CLS favorables en desktop y en `slow 3G`, y con
hardening verificado para datos extremos, errores y ausencia de JavaScript.

`HUMAN_STATUS: PENDING`

No se avanza a `CP-LAND-16` hasta aprobacion humana.

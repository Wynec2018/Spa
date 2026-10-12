# Landing Performance + Hardening Review — CP-LAND-15

Skill: `impeccable` (optimize/harden). Fecha: 2026-10-01. Entregable exigido por `07_landing_workflow.md` CP-LAND-15.

## Método

- Trazas Chrome DevTools (Lighthouse navigation desktop + performance trace con reload) sobre `http://127.0.0.1:8110/` con catálogo real (7 categorías / 70 productos, BD `sistema`).
- Mediciones de layout en vivo en vw 360 / 1280 / 1440 (computed styles de grids + `scrollWidth`).
- Auditoría de tamaño de imágenes (HEAD a URLs de `imagen_url` del fixture `tests/fixtures/catalogo_bolivia.json`).
- Revisión de transiciones/motion por fuente (grep `transition` en `assets/css/*.css`) + computed styles en navegador.

## Resultados de performance (antes → después)

| Métrica | CP-14 | CP-15 |
|---|---|---|
| LCP | 671 ms | **500 ms** (`IMG.hero__img`, TTFB 27 ms, load delay 20 ms) |
| CLS | 0.00 | **0.00** |
| Render-blocking savings | 0 ms | 0 ms |
| Lighthouse (access/SEO/agentic) | 100/100/100 | 100/100/100 |
| Best Practices | 100 | **77** (ver "Fuentes ajenos" abajo) |
| Consola | 0 errores | 0 errores |
| Overflow horizontal 360/1280/1440 | 0 px | **0 px** |

## Fuentes ajenos (no bloquean, documentados)

1. **Best Practices 77 — "Uses third-party cookies"**: disparado por el CDN de imágenes `thumb.wikimedia.org` (fuente permitida por `reglas_landing.md`). No hay cookies seteadas por la app; el audit es del navegador contra el cross-origin de las imágenes. Cambiar de CDN solo por esto no está justificado.
2. **"Issues were logged in the Issues panel"**: issue de DevTools del propio navegador (mismo origen: cookies third-party), no de la landing.
3. **DocumentLatency (40 kB wasted) + Cache (18.2 kB)**: limitación de `php -S` en desarrollo (sin gzip ni `Cache-Control`). La mitigación real es de configuración de producción: activar compresión (gzip/brotli) y caché estática de `assets/` en el servidor que despliegue (Apache/Nginx). **Acción de despliegue, no de código.**

## Hallazgos y correcciones aplicados en este checkpoint

### Motion (dueño: animation review CP-13)
- **M1** `landing.css` (`.cat-card__link`, `.prod-card`): `transition` de `var(--dur)` (300 ms) → `180ms` en transform y box-shadow. Verificado en navegador: `0.18s, 0.18s`.
- **M2** `components.css` (`.btn`): `transform 160ms` + `background-color 200ms` (antes 300 ms planos); `:active` `scale(0.98)` → `scale(0.97)`. Verificado: `0.16s, 0.2s`.
- **M3** `base.css` (reduced-motion): eliminado el blanket `transition-duration: 0.01ms !important` (mataba el feedback de interacción); se conservan `animation-duration`/`animation-iteration-count`/`scroll-behavior: auto` y se añade `.btn:active { transform: none; }` en `landing.css` para anular el press feedback bajo reduced-motion. Cada transición de la landing es ≤180 ms y no-táctil por defecto, así que el usuario con reduced-motion ve cambios casi instantáneos sin perder indicación de estado.
- **L1 (motion)** `landing.css` `.site-header`: eliminada `transition: box-shadow` — la sombra del header al hacer scroll aparece al instante (sin transición de box-shadow, property de bajo rendimiento). Verificado: `transition-duration: 0s`.

### Layout / diseño (dueño: design review CP-12)
- **L1 (grid)** `.categorias__grid` `minmax(240px,1fr)` → `minmax(280px,1fr)`.
  - Antes (vw 1440): 5 cols × 7 items = **3 celdas vacías** (confirmado con medición en vivo).
  - Después: **4 cols × 7 items = 1 celda vacía** a ancho máximo (1336 px de grid); a vw 1280 también 4 cols. Con 7 ítems siempre queda ≥1 hueco con `auto-fill`; 4×2 es el mejor balance (2 filas cortas vs. 3 filas desiguales).
- **L2 (grid productos)**: el review CP-12 reportó 6 cols con items vacíos; **no reproducible**: medido a vw 1440 → `repeat(auto-fill, …)` con `minmax(300px,1fr)` da **5 cols × 10 items = 0 celdas vacías** (5×2). Sin acción. Posible diferencia de viewport del revisor (grid ancho máximo 1336 px por `--container: 1400px` + padding 32px).
- **C1 (copy)** — 3 leads de sección recortados, conservando `demo_notice` (hero/CTA/footer):
  - `config/landing.php`: `hero_subtitle` → "Explora 7 categorías y 70 productos con precios pensados para el mercado boliviano."
  - `sections/categorias.php`: → "7 categorías con productos en Bs."
  - `sections/productos.php`: → "70 productos con precios en Bs."
  - Verificado en DOM: 0 ocurrencias de "demostrativ" en los leads; restan solo los 3 `demo_notice` (disclaimer) y la descripción de la categoría 7 en el seed (`database/02_landing_seed.sql`, dato BD, fuera de alcance de copy de vistas).
- **C2 (copy)** `config/landing.php`: headline → "Productos de calidad con precios en Bs" (mismo patrón que C1).
- **C3 (copy)** `sections/beneficios.php`: valor "0" + label plural → "cuentas o registros requeridos" (gramatical con 0).

### Accesibilidad / hardening (dueño: accessibility review CP-14)
- **L3**: `<meta name="theme-color" content="#f9fafb">` (coincide con `--color-bg`) + `:root { color-scheme: light; }` en `variables.css` (UA asume esquema claro: scrollbars, form controls) + `text-wrap: balance` en `h1, h2, h3` (`base.css`).
- **L4**: `overflow-wrap: anywhere` en `.prod-card__nombre` (nombres largos parten línea sin desbordar la card; verificado a 360 px con card de 156 px).
- **A2**: `viewport-fit=cover` en el meta viewport (`layouts/header.php`) + `padding-bottom: calc(var(--space-12) + env(safe-area-inset-bottom))` en `.site-footer` — juntos, para que el footer no quede bajo la home indicator de iOS. Ambos se agregan siempre en pareja (viewport-fit sin safe-area no sirve).
- **A3 (srcset/sizes, 78 imgs)**: **documentado como techo conocido, sin implementar.** Datos de medición: `imagen_url` son thumbnails fijos `thumb.wikimedia.org/.../960px-*` (201 kB medido) y originales `upload.wikimedia.org` (64–118 kB medido); todas las imágenes de grid son `loading="lazy"` y el hero lleva `fetchpriority="high"` con load delay de 20 ms; LCP 500 ms y CLS 0.00 están muy por dentro del presupuesto. Añadir `srcset`/`sizes` (80+ atributos) o un pipeline de variantes no compra nada medible hoy. Si en futuro se agregan más imágenes above-the-fold o el LCP supera 1.5 s, la vía es generar variantes 480/960 de las URLs thumb (patrón de ancho editable en la URL) y añadir `srcset` en `views/sections/*.php`.

### Verificaciones finales (post-fixes)

- vw 360 (móvil, DPR 2): overflow X = 0; cats 1 col, prods 2 cols (156 px).
- vw 1280: overflow X = 0; cats 4 cols, prods 5 cols.
- vw 1440: overflow X = 0; cats 4 cols (1 hueco), prods 5 cols (0 huecos), grid 1336 px.
- Transiciones computadas: cards `0.18s, 0.18s`; botones `0.16s, 0.2s`; header `0s`.
- Consola: 0 mensajes. LCP 500 ms, CLS 0.00.
- Copy: leads verificados en DOM; beneficios plural correcto.

## Severidad

- BLOCKER: 0
- HIGH: 0
- MEDIUM: 0
- LOW/INFO pendientes: 0 (A3 y gzip/cache quedan como techo conocido / acción de despliegue documentados arriba)

**Veredicto: PASS.** Pendiente de dueño CP-16: ninguno.

Evidencia: `proyecto/07_landing/tests/results/CP-LAND-15.txt`.

# Landing — Revisión UI/UX y accesibilidad (CP-LAND-14)

Skill obligatorio: `web-design-guidelines` (paso 0).
Método: revisión estática de vistas/CSS/JS + auditoría en navegador real
(Lighthouse snapshot desktop y mobile sobre `http://127.0.0.1:8110/`
con catálogo real 7 categorías × 10 productos = 70).

## Hallazgos

| ID | Severidad | Ubicación (`file:line`) | Descripción | Estado |
|----|-----------|-------------------------|-------------|--------|
| A1 | HIGH | `app/Views/layouts/footer.php:23` (antes) | Script `http://localhost:8400/live.js` (impeccable-live) servido a todos los visitantes: mezcla de contenido inseguro en HTTPS, error de consola `ERR_CONNECTION_REFUSED` y vector de inyección externo. | **RESUELTO en CP-14**: bloque `impeccable-live-*` eliminado del footer (reinyectable en CP-15 durante iteración UI). |
| A2 | HIGH | `public/assets/css/landing.css` — `.cta-final__lead` (antes `rgba(255,255,255,.85)` sobre `#047857`) | Contraste 4.43:1 < 4.5:1 exigido por WCAG 2.2 AA (1.4.3). Único fallo del axe/Lighthouse. | **RESUELTO en CP-14**: `color: var(--color-on-accent)` (blanco puro) → 5.55:1. Verificado: audit `color-contrast` PASS. |
| M1 | MEDIUM | `public/assets/css/base.css:6` (`html { scroll-behavior: smooth }`) | Sin `scroll-padding-top`: el header sticky (69px) tapaba el destino de `#categorias`, `#productos`, `#beneficios` y el target del skip-link (`elementTop=0` medido en navegador). | **RESUELTO en CP-14**: `scroll-padding-top: 84px` → destinos ahora a 84px (> header). |
| M2 | MEDIUM | `public/assets/css/responsive.css:8,20,21` (antes `grid-template-columns: 1fr`/`repeat(2,1fr)`) | Overflow horizontal real en 360px: `scrollWidth=658` vs `clientWidth=360`; la imagen de categoría con `width=640` fija el min-content de la columna `1fr` (min=auto) y rompe el grid. Regresión no detectada en CP-10/CP-11 porque las corridas previas tenían catálogo vacío (BD sin tablas landing sembradas). | **RESUELTO en CP-14**: `minmax(0, 1fr)` en `.hero__grid`, `.categorias__grid`, `repeat(2, minmax(0,1fr))` en `.productos__grid`. Verificado en vivo 360/768/1280: `scrollWidth == clientWidth`, 0 elementos fuera de viewport. |
| L1 | LOW | `app/Views/layouts/header.php` | Sin `favicon.ico` → 404 en consola. | **RESUELTO en CP-14**: `<link rel="icon" href="data:,">`. |
| L2 | LOW | `public/assets/css/base.css` | Sin `touch-action: manipulation` en enlaces/botones (retardo de doble-tap en móvil). | **RESUELTO en CP-14**: regla añadida. |
| L3 | LOW | `public/assets/css/base.css` / `variables.css` | Sin `theme-color`, sin `color-scheme`, sin `text-wrap: balance` en títulos. | **NO RESUELTO** — aditivo, bajo impacto. Dueño: CP-LAND-15 (polish). |
| L4 | LOW | `public/assets/css/components.css` (`.prod-card__nombre`) | Sin `overflow-wrap` explícito para nombres largos sin espacios. | **NO RESUELTO** — los 70 nombres del seed envuelven bien (sin overflow de documento en 360px). Dueño: CP-LAND-15. |

## Verificaciones OK (reglas 1–30 de la guía)

- `lang="es"` en `<html>`; skip-link → `<main id="contenido">`; landmarks `main`/`nav`/`header`/`footer` correctos.
- Jerarquía de encabezados con catálogo real: `H1`×1, `H2`×5, `H3`×7 (un subgrupo por categoría).
- `:focus-visible` global; sin `outline: none`/`outline: 0`; sin `transition: all`; sin `!important` fuera de los overrides de `prefers-reduced-motion`.
- `alt` descriptivo en todas las imágenes; `width`/`height` + `loading="lazy"` + `fetchpriority="high"` en hero (CP-13).
- Targets ≥44px: nav 44px, botones 48–49px; a 360px sin targets <44 (skip-link mide 42px, aceptado: es el único foco visible inicial).
- `prefers-reduced-motion` en base.css y override en landing.css (CP-11/CP-13).
- Sin `user-scalable=no` ni `maximum-scale`; viewport correcto.
- Precios con `font-variant-numeric: tabular-nums`; metas title/description OK.
- Sin formularios (reglas de formularios no aplican); CTAs son enlaces reales.
- Vacíos: `#contacto` existe (footer), `contacto.php` sin incluir es intencional (CP-10).
- Nav y CTA de header ocultos <768px: decisión aprobada en CP-10 (el CTA del hero sí está visible en móvil, 49px).

## Scores post-fix (catálogo real 7×70)

| Categoría | Desktop (1280) | Mobile (360) |
|-----------|----------------|--------------|
| Accessibility | 100 | 100 |
| Best Practices | 100 | 100 |
| SEO | 100 | 100 |
| Errores de consola | 0 | 0 |

Antes de los fixes: Accessibility 95 (fallo `color-contrast`), 2 errores de consola.

## Pendiente para CP-LAND-15

- L3, L4 (polish aditivo).
- M1–M3, L1 de CP-LAND-13 (motion) ya con dueño asignado.

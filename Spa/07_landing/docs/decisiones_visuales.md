# Decisiones visuales — Landing Bolivia (CP-LAND-05)

Skill obligatorio aplicado: `design-taste-frontend` (`.agents/skills/design-taste-frontend/SKILL.md`).
Precedencia respetada: DECISIONES (D-LAND-08/09) > WORKFLOW > GUIDES > SKILLS. El skill
define dirección y reglas de craft; el stack lo fijan las decisiones (PHP 8.x, CSS/JS puro,
sin React/Next, sin Framer Motion, sin GSAP, sin Node).

## 0. Baseline del skill (globals)

- `DESIGN_VARIANCE: 8` — layout asimétrico (split, grid fraccional), con fallback estricto a
  una columna en `< 768px`.
- `MOTION_INTENSITY: 6` — fluid CSS (transform/opacity, `cubic-bezier(0.16,1,0.3,1)`,
  cascadas con `animation-delay`); coreografía real queda para CP-LAND-11 con skill `animate`.
- `VISUAL_DENSITY: 4` — modo galería/airy: respiración, secciones amplias, sin apretar.

## 1. Dirección visual

- **Arquetipo:** commerce editorial sobrio para anuncios pagados. Blanco cálido-neutro,
  tipografía con carácter, un solo acento, fotografía de producto real (70 imágenes
  Commons de CP-LAND-04) como protagonista.
- **Hero asimétrico:** texto alineado a la izquierda (55%), imagen de producto a la derecha
  (45%) con fundido sutil hacia el fondo. Prohibido hero centrado sobre imagen oscura.
- **Categorías en zig-zag 2 columnas** (alternando imagen/texto), nunca 3 tarjetas iguales.
- **Productos:** grid CSS (`grid-template-columns` responsive), tarjetas solo donde la
  elevación comunica jerarquía; etiquetas/títulos fuera o debajo de la tarjeta.
- **Marca (ficticia):** «Altiplano Marketplace» — nombre contextual boliviano, sin insinuar
  afiliación con marcas reales (regla de `reglas_landing.md`).

## 2. Tokens de color (máx. 1 acento, saturación < 80%)

| Token | Valor | Uso |
|---|---|---|
| `--color-bg` | `#f9fafb` | fondo de página |
| `--color-surface` | `#ffffff` | tarjetas, secciones elevadas |
| `--color-ink` | `#18181b` | texto principal (off-black; `#000000` prohibido) |
| `--color-ink-muted` | `#52525b` | cuerpo/secundario (contraste AA ≥ 4.5:1) |
| `--color-line` | `#e4e4e7` | bordes 1px, divisores |
| `--color-accent` | `#047857` | CTA primario, links activos, focus, precio destacado |
| `--color-accent-hover` | `#065f46` | hover/active del CTA |
| `--color-on-accent` | `#ffffff` | texto sobre acento |
| `--color-warning` | `#b45309` | precio con `precio_anterior` (tachado) |

Prohibidos: gradiente morado/azul «AI», neón, glow exterior, puro negro, degradados de
texto, más de un acento cromático.

## 3. Tipografía (sin Inter, sin serif)

- Familia única: **`Outfit`** (400/600/800) via Google Fonts con `preconnect` +
  `display=swap`; fallback `system-ui, sans-serif`. Serif prohibido para UI; Inter vetado
  por el skill.
- Escala:

| Nivel | Tamaño | Peso | Tracking |
|---|---|---|---|
| H1 | `clamp(2rem, 4.5vw, 3.25rem)` | 800 | `-0.02em`, `line-height: 1.05` |
| H2 | `clamp(1.5rem, 3vw, 2.25rem)` | 800 | `-0.015em` |
| H3 | `1.25rem` | 600 | normal |
| Body | `1rem` / `1.65` | 400 | `max-width: 65ch` |
| Small | `0.875rem` | 400 | muted |
| Precio | `1.125rem` | 800 | `font-variant-numeric: tabular-nums` |

Jerarquía por peso y color, no por tamaño descomunal (H1 no grita).

## 4. Espaciado y layout

- Base 4px: escala `4/8/12/16/24/32/48/64/96`.
- Contenedor: `max-width: 1400px; margin-inline: auto; padding-inline: 16px`
  (`24px ≥768px`, `32px ≥1280px`).
- Secciones: `padding-block: 64px` móvil / `96px` desktop.
- Grids: CSS Grid siempre (nunca `calc(33% - 1rem)` en flex): categorías `1fr` →
  `2fr 1fr` asimétrico; productos `repeat(auto-fill, minmax(240px, 1fr))`, `gap: 24px`.
- Radios: `--radius-sm: 6px` (chips), `--radius-md: 10px` (botones/inputs),
  `--radius-lg: 16px` (tarjetas).
- Sombras tintadas, sin glow: `0 20px 40px -15px rgba(24,24,27,.08)`; bordes 1px en lugar
  de caja cuando no hay elevación real.
- `< 768px`: una sola columna, `padding-inline: 16px`, sin scroll horizontal.

## 5. Componentes base

- **CTA primario:** fondo `--color-accent`, texto blanco, `padding: 14px 28px`,
  `min-height: 48px` (target táctil ≥ 44px), `:active { transform: scale(.98) }`.
- **CTA secundario:** borde 1px `--color-ink`, transparente; mismo tamaño.
- **Tarjeta de producto:** superficie blanca, borde `--color-line`, radio `--radius-lg`,
  imagen `aspect-ratio: 4/3; object-fit: cover` (reserva espacio = sin CLS),
  `loading="lazy"` fuera del hero; fallback: bloque `--color-bg` con `aspect-ratio`
  mantenido si la imagen falla.
- **Estados obligatorios:** skeleton shimmer con dimensiones finales (nada de spinner
  circular), estado vacío compuesto, error inline, focus visible `2px solid
  var(--color-accent)` con `outline-offset: 2px`.
- **Iconos:** SVG inline propio, trazo `1.5` unificado. Sin emojis en markup, textos ni
  `alt` (anti-emoji policy).

## 6. Motion (dirección para CP-LAND-11)

- Solo CSS: `transition: transform .3s cubic-bezier(.16,1,.3,1), opacity .3s …`.
- Animar únicamente `transform` y `opacity` (nunca `top/left/width/height`).
- Entradas escalonadas con `animation-delay: calc(var(--i) * 60ms)`.
- `@media (prefers-reduced-motion: reduce)` desactiva todo movimiento (obligatorio).
- Sin GSAP/Framer/parallax complejo sin aprobación explícita (prohibido por reglas).

## 7. Copy y datos

- Español de Bolivia, precios en Bs con formato demostrativo (nunca «vigente real»).
- Números orgánicos (p. ej. `Bs 85`, `Bs 129`), sin `99.99%` ni teléfonos inventados;
  WhatsApp solo si `config/landing.php` trae un valor aprobado.
- Sin relleno de marketing («eleva», «seamless», «next-gen»), verbos concretos.

## 8. Pre-flight (checklist del skill, adaptado al stack)

- [ ] Hero `min-height` con reserva; sin `h-screen`.
- [ ] Grid sobre flex-math; asimetría con fallback móvil.
- [ ] 1 acento, sin morado/neón/negro puro, contraste AA verificado.
- [ ] `Outfit` sin Inter; sin serif en UI.
- [ ] Estados loading/empty/error + feedback táctil `:active`.
- [ ] `alt` descriptivo por producto; foco visible; targets ≥ 44px.
- [ ] `prefers-reduced-motion` respetado.
- [ ] CSS en `public/assets/css/*` (variables.css define estos tokens), JS en
  `public/assets/js/landing.js`; sin código incrustado en `index.php`.

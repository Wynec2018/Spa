---
name: Spa — Cielo
description: Una Spa donde se realiza todo tipo de servicios mediante reserva y/o pago.
colors:
  bg: "#f9fafb"
  surface: "#ffffff"
  ink: "#18181b"
  ink-muted: "#52525b"
  line: "#e4e4e7"
  accent: "#047857"
  accent-hover: "#065f46"
  on-accent: "#ffffff"
  warning: "#b45309"
typography:
  display:
    fontFamily: "Outfit, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "clamp(2rem, 4.5vw, 3.25rem)"
    fontWeight: 800
    lineHeight: "1.05"
    letterSpacing: "-0.02em"
  headline:
    fontFamily: "Outfit, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "clamp(1.5rem, 3vw, 2.25rem)"
    fontWeight: 800
    lineHeight: "1.15"
    letterSpacing: "-0.015em"
  title:
    fontFamily: "Outfit, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 600
    lineHeight: "1.3"
  body:
    fontFamily: "Outfit, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: "1.65"
  label:
    fontFamily: "Outfit, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: "1.5"
  price:
    fontFamily: "Outfit, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 800
    lineHeight: "1.2"
rounded:
  sm: "6px"
  md: "10px"
  lg: "16px"
spacing:
  1: "4px"
  2: "8px"
  3: "12px"
  4: "16px"
  6: "24px"
  8: "32px"
  12: "48px"
  16: "64px"
  24: "96px"
components:
  button-primary:
    backgroundColor: "{colors.accent}"
    textColor: "{colors.on-accent}"
    rounded: "{rounded.md}"
    padding: "14px 28px"
    height: "48px"
  button-primary-hover:
    backgroundColor: "{colors.accent-hover}"
    textColor: "{colors.on-accent}"
    rounded: "{rounded.md}"
    padding: "14px 28px"
    height: "48px"
  button-secondary:
    backgroundColor: "transparent"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: "14px 28px"
    height: "48px"
  badge:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink-muted}"
    rounded: "{rounded.sm}"
    padding: "4px 12px"
---

# Design System: Spa Cielo

## 1. Overview

**Creative North Star: "La vitrina editorial sobria"**

Sistema de marca y atención del spa: reserva de citas en línea, check-in presencial y
panel interno para secretaría y superusuario (RF-004, RF-006, RF-017). Se comunica con la
fotografía de los espacios y servicios, no con el ruido. Blanco cálido-neutro (`#f9fafb`),
tipografía Outfit con peso 800 en los títulos, un único acento verde (`#047857`) y la
imagen real del spa/servicio como protagonista de cada sección.
El layout es asimétrico por decisión: hero 55/45, categorías de servicio en zig-zag de 2
columnas, grid fraccional de servicios; nunca tres tarjetas idénticas ni héroe centrado.

Rechaza explícitamente el slop de landing AI (gradiente morado/azul, Inter, emojis,
eyebrow en mayúsculas repetido), el look de e-commerce de productos físicos (aquí se
reservan servicios y se paga por QR, no se compra un carrito) y el look de plantilla
dashboard genérica. Confianza sin gritar: jerarquía por peso y espacio, no por color extra.

**Key Characteristics:**
- Un acento verde; saturación < 80%; más de un acento cromático está prohibido.
- Outfit única (400/600/800); sin serif en UI; Inter vetado.
- Layout asimétrico con fallback estricto a 1 columna en `< 768px`.
- Superficies planas por defecto; sombra tintada difusa solo en elevación real.
- Movimiento CSS nativo (`transform`/`opacity`), `prefers-reduced-motion` obligatorio.
- Voz concreta en español de Bolivia; precios en `Bs`; datos marcados como demostrativos.
- El panel interno (secretaría/superusuario) reutiliza los mismos tokens, con densidad mayor.

## 2. Colors

Paleta de un solo acento sobre neutros tibios: el verde solo aparece donde hay acción o énfasis.

### Primary
- **Verde bosque de confianza** (`#047857`): CTA primario, links, `:focus-visible`, precio destacado. Es el 100% del acento cromático del sistema.
- **Verde profundo hover** (`#065f46`): hover/active del CTA primario y del bloque CTA final.

### Neutral
- **Blanco niebla** (`#f9fafb`): fondo de página y de secciones alternas (`.servicios`, `.prueba-social`).
- **Blanco puro** (`#ffffff`): superficie de tarjetas, header, secciones elevadas (`.categorias`, `.beneficios`).
- **Tinta off-black** (`#18181b`): texto principal y CTA secundario (borde/fondo en hover). `#000000` prohibido.
- **Gris acero** (`#52525b`): cuerpo secundario, metadata, captions (contraste AA ≥ 4.5:1 sobre `#f9fafb`).
- **Línea ceniza** (`#e4e4e7`): bordes de 1px, divisores, chips.

### Named Rules
**La Regla del Acento Único.** Verde `#047857` es el único acento cromático permitido.
Prohibidos: gradiente morado/azul «AI», neón, glow exterior, degradados de texto, puro negro.
**La Regla de la Promoción.** `#b45309` (ámbar) existe exclusivamente para el
`precio_anterior` tachado de una promoción (RF-009) y para avisos de estado; nunca se usa
como acento de marca.

## 3. Typography

**Display Font:** Outfit (fallback `system-ui, -apple-system, "Segoe UI", sans-serif`)
**Body Font:** Outfit (misma familia)

**Character:** Una sola familia geométrica cálida; la personalidad viene del contraste de
peso (400 vs 800) y del tracking negativo en títulos, no de una segunda tipografía.

### Hierarchy
- **Display/H1** (800, `clamp(2rem, 4.5vw, 3.25rem)`, 1.05, `-0.02em`): título del hero; no grita por tamaño sino por peso.
- **Headline/H2** (800, `clamp(1.5rem, 3vw, 2.25rem)`, 1.15, `-0.015em`): títulos de sección.
- **Title/H3** (600, `1.25rem`, normal): títulos de tarjeta y sub-secciones.
- **Body** (400, `1rem`, 1.65, `max-width: 65ch`): párrafos.
- **Label** (400, `0.875rem`, muted): metadata, captions, chips, `demo-notice`.
- **Price** (800, `1.125rem`, `tabular-nums`): precio actual; `precio_anterior` tachado en muted.

### Named Rules
**La Regla del Peso sobre el Tamaño.** La jerarquía se construye con peso (400/600/800) y
color (ink vs muted); ningún título necesita superar `3.25rem`.

## 4. Elevation

Sistema plano por defecto con sombra tintada difusa como única herramienta de profundidad.
No hay capas de tono intermedias ni sombras duras: donde no hay elevación real, se usa
borde de 1px `--color-line`.

### Shadow Vocabulary
- **Elevación difusa** (`box-shadow: 0 20px 40px -15px rgba(24,24,27,.08)`): header al hacer scroll (`.is-scrolled`) y estados elevados de tarjetas en hover.

### Named Rules
**La Regla del Plano por Defecto.** Las superficies están planas en reposo. La sombra
aparece solo como respuesta a estado (scroll, hover, focus). Si la sombra es tan oscura
que se ve como 2014, está mal.

## 5. Components

### Buttons
- **Shape:** radio `10px` (`--radius-md`), `min-height: 48px` (target táctil ≥ 44px).
- **Primary:** fondo `#047857`, texto `#ffffff`, `padding: 14px 28px`, peso 600. Hover → `#065f46`. `:active { transform: scale(.98) }`.
- **Secondary:** transparente, texto y borde 1px `#18181b`. Hover → fondo `#18181b`, texto blanco.
- **On-accent** (bloque CTA final): texto/borde `#ffffff` sobre fondo verde; hover invierte a blanco sólido con texto verde.
- **Focus:** `2px solid #047857` con `outline-offset: 2px` (`:focus-visible` global).

### Chips / Badges
- **Style:** fondo `#ffffff`, borde 1px `#e4e4e7`, radio `6px`, texto `#52525b`, `font-size: 0.875rem`, `padding: 4px 12px`.
- **State:** estáticos para etiquetas de servicio/categoría; sin variante seleccionada.
- **Estados operativos** (RF-005): `libre` (verde tenue), `ocupada` (tinta `#18181b`) y
  `no_disponible`/pendiente (ámbar `#b45309`). El estado se comunica también con texto e
  ícono, nunca solo con color.

### Cards / Containers
- **Corner Style:** radio `16px` (`--radius-lg`) en tarjetas de servicio y de recurso.
- **Background:** `#ffffff` sobre fondo `#f9fafb` (y viceversa entre secciones).
- **Shadow Strategy:** borde 1px `#e4e4e7` en reposo; elevación difusa solo en hover (media query `hover: hover`).
- **Internal Padding:** escala base 4px (`16px`–`24px`).
- **Media:** imagen `aspect-ratio: 4/3; object-fit: cover` (reserva = sin CLS), `loading="lazy"` fuera del hero.

### Navigation (`.site-header`)
- **Style:** superficie blanca, sticky; adquiere elevación difusa con `.is-scrolled`.
- **Typography:** brand en 800; enlaces `1rem` muted → hover `#18181b` sin subrayado.
- **Mobile:** CTA del header oculto (`display: none` hasta `768px`); menú compacto.

### Signature Components
- **Hero de reserva (55/45):** texto a la izquierda alineado a la izquierda; figura del
  servicio/espacio a la derecha con fundido sutil hacia el fondo (`hero__img`); CTA «Reservar cita».
- **Tarjeta de servicio (zig-zag):** alterna imagen/texto en 2 columnas `2fr 1fr`; nunca 3 tarjetas iguales.
- **Agenda del panel (RF-004/013):** grid de camas (1–6) y franjas horarias con trabajadoras
  (1–10); celdas con badge de estado y bloqueo visual de solapes; reutiliza tokens con densidad mayor.
- **Bloque `.cta-final`:** banda verde `#047857` a ancho completo con título blanco y dos CTAs.

## 6. Do's and Don'ts

### Do:
- **Do** usar el verde `#047857` como único acento: CTA, links, focus, énfasis.
- **Do** comprobar contraste AA: `#52525b` sobre `#f9fafb` ≥ 4.5:1; targets ≥ 44px.
- **Do** jerarquizar con peso (400/600/800) y espacio; grid CSS siempre (nunca `calc(33% - 1rem)` en flex).
- **Do** animar solo `transform`/`opacity` con `cubic-bezier(.16,1,.3,1)` y desactivar todo con `prefers-reduced-motion`.
- **Do** mantener alt descriptivo por servicio/espacio, skip-link y `:focus-visible` visible.
- **Do** marcar datos/testimonios como demostrativos; precios en `Bs` con números orgánicos.
- **Do** mostrar la disponibilidad (camas/trabajadoras) y el estado del pago QR (pendiente/verificado)
  de forma explícita, con texto además de color.

### Don't:
- **Don't** usar gradiente morado/azul «AI», hero centrado sobre imagen oscura, 3 tarjetas idénticas, emojis en markup/alt, Inter, eyebrow en mayúsculas repetido por sección, hero-metric (anti-referencias de `spa/00_contexto/descripcion.md`: «slop de landing AI»).
- **Don't** simular un checkout/carrito de e-commerce de productos físicos: aquí se reservan servicios y el pago es por QR con validación de secretaría (RF-007/RF-012); nada de un flujo de «agregar al carrito».
- **Don't** usar el look de plantilla dashboard/admin genérica en el sitio de reserva; el panel interno (secretaría/superusuario) sí reutiliza los tokens del sistema.
- **Don't** caer en el lane editorial-typographic (serif display + mono labels): fuera de registro.
- **Don't** usar `#000000`, más de un acento cromático, neón, glow exterior, degradados de texto.
- **Don't** meter serif en UI, GSAP/Framer/parallax, React/Vue/Angular ni Node/Vite: stack PHP 8.3 + Laravel 11 (D-09) con CSS/JS puro.

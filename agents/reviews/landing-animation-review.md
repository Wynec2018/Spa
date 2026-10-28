# Auditoría motion - Landing Spa Cielo

- Checkpoint: `CP-LAND-13`
- Fecha: 2026-10-25
- Skills: `review-animations` + `improve-animations` (read-only)
- Alcance: motion de la landing; no se modificó código
- Estado: `HUMAN_STATUS: PENDING`

## Contexto

- Stack: CSS puro + `IntersectionObserver`; sin librerías de animación.
- Superficie: entrada del hero, scroll reveal, stagger, hover/press y reduced motion.
- Personalidad: editorial wellness cálido, primer impacto de una landing de anuncios.
- Frecuencia: hero y reveal se ven una vez por visita; hover/press es ocasional; no hay atajos de teclado ni acciones de 100+/día.

## Evidencia

- Chrome `155.0.8059.85`, viewports `1440x900`, `1024x768`, `768x1024`, `390x844`, `320x568`.
- Hero: `700ms` contenido, `900ms` media + `120ms` delay, easing `cubic-bezier(0.16, 1, 0.3, 1)`.
- Reveal: `600ms`, `opacity` + `translateY`, stagger efectivo `0/60/120ms` en servicios y beneficios, `0/80ms` en testimonios.
- Reduced motion: `has-motion=false`, `15/15` elementos visibles, `opacity:1`, `transform:none`, hero `animation:none`, `scroll-behavior:auto`, `0` animaciones.
- Cambio dinámico a reduced motion: `15/15` visibles; al volver a no-preference, el hero recupera `hero-enter` pero el contenido sigue visible.
- Performance: `CLS 0`, `0 long tasks`; las animaciones solo usan `transform` y `opacity`.
- LCP: `700ms` con motion vs `672ms` con reduced motion; sin regresión material.
- Barrido de fuente: sin `transition: all`, `ease-in`, `scale(0)`, animaciones infinitas ni librerías.
- Hover: regla dentro de `@media (hover: hover) and (pointer: fine)`; el headless no emula `pointer: coarse`, la verificación de esa parte es por código.
- Evidencia consolidada: `Spa/07_landing/tests/results/CP-LAND-13.txt`.

## Hallazgos

| # | Severidad | Categoría | Ubicación | Antes | Después | Por qué |
|---|---|---|---|---|---|---|
| 1 | MEDIUM | Cohesión / escalabilidad | `motion.css:36-48` | Delays solo para `nth-child(2/3)` en servicios y beneficios, y `nth-child(2)` en testimonios | Añadir delays para `nth-child(4..8)` o calcular el delay por índice | Con 4+ servicios, el cuarto entra junto al primero y rompe el ritmo; hoy hay 3/3/2, así que no bloquea |
| 2 | LOW | Cohesión / tokens | `motion.css:15,19,27-28,38,43,47` | Duraciones literales `700/900/600/60/80/120ms` | Usar `--duration-*` o tokens nuevos para hero/reveal/stagger | Facilita ajustar el motion sin valores dispersos; no afecta el resultado actual |
| 3 | LOW | Accesibilidad (handoff) | `landing.js:30-35`, `motion.css:23-29` | Los elementos reveal con `opacity:0` siguen siendo focusables | Resolver en CP-LAND-14 con `visibility`/`inert` o manejo de foco | Un enlace del CTA final puede recibir foco mientras aún es invisible; el observer lo revela al hacer scroll, pero conviene cubrirlo en accesibilidad |
| 4 | LOW | Performance / LCP (nota) | `motion.css:13-20` | Hero con `opacity:0`; media termina a ~`1.02s` | Si se aprieta LCP, bajar media a `700ms` o usar solo transform | LCP medido `700ms` normal vs `672ms` reduced; no es regresión actual, pero es el único costo perceptible |

## Oportunidades no implementadas

Ninguna. La landing ya cubre entrada, reveal, stagger, hover/press y reduced motion. Agregar más movimiento sería ruido para una página de conversión.

## Veredicto

### 1. Feel-breaking regressions

Ninguna. No hay `ease-in`, `scale(0)`, `transition: all`, animaciones infinitas ni movimiento en acciones de teclado o alta frecuencia.

### 2. Missed simplifications

Ninguna. El motion existente tiene propósito: entrada de primer impacto, continuidad espacial en scroll y feedback de press/hover. No hay animación obvia que deba eliminarse.

### 3. Performance

Sin hallazgos BLOCKER/HIGH. Solo se animan `transform` y `opacity`; `CLS 0` y sin long tasks. La entrada del hero con opacidad es el único costo perceptible y queda dentro del presupuesto de marketing.

### 4. Interruptibility & timing

El reveal usa transiciones CSS, por lo que es interrumpible y retargeteable. El hero usa keyframes, pero es una entrada única, no una interacción rápida. El stagger `0/60/120ms` y `0/80ms` se ejecuta correctamente. Queda el hallazgo MEDIUM de escalabilidad si el catálogo supera tres servicios.

### 5. Origin, physicality & cohesion

No hay popovers ni `transform-origin` que corregir. El hero entra con `translateY` y opacidad, sin `scale(0)`. La duración de `900ms` de la imagen es deliberada para el primer impacto y encaja con la personalidad editorial. Los tokens de duración son una mejora LOW, no un bloqueo.

### 6. Accessibility

Reduced motion se respeta: sin hero/reveal, contenido visible, scroll instantáneo y hover sin desplazamiento. El hover está gateado por `hover: hover` y `pointer: fine`. El cambio dinámico de preferencia también funciona. El foco sobre reveal aún invisible se deriva a CP-LAND-14.

## Decisión

**Approve.** No hay BLOCKER/HIGH; no se requiere modificar código en CP-LAND-13.

`HUMAN_STATUS: PENDING`

No se avanza a `CP-LAND-14` hasta aprobación humana.

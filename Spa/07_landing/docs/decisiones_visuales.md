# Decisiones visuales - Landing Spa Cielo

Fecha: 2026-10-25
Checkpoint: CP-LAND-11
Estado: pendiente de aprobacion humana

## 1. Contexto

La landing es un canal publico de conversion para trafico de anuncios. Debe
transmitir calma, cuidado y confianza sin parecer una plantilla generica ni un
catalogo de tienda.

Restricciones vigentes:

- CSS puro, HTML5 y JavaScript ES6+; sin React, Tailwind, Bootstrap ni jQuery.
- Sin fuentes externas ni dependencias de red para la tipografia.
- Sin login, dashboard, carrito, checkout ni pagos.
- Sin claims falsos, datos personales ni disponibilidad en tiempo real.
- Imagenes verificadas en `docs/fuentes_imagenes.md`.

Baseline del skill `design-taste-frontend`, adaptado a CSS puro:

- `DESIGN_VARIANCE: 8`
- `MOTION_INTENSITY: 6`
- `VISUAL_DENSITY: 4`

## 2. Direccion visual

**Editorial wellness calido y asimetrico.**

La landing se siente como una revista de bienestar: mucho aire, fotografia
amplia, tipografia con caracter y una paleta calida de baja saturacion. La
jerarquia se construye con escala, peso y contraste, no con efectos.

Decisiones centrales:

- Fondo marfil calido y tinta carbon; verde salvia como unico acento.
- Display serif editorial para titulares; sans humanista para interfaz y texto.
- Hero asimetrico, nunca centrado: contenido a un lado, imagen al otro.
- Secciones con ritmo amplio y cambios de superficie suaves.
- Una sola accion principal por bloque; CTA visible sin competir con el contenido.
- Sin morado/azul de IA, sin glows, sin negro puro, sin emojis, sin `Inter`.
- Sin tres tarjetas iguales en fila; usar zig-zag de dos columnas, listas
  editoriales o grids asimetricos.

## 3. Principios

1. **Claridad primero:** el visitante entiende que es Spa Cielo, que ofrece y
   como dar el siguiente paso en menos de cinco segundos.
2. **Premium sin ruido:** el lujo se percibe por espacio, fotografia y detalle,
   no por sombras excesivas ni decoracion.
3. **Asimetria controlada:** la variacion visual se concentra en desktop; en
   movil todo colapsa a una columna limpia.
4. **Una sola accent:** el verde salvia guia CTA, enlaces y detalles; el resto
   es neutral calido.
5. **Accesibilidad integrada:** contraste, foco visible, tamano tactil y
   `prefers-reduced-motion` se definen desde el sistema, no al final.
6. **Contenido real:** no se inventan precios, telefonos, testimonios ni
   disponibilidad.

## 4. Tokens

Fuente de verdad: `public/assets/css/variables.css`.

### Color

| Token | Valor | Uso |
|---|---|---|
| `--color-bg` | `#f7f2ea` | Fondo principal marfil |
| `--color-surface` | `#fcf9f4` | Superficie elevada suave |
| `--color-surface-muted` | `#efe7db` | Bloques alternos y estados vacios |
| `--color-ink` | `#23251f` | Texto principal, carbon calido |
| `--color-ink-soft` | `#3a3d35` | Texto secundario fuerte |
| `--color-ink-muted` | `#5e6158` | Parrafos y metadatos |
| `--color-line` | `#d9d0c3` | Bordes hairline |
| `--color-accent` | `#6f7f63` | Acento salvia decorativo |
| `--color-accent-strong` | `#4f5d45` | CTA, enlaces y foco |
| `--color-accent-soft` | `#e4e8df` | Seleccion y fondos de apoyo |
| `--color-on-accent` | `#fffdf8` | Texto sobre accent |
| `--color-overlay` | `rgba(35, 37, 31, 0.55)` | Velos sobre imagen |

Regla: una sola accent. No se agregan morados, azules neon, degradados de texto
ni glows.

### Tipografia

| Token | Valor | Uso |
|---|---|---|
| `--font-display` | `"Iowan Old Style", "Palatino Linotype", "Book Antiqua", Palatino, Georgia, serif` | Titulares editoriales |
| `--font-body` | `"Avenir Next", Avenir, "Segoe UI", "Helvetica Neue", Arial, sans-serif` | Texto e interfaz |
| `--font-mono` | `"Cascadia Mono", "SFMono-Regular", Consolas, "Liberation Mono", monospace` | Precios y conteos |
| `--text-display` | `clamp(3rem, 1.8rem + 5vw, 5.25rem)` | Solo piezas editoriales excepcionales |
| `--text-h1` | `clamp(2.5rem, 1.8rem + 3.2vw, 4.25rem)` | Titular principal |
| `--text-h2` | `clamp(1.85rem, 1.45rem + 1.8vw, 2.85rem)` | Titulos de seccion |
| `--text-h3` | `clamp(1.15rem, 1.08rem + 0.35vw, 1.375rem)` | Subtitulos y tarjetas |
| `--text-lg` | `clamp(1.125rem, 1.08rem + 0.22vw, 1.25rem)` | Lede |
| `--text-body` | `clamp(1rem, 0.97rem + 0.15vw, 1.0625rem)` | Texto base |
| `--text-sm` | `0.875rem` | Metadatos y botones |
| `--text-xs` | `0.75rem` | Eyebrow y etiquetas |

Reglas: H1 maximo 4.25rem; no se usa un H1 gigante para gritar. El display serif
se reserva para titulares; el cuerpo y la interfaz usan sans. No se usa `Inter`.

### Espaciado

| Token | Valor | Uso |
|---|---|---|
| `--space-1` a `--space-32` | `0.25rem` a `8rem` | Escala base de 4px |
| `--space-4` | `1rem` | Unidad base de separacion |
| `--section-space` | `clamp(4.5rem, 9vw, 8rem)` | Ritmo vertical principal |
| `--section-space-sm` | `clamp(3rem, 6vw, 5rem)` | Secciones compactas |
| `--gutter` | `clamp(1.25rem, 4vw, 2.5rem)` | Margen lateral |
| `--grid-gap` | `clamp(1.5rem, 4vw, 3rem)` | Separacion de grids |
| `--container` | `1180px`, `1240px` desde 1024px | Ancho maximo |
| `--container-narrow` | `760px` | Bloques de lectura |
| `--measure` | `62ch` | Longitud de parrafo |

Regla: usar CSS Grid para estructuras; no usar calculos de porcentaje con flex.

### Radios, bordes y sombras

| Token | Valor | Uso |
|---|---|---|
| `--radius-xs` | `0.25rem` | Detalles |
| `--radius-sm` | `0.5rem` | Controles pequenos |
| `--radius-md` | `0.875rem` | Imagenes y contenedores |
| `--radius-lg` | `1.5rem` | Bloques destacados |
| `--radius-pill` | `999px` | Botones |
| `--border-hairline` | `1px solid var(--color-line)` | Divisiones |
| `--shadow-soft` | `0 24px 70px -40px rgba(35, 37, 31, 0.35)` | Elevacion amplia |
| `--shadow-card` | `0 14px 40px -30px rgba(35, 37, 31, 0.32)` | Tarjetas excepcionales |
| `--shadow-inset` | `inset 0 1px 0 rgba(255, 253, 248, 0.65)` | Borde de refraccion |

Regla: sombras tintadas al fondo, sin glow. Las tarjetas se usan solo cuando la
elevacion comunica jerarquia.

### Movimiento

| Token | Valor | Uso |
|---|---|---|
| `--ease-out` | `cubic-bezier(0.16, 1, 0.3, 1)` | Entradas y hover |
| `--ease-in-out` | `cubic-bezier(0.65, 0, 0.35, 1)` | Cambios de estado |
| `--duration-fast` | `140ms` | Feedback tactil |
| `--duration-base` | `260ms` | Hover y color |
| `--duration-slow` | `520ms` | Revelados |

Regla: animar solo `transform` y `opacity`; `prefers-reduced-motion` se
implementa en CP-LAND-11.

### Layout e imagenes

| Token | Valor | Uso |
|---|---|---|
| `--header-height` | `4.5rem`, `5rem` desde 768px | Header sticky |
| `--image-ratio-wide` | `16 / 10` | Hero e instalaciones |
| `--image-ratio-portrait` | `3 / 4` | Retratos editoriales |
| `--image-ratio-square` | `1 / 1` | Detalles |
| `--z-header` | `30` | Header |
| `--z-overlay` | `60` | Overlays |

## 5. Jerarquia tipografica

| Nivel | Estilo | Uso |
|---|---|---|
| Display | Serif, `--text-display`, tracking `-0.035em`, line-height `0.98` | Solo una pieza por pagina si aporta |
| H1 | Serif, `--text-h1`, tracking `-0.035em`, line-height `0.98` | Titular del hero |
| H2 | Serif, `--text-h2`, tracking `-0.02em`, line-height `1.08` | Titulo de seccion |
| H3 | Sans 600, `--text-h3`, line-height `1.3` | Nombre de servicio o bloque |
| Lede | Sans, `--text-lg`, color `--color-ink-soft`, max `46rem` | Introduccion |
| Body | Sans, `--text-body`, line-height `1.7`, max `62ch` | Parrafos |
| Eyebrow | Sans 600, `--text-xs`, uppercase, tracking `0.14em` | Etiqueta de seccion |
| Caption | Sans, `--text-sm`, color `--color-ink-muted` | Notas y metadatos |

El H1 no se centra. La jerarquia se refuerza con color y peso, no solo con
tamano. CP-LAND-12 retiro los kickers repetidos de la landing: el encabezado
carga la jerarquia y la clase `.eyebrow` queda disponible sin uso.

## 6. Espaciado y composicion

- Escala base de 4px; no se inventan valores fuera de la escala sin justificacion.
- Secciones principales con `--section-space`; bloques compactos con
  `--section-space-sm`.
- Contenedor maximo 1180px; 1240px en desktop amplio.
- Grid asimetrico permitido: `2fr 1fr`, `1.2fr 1fr`, `minmax(0, 1fr)`.
- En mobile, toda asimetria colapsa a una columna con `--gutter`.
- Evitar cards genericas; preferir separadores, listas y espacio negativo.
- No usar tres tarjetas iguales en fila.

## 7. Componentes base

- **Boton primario:** pill, fondo `--color-accent-strong`, texto
  `--color-on-accent`, hover a tinta, active con desplazamiento de 1px.
- **Boton ghost:** borde hairline, fondo transparente; hover a superficie.
- **Boton link:** sin caja, color accent, subrayado solo si el contexto lo pide.
- **Eyebrow:** etiqueta uppercase disponible, retirada de la landing en
  CP-LAND-12 para evitar kickers repetidos.
- **Lede:** texto introductorio maximo 46rem.
- **Image frame:** `aspect-ratio` reservado, `object-fit: cover`, radio medio.
- **Foco:** outline de 2px con offset de 4px; nunca se elimina.
- **Targets tactiles:** minimo 44px de alto en controles.

## 8. Responsive

Breakpoints de referencia:

- `480px`: ajustes de texto y apilado.
- `768px`: header mas alto, grids de dos columnas.
- `1024px`: contenedor amplio y asimetria completa.
- `1280px`: limite de lectura y aire adicional.

Mobile-first. Sin scroll horizontal. La asimetria de desktop no se traslada a
movil. Las imagenes mantienen su `aspect-ratio` para evitar saltos de layout.

## 9. Accesibilidad

- Contraste minimo AA para texto normal; los pares principales usan tinta
  carbon sobre marfil y texto marfil sobre verde profundo.
- Foco visible en todos los interactivos.
- No se transmite informacion solo por color.
- Imagenes con `alt` descriptivo; decorativas con `alt=""`.
- Movimiento reducido implementado en CP-LAND-11.
- Sin emojis en contenido, markup ni alt.

## 10. Movimiento

Gate: landing de marketing, visita ocasional. Proposito: explicacion y delight
en la primera visita; nunca en acciones repetidas ni en contenido funcional.

- Hero: entrada CSS de 700ms/900ms con `opacity` y `translateY`, curva
  `--ease-out`.
- Scroll reveal: `IntersectionObserver`, una sola vez por elemento, 600ms con
  `opacity` y `translateY`; stagger de 60ms a 80ms.
- Hover: solo con `hover: hover` y `pointer: fine`; press feedback de 1px.
- Reduced motion: sin animacion de hero ni reveal, `scroll-behavior: auto` y
  hover sin desplazamiento.
- Progressive enhancement: sin JavaScript o sin `IntersectionObserver`, el
  contenido permanece visible; `has-motion` solo se agrega cuando el reveal
  puede ejecutarse.
- Sin librerias de animacion; CSS y JavaScript nativo.
- Trazabilidad: CP-LAND-11, skill `animate`, `MOTION_INTENSITY: 6`.

## 11. Accesibilidad

- Skip link con destino `main` focusable (`tabindex="-1"`); el siguiente Tab
  entra al contenido y no vuelve al header.
- Reveal con `:focus-within`: el CTA final se hace visible al recibir foco,
  aunque el observer todavía no lo haya revelado.
- Foco del CTA oscuro con `outline-color: var(--color-bg)` para mantener
  contraste sobre el panel `--color-ink`.
- `scroll-margin-top` global para `main`, hero y secciones, de modo que el
  header sticky no cubra el destino de un ancla.
- `touch-action: manipulation` y tap highlight intencional en enlaces y botones.
- Target de marca de 44x44 px también en 320px, cuando se oculta el nombre.
- Trazabilidad: CP-LAND-14, skill `web-design-guidelines`.

## 12. Trazabilidad

| Decision | Fuente | Efecto |
|---|---|---|
| DV-01 Paleta calida con una accent salvia | CP-LAND-05, skill `design-taste-frontend` | Evita AI purple, neon y ruido visual |
| DV-02 Serif editorial + sans humanista | CP-LAND-05, D-LAND-08 | Identidad premium sin fuentes externas |
| DV-03 Hero y grids asimetricos | CP-LAND-05, DESIGN_VARIANCE 8 | Evita el hero centrado generico |
| DV-04 Escala de 4px y secciones amplias | CP-LAND-05, VISUAL_DENSITY 4 | Ritmo consistente y legible |
| DV-05 Botones pill y foco visible | CP-LAND-05, RNF de accesibilidad | Conversion clara y navegacion accesible |
| DV-06 Imagenes con ratio reservado | CP-LAND-04, CP-LAND-05 | Estabilidad de layout y performance |
| DV-07 Sin 3 tarjetas iguales | CP-LAND-05, skill `design-taste-frontend` | Evita el patron generico de features |
| DV-08 Movimiento solo con transform/opacity | CP-LAND-05, CP-LAND-11 | Performance y respeto a reduced-motion |
| DV-09 Sin kickers repetidos; encabezados autonomos | CP-LAND-12, auditoria `impeccable` | Menos ruido visual y jerarquia mas directa |
| DV-10 Skip link enfoca `main` | CP-LAND-14, `web-design-guidelines` | Navegacion por teclado confiable |
| DV-11 Reveal visible al foco | CP-LAND-14, CP-LAND-13 | Evita foco sobre contenido invisible |
| DV-12 Foco claro en CTA oscuro | CP-LAND-14, WCAG focus appearance | Indicador de foco con contraste suficiente |
| DV-13 Scroll margin global | CP-LAND-14, header sticky | Las anclas no quedan bajo el header |
| DV-14 Touch targets y tap highlight | CP-LAND-14, CP-LAND-10 | Targets de 44px y feedback tactil |

## 13. Estado

Decisiones propuestas para aprobacion humana de CP-LAND-14. La auditoria de
UI/UX/accesibilidad corrigio el skip link, el foco del CTA final, el reveal con
foco, los margenes de anclaje y los targets tactiles. Queda pendiente la
aprobacion humana.

**HUMAN_STATUS: PENDING**

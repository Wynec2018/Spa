# Auditoría UI/UX/accesibilidad - Landing Spa Cielo

- Checkpoint: `CP-LAND-14`
- Fecha: 2026-10-25
- Skill: `web-design-guidelines` (Vercel)
- Fuente: `https://raw.githubusercontent.com/vercel-labs/web-interface-guidelines/main/command.md`
- Alcance: landing completa, código y navegador; sin cambios en `Spa/06_codigo` ni en el esquema
- Estado: `HUMAN_STATUS: PENDING`

## Contexto

Landing pública de conversión, en español, con una sola página y sin formularios,
login, carrito ni pagos. La revisión cubre semántica, foco, teclado, contraste,
targets táctiles, imágenes, anclas y comportamiento responsive.

## Evidencia

- Chrome `155.0.8059.85`; viewports `1440x900`, `390x844` y `320x568`.
- Árbol de accesibilidad de Chrome: `1` banner, `1` main, `1` contentinfo,
  `6` regiones nombradas, `12` headings (`1` h1, `5` h2, `6` h3), `6` enlaces,
  `1` imagen, `3` listas y `2` figuras.
- Sin IDs duplicados; los `6` `aria-labelledby` apuntan a IDs existentes.
- Skip link: `Tab` enfoca «Saltar al contenido»; `Enter` mueve el foco a
  `main#contenido`; el siguiente `Tab` entra al CTA del hero.
- Orden de foco: skip link, marca, CTA del header, CTA del hero, CTA final,
  «Ver disponibilidad».
- CTA final: al recibir foco, el contenedor reveal queda `opacity: 1`,
  `transform: none`; el outline es `rgb(247, 242, 234)` sobre `#23251f`
  (`13.9:1`).
- Contraste de texto muestreado: mínimo `5.66:1` para texto normal; títulos
  `13.9:1`; botones `6.92:1`; foco del CTA final `13.9:1`. Sin fallos.
- Responsive: sin scroll horizontal en `390px` ni `320px`; todos los targets
  interactivos miden al menos `44x44px`, incluida la marca en `320px`.
- Anclas: `scroll-margin-top` de `96px` en escritorio y `88px` en móvil.
  `#servicios` queda a `96px` con el header en `81px`; no hay solapamiento.
- Reduced motion: `15/15` elementos visibles, sin animaciones, `scroll-behavior:
  auto`. Heredado de CP-LAND-13 y revalidado tras el cambio de foco.
- Revisión de código: sin `transition: all`, `ease-in`, `scale(0)`, animaciones
  infinitas, `outline: none` sin reemplazo, `user-scalable=no`, `onPaste`,
  `autoFocus`, `<div>`/`<span>` clicables ni botones solo-icono.
- No se usó un lector de pantalla real; la semántica se verificó con el árbol de
  accesibilidad de Chrome y con navegación por teclado.

## Hallazgos y correcciones

| # | Severidad | Ubicación | Antes | Corrección | Verificación |
|---|---|---|---|---|---|
| 1 | HIGH | `Spa/07_landing/app/Views/layouts/app.php:25-27` | El skip link hacía scroll pero el foco quedaba en `body`; el siguiente Tab volvía al header | `main#contenido` con `tabindex="-1"` y foco visible | `Enter` deja `activeElement` en `MAIN#contenido`; el siguiente Tab va al CTA del hero |
| 2 | HIGH | `Spa/07_landing/public/assets/css/motion.css:23-40` | El CTA final podía recibir foco con el contenedor reveal en `opacity: 0` | `:focus-within` revela el contenedor al instante | Foco en «Ver servicios»: padre `opacity: 1`, `transform: none`, visible |
| 3 | HIGH | `Spa/07_landing/public/assets/css/landing.css:492-494` | Outline `#4f5d45` sobre panel `#23251f`: `2.20:1` | `outline-color: var(--color-bg)` en el CTA oscuro | Outline `#f7f2ea` sobre `#23251f`: `13.9:1` |
| 4 | MEDIUM | `Spa/07_landing/public/assets/css/layout.css:15-19` | Solo `#servicios` y `#camas` tenían `scroll-margin-top`; `main`, `#inicio`, `#beneficios`, `#testimonios` y `#cierre` quedaban en `0` | Margen global para `main`, `.hero` y `.landing-section` | `96px` escritorio / `88px` móvil en todos los destinos |
| 5 | MEDIUM | `Spa/07_landing/public/assets/css/landing.css:19-23` | Marca de `32x44px` en `320px`, debajo del target de `44px` | `min-width: 2.75rem` en `.brand` | Marca `44x44px` en `320px`; sin overflow |
| 6 | LOW | `Spa/07_landing/public/assets/css/base.css:17,90-93` | `touch-action: auto` y tap highlight por defecto | `touch-action: manipulation` en enlaces/botones y tap highlight salvia intencional | Computado `manipulation` y `rgba(79, 93, 69, 0.18)` |
| 7 | LOW | `Spa/07_landing/app/Views/layouts/header.php:11`, `footer.php:9` | «Spa Cielo» podía traducirse con la página | `translate="no"` en la marca | Markup renderizado con `translate="no"` |
| 8 | LOW | `Spa/07_landing/app/Services/LandingService.php:173-187` | Formato de moneda hardcodeado con `number_format` | `NumberFormatter('es_BO')` con fallback a `number_format` | Salida conserva `Bs 230,00`, `Bs 250,00`, `Bs 100,00` |
| 9 | LOW | `Spa/07_landing/app/Views/sections/llamada_accion.php:16` | WhatsApp abría pestaña nueva sin aviso accesible | Texto oculto «(abre en una pestaña nueva)» | Markup verificado; no se renderiza sin `LANDING_WHATSAPP` |

## Checks que pasan

- `lang="es"`, viewport con zoom habilitado y `theme-color` igual al fondo.
- Jerarquía de headings sin saltos: h1 → h2 → h3.
- Landmarks `header`, `main`, `footer`; seis secciones nombradas con
  `aria-labelledby`.
- Imagen del hero con `alt`, `width="1400"`, `height="934"` y
  `fetchpriority="high"`; es la única imagen y está above-the-fold, por lo que
  no lleva `loading="lazy"`.
- Enlaces semánticos con foco visible; sin controles de formulario ni botones
  solo-icono.
- Contraste de texto y de foco conforme a WCAG AA en las muestras.
- Sin scroll horizontal; targets táctiles de `44px` o más.
- Anclas protegidas contra el header sticky.
- Reduced motion respetado; sin movimiento en hover/press.
- Sin patrones antiaccesibles de los guidelines.

## Veredicto

**Approve.** No quedan hallazgos BLOCKER/HIGH/MEDIUM; los LOW detectados se
corrigieron. La landing mantiene su alcance, no escribe en la base de datos y no
modifica el sistema administrativo.

`HUMAN_STATUS: PENDING`

No se avanza a `CP-LAND-15` hasta aprobación humana.

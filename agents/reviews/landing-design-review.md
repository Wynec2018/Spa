# Auditoría visual - Landing Spa Cielo

- Checkpoint: `CP-LAND-12`
- Fecha: 2026-10-25
- Skill: `impeccable`
- Alcance: auditoría acotada de `layout`, `typeset`, `clarify` y `adapt`
- Estado: `HUMAN_STATUS: PENDING`

## Contexto

Fuentes usadas:

- `Spa/07_landing/docs/brief_landing.md`
- `Spa/04_decisiones/decisiones_landing.md`
- `Spa/07_landing/docs/decisiones_visuales.md`

Nota de contexto: los archivos `PRODUCT.md` y `DESIGN.md` de la raíz describen
"Altiplano Spa" y no corresponden a esta landing. No se usaron como fuente ni se
modificaron. La landing evaluada es Spa Cielo, según las decisiones locales.

Evidencia:

- Chrome headless, viewport `1440x900`, `768x1024` y `390x844`.
- Detector `impeccable detect --json --scope layout,type --no-design-system`.
- Reflujo a `720px` como equivalente de zoom 200% sobre escritorio.
- Prueba de contenido extremo con nombre de servicio largo.
- Capturas full-page revisadas; no se versionan para no inflar el repositorio.
- Evidencia consolidada: `Spa/07_landing/tests/results/CP-LAND-12.txt`.

## Resultado por pasada

### Layout

Sin hallazgos estructurales pendientes.

- Jerarquía: hero, servicios, disponibilidad, beneficios, prueba social y CTA
  final se leen en ese orden.
- Agrupación: las filas de servicios y el panel de camas mantienen relaciones
  claras; no hay tarjetas genéricas en tres columnas.
- Ritmo: los espacios verticales amplios se mantienen como decisión editorial.
  No se detectó desbordamiento horizontal ni solapamiento.
- Extremos: `720px` reflow sin overflow; nombre de servicio largo sin overflow
  dentro de la fila.
- Sin cambios de layout en esta pasada.

### Typeset

Se corrigieron los hallazgos del detector y dos detalles de legibilidad.

- Eliminados los kickers/eyebrows repetidos: "Bienestar en Bolivia", "Servicios",
  "Disponibilidad referencial", "Lo que encuentras", "Prueba social" y
  "Tu siguiente paso". Los encabezados ahora sostienen la jerarquía sin etiquetas
  redundantes.
- Testimonio: interlineado de `1.15` a `1.3`.
- Nota de testimonios y nota final del CTA: de `--text-xs` a `--text-sm`.
- Título del hero sin margen superior residual.
- Acentos corregidos en textos visibles: `cálido`, `Atención`, `Duración`,
  `Información`, `más`, `línea`, `gustó`, `información`, `ayudó`, `sección`,
  `demostración`, `diseño`, `cálida`.

### Clarify

Sin cambios de alcance ni de promesas.

- La landing sigue siendo de conversión, con CTA a servicios y disponibilidad
  referencial.
- Los testimonios siguen marcados como demostrativos/ficticios.
- La disponibilidad de camas sigue siendo referencial, no una reserva en tiempo
  real.
- No se agregaron precios, teléfonos, correos ni afirmaciones no verificadas.
- El CTA conserva fallback a `#servicios` y `#camas` cuando no hay WhatsApp o
  correo configurado.

### Adapt

Sin hallazgos pendientes.

- `1440x900`, `768x1024` y `390x844`: sin overflow horizontal.
- `320px`: ya validado en CP-LAND-10; sin regresión visual detectada.
- `720px` (zoom 200% simulado): una columna, sin overflow.
- Nombre de servicio extremadamente largo: la fila conserva su ancho y el texto
  envuelve sin romper el layout.
- Movimiento reducido: la landing sigue visible; el reveal no oculta contenido.

## Detector

Antes de los cambios:

- `1` hallazgo `hero-eyebrow-chip`.
- `6` hallazgos `kicker-above-heading`.
- `2` hallazgos `tight-leading`.
- Total: `9` hallazgos.

Después de los cambios:

- `1440x900`: `[]`.
- `390x844`: `[]`.
- Total: `0` hallazgos.

## Cambios aplicados

- `Spa/07_landing/app/Views/sections/hero.php`: retirado kicker del hero.
- `Spa/07_landing/app/Views/sections/servicios.php`: retirado kicker.
- `Spa/07_landing/app/Views/sections/camas.php`: retirado kicker.
- `Spa/07_landing/app/Views/sections/beneficios.php`: retirado kicker.
- `Spa/07_landing/app/Views/sections/prueba_social.php`: retirado kicker.
- `Spa/07_landing/app/Views/sections/llamada_accion.php`: retirado kicker.
- `Spa/07_landing/app/Views/components/servicio_card.php`: `Duracion` a
  `Duración`.
- `Spa/07_landing/config/landing.php`: acentos en textos alternativos y metadatos.
- `Spa/07_landing/public/assets/css/landing.css`: interlineado del testimonio,
  tamaño de notas y margen residual del título.
- `Spa/07_landing/docs/decisiones_visuales.md`: kickers retirados, `DV-09`
  agregada, estado actualizado a `CP-LAND-12`.

## Hallazgos abiertos

1. `Servicio CP-BACK-05` sigue visible porque existe como servicio activo en la
   base de datos. Es dato de prueba/demo; debe renombrarse o archivarse fuera de
   la landing. No se alteró la base de datos.
2. El CTA usa fallback interno porque no hay WhatsApp ni correo configurados en
   el entorno de prueba. Para producción se deben definir
   `LANDING_WHATSAPP` y/o `LANDING_CONTACT_EMAIL`.
3. `PRODUCT.md` y `DESIGN.md` de la raíz no describen esta landing. Se ignoraron
   en esta auditoría; conviene corregirlos o moverlos a su propio contexto.

## Decisión

La landing queda visualmente consistente con la dirección "editorial wellness
cálido y asimétrico", sin hallazgos del detector en escritorio ni móvil.

`HUMAN_STATUS: PENDING`

No se avanza a `CP-LAND-13` hasta aprobación humana.

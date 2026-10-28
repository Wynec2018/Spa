# Workflow 07 — Landing comercial Spa Cielo

Landing pública de conversión, separada del sistema administrativo.
Regla: UN checkpoint por interacción. Siempre terminar con
`HUMAN_STATUS: PENDING`.

Alcance: `Spa/07_landing`. No modifica `Spa/06_codigo` ni el esquema.
Sin login, dashboard, CRUD, carrito, checkout ni pagos.
Runtime de solo lectura.

## CP-LAND-01 — Contrato y estructura

Validar guías, decisiones, carpetas y prohibiciones.

- Leer `Spa/04_decisiones/decisiones_landing.md`.
- Verificar que no exista login, dashboard, CRUD ni tablas `landing_*`.
- Confirmar stack: PHP 8.x puro + PostgreSQL 18 + PDO + HTML/CSS/JS.
- Confirmar que la landing solo leerá `catalog.service` y `catalog.resource`.
- Crear `agents/state/landing-workflow.json` con el checkpoint actual.

Prueba: `php Spa/07_landing/tools/validate_checkpoint.php CP-LAND-01`
STOP.

## CP-LAND-02 — Fuente de datos de solo lectura

Verificar:

- rol de base de datos con `SELECT` únicamente;
- acceso a `catalog.service`, `catalog.resource`, `core.branch` y
  `core.app_config`;
- `SET LOCAL app.branch_id` para RLS;
- ausencia de migraciones/tablas de landing;
- ausencia de `INSERT`, `UPDATE`, `DELETE`.

Prueba: `php Spa/07_landing/tools/validate_checkpoint.php CP-LAND-02`
STOP.

## CP-LAND-03 — Datos demo existentes

Verificar que haya servicios activos y camas registradas para la demo.

- No crear un catálogo 7×10.
- No sembrar desde la landing.
- No escribir en la base de datos.
- No exponer clientes, reservas ni usuarios.

Prueba: `php Spa/07_landing/tools/validate_checkpoint.php CP-LAND-03`
STOP.

## CP-LAND-04 — Imágenes

Registrar fuente, licencia y fecha de cada imagen usada.

- Sin IA.
- Sin Google Images/Pinterest.
- Fallback obligatorio.
- Servicios sin imagen verificada se muestran sin imagen.

Prueba: `php Spa/07_landing/tools/validate_checkpoint.php CP-LAND-04`
STOP.

## CP-LAND-05 — Sistema visual

Skill obligatorio: `design-taste-frontend`.
Definir tokens, jerarquía, spacing y dirección visual.
Guardar `docs/decisiones_visuales.md`.
STOP.

## CP-LAND-06 — Header + Hero + CTA

Skill: `design-taste-frontend`.
CTA visible above-the-fold. Cero login.
STOP.

## CP-LAND-07 — Servicios

Skill: `design-taste-frontend`.
Leer servicios activos desde `LandingRepository`/`LandingService`.
Mostrar nombre, duración y precio referencial en `Bs`.
No inventar descripciones ni precios.
STOP.

## CP-LAND-08 — Camas y disponibilidad

Skill: `design-taste-frontend`.
Mostrar cantidad de camas disponibles y no disponibles.
La disponibilidad es referencial; no prometer tiempo real.
No listar reservas, clientes ni trabajadoras.
STOP.

## CP-LAND-09 — Beneficios + prueba social + CTA final

Skill: `design-taste-frontend`.
No claims falsos.
Testimonios ficticios deben identificarse como demostrativos.
STOP.

## CP-LAND-10 — Responsive base

Móvil/tablet/desktop. Sin scroll horizontal. Targets táctiles.
STOP.

## CP-LAND-11 — Motion

Skill obligatorio: `animate` (Emil Kowalski).
Motion con propósito, preferir CSS, reduced-motion obligatorio.
No librerías sin aprobación.
STOP.

## CP-LAND-12 — Auditoría visual post-create

Skill obligatorio: `impeccable`.
Usar pasadas acotadas: layout/typeset/clarify/adapt.
Guardar `agents/reviews/landing-design-review.md`.
STOP.

## CP-LAND-13 — Auditoría motion

Skills: `review-animations` + `improve-animations` (read-only).
Guardar `agents/reviews/landing-animation-review.md`.
Resolver BLOCKER/HIGH.
STOP.

## CP-LAND-14 — UI/UX/accesibilidad

Skill: `web-design-guidelines`.
Guardar `agents/reviews/landing-accessibility-review.md`.
STOP.

## CP-LAND-15 — Performance + hardening

Skill: `impeccable` (optimize/harden).
Guardar `agents/reviews/landing-performance-review.md`.
STOP.

## CP-LAND-16 — Validación completa

Ejecutar:

- `php Spa/07_landing/tools/validate_structure.php`
- `php Spa/07_landing/tools/validate_checkpoint.php CP-LAND-16`

Cualquier error => `TECHNICAL_STATUS: FAILED`, `CAN_CONTINUE: false`.
STOP.

## CP-LAND-17 — Cierre

Completar `docs/validacion_final.md`.

```text
TECHNICAL_STATUS: PASSED
HUMAN_STATUS: PENDING
```

El humano decide la aprobación final.

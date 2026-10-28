# Workflow 05 — Frontend

Precondición: backend PASSED + APPROVED.
Alcance ratificado: `Spa/04_decisiones/decisiones_alcance_fase3.md` (2026-10-20).

Skills: `frontend-engineering`, `frontend-design`; al final
`responsive-design`, `accessibility-compliance`.

Stack: Laravel Blade + HTML + CSS + JavaScript puro. Sin React/Vue/Angular.

Pantallas permitidas:
- `/login`
- `/dashboard`
- `/servicios`
- `/reservas`

## CP-FRONT-01
Login (sucursal, contacto, contraseña) con estados de error y carga. STOP.

## CP-FRONT-02
Layout + dashboard con conteos básicos de servicios y reservas. STOP.

## CP-FRONT-03
Listado de servicios con filtros `estado`/`q` y paginación. STOP.

## CP-FRONT-04
Alta de servicios con validación accesible. STOP.

## CP-FRONT-05
Edición e inactivación de servicios (sin borrado físico). STOP.

## CP-FRONT-06
Listado de reservas por fecha y estado. STOP.

## CP-FRONT-07
Alta de reserva: cliente existente, servicio, cama, trabajadora e inicio;
mostrar el `fin` calculado. STOP.

## CP-FRONT-08
Cancelación de reserva con confirmación. Sin acciones de confirmar/atender. STOP.

## CP-FRONT-09
Responsive + accesibilidad (WCAG AA, teclado, foco, contraste, 360/768/1280).
`HUMAN_STATUS: PENDING`. No añadir pantallas.

## Checkpoint

Un CP por interacción. Al cerrar: probarlo, actualizar
`agents/state/frontend-workflow.json`, marcar `HUMAN_STATUS: PENDING` y detenerse.

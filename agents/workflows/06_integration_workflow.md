# Workflow 06 — Integración final

Alcance ratificado: `Spa/04_decisiones/decisiones_alcance_fase3.md` (2026-10-20).

Solo validar login + servicios + reservas. No agregar módulos.

## CP-INT-01
Login → dashboard → logout. Verificar sesión y contexto de sucursal. STOP.

## CP-INT-02
Servicios: listar → crear → editar → inactivar. Verificar 409 por nombre
duplicado y que no exista borrado físico. STOP.

## CP-INT-03
Reservas: listar → crear con cliente existente → conflicto 409 → cancelar.
Verificar liberación de la trabajadora. STOP.

## CP-INT-04
Seguridad básica: sesión, SQLi, XSS, CSRF, secretos, logs, RLS entre sucursales
y rechazo de `confirmada` sin pago (RN-002). STOP.

## CP-INT-05
Cierre y validación final. `HUMAN_STATUS: PENDING`.

## Checkpoint

Un CP por interacción. Al cerrar: probarlo, actualizar
`agents/state/integration-workflow.json`, marcar `HUMAN_STATUS: PENDING` y detenerse.

# Workflow 03 — API piloto: Servicios

Tabla fija: `catalog.service`.
Alcance ratificado: `Spa/04_decisiones/decisiones_alcance_fase3.md` (2026-10-20).

Skills: `php-development`, `api-design-principles`, `openapi-spec-generation`.

## Contexto obligatorio

- PHP 8.3 + Laravel 13 (D-09 actualizado 2026-10-20), PostgreSQL 18.3 (D-08), API REST JSON.
- Rol de aplicación: `spa_app`. RLS activo: fijar `app.branch_id` con `SET LOCAL`
  en cada transacción (T-01).
- `branch_id` proviene del contexto, nunca del cuerpo de la petición.
- Prohibido `DELETE FROM`: la baja es `estado='inactivo'` (T-07).
- Dinero con `numeric(12,2)`; moneda desde `core.app_config` (T-08).

## CP-API-01
Diseñar el contrato de servicios (campos, validaciones, códigos 400/404/409,
paginación y filtros `estado`/`q`). No programar. STOP.

## CP-API-02
Bootstrap Laravel 13 + `.env` (sin credenciales versionadas) + conexión
PostgreSQL + contexto RLS + `GET /api/v1/health`. STOP.

## CP-API-03
`GET /api/v1/servicios`. Probar colección con datos, vacía y error de BD. STOP.

## CP-API-04
`GET /api/v1/servicios/{id}`. Probar existente, inexistente y UUID inválido. STOP.

## CP-API-05
`POST /api/v1/servicios` + validaciones (nombre 1–120, duración > 0, precio ≥ 0,
unicidad por sucursal → 409) + fixtures válidos e inválidos. STOP.

## CP-API-06
`PUT /api/v1/servicios/{id}` + validaciones y 404. STOP.

## CP-API-07
`DELETE /api/v1/servicios/{id}` como inactivación lógica (`estado='inactivo'`).
Verificar que no se borra la fila. STOP.

## CP-API-08
OpenAPI + suite CRUD completa. Terminar en `HUMAN_STATUS: PENDING`.
No iniciar backend.

## Checkpoint

Al cerrar cada CP: probarlo, actualizar `agents/state/api-pilot-workflow.json`,
marcar `HUMAN_STATUS: PENDING` y detenerse.

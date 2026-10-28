# Decisiones de API

Sistema: **Spa Cielo**
Fecha: 2026-10-20
Estado: `HUMAN_STATUS: APPROVED` (CP-API-01 aprobado 2026-10-20)
Alcance: `Spa/04_decisiones/decisiones_alcance_fase3.md`

## Stack

- PHP 8.3 + Laravel 13 (D-09 actualizado 2026-10-20; ver `decisiones_stack_fase3.md`).
- PostgreSQL 16+ (desplegado 18.3, D-08).
- REST + JSON UTF-8.
- Base: `/api/v1`.
- Sin React/Vue/Angular.

## Convenciones

- IDs UUID; inválido → `400 INVALID_ID`.
- Éxito: `{"success":true,"data":...,"meta":...}`.
- Error: `{"success":false,"error":{"code","message","details"},"request_id":"..."}`.
- Dinero como string decimal (`"250.00"`), nunca float.
- Fechas ISO 8601 UTC.
- Paginación `page`/`per_page` (default 20, máximo 100) con `meta`.
- Filtros y orden validados contra una lista cerrada.
- Campos desconocidos en body o query → `400`.
- `estado` es de solo lectura en el CRUD de servicios; en reservas solo se permite `PATCH .../estado` a `cancelada`.
- La baja de servicios es `DELETE` lógico.
- `branch_id` no viaja en la petición: contexto de servidor + RLS.

## Recursos

| Recurso | Fase | Estado |
|---|---|---|
| `/api/v1/health` | CP-API-02 | Autorizado |
| `/api/v1/servicios` | CP-API-03..08 | Autorizado (piloto) |
| `/api/v1/auth/*` | CP-BACK-03..04 | Implementado |
| `/api/v1/reservas` | CP-BACK-05..09 | Lectura, alta, cancelación y guardas implementadas y verificadas (CP-BACK-09) |

`DELETE /api/v1/servicios/{id}` inactiva (`estado='inactivo'`); nunca elimina.

## Seguridad

- Statement preparation vía Eloquent/PDO.
- Validación del lado servidor.
- Sin secretos en Git; solo `.env` (RNF-034).
- Sin stack traces en respuestas.
- RLS obligatorio; `SET LOCAL app.branch_id` por transacción.
- El piloto de servicios no exige autenticación; auth y reservas usan sesión de
  servidor desde CP-BACK-03.
- CSRF/sesión se resuelven en CP-BACK y CP-INT.

## Entregables CP-API-08

- `Spa/06_codigo/openapi/spa-api-v1.json`: OpenAPI 3.1.0 de `health` y del CRUD de servicios; validado con Redocly (`recommended`) sin errores.
- `Spa/06_codigo/scripts/api_crud_suite.php`: suite negra de contrato contra un servidor en ejecución; 138 comprobaciones (OpenAPI, health, listado, filtros, alta, edición, baja lógica e idempotencia).
- Los errores HTTP de API no controlados (`404` de ruta y `405`) se renderizan con el envelope estándar y sin traza ni rutas internas.
- `GET /api/v1/health` responde `200` sin `meta`, alineado con el contrato aprobado.

## Validación humana

`continúa` aprueba únicamente CP-API-01 (contrato) y habilita CP-API-02.
No autoriza a saltar checkpoints ni a desplegar.

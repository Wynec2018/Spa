# Workflow 04 — Backend: autenticación y reservas

Precondición: API de servicios PASSED + APPROVED.
Alcance ratificado: `Spa/04_decisiones/decisiones_alcance_fase3.md` (2026-10-20).

Alcance:
1. autenticación mínima sobre `auth.app_user` (login/logout/me);
2. reservas sobre `booking.booking` (alta `pendiente`, listado, cancelación).

Skills: `php-development`, `error-handling-patterns`;
para login, `auth-implementation-patterns`.

## Contexto obligatorio

- Login con `contacto` + contraseña + **sucursal** (el contacto es único solo por
  sucursal: `uq_user_contacto`, T-03).
- Contraseña verificada contra `hash_password` (Argon2id).
- Sesión de servidor; fijar `app.branch_id`, `app.user_id` y
  `app.correlation_id` con `SET LOCAL` (T-01).
- Reservas: transacción única con `SELECT ... FOR UPDATE SKIP LOCKED`;
  SQLSTATE `23P01` → 409 (RNF-005/020).
- `fin` se calcula con `service.duracion_min`.
- Solo `pendiente → cancelada`. `confirmada`/`atendida` quedan fuera (RN-002, R-02).
- El trigger libera la trabajadora al cancelar; no se escribe `catalog.resource`
  desde la aplicación (T-04). La disponibilidad de camas se calcula por `EXCLUDE`,
  no por `resource.estado` (T-05).

## CP-BACK-01
Consolidar el patrón reutilizable de servicios (repositorio, validación,
respuestas y errores). STOP.

## CP-BACK-02
Crear `Spa/06_codigo/tools/seed_demo.php`: una `core.branch` y usuarios demo
(superusuario, secretaria, trabajadora) con hash Argon2id. Sin contraseñas en el
repositorio. STOP.

## CP-BACK-03
`POST /api/v1/auth/login` con sesión PHP y contexto RLS. Probar credenciales
válidas, inválidas, usuario inactivo y sucursal incorrecta. STOP.

## CP-BACK-04
`POST /api/v1/auth/logout` + `GET /api/v1/auth/me`. STOP.

## CP-BACK-05
`GET /api/v1/reservas` (rango de fechas, estado, paginación) y
`GET /api/v1/reservas/{id}`. Verificar aislamiento por sucursal. STOP.

## CP-BACK-06
`POST /api/v1/reservas`: validar cliente, servicio activo, cama y trabajadora;
calcular `fin`; alta `pendiente`. Probar conflicto de franja → 409 y ausencia de
filas parciales. STOP.

## CP-BACK-07
`PATCH /api/v1/reservas/{id}/estado` solo a `cancelada`. Verificar liberación de
la trabajadora. STOP.

## CP-BACK-08
Guards: rechazar `confirmada` sin pago verificado (RN-002) y verificar que RLS
devuelve 0 filas con otra sucursal. STOP.

## CP-BACK-09
Suite backend. Terminar en `HUMAN_STATUS: PENDING`. No iniciar frontend.

## Checkpoint

Un CP por interacción. Al cerrar: probarlo, actualizar
`agents/state/backend-workflow.json`, marcar `HUMAN_STATUS: PENDING` y detenerse.

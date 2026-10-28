# Ratificación de alcance — Fase 3 (API, backend y frontend)

Sistema: **Spa Cielo**
Fecha: 2026-10-20
Estado: `HUMAN_STATUS: APPROVED` (ratificado 2026-10-20)
Fuentes: `Spa/00_contexto/`, `Spa/01_requisitos/`, `Spa/04_decisiones/decisiones_spa.md`,
`Spa/05_base_datos/15_reportes/validacion_final.md`, esquema desplegado (`V0001`–`V0011`).

> Este documento **no inicia** los workflows 03–06. Solo fija el alcance que debe
> aprobarse antes de ejecutarlos. Hasta la ratificación, la fase 3 permanece detenida.

---

## 1. Regla de alcance

Se implementará únicamente lo que use estas tres tablas:

| Tabla real | Módulo | Operaciones |
|---|---|---|
| `auth.app_user` | Autenticación | `SELECT` (login/me) |
| `catalog.service` | Catálogo de servicios | `SELECT`, `INSERT`, `UPDATE` (inactivación lógica) |
| `booking.booking` | Reservas | `SELECT`, `INSERT`, `UPDATE` (estado) |

El resto del esquema ya desplegado **no se modifica**. Las escrituras indirectas
provocadas por triggers existentes se aceptan y se documentan (§5).

---

## 2. Dependencias de solo lectura

El alcance anterior no es ejecutable sin leer estas tablas. Se autoriza **solo lectura**:

| Tabla | Uso | Motivo |
|---|---|---|
| `core.branch` | Contexto de sucursal y RLS | RNF-010, D-05 |
| `catalog.client` | Seleccionar cliente existente al reservar | FK `booking.client_id` |
| `catalog.resource` | Elegir cama y trabajadora; ver estado | FK `booking.cama_id`/`trabajadora_id`, RF-005 |
| `core.app_config` | Leer `ttl_reserva_min`, `moneda` | RF-016 |
| `payment.payment` | Lectura usada por trigger de confirmación | RN-002 (no se implementa pago) |

No se incluye alta ni edición de clientes, recursos, pagos ni configuración.

---

## 3. Módulos, orden y entregables

| Orden | Módulo | Tabla eje | Pantallas | RF/RNF |
|---|---|---|---|---|
| 1 | API piloto: servicios | `catalog.service` | — | RF-003, RNF-006 |
| 2 | Autenticación mínima | `auth.app_user` | `/login`, `/dashboard` | RF-001 (parcial), RNF-030/031/033, D-07 |
| 3 | Reservas | `booking.booking` | `/reservas` | RF-004/006/013 (parcial), RN-001/003/004, RNF-005/020/021 |
| 4 | Frontend | — | `/login`, `/dashboard`, `/servicios`, `/reservas` | RNF-006 |
| 5 | Integración | — | — | RNF-005/030/031 |

Orden propuesto: el piloto usa `catalog.service` (equivalente funcional a la
"tabla piloto" del framework, sin autenticación). La autenticación se implementa
antes de reservas porque estas requieren rol y contexto de sucursal.

---

## 4. Contratos funcionales (sin código)

### M1 — Servicios (`catalog.service`)

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/v1/servicios` | Lista con filtros `estado`, `q`, paginación |
| GET | `/api/v1/servicios/{id}` | Detalle |
| POST | `/api/v1/servicios` | Alta |
| PUT | `/api/v1/servicios/{id}` | Edición |
| DELETE | `/api/v1/servicios/{id}` | **Inactivación lógica** (`estado='inactivo'`) |

Campos: `nombre` (1–120), `duracion_min` (> 0), `precio` (numeric ≥ 0), `estado`
(`activo|inactivo`). `branch_id` no se recibe del cliente: proviene del contexto.
Conflicto de unicidad `(branch_id, nombre)` → HTTP 409. Nunca `DELETE FROM`.

### M2 — Autenticación (`auth.app_user`)

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/api/v1/auth/login` | `contacto` + contraseña + sucursal |
| POST | `/api/v1/auth/logout` | Cierra sesión |
| GET | `/api/v1/auth/me` | Usuario y rol de la sesión |

- Contraseña verificada contra `hash_password` (Argon2id, RNF-033).
- Sesión de servidor; sin JWT en esta fase (D-07 se mantiene para API pública futura).
- Roles utilizables: `superusuario`, `secretaria`, `trabajadora`.
- No se implementa alta/edición de usuarios (RF-001 completo queda fuera).

### M3 — Reservas (`booking.booking`)

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/v1/reservas` | Lista por rango de fechas y estado |
| GET | `/api/v1/reservas/{id}` | Detalle |
| POST | `/api/v1/reservas` | Alta en estado `pendiente` |
| PATCH | `/api/v1/reservas/{id}/estado` | Solo `cancelada` |

Alta: `client_id`, `service_id`, `cama_id`, `trabajadora_id`, `inicio`. `fin` se
calcula con `service.duracion_min`. Validaciones: servicio `activo`; cama de tipo
`cama`; trabajadora de tipo `trabajadora`; `fin > inicio`.

Concurrencia: transacción única con `SELECT ... FOR UPDATE SKIP LOCKED` sobre los
recursos y `app.branch_id` fijado con `SET LOCAL`. La violación de las
restricciones `ex_booking_cama`/`ex_booking_trab` (SQLSTATE `23P01`) se traduce a
HTTP 409, sin reintento ciego.

**Transiciones habilitadas:** `pendiente → cancelada`. Las transiciones
`pendiente → confirmada` y `→ atendida` quedan **fuera** porque el trigger
`trg_booking_confirm_requires_payment` exige `payment.estado='verificado'`
(RN-002) y el módulo de pagos no está en alcance.

---

## 5. Dependencias técnicas obligatorias

| # | Requisito | Origen |
|---|---|---|
| T-01 | Fijar `app.branch_id`, `app.user_id` y `app.correlation_id` por transacción (`SET LOCAL`) para RLS y auditoría | RNF-010/031, V0009/V0011 |
| T-02 | Sembrar una `core.branch` y usuarios demo con un comando/tool; nunca credenciales en el repositorio | RNF-034, D-07 |
| T-03 | Login debe resolver la sucursal: `contacto` es único solo por sucursal (`uq_user_contacto`) | esquema real |
| T-04 | Escrituras indirectas aceptadas: trigger pone trabajadora `ocupada` al crear reserva y la libera al cancelar/expirada | RN-003/005, V0007 |
| T-05 | `catalog.resource.estado` de la **cama** no lo sincroniza ningún trigger; la disponibilidad de camas se calcula por `EXCLUDE`, no por `estado` | hallazgo del esquema |
| T-06 | La expiración TTL requiere `booking.expirar_pendientes()` programada desde Laravel (`pg_cron` ausente) | P-03, D-09 |
| T-07 | Sin `DELETE` físico en ninguna tabla; inactivación lógica en servicios | RNF-022, diseño |
| T-08 | Dinero con `numeric(12,2)`, moneda desde `app_config` (`BOB`) | RNF-021 |
| T-09 | Stack: PHP 8.3 + Laravel 13, Blade, PostgreSQL 18.3, sin React/Vue/Angular | D-08, D-09, `decisiones_stack_fase3.md` |

---

## 6. Fuera de alcance (explícito)

| Requisito | Motivo |
|---|---|
| RF-002 registro de clientes (web/presencial) | Requiere `catalog.client` en escritura |
| RF-001 administración de usuarios/permisos | Solo autenticación en esta fase |
| RF-005 administración de camas/trabajadoras | Solo lectura para reservar |
| RF-007/RF-012 pagos QR y validación | Requiere `payment.*` |
| RF-008 idempotencia | Asociada a pagos |
| RF-009 promociones | Requiere `catalog.promotion` |
| RF-010 devoluciones | Requiere `payment.refund` |
| RF-011 notificaciones | Requiere `support.notification`/outbox |
| RF-014/RF-017 reportes y dashboard analítico | Vistas materializadas fuera de alcance; `/dashboard` solo muestra conteos básicos |
| Multi-sucursal en UI | El modelo lo soporta; la operación es una sede (D-05) |
| Landing pública | Workflow 07, independiente |

---

## 7. Trazabilidad

| Requisito | Cobertura en fase 3 | Cómo |
|---|---|---|
| RF-001 | Parcial | Login/logout/me por rol; sin CRUD de usuarios |
| RF-003 | Total | CRUD lógico de `catalog.service` |
| RF-004 | Parcial | Alta de reserva con cama y trabajadora; sin pago |
| RF-005 | Parcial | Lectura de disponibilidad; sin administración de recursos |
| RF-006 | Parcial | Recepción puede crear reserva para cliente existente |
| RF-013 | Parcial | EXCLUDE impide sobreasignación; sin reserva web pública |
| RF-016 | Parcial | Lectura de `app_config` (TTL, moneda) |
| RNF-005/020 | Total | Transacción + SKIP LOCKED + EXCLUDE + 409 |
| RNF-021 | Total | `numeric(12,2)` |
| RNF-030/031/033 | Total | Sesión, roles, Argon2id, RLS |
| RNF-006 | Total | MVC Laravel + servicios/repositorios |

---

## 8. Criterios de aceptación de la fase

- **CA-F3-01:** un servicio creado aparece en el listado y su nombre duplicado en la
  misma sucursal responde 409.
- **CA-F3-02:** "eliminar" un servicio cambia `estado` a `inactivo` y no borra la fila.
- **CA-F3-03:** login con credenciales válidas crea sesión; `me` devuelve el rol; logout la cierra.
- **CA-F3-04:** una reserva con cama o trabajadora ya ocupada en la franja responde 409,
  sin dejar filas parciales.
- **CA-F3-05:** cancelar una reserva la deja en `cancelada` y libera la trabajadora.
- **CA-F3-06:** un intento de `confirmada` sin pago verificado es rechazado (RN-002),
  incluso si se invoca la API directamente.
- **CA-F3-07:** con `app.branch_id` de otra sucursal, una consulta devuelve 0 filas (RLS).
- **CA-F3-08:** ninguna respuesta ni log expone `hash_password` ni credenciales.

---

## 9. Riesgos y decisiones abiertas

| ID | Riesgo | Propuesta |
|---|---|---|
| R-01 | `contacto` puede repetirse entre sucursales | Exigir selección de sucursal en login (T-03) |
| R-02 | Sin pagos, no se puede confirmar ni atender una reserva | Aceptado y declarado; se habilitará con el módulo de pagos |
| R-03 | `resource.estado` de camas no se sincroniza | Calcular disponibilidad por `EXCLUDE`; no confiar en `estado` de cama |
| R-04 | `spa_app` conserva `UPDATE` sobre `app_user` por el grant global | No exponer edición; revisar grants en DevSecOps |
| R-05 | Reserva `POST` no es idempotente en esta fase | Documentar; reintentos manuales pueden duplicar si cambia la franja |

---

## 10. Ratificación (2026-10-20)

Puntos ratificados:

- [x] Las tres tablas y sus operaciones (§1).
- [x] Las dependencias de solo lectura (§2).
- [x] El orden de módulos (§3).
- [x] Las rutas y pantallas (§4).
- [x] Las transiciones de reserva limitadas a `pendiente → cancelada` (§4, R-02).
- [x] Las exclusiones (§6).

Aplicado: `AGENTS.md` §"Alcance de desarrollo" actualizado y
`agents/workflows/03`–`06` adaptados a este alcance. El siguiente paso es CP-API-01.

---

## 11. Registro de ratificación

- **Fecha:** 2026-10-20.
- **Decisión:** aprobado el alcance tal como se propone en §1–§6, incluida la
  limitación de reservas a `pendiente → cancelada` (R-02).
- **Efecto:** habilita la adaptación de workflows y el inicio de CP-API-01.
  No autoriza despliegues ni cambios de esquema.

**HUMAN_STATUS: APPROVED**

# Paso 08 — Integridad

Sistema: **Spa Cielo**
Skills: `database-schema-designer` + `postgresql-table-design`
Motor: PostgreSQL 16+
Objetivo: mapear cada regla de negocio a **constraints de base de datos**.

---

## 1. Integridad declarativa por tabla

| Tabla | PK | NOT NULL | UNIQUE | CHECK | FK (ON DELETE) |
|---|---|---|---|---|---|
| branch | id | nombre, direccion, estado | nombre | estado | — |
| app_user | id | branch_id, rol, nombre, hash_password, estado | (branch_id, contacto) | rol | branch(RESTRICT) |
| client | id | branch_id, nombre, documento_cifrado, documento_hash | (branch_id, documento_hash) | — | branch(RESTRICT) |
| service | id | branch_id, nombre, duracion_min, precio, estado | (branch_id, nombre) | duracion_min>0, precio>=0, estado | branch(RESTRICT) |
| resource | id | branch_id, tipo, nombre, estado, version | (branch_id, tipo, nombre) | tipo, estado, tipo/user_id | branch(RESTRICT), app_user(SET NULL) |
| booking | id | branch_id, client_id, service_id, cama_id, trabajadora_id, inicio, fin, estado | — | fin>inicio, estado, EXCLUDE anti-solape | 4×RESTRICT + payment(SET NULL) |
| payment | id | booking_id, proveedor, monto, moneda, estado | booking_id | monto>=0, estado | booking(RESTRICT), app_user(SET NULL) |
| promotion | id | branch_id, nombre, reglas, activo | (branch_id, nombre) | valid_to>valid_from | branch(RESTRICT) |
| promotion_service | (promotion_id, service_id) | ambos | PK compuesta | — | CASCADE |
| refund | id | payment_id, monto, estado | — | monto>=0, estado | payment(RESTRICT), app_user(SET NULL) |
| notification | id | client_id, canal, plantilla, estado | — | canal, estado | client(RESTRICT) |
| idempotency_key | clave | request_hash, expires_at | clave | — | — |
| outbox | id | tipo_evento, payload, publicado, intentos | — | — | — |
| audit_log | id | accion, entidad, creado_en | — | — | app_user(SET NULL) |
| app_config | clave | valor | clave | — | app_user(SET NULL) |

DEFAULT: `estado` inicial por tabla, `created_at/updated_at now()`,
`moneda 'BOB'`, `publicado false`, `intentos 0`, `version 0`.

---

## 2. Reglas de integridad → mecanismo

| Regla | Mecanismo DB | Tipo |
|---|---|---|
| RI-01 no solape de cama activa | `EXCLUDE USING gist (cama_id WITH =, periodo WITH &&) WHERE estado activo` | Declarativo |
| RI-02 no solape de trabajadora activa | `EXCLUDE USING gist (trabajadora_id WITH =, periodo WITH &&) WHERE estado activo` | Declarativo |
| RI-03 confirmar exige pago verificado | Trigger `trg_booking_confirm_requires_payment` (BEFORE UPDATE) | Trigger |
| RI-04 trabajadora ocupada no recibe reservas | Trigger + estado en `resource` + EXCLUDE | Trigger/declarativo |
| RI-05 `fin > inicio` | `CHECK (fin > inicio)` | Declarativo |
| RI-06 dinero ≥ 0, exacto | `numeric(12,2) CHECK (>=0)` | Declarativo |
| RI-07 idempotencia | PK `idempotency_key.clave` + UK lógica `(request_hash)` | Declarativo |
| RI-08 documento cifrado | Almacenado como `bytea` cifrado; `documento_hash` para unicidad | Aplicación + constraint |
| RI-09 auditoría | `audit_log` append-only (permisos revocados) | Permisos |
| RI-10 parámetros en `app_config` | `jsonb` + validación por CHECK/trigger de rango | Declarativo |
| RI-11 tipo trabajadora ⇒ user_id | `CHECK (tipo='trabajadora' OR user_id IS NULL)` | Declarativo |

---

## 3. Triggers de negocio (los que los constraints no cubren)

| Trigger | Evento | Efecto | Regla |
|---|---|---|---|
| `trg_booking_confirm_requires_payment` | BEFORE UPDATE en `booking` (a `confirmada`) | Rechaza si el `payment` no está `verificado` | RN-002, RI-03 |
| `trg_resource_state_on_booking` | AFTER INSERT/UPDATE en `booking` | Marca/libera `resource.estado` según ciclo de la reserva | RN-003/005 |
| `trg_updated_at` | BEFORE UPDATE (tablas con `updated_at`) | `updated_at = now()` | Buenas prácticas |
| `trg_audit_critical` | AFTER INSERT/UPDATE/DELETE en tablas críticas | Inserta fila en `audit_log` con `prev_hash` | RF-015 |

---

## 4. Integridad referencial — estrategia de borrado

- `RESTRICT` en todas las referencias de dominio (branch/client/service/resource/booking/payment):
  protege el histórico y la auditoría.
- `SET NULL` en referencias a `app_user` (actor) y `booking.payment_id` (referencia opcional).
- `CASCADE` solo en `promotion_service` (tabla asociativa pura).
- Sin borrado físico de operaciones: las cancelaciones son **cambios de estado**, no `DELETE`.

---

## 5. Criterios de cierre

- [x] Toda tabla con PK.
- [x] Toda FK con `ON DELETE` definido e índice (Paso 11).
- [x] Todo estado/enumeración con `CHECK`.
- [x] Reglas de negocio RN-001..005 cubiertas por constraint o trigger.
- [x] Integridad de dinero/tiempo tipada y validada.

**Estado del paso:** completado.

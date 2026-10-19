# Paso 02 — Modelo conceptual

Sistema: **Spa Cielo**
Skill: `database-schema-designer`
Fecha: 2026-10-19
Entrada: `01_requisitos_datos/requisitos_datos.md`

Modelo **conceptual** (independiente del motor). No define tipos físicos ni SQL.

---

## 1. Entidades

| Entidad | Identificador conceptual | Atributos conceptuales |
|---|---|---|
| **Branch** | `branch` | nombre, dirección, estado |
| **User** | `user` | rol, nombre, credenciales, contacto, estado |
| **Client** | `client` | nombre, documento (protegido), contacto |
| **Service** | `service` | nombre, duración, precio, estado |
| **Resource** | `resource` | tipo (cama \| trabajadora), nombre, estado |
| **Booking** | `booking` | inicio, fin, estado |
| **Payment** | `payment` | proveedor, payload QR, monto, estado, verificador, fecha verificación |
| **Promotion** | `promotion` | nombre, reglas, vigencia, activo |
| **Refund** | `refund` | monto, motivo, estado, autorizador |
| **Notification** | `notification` | canal, plantilla, estado, fecha envío |
| **IdempotencyKey** | `idempotency_key` | clave, hash de petición, respuesta, expiración |
| **OutboxEvent** | `outbox` | tipo de evento, payload, publicado, intentos |
| **AuditLog** | `audit_log` | actor, acción, entidad, datos previos/nuevos, correlación, hash previo |
| **AppConfig** | `app_config` | clave, valor, actualizador, fecha |

Entidades asociativas (relaciones M:N):
- **PromotionService** (`promotion` ↔ `service`).
- **BookingAssignment**: materializa las dos asignaciones de una `Booking`
  (una `Resource` de tipo *cama* y una de tipo *trabajadora*), o se resuelve con dos
  referencias en `Booking` (decisión que se cierra en el modelo lógico).

---

## 2. Relaciones y cardinalidades

| # | Relación | Cardinalidad | Nota / Regla |
|---|---|---|---|
| R-01 | Branch — User | 1 : N | Todo usuario pertenece a una sucursal |
| R-02 | Branch — Client | 1 : N | Cliente atendido en una sucursal |
| R-03 | Branch — Service | 1 : N | Catálogo por sucursal |
| R-04 | Branch — Resource | 1 : N | Camas y trabajadoras por sucursal |
| R-05 | User — Resource | 1 : 0..1 | Una trabajadora *es* un recurso (RN-001) |
| R-06 | Client — Booking | 1 : N | Un cliente con varias reservas |
| R-07 | Service — Booking | 1 : N | Una reserva usa un servicio |
| R-08 | Resource(cama) — Booking | 1 : N | Cama asignada a la reserva (RN-001) |
| R-09 | Resource(trabajadora) — Booking | 1 : N | Trabajadora asignada (RN-003) |
| R-10 | Booking — Payment | 1 : 0..1 | Pago asociado; confirmación tras verificación (RN-002) |
| R-11 | Payment — Refund | 1 : N | Devoluciones totales/parciales (RF-010) |
| R-12 | Promotion — Service | N : M | Vía PromotionService (RF-009) |
| R-13 | Client — Notification | 1 : N | Confirmaciones y recordatorios (RF-011) |
| R-14 | User — AuditLog | 1 : N | Actor de auditoría (RF-015) |
| R-15 | Booking — OutboxEvent | 1 : N | Eventos de dominio (conceptual) |

---

## 3. Reglas de negocio representadas

| Regla | Representación conceptual |
|---|---|
| RN-001 | `Resource` acota camas (1-6) y trabajadoras (1-10); no se excede en reservas activas |
| RN-002 | `Booking.estado = confirmada` exige `Payment.estado = verificado` (secretaría) |
| RN-003 | `Resource(trabajadora).estado = ocupada` bloquea nuevas reservas |
| RN-004 | Walk-in permitido si existe ≥1 cama y ≥1 trabajadora `libre` |
| RN-005 | La liberación de recurso la pueden disparar trabajadora o secretaría |
| RF-016 | Nº de camas/trabajadoras y TTL son parámetros de `AppConfig` |

---

## 4. Estados (ciclos de vida)

- **Booking**: `pendiente → confirmada → atendida` ; `pendiente → expirada` (TTL) ;
  `pendiente|confirmada → cancelada` (liberación de recursos).
- **Payment**: `pendiente → verificado → (reembolsado)` ; `pendiente → rechazado`.
- **Resource**: `libre ⇄ ocupada` ; `no_disponible` (fuera de servicio).

---

## 5. Supuestos

- Una reserva ocupa exactamente **una cama** y **una trabajadora** (RF-004).
- El pago es **uno** por reserva en el MVP; los refunds pueden ser múltiples.
- La identidad del `client` presencial y la del `client` web convergen en la misma entidad.

**Estado del paso:** completado.

# Paso 04 — Modelo lógico

Sistema: **Spa Cielo**
Skill: `database-schema-designer`
Fecha: 2026-10-19
Entrada: `02_modelo_conceptual/`

Modelo lógico relacional: tablas, claves, nulabilidad, unicidad y constraints lógicos.
Tipos indicativos (se materializan en el diseño físico, Paso 07).

---

## 1. Tablas

### `branch`
| Columna | Tipo | Null | Clave | Notas |
|---|---|---|---|---|
| id | uuid | NN | PK | |
| nombre | varchar(120) | NN | UK | |
| direccion | varchar(255) | NN | | |
| estado | varchar(20) | NN | | `activa\|inactiva` |
| created_at | timestamptz | NN | | |
| updated_at | timestamptz | NN | | |

### `app_user`
| Columna | Tipo | Null | Clave | Notas |
|---|---|---|---|---|
| id | uuid | NN | PK | |
| branch_id | uuid | NN | FK→branch | ON DELETE RESTRICT |
| rol | varchar(20) | NN | | CHECK ∈ {superusuario,secretaria,trabajadora} |
| nombre | varchar(120) | NN | | |
| hash_password | varchar(255) | NN | | Argon2id (RNF-033) |
| contacto | varchar(160) | | UK | email/teléfono único por sucursal |
| estado | varchar(20) | NN | | `activo\|inactivo` |
| created_at / updated_at | timestamptz | NN | | |

- UK: `(branch_id, contacto)`.
- Nota: el rol *cliente* se representa en la entidad `client`, no en `app_user` (RF-002).

### `client`
| Columna | Tipo | Null | Clave | Notas |
|---|---|---|---|---|
| id | uuid | NN | PK | |
| branch_id | uuid | NN | FK→branch | RESTRICT |
| nombre | varchar(160) | NN | | |
| documento_cifrado | bytea | NN | | AES-256-GCM (RNF-032) |
| documento_hash | char(64) | NN | | SHA-256 para unicidad/búsqueda |
| contacto | varchar(160) | | | |
| created_at / updated_at | timestamptz | NN | | |

- UK: `(branch_id, documento_hash)`.

### `service`
| Columna | Tipo | Null | Clave | Notas |
|---|---|---|---|---|
| id | uuid | NN | PK | |
| branch_id | uuid | NN | FK→branch | RESTRICT |
| nombre | varchar(120) | NN | | UK por sucursal |
| duracion_min | int | NN | | CHECK > 0 |
| precio | numeric(12,2) | NN | | CHECK >= 0 (RNF-021) |
| estado | varchar(20) | NN | | `activo\|inactivo` |

- UK: `(branch_id, nombre)`.

### `resource`
| Columna | Tipo | Null | Clave | Notas |
|---|---|---|---|---|
| id | uuid | NN | PK | |
| branch_id | uuid | NN | FK→branch | RESTRICT |
| tipo | varchar(15) | NN | | CHECK ∈ {cama,trabajadora} |
| user_id | uuid | | FK→app_user | NULL salvo `trabajadora` |
| nombre | varchar(120) | NN | | |
| estado | varchar(20) | NN | | `libre\|ocupada\|no_disponible` |
| version | int | NN | | control optimista auxiliar |
| updated_at | timestamptz | NN | | |

- UK: `(branch_id, tipo, nombre)`.
- Regla lógica: `tipo='trabajadora' → user_id NOT NULL` (CHECK; ver Paso 08).

### `booking`
| Columna | Tipo | Null | Clave | Notas |
|---|---|---|---|---|
| id | uuid | NN | PK | |
| branch_id | uuid | NN | FK→branch | RESTRICT |
| client_id | uuid | NN | FK→client | RESTRICT |
| service_id | uuid | NN | FK→service | RESTRICT |
| cama_id | uuid | NN | FK→resource | RESTRICT; tipo=cama |
| trabajadora_id | uuid | NN | FK→resource | RESTRICT; tipo=trabajadora |
| inicio | timestamptz | NN | | |
| fin | timestamptz | NN | | CHECK fin > inicio |
| estado | varchar(15) | NN | | `pendiente\|confirmada\|cancelada\|expirada\|atendida` |
| payment_id | uuid | | FK→payment | desnormalización controlada 1:1 |
| created_at / updated_at | timestamptz | NN | | |

- Índices únicos **parciales** para no solapar reservas activas (Paso 10/07):
  `(cama_id, inicio)` y `(trabajadora_id, inicio)` condicionados a estado activo.

### `payment`
| Columna | Tipo | Null | Clave | Notas |
|---|---|---|---|---|
| id | uuid | NN | PK | |
| booking_id | uuid | NN | FK→booking, **UK** | 1:1 |
| proveedor | varchar(60) | NN | | adapter desacoplado (RF-012) |
| qr_payload | text | | | contenido del QR |
| monto | numeric(12,2) | NN | | CHECK >= 0 |
| moneda | char(3) | NN | | default `BOB` |
| estado | varchar(15) | NN | | `pendiente\|verificado\|rechazado\|reembolsado` |
| verificado_por | uuid | | FK→app_user | secretaría |
| verificado_en | timestamptz | | | |
| created_at / updated_at | timestamptz | NN | | |

### `promotion` y `promotion_service`
`promotion`: id PK, branch_id FK, nombre NN, reglas NN (jsonb), valid_from, valid_to,
activo NN; CHECK `valid_to > valid_from`.
`promotion_service`: `promotion_id` FK, `service_id` FK, **PK (promotion_id, service_id)**,
ON DELETE CASCADE. (M:N, RF-009)

### `refund`
| Columna | Tipo | Null | Clave | Notas |
|---|---|---|---|---|
| id | uuid | NN | PK | |
| payment_id | uuid | NN | FK→payment | RESTRICT |
| monto | numeric(12,2) | NN | | CHECK >= 0 y ≤ pago (app/trigger) |
| motivo | varchar(255) | | | |
| estado | varchar(15) | NN | | `solicitada\|aprobada\|rechazada` |
| autorizado_por | uuid | | FK→app_user | |
| created_at | timestamptz | NN | | |

### `notification`
id PK, client_id FK NN, canal NN (`whatsapp|email`), plantilla NN, estado NN
(`pendiente|enviada|fallida`), enviado_en, created_at.

### `idempotency_key`
`clave` PK varchar(255) (`scope:key`), `request_hash` NN, `response` jsonb, `expires_at` NN,
`created_at` NN. (RF-008)

### `outbox`
id PK bigserial, tipo_evento NN, payload NN jsonb, publicado NN default false,
intentos NN default 0, created_at NN.

### `audit_log` (append-only)
id PK bigserial, actor_user_id FK nullable, accion NN, entidad NN, entidad_id uuid,
datos_previos jsonb, datos_nuevos jsonb, correlation_id varchar, prev_hash varchar,
creado_en NN.

### `app_config`
`clave` PK varchar(120), valor NN jsonb, updated_by FK→app_user, updated_at NN. (RF-016)

---

## 2. Matriz de claves foráneas y borrado

| FK | Referencia | ON DELETE |
|---|---|---|
| app_user.branch_id | branch.id | RESTRICT |
| client.branch_id | branch.id | RESTRICT |
| service.branch_id | branch.id | RESTRICT |
| resource.branch_id | branch.id | RESTRICT |
| resource.user_id | app_user.id | SET NULL |
| booking.client_id | client.id | RESTRICT |
| booking.service_id | service.id | RESTRICT |
| booking.cama_id / trabajadora_id | resource.id | RESTRICT |
| booking.payment_id | payment.id | SET NULL |
| payment.booking_id | booking.id | RESTRICT |
| promotion_service.* | promotion.id / service.id | CASCADE |
| refund.payment_id | payment.id | RESTRICT |
| notification.client_id | client.id | RESTRICT |
| audit_log.actor_user_id | app_user.id | SET NULL |

---

## 3. Decisiones lógicas

- **UUID** como PK de dominio (anti-enumeración, D-07); `bigserial` en tablas de alto volumen.
- **Relación booking↔payment** modelada de forma reversible: `payment.booking_id` UK (fuente)
  y `booking.payment_id` (referencia desnormalizada 1:1, opcional).
- **Documento del cliente**: se guarda `documento_cifrado` + `documento_hash` para
  permitir unicidad sin descifrar (RNF-032).
- **Dinero** con `numeric(12,2)`; nunca `float` (RNF-021).

**Estado del paso:** completado.

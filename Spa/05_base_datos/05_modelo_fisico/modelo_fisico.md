# Paso 07 — Diseño físico (PostgreSQL 16+)

Sistema: **Spa Cielo**
Skills: `postgresql-table-design` + `databases`
Fecha: 2026-10-19
Entrada: `03_modelo_logico/`, `05_modelo_fisico/seleccion_dbms.md`
Motor: **PostgreSQL 16+** (D-08).

> Este documento fija tipos y mecanismos físicos. Los scripts SQL definitivos se
> generan en el Paso 15, solo tras `STATUS: APPROVED` (Paso 14).

---

## 1. Convenciones

- Identificadores `snake_case`, sin comillas (evita plegado a minúsculas).
- Texto → `text` con `CHECK (length(...) <= n)` en vez de `varchar(n)`.
- Dinero → `numeric(12,2)`; nunca `float`/`money` (RNF-021).
- Fecha/hora → `timestamptz` (UTC).
- PK de dominio → `uuid DEFAULT gen_random_uuid()` (opacidad/anti-enumeración, D-07);
  PK de alto volumen (`outbox`, `audit_log`) → `bigint GENERATED ALWAYS AS IDENTITY`.
- Estados → `text` + `CHECK` (no `ENUM`, para permitir evolución de valores).
- Índices sobre **toda** columna FK (PostgreSQL no los crea automáticamente).

---

## 2. Tipos y constraints por tabla

### `branch`
- `id uuid PK DEFAULT gen_random_uuid()`; `nombre text NOT NULL CHECK(length(nombre)<=120)`;
  `direccion text NOT NULL`; `estado text NOT NULL DEFAULT 'activa' CHECK (estado IN ('activa','inactiva'))`;
  `created_at/updated_at timestamptz NOT NULL DEFAULT now()`.
- `UNIQUE (nombre)`.

### `app_user`
- `id uuid PK`; `branch_id uuid NOT NULL REFERENCES branch(id) ON DELETE RESTRICT`;
  `rol text NOT NULL CHECK (rol IN ('superusuario','secretaria','trabajadora'))`;
  `nombre text NOT NULL`; `hash_password text NOT NULL`;
  `contacto text`; `estado text NOT NULL DEFAULT 'activo' CHECK (estado IN ('activo','inactivo'))`.
- `UNIQUE (branch_id, contacto)`.

### `client`
- `documento_cifrado bytea NOT NULL`; `documento_hash text NOT NULL`
  (SHA-256 hex, para unicidad sin descifrar).
- `UNIQUE (branch_id, documento_hash)`.

### `service`
- `precio numeric(12,2) NOT NULL CHECK (precio >= 0)`;
  `duracion_min integer NOT NULL CHECK (duracion_min > 0)`.
- `UNIQUE (branch_id, nombre)`.

### `resource`
- `tipo text NOT NULL CHECK (tipo IN ('cama','trabajadora'))`;
  `user_id uuid REFERENCES app_user(id) ON DELETE SET NULL`;
  `estado text NOT NULL DEFAULT 'libre' CHECK (estado IN ('libre','ocupada','no_disponible'))`;
  `version integer NOT NULL DEFAULT 0`.
- `UNIQUE (branch_id, tipo, nombre)`.
- `CHECK (tipo = 'trabajadora' OR user_id IS NULL)` (una cama no tiene usuario).

### `booking` — **anti-solape por EXCLUDE**
- `inicio timestamptz NOT NULL`, `fin timestamptz NOT NULL`,
  `CHECK (fin > inicio)`.
- Columna generada de periodo y exclusión (clave para RNF-020):
  ```sql
  periodo tstzrange GENERATED ALWAYS AS (tstzrange(inicio, fin, '[)')) STORED,
  EXCLUDE USING gist (cama_id WITH =, periodo WITH &&)
      WHERE (estado IN ('pendiente','confirmada')),
  EXCLUDE USING gist (trabajadora_id WITH =, periodo WITH &&)
      WHERE (estado IN ('pendiente','confirmada'))
  ```
  > Requiere `btree_gist`. Impide a nivel de motor dos reservas activas solapadas
  > de la misma cama o trabajadora (RI-01, RI-02), complementando los locks (Paso 12).
- `estado text NOT NULL DEFAULT 'pendiente' CHECK (estado IN ('pendiente','confirmada','cancelada','expirada','atendida'))`.

### `payment`
- `monto numeric(12,2) NOT NULL CHECK (monto >= 0)`; `moneda char(3) NOT NULL DEFAULT 'BOB'`;
  `estado text NOT NULL CHECK (estado IN ('pendiente','verificado','rechazado','reembolsado'))`.
- `UNIQUE (booking_id)` (1:1 con booking).

### `promotion` / `promotion_service`
- `reglas jsonb NOT NULL DEFAULT '{}'`; `CHECK (valid_to > valid_from)`.
- `promotion_service`: PK `(promotion_id, service_id)`, FK `ON DELETE CASCADE`.

### `refund`
- `monto numeric(12,2) NOT NULL CHECK (monto >= 0)`;
  `estado text NOT NULL CHECK (estado IN ('solicitada','aprobada','rechazada'))`.

### `notification`
- `canal text NOT NULL CHECK (canal IN ('whatsapp','email'))`;
  `estado text NOT NULL CHECK (estado IN ('pendiente','enviada','fallida'))`.

### `idempotency_key`
- `clave text PRIMARY KEY` (`scope:key`); `request_hash text NOT NULL`;
  `response jsonb`; `expires_at timestamptz NOT NULL`.

### `outbox`
- `id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY`;
  `payload jsonb NOT NULL`; `publicado boolean NOT NULL DEFAULT false`;
  `intentos integer NOT NULL DEFAULT 0`.

### `audit_log` (append-only)
- `id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY`;
  `datos_previos/datos_nuevos jsonb`; `prev_hash text`; `creado_en timestamptz NOT NULL DEFAULT now()`.
- Sin `UPDATE`/`DELETE` (se revoca en Paso 09).

### `app_config`
- `clave text PRIMARY KEY`; `valor jsonb NOT NULL`.

---

## 3. Decisiones de almacenamiento / particionado

| Tabla | Estrategia | Motivo |
|---|---|---|
| `outbox` | tabla normal; revisar partición RANGE si crece | Volumen bajo en MVP (RNF-001) |
| `audit_log` | tabla normal; partición RANGE por `creado_en` si supera ~10M filas | Crecimiento monotónico (RF-015) |
| `booking` | normal; EXCLUDE gist; sin partición | Recursos escasos, franjas cortas |
| `idempotency_key` | normal; job de purga por `expires_at` | TTL (RF-008) |

No se particiona en el MVP (volumen 50-500 tx/h); se deja documentado el umbral.

---

## 4. Extensiones requeridas

`pgcrypto` (`gen_random_uuid()`), `btree_gist` (EXCLUDE de rangos),
`pg_cron` (job de expiración, RF-016), `pgaudit` (auditoría, RF-015),
opcional `pg_trgm` (búsqueda de nombres).

---

## 5. RLS (adelanto — detalle en Paso 09)

Todas las tablas con `branch_id` llevan `ALTER TABLE ... ENABLE ROW LEVEL SECURITY`
y política `USING (branch_id = current_setting('app.branch_id')::uuid)` (RNF-010/031, D-05).

---

## 6. DDL ilustrativo (no es el script final)

```sql
CREATE TABLE booking (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  branch_id uuid NOT NULL REFERENCES branch(id) ON DELETE RESTRICT,
  client_id uuid NOT NULL REFERENCES client(id) ON DELETE RESTRICT,
  service_id uuid NOT NULL REFERENCES service(id) ON DELETE RESTRICT,
  cama_id uuid NOT NULL REFERENCES resource(id) ON DELETE RESTRICT,
  trabajadora_id uuid NOT NULL REFERENCES resource(id) ON DELETE RESTRICT,
  inicio timestamptz NOT NULL,
  fin timestamptz NOT NULL,
  periodo tstzrange GENERATED ALWAYS AS (tstzrange(inicio, fin, '[)')) STORED,
  estado text NOT NULL DEFAULT 'pendiente'
    CHECK (estado IN ('pendiente','confirmada','cancelada','expirada','atendida')),
  payment_id uuid REFERENCES payment(id) ON DELETE SET NULL,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  CHECK (fin > inicio),
  EXCLUDE USING gist (cama_id WITH =, periodo WITH &&)
    WHERE (estado IN ('pendiente','confirmada')),
  EXCLUDE USING gist (trabajadora_id WITH =, periodo WITH &&)
    WHERE (estado IN ('pendiente','confirmada'))
);
CREATE INDEX ON booking (cama_id);
CREATE INDEX ON booking (trabajadora_id);
CREATE INDEX ON booking (branch_id, inicio);
```

**Estado del paso:** completado.

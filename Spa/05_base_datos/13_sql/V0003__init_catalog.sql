-- V0003__init_catalog.sql
-- Spa Cielo — clientes, servicios y recursos (camas/trabajadoras).
-- Motivo: RF-002/003/005, RN-001/004, RNF-032.
-- Forward + Rollback.

-- ===================== UP =====================
BEGIN;

CREATE TABLE catalog.client (
    id                uuid        PRIMARY KEY DEFAULT gen_random_uuid(),
    branch_id         uuid        NOT NULL REFERENCES core.branch(id) ON DELETE RESTRICT,
    nombre            text        NOT NULL CHECK (length(nombre) <= 160),
    documento_cifrado bytea       NOT NULL,           -- AES-256-GCM (aplicación)
    documento_hash    text        NOT NULL,           -- SHA-256 hex para unicidad
    contacto          text        CHECK (contacto IS NULL OR length(contacto) <= 160),
    created_at        timestamptz NOT NULL DEFAULT now(),
    updated_at        timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT uq_client_doc UNIQUE (branch_id, documento_hash)
);

CREATE TABLE catalog.service (
    id           uuid        PRIMARY KEY DEFAULT gen_random_uuid(),
    branch_id    uuid        NOT NULL REFERENCES core.branch(id) ON DELETE RESTRICT,
    nombre       text        NOT NULL CHECK (length(nombre) <= 120),
    duracion_min integer     NOT NULL CHECK (duracion_min > 0),
    precio       numeric(12,2) NOT NULL CHECK (precio >= 0),
    estado       text        NOT NULL DEFAULT 'activo' CHECK (estado IN ('activo','inactivo')),
    created_at   timestamptz NOT NULL DEFAULT now(),
    updated_at   timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT uq_service_nombre UNIQUE (branch_id, nombre)
);

CREATE TABLE catalog.resource (
    id         uuid        PRIMARY KEY DEFAULT gen_random_uuid(),
    branch_id  uuid        NOT NULL REFERENCES core.branch(id) ON DELETE RESTRICT,
    tipo       text        NOT NULL CHECK (tipo IN ('cama','trabajadora')),
    user_id    uuid        REFERENCES auth.app_user(id) ON DELETE SET NULL,
    nombre     text        NOT NULL CHECK (length(nombre) <= 120),
    estado     text        NOT NULL DEFAULT 'libre' CHECK (estado IN ('libre','ocupada','no_disponible')),
    version    integer     NOT NULL DEFAULT 0,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT uq_resource_nombre UNIQUE (branch_id, tipo, nombre),
    CONSTRAINT ck_resource_user CHECK (tipo = 'trabajadora' OR user_id IS NULL)
);

CREATE INDEX ix_client_branch    ON catalog.client (branch_id);
CREATE INDEX ix_service_branch   ON catalog.service (branch_id);
CREATE INDEX ix_resource_estado  ON catalog.resource (branch_id, tipo, estado);
CREATE INDEX ix_resource_user    ON catalog.resource (user_id);

COMMIT;

-- ===================== DOWN =====================
-- BEGIN;
-- DROP TABLE IF EXISTS catalog.resource;
-- DROP TABLE IF EXISTS catalog.service;
-- DROP TABLE IF EXISTS catalog.client;
-- COMMIT;

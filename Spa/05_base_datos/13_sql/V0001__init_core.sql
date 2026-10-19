-- V0001__init_core.sql
-- Spa Cielo — esquema core. Motivo: RNF-060, RF-016.
-- Motor: PostgreSQL 16+. Forward + Rollback.

-- ===================== UP =====================
BEGIN;

CREATE EXTENSION IF NOT EXISTS pgcrypto;    -- gen_random_uuid()
CREATE EXTENSION IF NOT EXISTS btree_gist;  -- EXCLUDE con igualdad + rango
CREATE EXTENSION IF NOT EXISTS pg_cron;     -- jobs (expiración TTL)
CREATE EXTENSION IF NOT EXISTS pg_trgm;     -- búsqueda difusa de nombres

CREATE SCHEMA IF NOT EXISTS core;
CREATE SCHEMA IF NOT EXISTS auth;
CREATE SCHEMA IF NOT EXISTS catalog;
CREATE SCHEMA IF NOT EXISTS booking;
CREATE SCHEMA IF NOT EXISTS payment;

CREATE TABLE core.branch (
    id         uuid        PRIMARY KEY DEFAULT gen_random_uuid(),
    nombre     text        NOT NULL CHECK (length(nombre) <= 120),
    direccion  text        NOT NULL CHECK (length(direccion) <= 255),
    estado     text        NOT NULL DEFAULT 'activa' CHECK (estado IN ('activa','inactiva')),
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT uq_branch_nombre UNIQUE (nombre)
);

CREATE TABLE core.app_config (
    clave      text        PRIMARY KEY CHECK (length(clave) <= 120),
    valor      jsonb       NOT NULL,
    updated_by uuid,                        -- FK a auth.app_user (V0002)
    updated_at timestamptz NOT NULL DEFAULT now()
);

-- Función reutilizable de updated_at
CREATE OR REPLACE FUNCTION core.set_updated_at() RETURNS trigger AS $$
BEGIN
    NEW.updated_at := now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

COMMIT;

-- ===================== DOWN =====================
-- BEGIN;
-- DROP FUNCTION IF EXISTS core.set_updated_at();
-- DROP TABLE IF EXISTS core.app_config;
-- DROP TABLE IF EXISTS core.branch;
-- DROP SCHEMA IF EXISTS core;
-- DROP SCHEMA IF EXISTS auth;
-- DROP SCHEMA IF EXISTS catalog;
-- DROP SCHEMA IF EXISTS booking;
-- DROP SCHEMA IF EXISTS payment;
-- COMMIT;

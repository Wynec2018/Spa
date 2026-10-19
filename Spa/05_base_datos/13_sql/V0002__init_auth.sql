-- V0002__init_auth.sql
-- Spa Cielo — usuarios y roles. Motivo: RF-001, RNF-030/031/033.
-- Forward + Rollback.

-- ===================== UP =====================
BEGIN;

CREATE TABLE auth.app_user (
    id            uuid        PRIMARY KEY DEFAULT gen_random_uuid(),
    branch_id     uuid        NOT NULL REFERENCES core.branch(id) ON DELETE RESTRICT,
    rol           text        NOT NULL CHECK (rol IN ('superusuario','secretaria','trabajadora')),
    nombre        text        NOT NULL CHECK (length(nombre) <= 120),
    hash_password text        NOT NULL,
    contacto      text        CHECK (contacto IS NULL OR length(contacto) <= 160),
    estado        text        NOT NULL DEFAULT 'activo' CHECK (estado IN ('activo','inactivo')),
    created_at    timestamptz NOT NULL DEFAULT now(),
    updated_at    timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT uq_user_contacto UNIQUE (branch_id, contacto)
);

CREATE INDEX ix_user_branch ON auth.app_user (branch_id);

-- FK pendiente de V0001
ALTER TABLE core.app_config
    ADD CONSTRAINT fk_appconfig_user
    FOREIGN KEY (updated_by) REFERENCES auth.app_user(id) ON DELETE SET NULL;

COMMIT;

-- ===================== DOWN =====================
-- BEGIN;
-- ALTER TABLE core.app_config DROP CONSTRAINT IF EXISTS fk_appconfig_user;
-- DROP TABLE IF EXISTS auth.app_user;
-- COMMIT;

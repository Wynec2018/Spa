-- V0006__init_support.sql
-- Spa Cielo — promociones, notificaciones y outbox.
-- Motivo: RF-009/011, RNF-042.
-- Forward + Rollback.

-- ===================== UP =====================
BEGIN;

CREATE SCHEMA IF NOT EXISTS support;

CREATE TABLE catalog.promotion (
    id         uuid        PRIMARY KEY DEFAULT gen_random_uuid(),
    branch_id  uuid        NOT NULL REFERENCES core.branch(id) ON DELETE RESTRICT,
    nombre     text        NOT NULL CHECK (length(nombre) <= 120),
    reglas     jsonb       NOT NULL DEFAULT '{}',
    valid_from timestamptz NOT NULL,
    valid_to   timestamptz NOT NULL,
    activo     boolean     NOT NULL DEFAULT true,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT uq_promotion_nombre UNIQUE (branch_id, nombre),
    CONSTRAINT ck_promotion_vigencia CHECK (valid_to > valid_from)
);

CREATE TABLE catalog.promotion_service (
    promotion_id uuid NOT NULL REFERENCES catalog.promotion(id) ON DELETE CASCADE,
    service_id   uuid NOT NULL REFERENCES catalog.service(id)   ON DELETE CASCADE,
    PRIMARY KEY (promotion_id, service_id)
);

CREATE INDEX ix_promotion_service_service ON catalog.promotion_service (service_id);
CREATE INDEX gin_promotion_reglas ON catalog.promotion USING gin (reglas);

CREATE TABLE support.notification (
    id          uuid        PRIMARY KEY DEFAULT gen_random_uuid(),
    client_id   uuid        NOT NULL REFERENCES catalog.client(id) ON DELETE RESTRICT,
    canal       text        NOT NULL CHECK (canal IN ('whatsapp','email')),
    plantilla   text        NOT NULL CHECK (length(plantilla) <= 120),
    estado      text        NOT NULL DEFAULT 'pendiente' CHECK (estado IN ('pendiente','enviada','fallida')),
    enviado_en  timestamptz,
    created_at  timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX ix_notification_pendiente ON support.notification (estado) WHERE estado = 'pendiente';

CREATE TABLE support.outbox (
    id          bigint      GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    tipo_evento text        NOT NULL CHECK (length(tipo_evento) <= 120),
    payload     jsonb       NOT NULL,
    publicado   boolean     NOT NULL DEFAULT false,
    intentos    integer     NOT NULL DEFAULT 0,
    created_at  timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX ix_outbox_pending ON support.outbox (publicado, created_at) WHERE publicado = false;

COMMIT;

-- ===================== DOWN =====================
-- BEGIN;
-- DROP TABLE IF EXISTS support.outbox;
-- DROP TABLE IF EXISTS support.notification;
-- DROP TABLE IF EXISTS catalog.promotion_service;
-- DROP TABLE IF EXISTS catalog.promotion;
-- DROP SCHEMA IF EXISTS support;
-- COMMIT;

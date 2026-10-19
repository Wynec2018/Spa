-- V0005__init_payment.sql
-- Spa Cielo — pagos, devoluciones e idempotencia.
-- Motivo: RF-007/008/010/012, RN-002, RNF-022.
-- Forward + Rollback.

-- ===================== UP =====================
BEGIN;

CREATE TABLE payment.payment (
    id             uuid        PRIMARY KEY DEFAULT gen_random_uuid(),
    booking_id     uuid        NOT NULL REFERENCES booking.booking(id) ON DELETE RESTRICT,
    proveedor      text        NOT NULL CHECK (length(proveedor) <= 60),
    qr_payload     text,
    monto          numeric(12,2) NOT NULL CHECK (monto >= 0),
    moneda         char(3)     NOT NULL DEFAULT 'BOB',
    estado         text        NOT NULL DEFAULT 'pendiente'
                               CHECK (estado IN ('pendiente','verificado','rechazado','reembolsado')),
    verificado_por uuid        REFERENCES auth.app_user(id) ON DELETE SET NULL,
    verificado_en  timestamptz,
    created_at     timestamptz NOT NULL DEFAULT now(),
    updated_at     timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT uq_payment_booking UNIQUE (booking_id)   -- 1:1 con booking
);

CREATE INDEX ix_payment_estado ON payment.payment (estado) WHERE estado = 'pendiente';

-- Referencia desnormalizada 1:1 (DZ-01)
ALTER TABLE booking.booking
    ADD COLUMN payment_id uuid REFERENCES payment.payment(id) ON DELETE SET NULL;

CREATE TABLE payment.refund (
    id             uuid        PRIMARY KEY DEFAULT gen_random_uuid(),
    payment_id     uuid        NOT NULL REFERENCES payment.payment(id) ON DELETE RESTRICT,
    monto          numeric(12,2) NOT NULL CHECK (monto >= 0),
    motivo         text        CHECK (motivo IS NULL OR length(motivo) <= 255),
    estado         text        NOT NULL DEFAULT 'solicitada'
                               CHECK (estado IN ('solicitada','aprobada','rechazada')),
    autorizado_por uuid        REFERENCES auth.app_user(id) ON DELETE SET NULL,
    created_at     timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX ix_refund_payment ON payment.refund (payment_id);

CREATE TABLE payment.idempotency_key (
    clave        text        PRIMARY KEY CHECK (length(clave) <= 255),  -- scope:key
    request_hash text        NOT NULL,
    response     jsonb,
    expires_at   timestamptz NOT NULL,
    created_at   timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX ix_idem_expires ON payment.idempotency_key (expires_at);

COMMIT;

-- ===================== DOWN =====================
-- BEGIN;
-- DROP TABLE IF EXISTS payment.idempotency_key;
-- DROP TABLE IF EXISTS payment.refund;
-- ALTER TABLE booking.booking DROP COLUMN IF EXISTS payment_id;
-- DROP TABLE IF EXISTS payment.payment;
-- COMMIT;

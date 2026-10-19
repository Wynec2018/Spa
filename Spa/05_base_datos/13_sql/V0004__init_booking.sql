-- V0004__init_booking.sql
-- Spa Cielo — reservas/citas con anti-solape de recursos.
-- Motivo: RF-004/006/013, RN-001/003, RNF-005/020.
-- Forward + Rollback.

-- ===================== UP =====================
BEGIN;

CREATE TABLE booking.booking (
    id             uuid        PRIMARY KEY DEFAULT gen_random_uuid(),
    branch_id      uuid        NOT NULL REFERENCES core.branch(id) ON DELETE RESTRICT,
    client_id      uuid        NOT NULL REFERENCES catalog.client(id) ON DELETE RESTRICT,
    service_id     uuid        NOT NULL REFERENCES catalog.service(id) ON DELETE RESTRICT,
    cama_id        uuid        NOT NULL REFERENCES catalog.resource(id) ON DELETE RESTRICT,
    trabajadora_id uuid        NOT NULL REFERENCES catalog.resource(id) ON DELETE RESTRICT,
    inicio         timestamptz NOT NULL,
    fin            timestamptz NOT NULL,
    periodo        tstzrange   GENERATED ALWAYS AS (tstzrange(inicio, fin, '[)')) STORED,
    estado         text        NOT NULL DEFAULT 'pendiente'
                               CHECK (estado IN ('pendiente','confirmada','cancelada','expirada','atendida')),
    -- payment_id se añade en V0005 (dependencia circular booking<->payment)
    created_at     timestamptz NOT NULL DEFAULT now(),
    updated_at     timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT ck_booking_periodo CHECK (fin > inicio),
    CONSTRAINT ex_booking_cama EXCLUDE USING gist (cama_id WITH =, periodo WITH &&)
        WHERE (estado IN ('pendiente','confirmada')),
    CONSTRAINT ex_booking_trab EXCLUDE USING gist (trabajadora_id WITH =, periodo WITH &&)
        WHERE (estado IN ('pendiente','confirmada'))
);

CREATE INDEX ix_booking_cama         ON booking.booking (cama_id);
CREATE INDEX ix_booking_trabajadora  ON booking.booking (trabajadora_id);
CREATE INDEX ix_booking_branch_inicio ON booking.booking (branch_id, inicio);
CREATE INDEX ix_booking_client       ON booking.booking (client_id);
CREATE INDEX ix_booking_service      ON booking.booking (service_id);
CREATE INDEX ix_booking_activas      ON booking.booking (branch_id, estado)
    WHERE estado IN ('pendiente','confirmada');

COMMIT;

-- ===================== DOWN =====================
-- BEGIN;
-- DROP TABLE IF EXISTS booking.booking;
-- COMMIT;

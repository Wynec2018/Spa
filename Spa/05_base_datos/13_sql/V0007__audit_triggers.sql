-- V0007__audit_triggers.sql
-- Spa Cielo — auditoría, triggers de integridad y función de expiración.
-- Motivo: RF-015, RN-002/003/005, RNF-031.
-- Forward + Rollback.

-- ===================== UP =====================
BEGIN;

CREATE SCHEMA IF NOT EXISTS audit;

CREATE TABLE audit.audit_log (
    id            bigint      GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    actor_user_id uuid        REFERENCES auth.app_user(id) ON DELETE SET NULL,
    accion        text        NOT NULL CHECK (length(accion) <= 40),
    entidad       text        NOT NULL CHECK (length(entidad) <= 80),
    entidad_id    uuid,
    datos_previos jsonb,
    datos_nuevos  jsonb,
    correlation_id text,
    prev_hash     text,
    creado_en     timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX ix_audit_entidad ON audit.audit_log (entidad, entidad_id);
CREATE INDEX ix_audit_created_brin ON audit.audit_log USING brin (creado_en);

-- 1) updated_at automático
CREATE TRIGGER trg_updated_branch   BEFORE UPDATE ON core.branch
    FOR EACH ROW EXECUTE FUNCTION core.set_updated_at();
CREATE TRIGGER trg_updated_user     BEFORE UPDATE ON auth.app_user
    FOR EACH ROW EXECUTE FUNCTION core.set_updated_at();
CREATE TRIGGER trg_updated_client   BEFORE UPDATE ON catalog.client
    FOR EACH ROW EXECUTE FUNCTION core.set_updated_at();
CREATE TRIGGER trg_updated_service  BEFORE UPDATE ON catalog.service
    FOR EACH ROW EXECUTE FUNCTION core.set_updated_at();
CREATE TRIGGER trg_updated_resource BEFORE UPDATE ON catalog.resource
    FOR EACH ROW EXECUTE FUNCTION core.set_updated_at();
CREATE TRIGGER trg_updated_booking  BEFORE UPDATE ON booking.booking
    FOR EACH ROW EXECUTE FUNCTION core.set_updated_at();
CREATE TRIGGER trg_updated_payment  BEFORE UPDATE ON payment.payment
    FOR EACH ROW EXECUTE FUNCTION core.set_updated_at();
CREATE TRIGGER trg_updated_promotion BEFORE UPDATE ON catalog.promotion
    FOR EACH ROW EXECUTE FUNCTION core.set_updated_at();

-- 2) RI-03: no confirmar sin pago verificado (RN-002)
CREATE OR REPLACE FUNCTION booking.fn_confirm_requires_payment() RETURNS trigger AS $$
DECLARE v_estado text;
BEGIN
    IF NEW.estado = 'confirmada' AND OLD.estado IS DISTINCT FROM 'confirmada' THEN
        SELECT p.estado INTO v_estado
        FROM payment.payment p
        WHERE p.booking_id = NEW.id
        ORDER BY p.created_at DESC
        LIMIT 1;
        IF v_estado IS DISTINCT FROM 'verificado' THEN
            RAISE EXCEPTION 'La reserva % no puede confirmarse sin pago verificado (RN-002)', NEW.id;
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_booking_confirm_requires_payment
    BEFORE UPDATE ON booking.booking
    FOR EACH ROW EXECUTE FUNCTION booking.fn_confirm_requires_payment();

-- 3) RI-04/RN-003/RN-005: estado de la trabajadora según reserva
CREATE OR REPLACE FUNCTION booking.fn_sync_trabajadora_estado() RETURNS trigger AS $$
BEGIN
    IF NEW.estado IN ('pendiente','confirmada') THEN
        UPDATE catalog.resource SET estado = 'ocupada', updated_at = now()
        WHERE id = NEW.trabajadora_id AND estado = 'libre';
    ELSIF NEW.estado IN ('cancelada','expirada','atendida') THEN
        UPDATE catalog.resource SET estado = 'libre', updated_at = now()
        WHERE id = NEW.trabajadora_id AND estado = 'ocupada';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_resource_state_on_booking
    AFTER INSERT OR UPDATE OF estado ON booking.booking
    FOR EACH ROW EXECUTE FUNCTION booking.fn_sync_trabajadora_estado();

-- 4) Auditoría genérica de operaciones críticas
CREATE OR REPLACE FUNCTION audit.fn_audit() RETURNS trigger AS $$
DECLARE
    v_prev text;
BEGIN
    SELECT prev_hash INTO v_prev
    FROM audit.audit_log ORDER BY id DESC LIMIT 1;

    INSERT INTO audit.audit_log (actor_user_id, accion, entidad, entidad_id,
                                 datos_previos, datos_nuevos, correlation_id, prev_hash)
    VALUES (
        NULLIF(current_setting('app.user_id', true), '')::uuid,
        TG_OP, TG_TABLE_NAME,
        COALESCE((to_jsonb(NEW)->>'id')::uuid, (to_jsonb(OLD)->>'id')::uuid),
        CASE WHEN TG_OP IN ('UPDATE','DELETE') THEN to_jsonb(OLD) END,
        CASE WHEN TG_OP IN ('INSERT','UPDATE') THEN to_jsonb(NEW) END,
        current_setting('app.correlation_id', true),
        v_prev
    );
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_audit_user    AFTER INSERT OR UPDATE OR DELETE ON auth.app_user
    FOR EACH ROW EXECUTE FUNCTION audit.fn_audit();
CREATE TRIGGER trg_audit_booking AFTER INSERT OR UPDATE OR DELETE ON booking.booking
    FOR EACH ROW EXECUTE FUNCTION audit.fn_audit();
CREATE TRIGGER trg_audit_payment AFTER INSERT OR UPDATE OR DELETE ON payment.payment
    FOR EACH ROW EXECUTE FUNCTION audit.fn_audit();
CREATE TRIGGER trg_audit_config  AFTER INSERT OR UPDATE OR DELETE ON core.app_config
    FOR EACH ROW EXECUTE FUNCTION audit.fn_audit();

-- 5) Expiración de reservas pendientes por TTL (RF-016, P-03)
CREATE OR REPLACE FUNCTION booking.expirar_pendientes() RETURNS integer AS $$
DECLARE v_ttl interval; v_n integer;
BEGIN
    v_ttl := COALESCE((SELECT (valor->>'ttl_reserva_min')::int || ' minutes'
                       FROM core.app_config WHERE clave = 'ttl_reserva_min'), '15 minutes'::interval);
    WITH exp AS (
        UPDATE booking.booking
        SET estado = 'expirada', updated_at = now()
        WHERE estado = 'pendiente' AND created_at < now() - v_ttl
        RETURNING id
    )
    SELECT count(*) INTO v_n FROM exp;
    RETURN v_n;
END;
$$ LANGUAGE plpgsql;

COMMIT;

-- ===================== DOWN =====================
-- BEGIN;
-- DROP FUNCTION IF EXISTS booking.expirar_pendientes();
-- DROP TRIGGER IF EXISTS trg_audit_config ON core.app_config;
-- DROP TRIGGER IF EXISTS trg_audit_payment ON payment.payment;
-- DROP TRIGGER IF EXISTS trg_audit_booking ON booking.booking;
-- DROP TRIGGER IF EXISTS trg_audit_user ON auth.app_user;
-- DROP FUNCTION IF EXISTS audit.fn_audit();
-- DROP TRIGGER IF EXISTS trg_resource_state_on_booking ON booking.booking;
-- DROP FUNCTION IF EXISTS booking.fn_sync_trabajadora_estado();
-- DROP TRIGGER IF EXISTS trg_booking_confirm_requires_payment ON booking.booking;
-- DROP FUNCTION IF EXISTS booking.fn_confirm_requires_payment();
-- DROP TABLE IF EXISTS audit.audit_log;
-- DROP SCHEMA IF EXISTS audit;
-- COMMIT;

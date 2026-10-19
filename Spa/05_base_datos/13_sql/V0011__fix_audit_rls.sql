-- V0011__fix_audit_rls.sql
-- Spa Cielo — correcciones detectadas en el Paso 19 (pruebas).
--   DEF-01: la cadena prev_hash de audit_log no se calculaba (siempre NULL).
--   DRIFT-02: faltaban triggers de auditoria en 5 tablas criticas.
--   DEF-02: las politicas RLS lanzan 22P02 si app.branch_id esta vacio.
-- Motivo: RF-015, RNF-010/031. Forward + Rollback.

-- ===================== UP =====================
BEGIN;

-- ---------- DEF-01: cadena de hash funcional + DRIFT-02 ----------
CREATE OR REPLACE FUNCTION audit.fn_audit() RETURNS trigger AS $$
DECLARE
    v_prev_hash text;
    v_prev_json jsonb;
BEGIN
    SELECT a.prev_hash, to_jsonb(a)
      INTO v_prev_hash, v_prev_json
    FROM audit.audit_log a
    ORDER BY a.id DESC
    LIMIT 1;

    INSERT INTO audit.audit_log (actor_user_id, accion, entidad, entidad_id,
                                 datos_previos, datos_nuevos, correlation_id, prev_hash)
    VALUES (
        NULLIF(current_setting('app.user_id', true), '')::uuid,
        TG_OP, TG_TABLE_NAME,
        COALESCE((to_jsonb(NEW)->>'id')::uuid, (to_jsonb(OLD)->>'id')::uuid),
        CASE WHEN TG_OP IN ('UPDATE','DELETE') THEN to_jsonb(OLD) END,
        CASE WHEN TG_OP IN ('INSERT','UPDATE') THEN to_jsonb(NEW) END,
        current_setting('app.correlation_id', true),
        encode(digest(COALESCE(v_prev_hash, 'spa-cielo-genesis')
                      || COALESCE(v_prev_json::text, ''), 'sha256'), 'hex')
    );
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

-- Auditoria en las tablas criticas que faltaban (DRIFT-02)
CREATE TRIGGER trg_audit_client    AFTER INSERT OR UPDATE OR DELETE ON catalog.client
    FOR EACH ROW EXECUTE FUNCTION audit.fn_audit();
CREATE TRIGGER trg_audit_service   AFTER INSERT OR UPDATE OR DELETE ON catalog.service
    FOR EACH ROW EXECUTE FUNCTION audit.fn_audit();
CREATE TRIGGER trg_audit_resource  AFTER INSERT OR UPDATE OR DELETE ON catalog.resource
    FOR EACH ROW EXECUTE FUNCTION audit.fn_audit();
CREATE TRIGGER trg_audit_refund    AFTER INSERT OR UPDATE OR DELETE ON payment.refund
    FOR EACH ROW EXECUTE FUNCTION audit.fn_audit();
CREATE TRIGGER trg_audit_promotion AFTER INSERT OR UPDATE OR DELETE ON catalog.promotion
    FOR EACH ROW EXECUTE FUNCTION audit.fn_audit();

-- ---------- DEF-02: RLS robusta ante contexto vacio/ausente ----------
DROP POLICY IF EXISTS p_branch_self ON core.branch;
CREATE POLICY p_branch_self ON core.branch
    USING (id = NULLIF(current_setting('app.branch_id', true), '')::uuid);

DROP POLICY IF EXISTS p_user ON auth.app_user;
CREATE POLICY p_user ON auth.app_user
    USING (branch_id = NULLIF(current_setting('app.branch_id', true), '')::uuid);

DROP POLICY IF EXISTS p_client ON catalog.client;
CREATE POLICY p_client ON catalog.client
    USING (branch_id = NULLIF(current_setting('app.branch_id', true), '')::uuid);

DROP POLICY IF EXISTS p_service ON catalog.service;
CREATE POLICY p_service ON catalog.service
    USING (branch_id = NULLIF(current_setting('app.branch_id', true), '')::uuid);

DROP POLICY IF EXISTS p_resource ON catalog.resource;
CREATE POLICY p_resource ON catalog.resource
    USING (branch_id = NULLIF(current_setting('app.branch_id', true), '')::uuid);

DROP POLICY IF EXISTS p_promotion ON catalog.promotion;
CREATE POLICY p_promotion ON catalog.promotion
    USING (branch_id = NULLIF(current_setting('app.branch_id', true), '')::uuid);

DROP POLICY IF EXISTS p_booking ON booking.booking;
CREATE POLICY p_booking ON booking.booking
    USING (branch_id = NULLIF(current_setting('app.branch_id', true), '')::uuid);

DROP POLICY IF EXISTS p_payment ON payment.payment;
CREATE POLICY p_payment ON payment.payment
    USING (EXISTS (SELECT 1 FROM booking.booking b
                   WHERE b.id = payment.payment.booking_id
                     AND b.branch_id = NULLIF(current_setting('app.branch_id', true), '')::uuid));

DROP POLICY IF EXISTS p_refund ON payment.refund;
CREATE POLICY p_refund ON payment.refund
    USING (EXISTS (SELECT 1 FROM payment.payment p
                   JOIN booking.booking b ON b.id = p.booking_id
                   WHERE p.id = payment.refund.payment_id
                     AND b.branch_id = NULLIF(current_setting('app.branch_id', true), '')::uuid));

COMMIT;

-- ===================== DOWN =====================
-- BEGIN;
-- -- Restaurar politicas sin NULLIF
-- DROP POLICY IF EXISTS p_refund ON payment.refund;   (y el resto, revirtiendo a la forma de V0009)
-- DROP TRIGGER IF EXISTS trg_audit_promotion ON catalog.promotion;
-- DROP TRIGGER IF EXISTS trg_audit_refund ON payment.refund;
-- DROP TRIGGER IF EXISTS trg_audit_resource ON catalog.resource;
-- DROP TRIGGER IF EXISTS trg_audit_service ON catalog.service;
-- DROP TRIGGER IF EXISTS trg_audit_client ON catalog.client;
-- -- Restaurar audit.fn_audit() a la version de V0007 (sin hash encadenado)
-- COMMIT;

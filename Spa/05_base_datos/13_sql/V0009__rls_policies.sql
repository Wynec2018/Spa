-- V0009__rls_policies.sql
-- Spa Cielo — Row Level Security por sucursal.
-- Motivo: RNF-010/031, D-05.
-- Forward + Rollback.

-- ===================== UP =====================
BEGIN;

-- Tablas con branch_id directo
ALTER TABLE core.branch         ENABLE ROW LEVEL SECURITY;
ALTER TABLE auth.app_user       ENABLE ROW LEVEL SECURITY;
ALTER TABLE catalog.client      ENABLE ROW LEVEL SECURITY;
ALTER TABLE catalog.service     ENABLE ROW LEVEL SECURITY;
ALTER TABLE catalog.resource    ENABLE ROW LEVEL SECURITY;
ALTER TABLE catalog.promotion   ENABLE ROW LEVEL SECURITY;
ALTER TABLE booking.booking     ENABLE ROW LEVEL SECURITY;

CREATE POLICY p_branch_self ON core.branch
    USING (id = current_setting('app.branch_id', true)::uuid);
CREATE POLICY p_user ON auth.app_user
    USING (branch_id = current_setting('app.branch_id', true)::uuid);
CREATE POLICY p_client ON catalog.client
    USING (branch_id = current_setting('app.branch_id', true)::uuid);
CREATE POLICY p_service ON catalog.service
    USING (branch_id = current_setting('app.branch_id', true)::uuid);
CREATE POLICY p_resource ON catalog.resource
    USING (branch_id = current_setting('app.branch_id', true)::uuid);
CREATE POLICY p_promotion ON catalog.promotion
    USING (branch_id = current_setting('app.branch_id', true)::uuid);
CREATE POLICY p_booking ON booking.booking
    USING (branch_id = current_setting('app.branch_id', true)::uuid);

-- Tablas sin branch_id: se aísla vía booking
ALTER TABLE payment.payment ENABLE ROW LEVEL SECURITY;
CREATE POLICY p_payment ON payment.payment
    USING (EXISTS (SELECT 1 FROM booking.booking b
                   WHERE b.id = payment.payment.booking_id
                     AND b.branch_id = current_setting('app.branch_id', true)::uuid));

ALTER TABLE payment.refund ENABLE ROW LEVEL SECURITY;
CREATE POLICY p_refund ON payment.refund
    USING (EXISTS (SELECT 1 FROM payment.payment p
                   JOIN booking.booking b ON b.id = p.booking_id
                   WHERE p.id = payment.refund.payment_id
                     AND b.branch_id = current_setting('app.branch_id', true)::uuid));

COMMIT;

-- ===================== DOWN =====================
-- BEGIN;
-- DROP POLICY IF EXISTS p_refund ON payment.refund;
-- ALTER TABLE payment.refund DISABLE ROW LEVEL SECURITY;
-- DROP POLICY IF EXISTS p_payment ON payment.payment;
-- ALTER TABLE payment.payment DISABLE ROW LEVEL SECURITY;
-- DROP POLICY IF EXISTS p_booking ON booking.booking;
-- ... (DROP POLICY + DISABLE para cada tabla)
-- COMMIT;

-- V0010__grants_seed.sql
-- Spa Cielo — roles, privilegios, seed de parámetros y jobs.
-- Motivo: RF-016, RNF-031/034. Sin credenciales en este archivo.
-- Forward + Rollback.

-- ===================== UP =====================
BEGIN;

-- Roles (las contraseñas se asignan fuera del repositorio, vía .env/Secrets)
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'spa_app') THEN
        CREATE ROLE spa_app LOGIN;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'spa_readonly') THEN
        CREATE ROLE spa_readonly LOGIN;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'spa_cron') THEN
        CREATE ROLE spa_cron LOGIN;
    END IF;
END $$;

GRANT USAGE ON SCHEMA core, auth, catalog, booking, payment, support, audit TO spa_app;
GRANT USAGE ON SCHEMA core, auth, catalog, booking, payment, support, audit TO spa_readonly;

-- App: lectura/escritura de dominio; sin DELETE en dominio; sin UPDATE/DELETE en auditoría
GRANT SELECT, INSERT, UPDATE ON ALL TABLES IN SCHEMA core    TO spa_app;
GRANT SELECT, INSERT, UPDATE ON ALL TABLES IN SCHEMA auth    TO spa_app;
GRANT SELECT, INSERT, UPDATE ON ALL TABLES IN SCHEMA catalog TO spa_app;
GRANT SELECT, INSERT, UPDATE ON ALL TABLES IN SCHEMA booking TO spa_app;
GRANT SELECT, INSERT, UPDATE ON ALL TABLES IN SCHEMA payment TO spa_app;
GRANT SELECT, INSERT, UPDATE ON ALL TABLES IN SCHEMA support TO spa_app;
GRANT INSERT, SELECT         ON ALL TABLES IN SCHEMA audit   TO spa_app;
REVOKE UPDATE, DELETE ON audit.audit_log FROM spa_app;

-- Reportes: solo lectura
GRANT SELECT ON ALL TABLES IN SCHEMA core, auth, catalog, booking, payment, support TO spa_readonly;

-- Cron: solo la función de expiración
GRANT UPDATE (estado) ON booking.booking TO spa_cron;
GRANT EXECUTE ON FUNCTION booking.expirar_pendientes() TO spa_cron;

-- Seed de parámetros operativos (RF-016). Sin secretos.
INSERT INTO core.app_config (clave, valor) VALUES
    ('numero_camas_max',        '6'::jsonb),
    ('numero_trabajadoras_max', '10'::jsonb),
    ('ttl_reserva_min',         '15'::jsonb),
    ('moneda',                  '"BOB"'::jsonb),
    ('capacidad_tx_hora',       '50'::jsonb)
ON CONFLICT (clave) DO NOTHING;

-- Job de expiración (cada minuto)
SELECT cron.schedule('expira_reservas', '* * * * *',
                     $$ SELECT booking.expirar_pendientes(); $$);

COMMIT;

-- ===================== DOWN =====================
-- BEGIN;
-- SELECT cron.unschedule('expira_reservas');
-- DELETE FROM core.app_config WHERE clave IN
--   ('numero_camas_max','numero_trabajadoras_max','ttl_reserva_min','moneda','capacidad_tx_hora');
-- REVOKE ALL ON ALL TABLES IN SCHEMA core, auth, catalog, booking, payment, support, audit FROM spa_app;
-- REVOKE ALL ON ALL TABLES IN SCHEMA core, auth, catalog, booking, payment, support FROM spa_readonly;
-- REVOKE ALL ON FUNCTION booking.expirar_pendientes() FROM spa_cron;
-- DROP ROLE IF EXISTS spa_cron;
-- DROP ROLE IF EXISTS spa_readonly;
-- DROP ROLE IF EXISTS spa_app;
-- COMMIT;

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

-- App: lectura/escritura de dominio (excluye DELETE); auditoría solo INSERT/SELECT.
-- Se itera sobre pg_class porque los GRANT ... ON ALL TABLES fallan sobre
-- vistas materializadas (solo admiten SELECT). Ajuste de portabilidad documentado.
DO $$
DECLARE r record;
BEGIN
    FOR r IN
        SELECT n.nspname, c.relname, c.relkind
        FROM pg_class c
        JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname IN ('core','auth','catalog','booking','payment','support','audit')
          AND c.relkind IN ('r','p','m','v')
    LOOP
        EXECUTE format('GRANT SELECT ON %I.%I TO spa_app, spa_readonly', r.nspname, r.relname);
        IF r.relkind IN ('r','p') THEN
            EXECUTE format('GRANT INSERT, UPDATE ON %I.%I TO spa_app', r.nspname, r.relname);
        END IF;
    END LOOP;
END $$;

REVOKE UPDATE, DELETE ON audit.audit_log FROM spa_app;

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

-- Job de expiración (cada minuto). Si pg_cron no está instalado, se omite y
-- la aplicación (Laravel scheduler, D-09) programa booking.expirar_pendientes().
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pg_cron') THEN
        PERFORM cron.schedule('expira_reservas', '* * * * *',
                              'SELECT booking.expirar_pendientes();');
    ELSE
        RAISE NOTICE 'pg_cron ausente: programar la expiracion TTL desde la aplicacion (D-09).';
    END IF;
END $$;

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

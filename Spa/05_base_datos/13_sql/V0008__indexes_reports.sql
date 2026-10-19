-- V0008__indexes_reports.sql
-- Spa Cielo — índices JSON y vistas materializadas de reportes.
-- Motivo: RF-014/016/017, RNF-051.
-- Forward + Rollback.

-- ===================== UP =====================
BEGIN;

CREATE INDEX gin_app_config_valor ON core.app_config USING gin (valor);

-- Reportes (refresh 5 min, P-09). Latencia objetivo < 2 s.
CREATE MATERIALIZED VIEW booking.mv_ocupacion_diaria AS
SELECT b.branch_id,
       date_trunc('day', b.inicio)::date AS dia,
       b.cama_id,
       b.trabajadora_id,
       count(*)                          AS reservas,
       sum(EXTRACT(EPOCH FROM (b.fin - b.inicio))/60)::numeric(10,2) AS minutos_ocupados
FROM booking.booking b
WHERE b.estado IN ('confirmada','atendida')
GROUP BY b.branch_id, date_trunc('day', b.inicio)::date, b.cama_id, b.trabajadora_id;

CREATE UNIQUE INDEX ux_mv_ocupacion ON booking.mv_ocupacion_diaria (branch_id, dia, cama_id, trabajadora_id);

CREATE MATERIALIZED VIEW payment.mv_ingresos_periodo AS
SELECT b.branch_id,
       date_trunc('day', p.created_at)::date AS dia,
       b.service_id,
       sum(p.monto) FILTER (WHERE p.estado = 'verificado') AS ingresos_verificados,
       count(*) FILTER (WHERE p.estado = 'verificado')     AS pagos_verificados
FROM payment.payment p
JOIN booking.booking b ON b.id = p.booking_id
GROUP BY b.branch_id, date_trunc('day', p.created_at)::date, b.service_id;

CREATE UNIQUE INDEX ux_mv_ingresos ON payment.mv_ingresos_periodo (branch_id, dia, service_id);

CREATE MATERIALIZED VIEW booking.mv_rendimiento_trabajadora AS
SELECT b.branch_id,
       b.trabajadora_id,
       date_trunc('month', b.inicio)::date AS mes,
       count(*) FILTER (WHERE b.estado = 'atendida') AS atendidas
FROM booking.booking b
GROUP BY b.branch_id, b.trabajadora_id, date_trunc('month', b.inicio)::date;

CREATE UNIQUE INDEX ux_mv_rendimiento ON booking.mv_rendimiento_trabajadora (branch_id, trabajadora_id, mes);

COMMIT;

-- ===================== DOWN =====================
-- BEGIN;
-- DROP MATERIALIZED VIEW IF EXISTS booking.mv_rendimiento_trabajadora;
-- DROP MATERIALIZED VIEW IF EXISTS payment.mv_ingresos_periodo;
-- DROP MATERIALIZED VIEW IF EXISTS booking.mv_ocupacion_diaria;
-- DROP INDEX IF EXISTS core.gin_app_config_valor;
-- COMMIT;

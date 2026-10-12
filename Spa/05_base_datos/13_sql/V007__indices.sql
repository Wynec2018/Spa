-- V007__indices
-- Índices adicionales tras revisión Paso 11 (G1: gap reportado; resto ya en V001–V006).
-- Rollback: ALTER TABLE ... DROP INDEX (seguro, sin pérdida de datos).
-- Ventana: aditiva, segura en caliente (MySQL 8 InnoDB en línea para ADD INDEX).
-- Índice condicionado (G2) NO creado: TTL carrito aún no definido (pendiente DB-Pxx abierta).
SET NAMES utf8mb4;

-- G1: consultas por sucursal + rango de fechas (dashboard, reportes de ventas).
ALTER TABLE sales_orden_venta
  ADD INDEX idx_sales_orden_sucursal_fecha (sucursal_id, creado_en);

-- G2 (G2 pendiente de TTL carrito): índice parcial no nativo en MySQL 8;
-- no se crea hasta definir ventana de purga. Upgrade path: tabla particionada o job+índice.

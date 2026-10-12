-- V009__roles_grants
-- Roles de MySQL 8 + GRANT (sección 7_seguridad). No crea usuarios ni contraseñas
-- (secrets en .env, bootstraps los ejecuta el DBA/DevOps fuera del repo).
-- Rollback: REVOKE + DROP ROLE (seguro, sin pérdida de datos).
-- Ventana: aditiva, segura en caliente.
-- Orden de roles: app_read ⊂ app_rw ⊂ migraciones; backup y dba separados.
-- SEC-01: MySQL no permite REVOKE por tabla desde GRANT de esquema (ERROR 1147);
-- por eso app_rw recibe SELECT+INSERT a nivel esquema y UPDATE solo en las 34
-- tablas de negocio (excluye core_auditoria e inv_movimiento_inventario).
SET NAMES utf8mb4;

-- Limpieza de grants residuales (esquema de ejemplo / corridas previas fallidas).
-- Idempotente: si el grant no existe, MySQL error 1141 — se ignora a propósito.
-- (El servidor detiene el script ante ERROR; por eso se emite cada REVOKE con
--  \Gerror continuo y se comprueba abajo. Para bootstrap de BD nueva no aplica.)
SET GLOBAL sql_notes = 0;

-- Roles (sin contraseña aquí: CREATE USER con secreto → fuera del repo, gestión DBA).
CREATE ROLE IF NOT EXISTS app_read;
CREATE ROLE IF NOT EXISTS app_rw;
CREATE ROLE IF NOT EXISTS migraciones;
CREATE ROLE IF NOT EXISTS backup;
CREATE ROLE IF NOT EXISTS dba;

-- app_read: SELECT en todas las tablas del esquema.
GRANT SELECT ON `sistema`.* TO app_read;

-- app_rw (SEC-01, dos capas):
--   capa 1a: SELECT/INSERT en todo el esquema (incl. append-only);
--   capa 1b: UPDATE solo en tablas de negocio (34); sin UPDATE en
--            core_auditoria ni inv_movimiento_inventario;
--   capa 2: triggers SIGNAL en V008.
-- DELETE denegado: carrito web se purga con grants futuros cuando exista TTL (DB abierta).
GRANT SELECT, INSERT ON `sistema`.* TO app_rw;
GRANT UPDATE ON `sistema`.auth_permiso TO app_rw;
GRANT UPDATE ON `sistema`.auth_rol TO app_rw;
GRANT UPDATE ON `sistema`.auth_rol_permiso TO app_rw;
GRANT UPDATE ON `sistema`.auth_token_blacklist TO app_rw;
GRANT UPDATE ON `sistema`.auth_usuario TO app_rw;
GRANT UPDATE ON `sistema`.auth_usuario_rol TO app_rw;
GRANT UPDATE ON `sistema`.cat_categoria TO app_rw;
GRANT UPDATE ON `sistema`.cat_precio TO app_rw;
GRANT UPDATE ON `sistema`.cat_producto TO app_rw;
GRANT UPDATE ON `sistema`.cat_promocion TO app_rw;
GRANT UPDATE ON `sistema`.com_orden_compra TO app_rw;
GRANT UPDATE ON `sistema`.com_orden_compra_item TO app_rw;
GRANT UPDATE ON `sistema`.com_producto_promocion TO app_rw;
GRANT UPDATE ON `sistema`.com_proveedor TO app_rw;
GRANT UPDATE ON `sistema`.com_recepcion TO app_rw;
GRANT UPDATE ON `sistema`.com_recepcion_item TO app_rw;
GRANT UPDATE ON `sistema`.core_caja_pos TO app_rw;
GRANT UPDATE ON `sistema`.core_cliente TO app_rw;
GRANT UPDATE ON `sistema`.core_configuracion TO app_rw;
GRANT UPDATE ON `sistema`.core_idempotency_key TO app_rw;
GRANT UPDATE ON `sistema`.core_sucursal TO app_rw;
GRANT UPDATE ON `sistema`.inv_ajuste_stock TO app_rw;
GRANT UPDATE ON `sistema`.inv_inventario TO app_rw;
GRANT UPDATE ON `sistema`.inv_reserva TO app_rw;
GRANT UPDATE ON `sistema`.inv_transferencia TO app_rw;
GRANT UPDATE ON `sistema`.inv_transferencia_item TO app_rw;
GRANT UPDATE ON `sistema`.pay_outbox_evento TO app_rw;
GRANT UPDATE ON `sistema`.pay_transaccion_pago TO app_rw;
GRANT UPDATE ON `sistema`.sales_carrito_web TO app_rw;
GRANT UPDATE ON `sistema`.sales_carrito_web_item TO app_rw;
GRANT UPDATE ON `sistema`.sales_devolucion TO app_rw;
GRANT UPDATE ON `sistema`.sales_devolucion_item TO app_rw;
GRANT UPDATE ON `sistema`.sales_orden_venta TO app_rw;
GRANT UPDATE ON `sistema`.sales_orden_venta_item TO app_rw;

-- SEC-03 (MEDIUM): PITR en MySQL 8.4 — REPLICATION CLIENT cubre binlog
-- (BINLOG MONITOR es privilegio MariaDB, no existe en MySQL 8.x).
GRANT REPLICATION CLIENT ON *.* TO backup;

-- migraciones: DDL + datos (solo en ventanas de migración; revocar en runtime si se desea).
GRANT ALL PRIVILEGES ON `sistema`.* TO migraciones;

-- backup: SELECT (mysqldump) + RELOAD/PROCESS/BACKUP_ADMIN según servidor.
GRANT SELECT, RELOAD, PROCESS, BACKUP_ADMIN ON *.* TO backup;

-- dba: control total (uso humano / operación).
GRANT ALL PRIVILEGES ON *.* TO dba WITH GRANT OPTION;

FLUSH PRIVILEGES;

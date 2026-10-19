-- V008__triggers
-- Triggers append-only: SIGNAL en UPDATE/DELETE sobre core_auditoria e inv_movimiento_inventario.
-- Rollback: DROP TRIGGER (seguro, sin pérdida de datos).
-- Ventana: aditiva, segura en caliente.
-- Nota: los 4 campos de auditoría (user_create/user_update/user_created_at/user_update_at)
-- se escriben desde la capa de aplicación (app_rw con GRANT INSERT en V009); no se generan aquí.
SET NAMES utf8mb4;

DELIMITER $$

CREATE TRIGGER trg_core_auditoria_no_update
BEFORE UPDATE ON core_auditoria
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'core_auditoria es append-only: UPDATE no permitido';
END$$

CREATE TRIGGER trg_core_auditoria_no_delete
BEFORE DELETE ON core_auditoria
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'core_auditoria es append-only: DELETE no permitido';
END$$

CREATE TRIGGER trg_inv_movimiento_no_update
BEFORE UPDATE ON inv_movimiento_inventario
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'inv_movimiento_inventario es append-only: UPDATE no permitido';
END$$

CREATE TRIGGER trg_inv_movimiento_no_delete
BEFORE DELETE ON inv_movimiento_inventario
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'inv_movimiento_inventario es append-only: DELETE no permitido';
END$$

DELIMITER ;

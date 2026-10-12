-- V005__init_inventario
-- Módulo: inventario. inv_reserva al FINAL (FK → sales_orden_venta de V004; nota modelo_fisico §5).
-- DB-P01 CERRADO: reserva web descuenta stock_available al crear (bloqueo SÍ).
-- Rollback: DROP vacío (bootstrap); con datos → restore.
-- Ventana: segura (requiere V001 core_*, V002 cat_*, V004 sales_*).
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE inv_inventario (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  producto_id BIGINT UNSIGNED NOT NULL,
  sucursal_id BIGINT UNSIGNED NOT NULL,
  stock_available INT UNSIGNED NOT NULL DEFAULT 0,
  stock_reserved INT UNSIGNED NOT NULL DEFAULT 0,
  stock_sold INT UNSIGNED NOT NULL DEFAULT 0,
  version INT UNSIGNED NOT NULL DEFAULT 0,
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_inv_inventario_producto_sucursal (producto_id, sucursal_id),
  KEY idx_inv_inventario_sucursal (sucursal_id),
  CONSTRAINT fk_inv_inventario_producto FOREIGN KEY (producto_id) REFERENCES cat_producto (id) ON DELETE RESTRICT,
  CONSTRAINT fk_inv_inventario_sucursal FOREIGN KEY (sucursal_id) REFERENCES core_sucursal (id) ON DELETE RESTRICT,
  CONSTRAINT chk_inv_inventario_available CHECK (stock_available >= 0),
  CONSTRAINT chk_inv_inventario_reserved CHECK (stock_reserved >= 0),
  CONSTRAINT chk_inv_inventario_sold CHECK (stock_sold >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inv_movimiento_inventario (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  inventario_id BIGINT UNSIGNED NOT NULL,
  producto_id BIGINT UNSIGNED NOT NULL,
  sucursal_id BIGINT UNSIGNED NOT NULL,
  tipo ENUM('entrada','salida','reserva','liberacion','ajuste','devolucion','transferencia_salida','transferencia_entrada','recepcion') NOT NULL,
  cantidad INT NOT NULL,
  referencia_tipo VARCHAR(50) NULL,
  referencia_id BIGINT UNSIGNED NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  motivo TEXT NULL,
  fecha DATETIME NOT NULL,
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_inv_mov_inventario (inventario_id, fecha),
  KEY idx_inv_mov_producto_fecha (producto_id, fecha),
  KEY idx_inv_mov_sucursal_fecha (sucursal_id, fecha),
  KEY idx_inv_mov_usuario (usuario_id),
  CONSTRAINT fk_inv_mov_inventario FOREIGN KEY (inventario_id) REFERENCES inv_inventario (id) ON DELETE RESTRICT,
  CONSTRAINT fk_inv_mov_producto FOREIGN KEY (producto_id) REFERENCES cat_producto (id) ON DELETE RESTRICT,
  CONSTRAINT fk_inv_mov_sucursal FOREIGN KEY (sucursal_id) REFERENCES core_sucursal (id) ON DELETE RESTRICT,
  CONSTRAINT fk_inv_mov_usuario FOREIGN KEY (usuario_id) REFERENCES auth_usuario (id) ON DELETE RESTRICT,
  CONSTRAINT chk_inv_mov_cantidad CHECK (cantidad <> 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inv_ajuste_stock (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  inventario_id BIGINT UNSIGNED NOT NULL,
  cantidad INT NOT NULL,
  motivo TEXT NOT NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  fecha DATETIME NOT NULL,
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_inv_ajuste_inventario (inventario_id),
  KEY idx_inv_ajuste_usuario (usuario_id),
  CONSTRAINT fk_inv_ajuste_inventario FOREIGN KEY (inventario_id) REFERENCES inv_inventario (id) ON DELETE RESTRICT,
  CONSTRAINT fk_inv_ajuste_usuario FOREIGN KEY (usuario_id) REFERENCES auth_usuario (id) ON DELETE RESTRICT,
  CONSTRAINT chk_inv_ajuste_cantidad CHECK (cantidad <> 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inv_transferencia (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sucursal_origen_id BIGINT UNSIGNED NOT NULL,
  sucursal_destino_id BIGINT UNSIGNED NOT NULL,
  estado ENUM('draft','in_transit','received','cancelled') NOT NULL DEFAULT 'draft',
  usuario_id BIGINT UNSIGNED NOT NULL,
  fecha_salida DATETIME NULL,
  fecha_recepcion DATETIME NULL,
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_inv_trans_origen (sucursal_origen_id),
  KEY idx_inv_trans_destino (sucursal_destino_id),
  KEY idx_inv_trans_usuario (usuario_id),
  CONSTRAINT fk_inv_trans_origen FOREIGN KEY (sucursal_origen_id) REFERENCES core_sucursal (id) ON DELETE RESTRICT,
  CONSTRAINT fk_inv_trans_destino FOREIGN KEY (sucursal_destino_id) REFERENCES core_sucursal (id) ON DELETE RESTRICT,
  CONSTRAINT fk_inv_trans_usuario FOREIGN KEY (usuario_id) REFERENCES auth_usuario (id) ON DELETE RESTRICT,
  CONSTRAINT chk_inv_trans_distinta CHECK (sucursal_origen_id <> sucursal_destino_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inv_transferencia_item (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  transferencia_id BIGINT UNSIGNED NOT NULL,
  producto_id BIGINT UNSIGNED NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_inv_trans_item_trans_producto (transferencia_id, producto_id),
  KEY idx_inv_trans_item_producto (producto_id),
  CONSTRAINT fk_inv_trans_item_trans FOREIGN KEY (transferencia_id) REFERENCES inv_transferencia (id) ON DELETE RESTRICT,
  CONSTRAINT fk_inv_trans_item_producto FOREIGN KEY (producto_id) REFERENCES cat_producto (id) ON DELETE RESTRICT,
  CONSTRAINT chk_inv_trans_item_cantidad CHECK (cantidad > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Al final: FK → sales_orden_venta (V004). DB-P02 CERRADO: expira_en + job SKIP LOCKED, TTL 15 min.
CREATE TABLE inv_reserva (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  orden_id BIGINT UNSIGNED NOT NULL,
  inventario_id BIGINT UNSIGNED NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  estado ENUM('pending','confirmed','expired','cancelled') NOT NULL DEFAULT 'pending',
  expira_en DATETIME NOT NULL,
  creado_en DATETIME NOT NULL,
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_inv_reserva_orden (orden_id),
  KEY idx_inv_reserva_inventario (inventario_id),
  KEY idx_inv_reserva_estado_expira (estado, expira_en),
  CONSTRAINT fk_inv_reserva_orden FOREIGN KEY (orden_id) REFERENCES sales_orden_venta (id) ON DELETE RESTRICT,
  CONSTRAINT fk_inv_reserva_inventario FOREIGN KEY (inventario_id) REFERENCES inv_inventario (id) ON DELETE RESTRICT,
  CONSTRAINT chk_inv_reserva_cantidad CHECK (cantidad > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

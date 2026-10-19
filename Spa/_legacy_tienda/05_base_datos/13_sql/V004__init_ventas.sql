-- V004__init_ventas
-- Módulo: ventas. Incluye carrito web (RF-060, persistencia en BD confirmada 2026-09-22).
-- Rollback: DROP vacío (bootstrap); con datos → restore.
-- Ventana: segura (requiere V001 core_*, V002 cat_*).
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE sales_orden_venta (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  numero VARCHAR(32) NOT NULL,
  canal ENUM('pos','web') NOT NULL,
  cliente_id BIGINT UNSIGNED NULL,
  sucursal_id BIGINT UNSIGNED NOT NULL,
  estado ENUM('pending','pagada','confirmada','cancelada','devuelta') NOT NULL DEFAULT 'pending',
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  idempotency_key VARCHAR(64) NULL,
  fulfillment_type ENUM('entrega','retiro') NULL,
  fulfillment_direccion VARCHAR(255) NULL,
  fecha_entrega DATETIME NULL,
  creado_en DATETIME NOT NULL,
  confirmado_en DATETIME NULL,
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sales_orden_numero (numero),
  UNIQUE KEY uq_sales_orden_idempotency (idempotency_key),
  KEY idx_sales_orden_cliente (cliente_id),
  KEY idx_sales_orden_sucursal (sucursal_id),
  KEY idx_sales_orden_estado_fecha (estado, creado_en),
  CONSTRAINT fk_sales_orden_cliente FOREIGN KEY (cliente_id) REFERENCES core_cliente (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sales_orden_sucursal FOREIGN KEY (sucursal_id) REFERENCES core_sucursal (id) ON DELETE RESTRICT,
  CONSTRAINT chk_sales_orden_total CHECK (total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales_orden_venta_item (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  orden_id BIGINT UNSIGNED NOT NULL,
  producto_id BIGINT UNSIGNED NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  precio_unitario DECIMAL(12,2) NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL,
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sales_item_orden_producto (orden_id, producto_id),
  KEY idx_sales_item_producto (producto_id),
  CONSTRAINT fk_sales_item_orden FOREIGN KEY (orden_id) REFERENCES sales_orden_venta (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sales_item_producto FOREIGN KEY (producto_id) REFERENCES cat_producto (id) ON DELETE RESTRICT,
  CONSTRAINT chk_sales_item_cantidad CHECK (cantidad > 0),
  CONSTRAINT chk_sales_item_precio CHECK (precio_unitario >= 0),
  CONSTRAINT chk_sales_item_subtotal CHECK (subtotal >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales_carrito_web (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cliente_id BIGINT UNSIGNED NULL,
  sesion_key VARCHAR(64) NOT NULL,
  actualizado_en DATETIME NOT NULL,
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sales_carrito_sesion (sesion_key),
  KEY idx_sales_carrito_cliente (cliente_id),
  CONSTRAINT fk_sales_carrito_cliente FOREIGN KEY (cliente_id) REFERENCES core_cliente (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales_carrito_web_item (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  carrito_id BIGINT UNSIGNED NOT NULL,
  producto_id BIGINT UNSIGNED NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sales_carrito_item_carrito_producto (carrito_id, producto_id),
  KEY idx_sales_carrito_item_producto (producto_id),
  CONSTRAINT fk_sales_carrito_item_carrito FOREIGN KEY (carrito_id) REFERENCES sales_carrito_web (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sales_carrito_item_producto FOREIGN KEY (producto_id) REFERENCES cat_producto (id) ON DELETE RESTRICT,
  CONSTRAINT chk_sales_carrito_item_cantidad CHECK (cantidad > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales_devolucion (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  orden_original_id BIGINT UNSIGNED NOT NULL,
  cliente_id BIGINT UNSIGNED NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  estado ENUM('draft','aprobada','rechazada','completada') NOT NULL DEFAULT 'draft',
  motivo TEXT NULL,
  monto DECIMAL(12,2) NOT NULL DEFAULT 0,
  fecha DATETIME NOT NULL,
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_sales_dev_orden (orden_original_id),
  KEY idx_sales_dev_cliente (cliente_id),
  KEY idx_sales_dev_usuario (usuario_id),
  CONSTRAINT fk_sales_dev_orden FOREIGN KEY (orden_original_id) REFERENCES sales_orden_venta (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sales_dev_cliente FOREIGN KEY (cliente_id) REFERENCES core_cliente (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sales_dev_usuario FOREIGN KEY (usuario_id) REFERENCES auth_usuario (id) ON DELETE RESTRICT,
  CONSTRAINT chk_sales_dev_monto CHECK (monto >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales_devolucion_item (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  devolucion_id BIGINT UNSIGNED NOT NULL,
  producto_id BIGINT UNSIGNED NOT NULL,
  producto_orden_item_id BIGINT UNSIGNED NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  monto DECIMAL(12,2) NOT NULL,
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sales_dev_item_dev_producto (devolucion_id, producto_id),
  KEY idx_sales_dev_item_dev (devolucion_id),
  KEY idx_sales_dev_item_producto (producto_id),
  KEY idx_sales_dev_item_orden_item (producto_orden_item_id),
  CONSTRAINT fk_sales_dev_item_dev FOREIGN KEY (devolucion_id) REFERENCES sales_devolucion (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sales_dev_item_producto FOREIGN KEY (producto_id) REFERENCES cat_producto (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sales_dev_item_orden_item FOREIGN KEY (producto_orden_item_id) REFERENCES sales_orden_venta_item (id) ON DELETE RESTRICT,
  CONSTRAINT chk_sales_dev_item_cantidad CHECK (cantidad > 0),
  CONSTRAINT chk_sales_dev_item_monto CHECK (monto >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

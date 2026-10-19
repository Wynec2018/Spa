-- V003__init_compras
-- Módulo: compras + asociación producto-promoción.
-- Rollback: DROP vacío (bootstrap); con datos → restore.
-- Ventana: segura (requiere V001 auth_usuario, V002 cat_*).
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE com_producto_promocion (
  producto_id BIGINT UNSIGNED NOT NULL,
  promocion_id BIGINT UNSIGNED NOT NULL,
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (producto_id, promocion_id),
  KEY idx_com_producto_promocion_promo (promocion_id),
  CONSTRAINT fk_com_producto_promocion_producto FOREIGN KEY (producto_id) REFERENCES cat_producto (id) ON DELETE RESTRICT,
  CONSTRAINT fk_com_producto_promocion_promo FOREIGN KEY (promocion_id) REFERENCES cat_promocion (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE com_proveedor (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(150) NOT NULL,
  codigo VARCHAR(32) NULL,
  contacto VARCHAR(150) NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_com_proveedor_nombre (nombre),
  UNIQUE KEY uq_com_proveedor_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE com_orden_compra (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  numero VARCHAR(32) NOT NULL,
  proveedor_id BIGINT UNSIGNED NOT NULL,
  estado ENUM('draft','enviada','recibida','cancelada') NOT NULL DEFAULT 'draft',
  fecha_orden DATETIME NOT NULL,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_com_orden_compra_numero (numero),
  KEY idx_com_orden_compra_proveedor (proveedor_id),
  CONSTRAINT fk_com_orden_compra_proveedor FOREIGN KEY (proveedor_id) REFERENCES com_proveedor (id) ON DELETE RESTRICT,
  CONSTRAINT chk_com_orden_compra_total CHECK (total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE com_orden_compra_item (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  orden_id BIGINT UNSIGNED NOT NULL,
  producto_id BIGINT UNSIGNED NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  precio_pactado DECIMAL(12,2) NOT NULL,
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_com_orden_item_orden_producto (orden_id, producto_id),
  KEY idx_com_orden_item_producto (producto_id),
  CONSTRAINT fk_com_orden_item_orden FOREIGN KEY (orden_id) REFERENCES com_orden_compra (id) ON DELETE RESTRICT,
  CONSTRAINT fk_com_orden_item_producto FOREIGN KEY (producto_id) REFERENCES cat_producto (id) ON DELETE RESTRICT,
  CONSTRAINT chk_com_orden_item_cantidad CHECK (cantidad > 0),
  CONSTRAINT chk_com_orden_item_precio CHECK (precio_pactado >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE com_recepcion (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  orden_id BIGINT UNSIGNED NOT NULL,
  estado ENUM('draft','confirmada') NOT NULL DEFAULT 'draft',
  fecha DATETIME NOT NULL,
  usuario_confirma BIGINT UNSIGNED NULL,
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_com_recepcion_orden (orden_id),
  KEY idx_com_recepcion_usuario (usuario_confirma),
  CONSTRAINT fk_com_recepcion_orden FOREIGN KEY (orden_id) REFERENCES com_orden_compra (id) ON DELETE RESTRICT,
  CONSTRAINT fk_com_recepcion_usuario FOREIGN KEY (usuario_confirma) REFERENCES auth_usuario (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE com_recepcion_item (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  recepcion_id BIGINT UNSIGNED NOT NULL,
  producto_id BIGINT UNSIGNED NOT NULL,
  cantidad_recibida INT UNSIGNED NOT NULL,
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_com_recepcion_item_recepcion_producto (recepcion_id, producto_id),
  KEY idx_com_recepcion_item_recepcion (recepcion_id),
  KEY idx_com_recepcion_item_producto (producto_id),
  CONSTRAINT fk_com_recepcion_item_recepcion FOREIGN KEY (recepcion_id) REFERENCES com_recepcion (id) ON DELETE RESTRICT,
  CONSTRAINT fk_com_recepcion_item_producto FOREIGN KEY (producto_id) REFERENCES cat_producto (id) ON DELETE RESTRICT,
  CONSTRAINT chk_com_recepcion_item_cantidad CHECK (cantidad_recibida > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

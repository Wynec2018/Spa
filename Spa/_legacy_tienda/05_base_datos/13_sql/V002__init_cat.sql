-- V002__init_cat
-- Módulo: catálogo. cat_precio por sucursal (DB-P09 cerrado: sucursal_id NOT NULL).
-- Rollback: DROP vacío (bootstrap); con datos → restore.
-- Ventana: segura en BD vacía (tras V001: core_sucursal ya existe).
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE cat_categoria (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  categoria_padre_id BIGINT UNSIGNED NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_cat_categoria_padre (categoria_padre_id),
  CONSTRAINT fk_cat_categoria_padre FOREIGN KEY (categoria_padre_id) REFERENCES cat_categoria (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cat_producto (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sku VARCHAR(64) NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  categoria_id BIGINT UNSIGNED NOT NULL,
  unidad VARCHAR(32) NOT NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cat_producto_sku (sku),
  KEY idx_cat_producto_categoria (categoria_id),
  CONSTRAINT fk_cat_producto_categoria FOREIGN KEY (categoria_id) REFERENCES cat_categoria (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Precio por sucursal (DB-P09 CERRADO 2026-09-22): lookup siempre lleva sucursal_id.
CREATE TABLE cat_precio (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sucursal_id BIGINT UNSIGNED NOT NULL,
  producto_id BIGINT UNSIGNED NOT NULL,
  monto DECIMAL(12,2) NOT NULL,
  moneda CHAR(3) NOT NULL,
  vigente_desde DATETIME NOT NULL,
  vigente_hasta DATETIME NULL,
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cat_precio_sucursal_producto_desde (sucursal_id, producto_id, vigente_desde),
  CONSTRAINT fk_cat_precio_sucursal FOREIGN KEY (sucursal_id) REFERENCES core_sucursal (id) ON DELETE RESTRICT,
  CONSTRAINT fk_cat_precio_producto FOREIGN KEY (producto_id) REFERENCES cat_producto (id) ON DELETE RESTRICT,
  CONSTRAINT chk_cat_precio_monto CHECK (monto >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cat_promocion (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(120) NOT NULL,
  reglas JSON NOT NULL,
  vigente_desde DATETIME NOT NULL,
  vigente_hasta DATETIME NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

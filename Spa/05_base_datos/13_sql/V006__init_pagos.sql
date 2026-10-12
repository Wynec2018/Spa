-- V006__init_pagos
-- Módulo: pagos + outbox + idempotency (D-03/D-04).
-- Rollback: DROP vacío (bootstrap); con datos → restore.
-- Ventana: segura (requiere V004 sales_orden_venta).
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE pay_transaccion_pago (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  orden_id BIGINT UNSIGNED NOT NULL,
  proveedor_pago VARCHAR(50) NOT NULL,
  referencia_externa VARCHAR(120) NOT NULL,
  monto DECIMAL(12,2) NOT NULL,
  estado ENUM('initiated','authorized','failed','refunded','compensated') NOT NULL DEFAULT 'initiated',
  idempotency_key VARCHAR(64) NOT NULL,
  fecha DATETIME NOT NULL,
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pay_pago_idempotency (idempotency_key),
  UNIQUE KEY uq_pay_pago_proveedor_ref (proveedor_pago, referencia_externa),
  KEY idx_pay_pago_orden (orden_id),
  CONSTRAINT fk_pay_pago_orden FOREIGN KEY (orden_id) REFERENCES sales_orden_venta (id) ON DELETE RESTRICT,
  CONSTRAINT chk_pay_pago_monto CHECK (monto > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pay_outbox_evento (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  agregado_tipo VARCHAR(50) NOT NULL,
  agregado_id BIGINT UNSIGNED NOT NULL,
  evento_tipo VARCHAR(80) NOT NULL,
  payload JSON NOT NULL,
  estado ENUM('pending','processing','processed','failed') NOT NULL DEFAULT 'pending',
  claimed_at DATETIME NULL,
  correlation_id VARCHAR(64) NOT NULL,
  creado_en DATETIME NOT NULL,
  procesado_en DATETIME NULL,
  reintentos INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_pay_outbox_estado_fecha (estado, creado_en),
  KEY idx_pay_outbox_correlation (correlation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE core_idempotency_key (
  `key` CHAR(36) NOT NULL,
  operacion VARCHAR(80) NOT NULL,
  respuesta MEDIUMTEXT NULL,
  expiracion DATETIME NOT NULL,
  creado_en DATETIME NOT NULL,
  PRIMARY KEY (`key`),
  KEY idx_core_idem_expiracion (expiracion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- V001__init_auth_core
-- Módulos: auth + core (base). Bootstrap, entorno vacío.
-- Rollback: DROP tablas vacías solo si data_loss_aceptado=true (entorno desechable); con datos → restore.
-- Ventana: segura en BD vacía. Rol ejecutor: migraciones (no app_rw).
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE auth_usuario (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(120) NOT NULL,
  email VARCHAR(254) NOT NULL,
  hash_password VARCHAR(255) NOT NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_auth_usuario_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_rol (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(80) NOT NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_auth_rol_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_permiso (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(64) NOT NULL,
  descripcion VARCHAR(255) NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_auth_permiso_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_usuario_rol (
  usuario_id BIGINT UNSIGNED NOT NULL,
  rol_id BIGINT UNSIGNED NOT NULL,
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (usuario_id, rol_id),
  KEY idx_auth_usuario_rol_rol (rol_id),
  CONSTRAINT fk_auth_usuario_rol_usuario FOREIGN KEY (usuario_id) REFERENCES auth_usuario (id) ON DELETE RESTRICT,
  CONSTRAINT fk_auth_usuario_rol_rol FOREIGN KEY (rol_id) REFERENCES auth_rol (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_rol_permiso (
  rol_id BIGINT UNSIGNED NOT NULL,
  permiso_id BIGINT UNSIGNED NOT NULL,
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (rol_id, permiso_id),
  KEY idx_auth_rol_permiso_permiso (permiso_id),
  CONSTRAINT fk_auth_rol_permiso_rol FOREIGN KEY (rol_id) REFERENCES auth_rol (id) ON DELETE RESTRICT,
  CONSTRAINT fk_auth_rol_permiso_permiso FOREIGN KEY (permiso_id) REFERENCES auth_permiso (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_token_blacklist (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  token_hash VARCHAR(64) NOT NULL,
  expira_en DATETIME NOT NULL,
  creado_en DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_auth_token_blacklist_hash (token_hash),
  KEY idx_auth_token_blacklist_expira (expira_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE core_sucursal (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(120) NOT NULL,
  direccion VARCHAR(255) NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE core_caja_pos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sucursal_id BIGINT UNSIGNED NOT NULL,
  codigo VARCHAR(32) NOT NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_core_caja_pos_sucursal_codigo (sucursal_id, codigo),
  CONSTRAINT fk_core_caja_pos_sucursal FOREIGN KEY (sucursal_id) REFERENCES core_sucursal (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE core_cliente (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(150) NOT NULL,
  razon_social VARCHAR(150) NULL,
  identificacion VARCHAR(32) NULL,
  email VARCHAR(254) NULL,
  telefono VARCHAR(32) NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_core_cliente_identificacion (identificacion),
  KEY idx_core_cliente_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE core_configuracion (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clave VARCHAR(80) NOT NULL,
  valor TEXT NOT NULL,
  descripcion VARCHAR(255) NULL,
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_core_config_clave (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE core_auditoria (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  accion VARCHAR(50) NOT NULL,
  entidad_tipo VARCHAR(80) NOT NULL,
  entidad_id BIGINT UNSIGNED NOT NULL,
  datos_previos TEXT NULL,
  datos_nuevos TEXT NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  motivo TEXT NULL,
  fecha DATETIME NOT NULL,
  correlation_id VARCHAR(64) NULL,
  user_create BIGINT UNSIGNED NOT NULL,
  user_update BIGINT UNSIGNED NOT NULL,
  user_created_at DATETIME NOT NULL,
  user_update_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_core_auditoria_entidad (entidad_tipo, entidad_id, fecha),
  KEY idx_core_auditoria_usuario (usuario_id),
  KEY idx_core_auditoria_fecha (fecha),
  CONSTRAINT fk_core_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES auth_usuario (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

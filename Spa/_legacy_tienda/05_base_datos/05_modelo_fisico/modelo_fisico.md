# Paso 07 — Diseño físico (MySQL 8.x / InnoDB)

**Workflow:** 02_database_workflow — Paso 07
**Skills utilizados:** `database-schema-designer`, `mysql-patterns`
**DBMS seleccionado (Paso 06):** MySQL 8.x LTS (8.4+), InnoDB, `utf8mb4`.
**Entrada:** `03_modelo_logico/modelo_logico.md`, `05_modelo_fisico/seleccion_dbms.md`
**Salida:** `modelo_fisico.md` (+ anexo `scripts/esquema.sql`)

## 1. Decisiones de tipado (del lógico → MySQL)

| Dominio lógico | Tipo MySQL | Regla |
|---|---|---|
| PK surrogate | `BIGINT UNSIGNED AUTO_INCREMENT` | surrogate en todas menos asociativas puras |
| Dinero / monto | `DECIMAL(12,2)` | **nunca** FLOAT/DOUBLE |
| Entero positivo (cantidades, stock) | `INT UNSIGNED` + `CHECK > 0` (o `>= 0` según columna) | CHECK confiable en InnoDB 8.0+ |
| Cadena corta (email, sku, clave) | `VARCHAR(n)` con `n` real (no 255 por defecto) | p.ej. email 254, sku 64, código 32 |
| Texto largo (motivo, payload) | `TEXT` / `JSON` | JSON solo para extensiones (`cat_promocion.reglas`, `pay_outbox_evento.payload`) |
| Fecha/hora | `DATETIME` (UTC gestionado por la app) | evita el bug 2038 de `TIMESTAMP` |
| Booleano | `TINYINT(1)` o `estado` ENUM | `estado` como `ENUM` o `VARCHAR` con CHECK |
| Auditoría estándar | `user_create`, `user_update` (`BIGINT UNSIGNED`), `user_created_at`, `user_update_at` (`DATETIME`) | obligatorio en toda tabla de negocio |

**Motor/carácter:** `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci` en todas las tablas.

## 2. Convención de FK y borrado

- **Sin borrado físico** en tablas transaccionales (RN-08): FK hacia ellas usan `ON DELETE RESTRICT` (o `NO ACTION`).
- Entidades maestras con `estado`: `ON DELETE RESTRICT`.
- Relación opcional que puede anularse (p.ej. `cliente_id` NULL en POS): `ON DELETE SET NULL` solo donde el dominio lo permite.
- **Índice en toda columna FK** (InnoDB indexa FK automáticamente para `FOREIGN KEY`, pero se declara explícito el índice compuesto cuando el patrón de acceso lo exige).

## 3. Índices (mínimo indispensable; refinamiento en Paso 11)

- PK y UNIQUE → índice implícito.
- Todas las FK → índice.
- Acceso por `(producto_id, sucursal_id)` en inventario → UNIQUE.
- `sales_orden_venta(idempotency_key)` → UNIQUE.
- `pay_outbox_evento(estado, creado_en)` → índice compuesto (barrido de outbox).
- `core_auditoria(entidad_tipo, entidad_id, fecha)` → índice compuesto.
- `inv_movimiento_inventario(producto_id, fecha)`, `(sucursal_id, fecha)` → consultas de auditoría/RF-090.

## 4. Anexo — esquema SQL (MySQL 8.x)

Primera materialización de SQL del workflow. **No se ejecuta aún** (regla: sin despliegue/migración real hasta `STATUS: APPROVED`).

```sql
-- ============================================================
-- Tienda omnicanal — esquema físico MySQL 8.x (InnoDB, utf8mb4)
-- PASO 07 — BORRADOR DE DISEÑO. No ejecutar hasta STATUS: APPROVED.
-- ============================================================
SET NAMES utf8mb4;

-- ---------- MÓDULO auth ----------
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

-- ---------- MÓDULO core ----------
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

-- ---------- MÓDULO catálogo ----------
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

-- ---------- MÓDULO compras ----------
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

-- ---------- MÓDULO inventario ----------
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

-- ---------- MÓDULO ventas ----------
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
  KEY idx_sales_orden_sucursal_fecha (sucursal_id, creado_en),
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

-- ---------- MÓDULO pagos ----------
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

-- ---------- MÓDULO sistema ----------
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
```

## 5. Notas y pendientes abiertos (no resueltos en Paso 07)

- **Carrito web (RF-060, confirmado 2026-09-22):** el carrito **persiste en BD**; `sales_carrito_web` se incluye con `sesion_key UNIQUE`. TTL de purga sigue sin definirse en RF (queda como parámetro en `core_configuracion`; índice G2 condicionado).
- **FK `inv_reserva.orden_id` → `sales_orden_venta` (tabla definida después):** en MySQL la orden debe existir antes (creación de orden en `pending` → luego reserva). El orden físico de `CREATE TABLE` puede requerir `SET FOREIGN_KEY_CHECKS=0` solo si se crea en un script único sin datos; en migración real se respeta la dependencia.
- **Auditoría `user_create`/`user_update`:** en `auth_usuario` y tablas sin usuario propio, se admite `user_create = id` del propio registro al ser insert (o sistema seed). Detallar en Paso 08/09.
- **`ENUM` vs `VARCHAR`:** se usó `ENUM` por conjuntos estables (estados). Si algún enum debiera evolucionar con frecuencia, migrar a `VARCHAR`+CHECK (Paso 13).
- **CHECK con `ENUM`:** InnoDB valida `CHECK`; validar en staging (self-check del skill).
- **Retención idempotency_key:** 30 días → job de purga `core_idempotency_key WHERE expiracion < NOW()` (definir en Paso 13).

## 6. Criterios de salida (Paso 07)

- [x] Tipos concretos de motor elegidos (DECIMAL, DATETIME, VARCHAR(n), ENUM, JSON).
- [x] PK, FK, UNIQUE, CHECK, NOT NULL materializados.
- [x] `ON DELETE` explícito en toda FK (RESTRICT por defecto).
- [x] Índices mínimos declarados (refinamiento Paso 11).
- [x] Auditoría estándar en toda tabla de negocio.
- [x] `utf8mb4` / InnoDB en todas las tablas.
- [x] SQL = **borrador de diseño**, no migración desplegada.

**Estado:** Paso 07 COMPLETADO → siguiente: Paso 08 (`06_integridad/integridad.md`).

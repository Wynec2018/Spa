### auth_permiso

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `codigo` | varchar(64) | NO |  | UNI |  |
| `descripcion` | varchar(255) | YES |  |  |  |
| `estado` | enum('activo','inactivo') | NO | `activo` |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**UNIQUE:** `uq_auth_permiso_codigo`(`codigo`)

### auth_rol

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `nombre` | varchar(80) | NO |  | UNI |  |
| `estado` | enum('activo','inactivo') | NO | `activo` |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**UNIQUE:** `uq_auth_rol_nombre`(`nombre`)

### auth_rol_permiso

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `rol_id` | bigint unsigned | NO |  | PRI |  |
| `permiso_id` | bigint unsigned | NO |  | PRI |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_auth_rol_permiso_permiso` → `auth_permiso.id` (`permiso_id`); `fk_auth_rol_permiso_rol` → `auth_rol.id` (`rol_id`)

### auth_token_blacklist

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `token_hash` | varchar(64) | NO |  | UNI |  |
| `expira_en` | datetime | NO |  | MUL |  |
| `creado_en` | datetime | NO |  |  |  |

**UNIQUE:** `uq_auth_token_blacklist_hash`(`token_hash`)

### auth_usuario

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `nombre` | varchar(120) | NO |  |  |  |
| `email` | varchar(254) | NO |  | UNI |  |
| `hash_password` | varchar(255) | NO |  |  |  |
| `estado` | enum('activo','inactivo') | NO | `activo` |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**UNIQUE:** `uq_auth_usuario_email`(`email`)

### auth_usuario_rol

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `usuario_id` | bigint unsigned | NO |  | PRI |  |
| `rol_id` | bigint unsigned | NO |  | PRI |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_auth_usuario_rol_rol` → `auth_rol.id` (`rol_id`); `fk_auth_usuario_rol_usuario` → `auth_usuario.id` (`usuario_id`)

### cat_categoria

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `nombre` | varchar(100) | NO |  |  |  |
| `categoria_padre_id` | bigint unsigned | YES |  | MUL |  |
| `estado` | enum('activo','inactivo') | NO | `activo` |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_cat_categoria_padre` → `cat_categoria.id` (`categoria_padre_id`)

### cat_precio

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `sucursal_id` | bigint unsigned | NO |  | MUL |  |
| `producto_id` | bigint unsigned | NO |  | MUL |  |
| `monto` | decimal(12,2) | NO |  |  |  |
| `moneda` | char(3) | NO |  |  |  |
| `vigente_desde` | datetime | NO |  |  |  |
| `vigente_hasta` | datetime | YES |  |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_cat_precio_producto` → `cat_producto.id` (`producto_id`); `fk_cat_precio_sucursal` → `core_sucursal.id` (`sucursal_id`)

**UNIQUE:** `uq_cat_precio_sucursal_producto_desde`(`sucursal_id,producto_id,vigente_desde`)

### cat_producto

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `sku` | varchar(64) | NO |  | UNI |  |
| `nombre` | varchar(150) | NO |  |  |  |
| `categoria_id` | bigint unsigned | NO |  | MUL |  |
| `unidad` | varchar(32) | NO |  |  |  |
| `estado` | enum('activo','inactivo') | NO | `activo` |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_cat_producto_categoria` → `cat_categoria.id` (`categoria_id`)

**UNIQUE:** `uq_cat_producto_sku`(`sku`)

### cat_promocion

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `nombre` | varchar(120) | NO |  |  |  |
| `reglas` | json | NO |  |  |  |
| `vigente_desde` | datetime | NO |  |  |  |
| `vigente_hasta` | datetime | YES |  |  |  |
| `estado` | enum('activo','inactivo') | NO | `activo` |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

### com_orden_compra

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `numero` | varchar(32) | NO |  | UNI |  |
| `proveedor_id` | bigint unsigned | NO |  | MUL |  |
| `estado` | enum('draft','enviada','recibida','cancelada') | NO | `draft` |  |  |
| `fecha_orden` | datetime | NO |  |  |  |
| `total` | decimal(12,2) | NO | `0.00` |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_com_orden_compra_proveedor` → `com_proveedor.id` (`proveedor_id`)

**UNIQUE:** `uq_com_orden_compra_numero`(`numero`)

### com_orden_compra_item

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `orden_id` | bigint unsigned | NO |  | MUL |  |
| `producto_id` | bigint unsigned | NO |  | MUL |  |
| `cantidad` | int unsigned | NO |  |  |  |
| `precio_pactado` | decimal(12,2) | NO |  |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_com_orden_item_orden` → `com_orden_compra.id` (`orden_id`); `fk_com_orden_item_producto` → `cat_producto.id` (`producto_id`)

**UNIQUE:** `uq_com_orden_item_orden_producto`(`orden_id,producto_id`)

### com_producto_promocion

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `producto_id` | bigint unsigned | NO |  | PRI |  |
| `promocion_id` | bigint unsigned | NO |  | PRI |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_com_producto_promocion_producto` → `cat_producto.id` (`producto_id`); `fk_com_producto_promocion_promo` → `cat_promocion.id` (`promocion_id`)

### com_proveedor

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `nombre` | varchar(150) | NO |  | UNI |  |
| `codigo` | varchar(32) | YES |  | UNI |  |
| `contacto` | varchar(150) | YES |  |  |  |
| `estado` | enum('activo','inactivo') | NO | `activo` |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**UNIQUE:** `uq_com_proveedor_codigo`(`codigo`); `uq_com_proveedor_nombre`(`nombre`)

### com_recepcion

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `orden_id` | bigint unsigned | NO |  | MUL |  |
| `estado` | enum('draft','confirmada') | NO | `draft` |  |  |
| `fecha` | datetime | NO |  |  |  |
| `usuario_confirma` | bigint unsigned | YES |  | MUL |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_com_recepcion_orden` → `com_orden_compra.id` (`orden_id`); `fk_com_recepcion_usuario` → `auth_usuario.id` (`usuario_confirma`)

### com_recepcion_item

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `recepcion_id` | bigint unsigned | NO |  | MUL |  |
| `producto_id` | bigint unsigned | NO |  | MUL |  |
| `cantidad_recibida` | int unsigned | NO |  |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_com_recepcion_item_producto` → `cat_producto.id` (`producto_id`); `fk_com_recepcion_item_recepcion` → `com_recepcion.id` (`recepcion_id`)

**UNIQUE:** `uq_com_recepcion_item_recepcion_producto`(`recepcion_id,producto_id`)

### core_auditoria

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `accion` | varchar(50) | NO |  |  |  |
| `entidad_tipo` | varchar(80) | NO |  | MUL |  |
| `entidad_id` | bigint unsigned | NO |  |  |  |
| `datos_previos` | text | YES |  |  |  |
| `datos_nuevos` | text | YES |  |  |  |
| `usuario_id` | bigint unsigned | NO |  | MUL |  |
| `motivo` | text | YES |  |  |  |
| `fecha` | datetime | NO |  | MUL |  |
| `correlation_id` | varchar(64) | YES |  |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_core_auditoria_usuario` → `auth_usuario.id` (`usuario_id`)

### core_caja_pos

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `sucursal_id` | bigint unsigned | NO |  | MUL |  |
| `codigo` | varchar(32) | NO |  |  |  |
| `estado` | enum('activo','inactivo') | NO | `activo` |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_core_caja_pos_sucursal` → `core_sucursal.id` (`sucursal_id`)

**UNIQUE:** `uq_core_caja_pos_sucursal_codigo`(`sucursal_id,codigo`)

### core_cliente

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `nombre` | varchar(150) | NO |  |  |  |
| `razon_social` | varchar(150) | YES |  |  |  |
| `identificacion` | varchar(32) | YES |  | UNI |  |
| `email` | varchar(254) | YES |  | MUL |  |
| `telefono` | varchar(32) | YES |  |  |  |
| `estado` | enum('activo','inactivo') | NO | `activo` |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**UNIQUE:** `uq_core_cliente_identificacion`(`identificacion`)

### core_configuracion

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `clave` | varchar(80) | NO |  | UNI |  |
| `valor` | text | NO |  |  |  |
| `descripcion` | varchar(255) | YES |  |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**UNIQUE:** `uq_core_config_clave`(`clave`)

### core_idempotency_key

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `key` | char(36) | NO |  | PRI |  |
| `operacion` | varchar(80) | NO |  |  |  |
| `respuesta` | mediumtext | YES |  |  |  |
| `expiracion` | datetime | NO |  | MUL |  |
| `creado_en` | datetime | NO |  |  |  |

### core_sucursal

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `nombre` | varchar(120) | NO |  |  |  |
| `direccion` | varchar(255) | YES |  |  |  |
| `estado` | enum('activo','inactivo') | NO | `activo` |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

### inv_ajuste_stock

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `inventario_id` | bigint unsigned | NO |  | MUL |  |
| `cantidad` | int | NO |  |  |  |
| `motivo` | text | NO |  |  |  |
| `usuario_id` | bigint unsigned | NO |  | MUL |  |
| `fecha` | datetime | NO |  |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_inv_ajuste_inventario` → `inv_inventario.id` (`inventario_id`); `fk_inv_ajuste_usuario` → `auth_usuario.id` (`usuario_id`)

### inv_inventario

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `producto_id` | bigint unsigned | NO |  | MUL |  |
| `sucursal_id` | bigint unsigned | NO |  | MUL |  |
| `stock_available` | int unsigned | NO | `0` |  |  |
| `stock_reserved` | int unsigned | NO | `0` |  |  |
| `stock_sold` | int unsigned | NO | `0` |  |  |
| `version` | int unsigned | NO | `0` |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_inv_inventario_producto` → `cat_producto.id` (`producto_id`); `fk_inv_inventario_sucursal` → `core_sucursal.id` (`sucursal_id`)

**UNIQUE:** `uq_inv_inventario_producto_sucursal`(`producto_id,sucursal_id`)

### inv_movimiento_inventario

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `inventario_id` | bigint unsigned | NO |  | MUL |  |
| `producto_id` | bigint unsigned | NO |  | MUL |  |
| `sucursal_id` | bigint unsigned | NO |  | MUL |  |
| `tipo` | enum('entrada','salida','reserva','liberacion','ajuste','devolucion','transferencia_salida','transferencia_entrada','recepcion') | NO |  |  |  |
| `cantidad` | int | NO |  |  |  |
| `referencia_tipo` | varchar(50) | YES |  |  |  |
| `referencia_id` | bigint unsigned | YES |  |  |  |
| `usuario_id` | bigint unsigned | NO |  | MUL |  |
| `motivo` | text | YES |  |  |  |
| `fecha` | datetime | NO |  |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_inv_mov_inventario` → `inv_inventario.id` (`inventario_id`); `fk_inv_mov_producto` → `cat_producto.id` (`producto_id`); `fk_inv_mov_sucursal` → `core_sucursal.id` (`sucursal_id`); `fk_inv_mov_usuario` → `auth_usuario.id` (`usuario_id`)

### inv_reserva

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `orden_id` | bigint unsigned | NO |  | MUL |  |
| `inventario_id` | bigint unsigned | NO |  | MUL |  |
| `cantidad` | int unsigned | NO |  |  |  |
| `estado` | enum('pending','confirmed','expired','cancelled') | NO | `pending` | MUL |  |
| `expira_en` | datetime | NO |  |  |  |
| `creado_en` | datetime | NO |  |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_inv_reserva_inventario` → `inv_inventario.id` (`inventario_id`); `fk_inv_reserva_orden` → `sales_orden_venta.id` (`orden_id`)

### inv_transferencia

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `sucursal_origen_id` | bigint unsigned | NO |  | MUL |  |
| `sucursal_destino_id` | bigint unsigned | NO |  | MUL |  |
| `estado` | enum('draft','in_transit','received','cancelled') | NO | `draft` |  |  |
| `usuario_id` | bigint unsigned | NO |  | MUL |  |
| `fecha_salida` | datetime | YES |  |  |  |
| `fecha_recepcion` | datetime | YES |  |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_inv_trans_destino` → `core_sucursal.id` (`sucursal_destino_id`); `fk_inv_trans_origen` → `core_sucursal.id` (`sucursal_origen_id`); `fk_inv_trans_usuario` → `auth_usuario.id` (`usuario_id`)

### inv_transferencia_item

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `transferencia_id` | bigint unsigned | NO |  | MUL |  |
| `producto_id` | bigint unsigned | NO |  | MUL |  |
| `cantidad` | int unsigned | NO |  |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_inv_trans_item_producto` → `cat_producto.id` (`producto_id`); `fk_inv_trans_item_trans` → `inv_transferencia.id` (`transferencia_id`)

**UNIQUE:** `uq_inv_trans_item_trans_producto`(`transferencia_id,producto_id`)

### pay_outbox_evento

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `agregado_tipo` | varchar(50) | NO |  |  |  |
| `agregado_id` | bigint unsigned | NO |  |  |  |
| `evento_tipo` | varchar(80) | NO |  |  |  |
| `payload` | json | NO |  |  |  |
| `estado` | enum('pending','processing','processed','failed') | NO | `pending` | MUL |  |
| `claimed_at` | datetime | YES |  |  |  |
| `correlation_id` | varchar(64) | NO |  | MUL |  |
| `creado_en` | datetime | NO |  |  |  |
| `procesado_en` | datetime | YES |  |  |  |
| `reintentos` | int unsigned | NO | `0` |  |  |

### pay_transaccion_pago

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `orden_id` | bigint unsigned | NO |  | MUL |  |
| `proveedor_pago` | varchar(50) | NO |  | MUL |  |
| `referencia_externa` | varchar(120) | NO |  |  |  |
| `monto` | decimal(12,2) | NO |  |  |  |
| `estado` | enum('initiated','authorized','failed','refunded','compensated') | NO | `initiated` |  |  |
| `idempotency_key` | varchar(64) | NO |  | UNI |  |
| `fecha` | datetime | NO |  |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_pay_pago_orden` → `sales_orden_venta.id` (`orden_id`)

**UNIQUE:** `uq_pay_pago_idempotency`(`idempotency_key`); `uq_pay_pago_proveedor_ref`(`proveedor_pago,referencia_externa`)

### sales_carrito_web

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `cliente_id` | bigint unsigned | YES |  | MUL |  |
| `sesion_key` | varchar(64) | NO |  | UNI |  |
| `actualizado_en` | datetime | NO |  |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_sales_carrito_cliente` → `core_cliente.id` (`cliente_id`)

**UNIQUE:** `uq_sales_carrito_sesion`(`sesion_key`)

### sales_carrito_web_item

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `carrito_id` | bigint unsigned | NO |  | MUL |  |
| `producto_id` | bigint unsigned | NO |  | MUL |  |
| `cantidad` | int unsigned | NO |  |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_sales_carrito_item_carrito` → `sales_carrito_web.id` (`carrito_id`); `fk_sales_carrito_item_producto` → `cat_producto.id` (`producto_id`)

**UNIQUE:** `uq_sales_carrito_item_carrito_producto`(`carrito_id,producto_id`)

### sales_devolucion

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `orden_original_id` | bigint unsigned | NO |  | MUL |  |
| `cliente_id` | bigint unsigned | YES |  | MUL |  |
| `usuario_id` | bigint unsigned | NO |  | MUL |  |
| `estado` | enum('draft','aprobada','rechazada','completada') | NO | `draft` |  |  |
| `motivo` | text | YES |  |  |  |
| `monto` | decimal(12,2) | NO | `0.00` |  |  |
| `fecha` | datetime | NO |  |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_sales_dev_cliente` → `core_cliente.id` (`cliente_id`); `fk_sales_dev_orden` → `sales_orden_venta.id` (`orden_original_id`); `fk_sales_dev_usuario` → `auth_usuario.id` (`usuario_id`)

### sales_devolucion_item

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `devolucion_id` | bigint unsigned | NO |  | MUL |  |
| `producto_id` | bigint unsigned | NO |  | MUL |  |
| `producto_orden_item_id` | bigint unsigned | NO |  | MUL |  |
| `cantidad` | int unsigned | NO |  |  |  |
| `monto` | decimal(12,2) | NO |  |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_sales_dev_item_dev` → `sales_devolucion.id` (`devolucion_id`); `fk_sales_dev_item_orden_item` → `sales_orden_venta_item.id` (`producto_orden_item_id`); `fk_sales_dev_item_producto` → `cat_producto.id` (`producto_id`)

**UNIQUE:** `uq_sales_dev_item_dev_producto`(`devolucion_id,producto_id`)

### sales_orden_venta

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `numero` | varchar(32) | NO |  | UNI |  |
| `canal` | enum('pos','web') | NO |  |  |  |
| `cliente_id` | bigint unsigned | YES |  | MUL |  |
| `sucursal_id` | bigint unsigned | NO |  | MUL |  |
| `estado` | enum('pending','pagada','confirmada','cancelada','devuelta') | NO | `pending` | MUL |  |
| `total` | decimal(12,2) | NO | `0.00` |  |  |
| `idempotency_key` | varchar(64) | YES |  | UNI |  |
| `fulfillment_type` | enum('entrega','retiro') | YES |  |  |  |
| `fulfillment_direccion` | varchar(255) | YES |  |  |  |
| `fecha_entrega` | datetime | YES |  |  |  |
| `creado_en` | datetime | NO |  |  |  |
| `confirmado_en` | datetime | YES |  |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_sales_orden_cliente` → `core_cliente.id` (`cliente_id`); `fk_sales_orden_sucursal` → `core_sucursal.id` (`sucursal_id`)

**UNIQUE:** `uq_sales_orden_idempotency`(`idempotency_key`); `uq_sales_orden_numero`(`numero`)

### sales_orden_venta_item

| Column | Type | Nullable | Default | Key | Extra |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO |  | PRI | auto_increment |
| `orden_id` | bigint unsigned | NO |  | MUL |  |
| `producto_id` | bigint unsigned | NO |  | MUL |  |
| `cantidad` | int unsigned | NO |  |  |  |
| `precio_unitario` | decimal(12,2) | NO |  |  |  |
| `subtotal` | decimal(12,2) | NO |  |  |  |
| `user_create` | bigint unsigned | NO |  |  |  |
| `user_update` | bigint unsigned | NO |  |  |  |
| `user_created_at` | datetime | NO |  |  |  |
| `user_update_at` | datetime | NO |  |  |  |

**FK:** `fk_sales_item_orden` → `sales_orden_venta.id` (`orden_id`); `fk_sales_item_producto` → `cat_producto.id` (`producto_id`)

**UNIQUE:** `uq_sales_item_orden_producto`(`orden_id,producto_id`)



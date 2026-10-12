**auth_permiso**
- `PRIMARY` (id)
- `uq_auth_permiso_codigo` (codigo)
**auth_rol**
- `PRIMARY` (id)
- `uq_auth_rol_nombre` (nombre)
**auth_rol_permiso**
- `PRIMARY` (rol_id, permiso_id)
- `idx_auth_rol_permiso_permiso` (permiso_id)
**auth_token_blacklist**
- `PRIMARY` (id)
- `idx_auth_token_blacklist_expira` (expira_en)
- `uq_auth_token_blacklist_hash` (token_hash)
**auth_usuario**
- `PRIMARY` (id)
- `uq_auth_usuario_email` (email)
**auth_usuario_rol**
- `PRIMARY` (usuario_id, rol_id)
- `idx_auth_usuario_rol_rol` (rol_id)
**cat_categoria**
- `PRIMARY` (id)
- `idx_cat_categoria_padre` (categoria_padre_id)
**cat_precio**
- `PRIMARY` (id)
- `fk_cat_precio_producto` (producto_id)
- `uq_cat_precio_sucursal_producto_desde` (sucursal_id, producto_id, vigente_desde)
**cat_producto**
- `PRIMARY` (id)
- `idx_cat_producto_categoria` (categoria_id)
- `uq_cat_producto_sku` (sku)
**cat_promocion**
- `PRIMARY` (id)
**com_orden_compra**
- `PRIMARY` (id)
- `idx_com_orden_compra_proveedor` (proveedor_id)
- `uq_com_orden_compra_numero` (numero)
**com_orden_compra_item**
- `PRIMARY` (id)
- `idx_com_orden_item_producto` (producto_id)
- `uq_com_orden_item_orden_producto` (orden_id, producto_id)
**com_producto_promocion**
- `PRIMARY` (producto_id, promocion_id)
- `idx_com_producto_promocion_promo` (promocion_id)
**com_proveedor**
- `PRIMARY` (id)
- `uq_com_proveedor_codigo` (codigo)
- `uq_com_proveedor_nombre` (nombre)
**com_recepcion**
- `PRIMARY` (id)
- `idx_com_recepcion_orden` (orden_id)
- `idx_com_recepcion_usuario` (usuario_confirma)
**com_recepcion_item**
- `PRIMARY` (id)
- `idx_com_recepcion_item_producto` (producto_id)
- `idx_com_recepcion_item_recepcion` (recepcion_id)
- `uq_com_recepcion_item_recepcion_producto` (recepcion_id, producto_id)
**core_auditoria**
- `PRIMARY` (id)
- `idx_core_auditoria_entidad` (entidad_tipo, entidad_id, fecha)
- `idx_core_auditoria_fecha` (fecha)
- `idx_core_auditoria_usuario` (usuario_id)
**core_caja_pos**
- `PRIMARY` (id)
- `uq_core_caja_pos_sucursal_codigo` (sucursal_id, codigo)
**core_cliente**
- `PRIMARY` (id)
- `idx_core_cliente_email` (email)
- `uq_core_cliente_identificacion` (identificacion)
**core_configuracion**
- `PRIMARY` (id)
- `uq_core_config_clave` (clave)
**core_idempotency_key**
- `PRIMARY` (key)
- `idx_core_idem_expiracion` (expiracion)
**core_sucursal**
- `PRIMARY` (id)
**inv_ajuste_stock**
- `PRIMARY` (id)
- `idx_inv_ajuste_inventario` (inventario_id)
- `idx_inv_ajuste_usuario` (usuario_id)
**inv_inventario**
- `PRIMARY` (id)
- `idx_inv_inventario_sucursal` (sucursal_id)
- `uq_inv_inventario_producto_sucursal` (producto_id, sucursal_id)
**inv_movimiento_inventario**
- `PRIMARY` (id)
- `idx_inv_mov_inventario` (inventario_id, fecha)
- `idx_inv_mov_producto_fecha` (producto_id, fecha)
- `idx_inv_mov_sucursal_fecha` (sucursal_id, fecha)
- `idx_inv_mov_usuario` (usuario_id)
**inv_reserva**
- `PRIMARY` (id)
- `idx_inv_reserva_estado_expira` (estado, expira_en)
- `idx_inv_reserva_inventario` (inventario_id)
- `idx_inv_reserva_orden` (orden_id)
**inv_transferencia**
- `PRIMARY` (id)
- `idx_inv_trans_destino` (sucursal_destino_id)
- `idx_inv_trans_origen` (sucursal_origen_id)
- `idx_inv_trans_usuario` (usuario_id)
**inv_transferencia_item**
- `PRIMARY` (id)
- `idx_inv_trans_item_producto` (producto_id)
- `uq_inv_trans_item_trans_producto` (transferencia_id, producto_id)
**pay_outbox_evento**
- `PRIMARY` (id)
- `idx_pay_outbox_correlation` (correlation_id)
- `idx_pay_outbox_estado_fecha` (estado, creado_en)
**pay_transaccion_pago**
- `PRIMARY` (id)
- `idx_pay_pago_orden` (orden_id)
- `uq_pay_pago_idempotency` (idempotency_key)
- `uq_pay_pago_proveedor_ref` (proveedor_pago, referencia_externa)
**sales_carrito_web**
- `PRIMARY` (id)
- `idx_sales_carrito_cliente` (cliente_id)
- `uq_sales_carrito_sesion` (sesion_key)
**sales_carrito_web_item**
- `PRIMARY` (id)
- `idx_sales_carrito_item_producto` (producto_id)
- `uq_sales_carrito_item_carrito_producto` (carrito_id, producto_id)
**sales_devolucion**
- `PRIMARY` (id)
- `idx_sales_dev_cliente` (cliente_id)
- `idx_sales_dev_orden` (orden_original_id)
- `idx_sales_dev_usuario` (usuario_id)
**sales_devolucion_item**
- `PRIMARY` (id)
- `idx_sales_dev_item_dev` (devolucion_id)
- `idx_sales_dev_item_orden_item` (producto_orden_item_id)
- `idx_sales_dev_item_producto` (producto_id)
- `uq_sales_dev_item_dev_producto` (devolucion_id, producto_id)
**sales_orden_venta**
- `PRIMARY` (id)
- `idx_sales_orden_cliente` (cliente_id)
- `idx_sales_orden_estado_fecha` (estado, creado_en)
- `idx_sales_orden_sucursal` (sucursal_id)
- `idx_sales_orden_sucursal_fecha` (sucursal_id, creado_en)
- `uq_sales_orden_idempotency` (idempotency_key)
- `uq_sales_orden_numero` (numero)
**sales_orden_venta_item**
- `PRIMARY` (id)
- `idx_sales_item_producto` (producto_id)
- `uq_sales_item_orden_producto` (orden_id, producto_id)

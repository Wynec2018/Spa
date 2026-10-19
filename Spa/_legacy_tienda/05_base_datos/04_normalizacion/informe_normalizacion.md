# Paso 05 — Informe de normalización (1NF, 2NF, 3NF)

**Workflow:** 02_database_workflow — Paso 05
**Skill utilizado:** `database-schema-designer`
**Entrada:** `03_modelo_logico/modelo_logico.md`
**Objetivo:** demostrar que el modelo lógico cumple 1NF, 2NF y 3NF, o documentar desviaciones deliberadas.

## 1. Verificación 1NF (valores atómicos, sin grupos repetidos)

| Entidad | Comprobación | Resultado |
|---|---|---|
| sales_orden_venta_item | una fila por producto de la orden; no se guarda lista de productos | ✅ |
| inv_transferencia_item / com_*_item / sales_devolucion_item | igual: una fila por ítem | ✅ |
| inv_movimiento_inventario | una fila por movimiento (cantidad escalar, referencia en columnas separadas `referencia_tipo`/`referencia_id`) | ✅ |
| core_auditoria | datos_previos/datos_nuevos en columnas separadas de accion/usuario/fecha | ✅ (texto serializado aceptado; ver §4) |
| cat_categoria | jerarquía vía FK, no columna de "ruta" multi-valor | ✅ |

**Sin violaciones de 1NF detectadas.**

## 2. Verificación 2NF (sin dependencias parciales sobre PK compuesta)

Tablas con PK compuesta:

| Tabla | PK | Dependencias | Resultado |
|---|---|---|---|
| auth_usuario_rol | (usuario_id, rol_id) | solo referencias; ningún atributo no clave | ✅ trivial |
| auth_rol_permiso | (rol_id, permiso_id) | ídem | ✅ trivial |
| com_producto_promocion | (producto_id, promocion_id) | ídem | ✅ trivial |

Tablas de detalle con PK surrogate `id` (sales_orden_venta_item, com_orden_compra_item, etc.): no tienen PK compuesta; los atributos (`cantidad`, `precio_unitario`) dependen de la clave candidata natural `(orden_id, producto_id)`, que se protege con UNIQUE. Como el PK físico es `id`, 2NF se cumple por construcción; el UNIQUE evita duplicados lógicos.

**Sin violaciones de 2NF.**

## 3. Verificación 3NF (sin dependencias transitivas entre no-claves)

Se revisó cada atributo no clave: debe depender solo de la PK, no de otro atributo no clave.

| Entidad | Atributos evaluados | ¿Transitiva? | Resultado |
|---|---|---|---|
| cat_producto | sku, nombre, categoria_id, unidad, estado | categoría solo aporta FK; nombre/unidad dependen del producto | ✅ |
| sales_orden_venta_item | cantidad, precio_unitario, subtotal | subtotal depende de cantidad×precio de la **misma fila** (derivable); ver §4.1 | ✅ con nota |
| sales_orden_venta | total, canal, estado, fulfillment_* | total es suma de ítems (derivable); ver §4.1 | ✅ con nota |
| com_orden_compra | total | ídem | ✅ con nota |
| inv_inventario | stock_available, stock_reserved, stock_sold, version | dependen de la fila inventario (producto+sucursal) | ✅ |
| core_caja_pos | codigo | depende de la caja; sucursal es FK | ✅ |
| cat_precio | monto, moneda, vigencia | dependen de la sucursal+producto+vigencia (precios por sucursal, DB-P09 cerrado) | ✅ |
| pay_transaccion_pago | monto, estado, referencia_externa | monto copia el total de la orden al crear el pago; monto de pago no se deriva de estado — snapshot legítimo de intención de cobro | ✅ con nota |
| core_configuracion | clave, valor, descripcion | valor depende de la clave (PK) | ✅ |
| core_auditoria | datos_previos/nuevos | dependen de la fila de auditoría | ✅ |

**Sin violaciones de 3NF** (considerando UNIQUE sobre claves naturales de las tablas de detalle).

## 4. Desviaciones deliberadas (denormalización justificada)

Estas no son violaciones accidentales; se documentan y se aprueban explícitamente:

### 4.1 Columnas derivadas: `total` y `subtotal`
- **Dónde:** sales_orden_venta.total, sales_orden_venta_item.subtotal, com_orden_compra.total.
- **Por qué:** RF-080 (reportes operativos) y latencia p95 de consulta de venta; evitar SUM() en cada lectura de listado.
- **Riesgo de 3NF:** dependencia funcional derivada (subtotal = cantidad × precio_unitario).
- **Mitigación:** se recalcula solo en la misma transacción que modifica ítems; nunca se edita aisladamente. No es una dependencia transitiva entre no-claves independientes: es redundancia controlada.

### 4.2 Snapshot de precio en línea de orden
- sales_orden_venta_item.precio_unitario copia cat_precio al momento de la venta.
- **Correcto en 3NF:** el precio histórico de una venta no debe depender de cat_precio (cambiar el precio futuro no debe alterar ventas pasadas). RN-08 lo refuerza.

### 4.3 Referencias normalizadas en inventario
- inv_movimiento_inventario lleva producto_id y sucursal_id además de inventario_id (FK).
- Es redundante si inventario_id → (producto, sucursal) es funcional. Se conserva por trazabilidad directa de auditoría (RF-090) y para evitar JOIN obligatorio en consultas de movimiento. **Desviación documentada, aprobada en este paso.**

### 4.4 Datos serializados
- core_auditoria.datos_previos/datos_nuevos (texto serializado) y pay_outbox_evento.payload (JSON/TEXT).
- 3NF no prohíbe documentos serializados como valores de un atributo; el riesgo es que no sean consultables por columna. Se acepta porque: auditoría se consulta por (entidad, fecha) y outbox por (estado, creado_en); el contenido interno no filtra el modelo.

### 4.5 Entidades maestras con estado en lugar de historial de versiones
- cat_producto.estado, core_sucursal.estado: se desactiva, no se versiona. DB-P12 pendiente para soft-delete por entidad.

## 5. Conclusión

| Forma | Estado |
|---|---|
| 1NF | ✅ cumplida |
| 2NF | ✅ cumplida |
| 3NF | ✅ cumplida (con UNIQUE sobre claves naturales de detalle) |
| Denormalizaciones | §4.1–4.4 documentadas y justificadas por rendimiento/auditoría/histórico |

No se detectan anomalías de inserción/actualización/borrado no cubiertas por las notas de §4.

**Estado:** Paso 05 COMPLETADO → siguiente: Paso 06 (`05_modelo_fisico/seleccion_dbms.md`, skill `databases`).

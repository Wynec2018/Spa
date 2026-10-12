# Paso 04 — Modelo lógico

**Workflow:** 02_database_workflow — Paso 04
**Skill utilizado:** `database-schema-designer`
**Entrada:** `02_modelo_conceptual/modelo_conceptual.md`, `diagrama_er.md`
**Restricción:** atributos con dominio semántico (entero/decimal/cadena/fecha/booleano), PK/FK conceptuales, cardinalidades, sin sintaxis de motor (sin AUTO_INCREMENT, sin tipos MySQL/Postgres). Prefijos de módulo según decisión §3.

## 1. Criterios de diseño lógico

- Toda entidad recibe **PK surrogate** conceptual `id` salvo asociativas puras (`auth_usuario_rol`, `auth_rol_permiso`, `com_producto_promocion`) que usan PK compuesta.
- Toda entidad de negocio lleva campos de auditoría obligatorios (§9): `user_create`, `user_update`, `user_created_at`, `user_update_at`.
- Dinero: dominio `DECIMAL(12,2)` conceptual (validación de tipo exacto en Paso 07).
- Borrado físico prohibido en tablas transaccionales (RN-08); entidades maestras: estado activo/inactivo, soft-delete pendiente DB-P12.

## 2. Módulo auth (RF-001, RNF-030…033)

### auth_usuario
| Atributo | Dominio | Restricción |
|---|---|---|
| id | entero | PK |
| nombre | cadena | NOT NULL |
| email | cadena | NOT NULL, UNIQUE |
| hash_password | cadena | NOT NULL (Argon2id, §15) |
| estado | enum(activo,inactivo) | NOT NULL, default activo |
| user_create, user_update | referencia usuario | NOT NULL |
| user_created_at, user_update_at | fecha | NOT NULL |

### auth_rol
id PK; nombre NOT NULL UNIQUE; estado; auditoría.

### auth_permiso
id PK; codigo NOT NULL UNIQUE; descripcion; auditoría.

### auth_usuario_rol
PK compuesta (usuario_id, rol_id); FK a auth_usuario, auth_rol; auditoría.

### auth_rol_permiso
PK compuesta (rol_id, permiso_id); FK; auditoría.

### auth_token_blacklist (§15)
id PK; token_hash NOT NULL UNIQUE; expira_en NOT NULL; creado_en NOT NULL. Índice por expira_en para purga.

## 3. Módulo core (RF-002, RF-010)

### core_sucursal
id PK; nombre NOT NULL; direccion; estado; auditoría.

### core_caja_pos
id PK; sucursal_id FK→core_sucursal NOT NULL; codigo NOT NULL; estado; UNIQUE(sucursal_id, codigo); auditoría.

### core_cliente (DB-P08 abierto)
id PK; nombre/razon NOT NULL; identificacion UNIQUE parcial (declamar DB-P08); email; telefono; estado; auditoría. Atributos finales sujetos a DB-P08.

## 4. Módulo catálogo (RF-020)

### cat_categoria
id PK; nombre NOT NULL; categoria_padre_id FK→cat_categoria NULL; estado; auditoría. CHECK: no auto-referencia (id <> padre).

### cat_producto
id PK; sku NOT NULL UNIQUE; nombre NOT NULL; categoria_id FK NOT NULL; unidad NOT NULL; estado; auditoría.

### cat_precio (DB-P09 CERRADO: precios por sucursal)
id PK; sucursal_id FK NOT NULL; producto_id FK NOT NULL; monto DECIMAL(12,2) NOT NULL CHECK monto>=0; moneda NOT NULL; vigente_desde NOT NULL; vigente_hasta NULL; auditoría. UNIQUE(sucursal_id, producto_id, vigente_desde) para evitar dobles versiones simultáneas por sucursal (resuelto en Paso 08 + cierre DB-P09).

### cat_promocion
id PK; nombre NOT NULL; reglas (texto/JSON lógico); vigente_desde/hasta; estado; auditoría.

### com_producto_promocion (asociativa)
PK compuesta (producto_id, promocion_id); FK; auditoría.

## 5. Módulo compras (RF-030, RF-031, RN-05)

### com_proveedor
id PK; nombre NOT NULL; contacto; estado; auditoría; identificador lógico único (nombre o codigo — definir en Paso 08).

### com_orden_compra
id PK; numero NOT NULL UNIQUE; proveedor_id FK NOT NULL; estado enum; fecha_orden NOT NULL; total DECIMAL(12,2); auditoría.

### com_orden_compra_item
id PK; orden_id FK NOT NULL; producto_id FK NOT NULL; cantidad INT NOT NULL CHECK>0; precio_pactado DECIMAL(12,2) NOT NULL CHECK>=0; auditoría. UNIQUE(orden_id, producto_id).

### com_recepcion
id PK; orden_id FK NOT NULL; estado enum(draft,confirmada) NOT NULL; fecha NOT NULL; usuario_confirma FK→auth_usuario; auditoría. Solo estado confirmada mueve inventario (RN-05).

### com_recepcion_item
id PK; recepcion_id FK NOT NULL; producto_id FK NOT NULL; cantidad_recibida INT NOT NULL CHECK>0; auditoría.

## 6. Módulo inventario (RF-040…042, RF-062, RF-064, RN-01…RN-06, D-01)

### inv_inventario
| Atributo | Dominio | Restricción |
|---|---|---|
| id | entero | PK |
| producto_id | FK→cat_producto | NOT NULL, UNIQUE(producto_id, sucursal_id) |
| sucursal_id | FK→core_sucursal | NOT NULL |
| stock_available | entero | NOT NULL, CHECK >=0 |
| stock_reserved | entero | NOT NULL, CHECK >=0 |
| stock_sold | entero | NOT NULL, CHECK >=0 |
| version | entero | NOT NULL, default 0 (optimistic si se necesita) |
| auditoría | estándar | NOT NULL |

RN-02 se refuerza: `stock_available >= 0` y `stock_available` nunca cubierto por reservas; detalle de locking en Paso 12.

### inv_reserva (DB-P01/P02 CERRADOS 2026-09-22: bloqueo SÍ, TTL 15 min)
id PK; orden_id FK→sales_orden_venta NOT NULL; inventario_id FK NOT NULL; cantidad INT CHECK>0; estado enum(pending,confirmed,expired,cancelled) NOT NULL; expira_en NOT NULL (RN-03, umbral de core_configuracion `reserva_ttl_minutos=15`); creado_en; auditoría.

### inv_movimiento_inventario (append-only, RN-01, RF-090)
id PK; inventario_id FK NOT NULL; producto_id FK NOT NULL; sucursal_id FK NOT NULL; tipo enum(entrada,salida,reserva,liberacion,ajuste,devolucion,transferencia_salida,transferencia_entrada,recepcion) NOT NULL; cantidad INT NOT NULL CHECK<>0; referencia_tipo STRING; referencia_id entero; usuario_id FK NOT NULL; motivo TEXT; fecha NOT NULL; auditoría. **Sin UPDATE/DELETE de negocio** (solo columns de auditoría).

### inv_ajuste_stock (RN-04)
id PK; inventario_id FK NOT NULL; cantidad INT NOT NULL CHECK<>0; motivo TEXT NOT NULL; usuario_id FK NOT NULL; fecha NOT NULL; auditoría. Genera inv_movimiento_inventario tipo=ajuste.

### inv_transferencia (RN-06)
id PK; sucursal_origen_id FK NOT NULL; sucursal_destino_id FK NOT NULL CHECK(origen<>destino); estado enum(draft,in_transit,received,cancelled) NOT NULL; usuario_id FK NOT NULL; fechas; auditoría.

### inv_transferencia_item
id PK; transferencia_id FK NOT NULL; producto_id FK NOT NULL; cantidad INT CHECK>0; auditoría. UNIQUE(transferencia_id, producto_id).

## 7. Módulo ventas (RF-050, RF-051, RF-060…RF-064, RF-070, RN-07, RN-08)

### sales_orden_venta
| Atributo | Dominio | Restricción |
|---|---|---|
| id | entero | PK |
| numero | cadena | NOT NULL, UNIQUE |
| canal | enum(pos,web) | NOT NULL |
| cliente_id | FK→core_cliente | NULL en POS (DB-P08) |
| sucursal_id | FK→core_sucursal | NOT NULL |
| estado | enum | NOT NULL (pending/pagada/confirmada/cancelada/devuelta) |
| total | DECIMAL(12,2) | NOT NULL CHECK>=0 |
| idempotency_key | cadena | UNIQUE, NOT NULL en web (RN-07) |
| fulfillment_type | enum(entrega,retiro) | NULL en POS; DB-P10 |
| fulfillment_direccion / fecha_entrega | cadena/fecha | NULL; DB-P10 |
| creado_en / confirmado_en | fecha | auditoría estándar |

### sales_orden_venta_item
id PK; orden_id FK NOT NULL; producto_id FK NOT NULL; cantidad INT CHECK>0; precio_unitario DECIMAL(12,2) CHECK>=0; subtotal DECIMAL(12,2); auditoría. UNIQUE(orden_id, producto_id).

### sales_carrito_web (B-3 CONFIRMADO 2026-09-22: persiste en BD)
id PK; cliente_id FK NULL; sesion_key UNIQUE NOT NULL; actualizado_en; auditoría. **TTL de purga = parámetro abierto** (no inventar).

### sales_carrito_web_item
id PK; carrito_id FK NOT NULL; producto_id FK NOT NULL; cantidad CHECK>0; auditoría. UNIQUE(carrito_id, producto_id).

### sales_devolucion (RF-070, RN-08)
id PK; orden_original_id FK→sales_orden_venta NOT NULL; cliente_id FK NULL; usuario_id FK NOT NULL; estado enum; motivo TEXT; monto DECIMAL(12,2); fechas; auditoría. **Registro nuevo; no muta la orden original.**

### sales_devolucion_item
id PK; devolucion_id FK NOT NULL; producto_id FK NOT NULL; producto_orden_item_id FK→sales_orden_venta_item NOT NULL; cantidad CHECK>0; monto DECIMAL(12,2); auditoría.

## 8. Módulo pagos (RF-063, D-02, D-03, §16)

### pay_transaccion_pago
id PK; orden_id FK NOT NULL; proveedor_pago STRING NOT NULL; referencia_externa STRING NOT NULL; monto DECIMAL(12,2) CHECK>0; estado enum(initiated,authorized,failed,refunded,compensated) NOT NULL; idempotency_key STRING NOT NULL UNIQUE; fechas; auditoría. UNIQUE(proveedor_pago, referencia_externa).

### pay_outbox_evento (§16)
id PK; agregado_tipo STRING NOT NULL; agregado_id entero NOT NULL; evento_tipo STRING NOT NULL; payload JSON/TEXT NOT NULL; estado enum(pending,processing,processed,failed) NOT NULL; claimed_at fecha NULL; correlation_id STRING NOT NULL; creado_en; procesado_en; reintentos INT default 0. **Insert en la misma transacción de dominio.**

### core_idempotency_key (D-03)
key STRING PK (UUID); operacion STRING NOT NULL; respuesta TEXT; expiracion NOT NULL (retención 30 días inicial, validar Paso 07); creado_en NOT NULL.

## 9. Módulo sistema (RF-080, RF-090, RF-100, RNF-050)

### core_configuracion (RF-100, RNF-002)
id PK; clave STRING NOT NULL UNIQUE; valor TEXT NOT NULL; descripcion; user_update; user_update_at. (Auditoría de creación igual que estándar.)

### core_auditoria (RF-090)
id PK; accion STRING NOT NULL; entidad_tipo STRING NOT NULL; entidad_id entero NOT NULL; datos_previos TEXT; datos_nuevos TEXT; usuario_id FK NOT NULL; motivo TEXT; fecha NOT NULL; correlation_id STRING. Solo INSERT.

### Índices lógicos (se materializan en Paso 11)
Todas las FK serán indexadas; además: inv_inventario(producto_id, sucursal_id) UNIQUE, sales_orden_venta(numero) UNIQUE, pay_outbox_evento(estado, creado_en), core_auditoria(entidad_tipo, entidad_id, fecha).

## 10. Trazabilidad resumen

| Módulo | RF principales | RN principales |
|---|---|---|
| auth | RF-001 | — |
| core | RF-002, RF-010, RF-100 | RN-07 (config) |
| catálogo | RF-020 | — |
| compras | RF-030, RF-031 | RN-05 |
| inventario | RF-040…042, RF-062, RF-064 | RN-01…RN-04, RN-06 |
| ventas | RF-050, RF-051, RF-060…064, RF-070 | RN-07, RN-08 |
| pagos | RF-063 | RN-07 (idempotencia) |
| sistema | RF-080, RF-090 | RN-08 |

## 11. Criterios de salida

- [x] Toda entidad del E-R con atributos y dominios semánticos.
- [x] PK/FK/cardinalidades definidos; PK compuesta en asociativas.
- [x] Auditoría estándar en todas las tablas de negocio.
- [x] Pendientes DB-P01…DB-P12 y supuesto del carrito declarados.
- [x] Sin SQL ni tipos de motor concreto.

**Estado:** Paso 04 COMPLETADO → siguiente: Paso 05 (`04_normalizacion/informe_normalizacion.md`).

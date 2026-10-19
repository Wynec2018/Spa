# Paso 11 — Índices y rendimiento

**Workflow:** 02_database_workflow — Paso 11
**Skills:** `database-schema-designer`, `databases` (+ `mysql-patterns` de Paso 07). **`postgresql-table-design` NO aplica** (DBMS = MySQL).
**Entrada:** `05_modelo_fisico/modelo_fisico.md` (§3–4), `02_configuracion/perfil_carga.yaml`, `08_auditoria/auditoria.md`, RF/RNF
**Regla del workflow:** todo índice vinculado a una consulta o patrón de acceso.
**Objetivo:** catálogo de índices por patrón + huecos + política de operación. **Sin DDL.**

## 1. Carga de referencia

| Parámetro | Valor | Fuente |
|---|---|---|
| tx/hora objetivo | 25.000 (pico ×3.0 → ~75.000 tx/h) | perfil_carga |
| 10x a evaluar | 250.000 tx/h | perfil_carga, RNF-011 |
| Sucursales / cajas | 8 / 6 (48 POS) | perfil_carga |
| Concurrentes internos / web | 180 / 1.200 | perfil_carga |
| p95 consulta producto / stock | ≤ 500 ms | perfil_carga |
| p95 confirmar venta (sin pago externo) | ≤ 1.200 ms | perfil_carga |
| Disponibilidad | 99.9% | perfil_carga |

Horas pico ≈ 75.000/3600 ≈ 21 tx/s en diseño inicial; 10x ≈ 210 tx/s. Índices OLTP puntuales por PK/UNIQUE/FK son el grueso del tráfico; agregaciones RF-080 se dimensionan aparte (§5).

## 2. Catálogo índice → patrón de acceso

Cada entrada: **consulta/patrón → índice**. Estados: `OK` = ya en modelo_fisico; `GAP` = añadir (Paso 13/15); `RED` = posible redundante (revisar con EXPLAIN).

### 2.1 Auth / sesión

| # | Patrón / consulta | RF/RNF | Índice | Estado |
|---|---|---|---|---|
| A1 | Login por email | RF-001, RNF-030 | `uq_auth_usuario_email (email)` | OK |
| A2 | Validar JWT revocado: `WHERE token_hash = ?` | RNF-030 | `uq_auth_token_blacklist_hash (token_hash)` | OK |
| A3 | Purga de blacklist vencida: `WHERE expira_en < NOW()` | retención | `idx_auth_token_blacklist_expira (expira_en)` | OK |
| A4 | Permisos de usuario: join `usuario→rol→permiso` | RF-001 | PK compuestas + `idx_…_rol`, `idx_…_permiso` | OK |

### 2.2 Catálogo / precio (consulta producto p95 ≤ 500 ms)

| # | Patrón / consulta | RF | Índice | Estado |
|---|---|---|---|---|
| C1 | Lookup SKU (búsqueda/venta) | RF-020 | `uq_cat_producto_sku (sku)` | OK |
| C2 | Árbol de categorías / productos por categoría | RF-020 | `idx_cat_producto_categoria`, `idx_cat_categoria_padre` | OK |
| C3 | Precio vigente: `WHERE sucursal_id=? AND producto_id=? AND vigente_desde<=NOW() AND (vigente_hasta IS NULL OR vigente_hasta>NOW())` | RF-020 | `uq_cat_precio_sucursal_producto_desde (sucursal_id, producto_id, vigente_desde)` (DB-P09 cerrado: lookup por sucursal) | OK (índice de 3 col antiguo eliminado — redundante con prefijo del UNIQUE) |
| C4 | Productos en promoción activa (lista) | RF-020 | `idx_com_producto_promocion_promo (promocion_id)` | OK |
| C5 | ~~¿DB-P09 precio por sucursal?~~ | RF-020 | **CERRADO 2026-09-22**: precio por sucursal, sin rediseño adicional | **OK** |

### 2.3 Inventario (stock p95 ≤ 500 ms; RNF-005/020)

| # | Patrón / consulta | RF | Índice | Estado |
|---|---|---|---|---|
| I1 | Stock actual producto×sucursal: `WHERE producto_id=? AND sucursal_id=?` | RF-040 | `uq_inv_inventario_producto_sucursal` | OK |
| I2 | Stock por sucursal (reposición, listado caja) | RF-040 | `idx_inv_inventario_sucursal (sucursal_id)` | OK |
| I3 | Historial stock por par: `inventario_id + fecha` | RF-041, RF-090 | `idx_inv_mov_inventario (inventario_id, fecha)` | OK |
| I4 | Auditoría/consulta por producto en rango fechas | RF-090, RF-080 | `idx_inv_mov_producto_fecha (producto_id, fecha)` | OK |
| I5 | Movimientos por sucursal/día (cierre caja) | RF-080 | `idx_inv_mov_sucursal_fecha (sucursal_id, fecha)` | OK |
| I6 | Movimientos por usuario (investigación) | RF-090 | `idx_inv_mov_usuario` | OK |
| I7 | Reservas activas por orden / expiración de reserva (job) | D-01, RF-062 | `idx_inv_reserva_orden`, `idx_inv_reserva_estado_expira (estado, expira_en)` | OK |
| I8 | Ajustes por inventario / usuario | RF-041 | `idx_inv_ajuste_*` | OK |
| I9 | Transferencias por origen/destino | RF-041 | `idx_inv_trans_origen/destino` | OK |
| I10 | **¿Purga / agregados diarios por producto+fecha amplio?** | RF-080 | evaluar `(producto_id, fecha)` ya OK; partición NO (10x aún sin particionar — regla skill: no pre-particionar sin datos) | **GAP diferido**: considerar particionado solo si 10x lo exige |

### 2.4 Ventas POS/web (venta p95 ≤ 1.200 ms; RNF-022, RF-051/064)

| # | Patrón / consulta | RF | Índice | Estado |
|---|---|---|---|---|
| V1 | `numero` de orden (búsqueda ticket) | RF-050 | `uq_sales_orden_numero` | OK |
| V2 | Reintento idempotente de orden: `idempotency_key=?` | RF-042, RNF-022 | `uq_sales_orden_idempotency` | OK |
| V3 | Órdenes por cliente (historial web/CRM) | RF-002, RF-070 | `idx_sales_orden_cliente` | OK |
| V4 | Órdenes por sucursal / día (cierre, RF-080) | RF-080 | hoy: `idx_sales_orden_sucursal` + `idx_sales_orden_estado_fecha (estado, creado_en)` | **GAP**: compuesto `(sucursal_id, creado_en)` para `WHERE sucursal_id=? AND creado_en BETWEEN …` sin barajar índice de estado |
| V5 | Ítems de orden (`orden_id`) | RF-050 | UNIQUE `(orden_id, producto_id)` | OK |
| V6 | “¿Este producto se vendió en periodo X?” (RF-080) | RF-080 | `idx_sales_item_producto` — **sin fecha**; el UNIQUE es `(orden_id, producto_id)`, no `(producto_id, fecha)` | **GAP**: índice `(producto_id)` insuficiente para rango temporal sin join a orden; añadir join a orden y medir; `(producto_id, orden_id)` no mejora rango de fechas. Opción: índice compuesto en ítem **no** resuelve fecha (fecha está en orden). Dejar como: query join `item.producto_id` + `orden.creado_en` con índices existentes; **GAP solo si EXPLAIN muestra filesort/temp** → candidato `sales_orden_venta_item(producto_id)` ya existe (OK) |
| V7 | Carrito por sesión (p95 carrito web) | RF-060 | `uq_sales_carrito_sesion` | OK |
| V8 | Ítems del carrito | RF-060 | UNIQUE `(carrito_id, producto_id)` | OK |
| V9 | **Purga de carritos inactivos** (`actualizado_en < ahora - TTL`) | RF-060 | ninguno | **GAP**: `idx_sales_carrito_actualizado (actualizado_en)` |
| V10 | Devoluciones por orden original / cliente / usuario | RF-070 | `idx_sales_dev_orden/cliente/usuario` | OK |
| V11 | Ítems de devolución → orden_item | RF-070 | `idx_sales_dev_item_orden_item` | OK |

### 2.5 Pagos (RNF-022, RF-063)

| # | Patrón / consulta | RF | Índice | Estado |
|---|---|---|---|---|
| P1 | Idempotencia de pago | RF-042 | `uq_pay_pago_idempotency` | OK |
| P2 | Idem. proveedor (`proveedor_pago, referencia_externa`) — webhook | RF-063 | `uq_pay_pago_proveedor_ref` | OK |
| P3 | Pagos de una orden | RF-050 | `idx_pay_pago_orden` | OK |
| P4 | Barrido outbox `estado='pending'` ordenado por fecha | D-04, RNF-052 | `idx_pay_outbox_estado_fecha (estado, creado_en)` | OK |
| P5 | Correlación evento | RNF-052 | `idx_pay_outbox_correlation` | OK |
| P6 | Purga idempotency vencidas (job 30 días) | D-03 | `idx_core_idem_expiracion (expiracion)` | OK |

### 2.6 Compras / recepción (RF-030/031)

| # | Patrón | RF | Índice | Estado |
|---|---|---|---|---|
| K1 | OC por número / proveedor | RF-030 | `uq_…_numero`, `idx_…_proveedor` | OK |
| K2 | Ítems OC por orden/producto | RF-030 | UNIQUE + `idx_…_producto` | OK |
| K3 | Recepción por orden / recepción / producto | RF-031 | `idx_com_recepcion_*`, `idx_com_recepcion_item_*` | OK |

### 2.7 Auditoría, config, clientes (RF-090/100/002, RNF-050)

| # | Patrón | RF | Índice | Estado |
|---|---|---|---|---|
| U1 | Historial de una entidad: `entidad_tipo, entidad_id, fecha` | RF-090 | `idx_core_auditoria_entidad` | OK |
| U2 | Acciones por usuario / rango global | RF-090 | `idx_core_auditoria_usuario`, `…_fecha` | OK |
| U3 | Config por clave | RF-100 | `uq_core_config_clave` | OK |
| U4 | Cliente por identificación / email | RF-002 | UNIQUE ident, `idx_…_email` | OK |
| U5 | Caja POS por sucursal+código | RF-010 | `uq_core_caja_pos_…` | OK |

### 2.8 Consolidado de GAPS (accionables en Paso 13/15)

| ID | Índice propuesto | Justificación (patrón) | Prioridad |
|---|---|---|---|
| G1 | `sales_orden_venta (sucursal_id, creado_en)` | cierre diario / RF-080 por sucursal (V4) | **alta** |
| G2 | `sales_carrito_web (actualizado_en)` | job de purga de carritos (V9) | media (si hay TTL) |
| G3 | ~~(opcional) revisar `cat_precio` idx de 3 col~~ | ~~posible RED con UNIQUE (C3)~~ | **resuelto 2026-09-22**: idx antiguo eliminado; UNIQUE 3-col es el índice definitivo |

**No añadir “por si acaso”:** skill `databases` — cada índice cuesta INSERT/UPDATE (escritura a 75k tx/h pico). Solo G1 y G2 (si existe el job de TTL de carrito).

## 3. Patrones de escritura y costo de índice

| Tabla de escritura caliente | # índices no-PK (hoy) | Nota |
|---|---|---|
| `inv_movimiento_inventario` | 4 FK/consulta | aceptable; append-only, indexación inevitable |
| `inv_inventario` | 1 UNIQUE + 1 KEY | escritura stock en camino crítico venta (p95 1200 ms) |
| `sales_orden_venta` | +G1 → 5 no-PK | G1 añade ~1 B-tree write por orden — despreciable vs costo de consultar sin él |
| `pay_outbox_evento` | 2 | barrido prioriza `(estado, creado_en)` |
| `core_auditoria` | 3 | crece con RF-090; retención afecta tamaño de índice de `fecha` (DB-P05) |

## 4. Configuración MySQL de rendimiento (hereda Paso 06 §5)

| Parámetro | Valor / guía | Por qué |
|---|---|---|
| `innodb_buffer_pool_size` | ≈70% RAM | hot set de índices+datos en memoria (skill databases) |
| `innodb_buffer_pool_instances` | ≥2 si pool ≥1GB | reduce contención LRU |
| `innodb_flush_log_at_trx_commit` | `1` | durabilidad (coincide integridad/decisión) |
| `innodb_file_per_table` | `ON` | ibd por tabla, buffer pool compartido |
| `innodb_redo_log_capacity` | default+ según pico 10x | evitar stalls en ráfagas |
| `innodb_io_capacity(_max)` | según disco (SSD ≥1000/2000) | writeback |
| `query_cache` | **inexistente en 8.x** | no considerar |
| `temptable_max_ram` / `internal_tmp_mem_storage_engine` | default InnoDB/memory | evitar filesort a disco en reportes |
| `max_connections` | ≥ pool_app + dba + backups (margen 10x) | 1200 web concurrentes no = 1200 conexiones (pool en app) |
| `slow_query_log` | ON, `long_query_time` ≈ 0.2–0.5 s en staging; ≈1 s en prod | detectar regresiones vs p95 500/1200 ms |
| `innodb_stats_on_metadata` | OFF (default) | evitar latencia al tocar metadatos |
| Estadísticas | `innodb_stats_persistent ON` (default) | plan estables |

## 5. Reportes RF-080 (no romper el OLTP)

- Definir consultas de cierre/reportes **por fecha + sucursal** para que usen G1 e índices `*_fecha` (I3–I5).
- Si los reportes pesados compiten con POS: en fase DevOps considerar réplica lectura (**DB-P07**, ya contemplada en decisiones) o vistas agregadas; **no** crear índices columnstore/tablas de resumen en este paso sin medición.
- AGG por mes×sucursal×categoría: solo si EXPLAIN en staging lo exige (regla: índice según dato real, no proyección).

## 6. Política de medición (gate antes de prod)

1. **EXPLAIN (FORMAT=TREE)** de cada patrón de §2 en staging con datos sembrados al tamaño real.
2. Rechazar: full scan en tablas > ~10k filas para patrones OLTP; `Using filesort` en checkout/stock.
3. Slow query log → lista blanca de queries aceptadas vs p95 (perfil_carga).
4. Re-correr con dataset ×10 (RNF-011) y confirmar plan estable (stats persistentes).
5. Resultados → Paso 14 (revision_dba) y ajuste de G1/G2.

## 7. Trazabilidad

| RF/RNF | Soporte de índice |
|---|---|
| RNF-001/004/011 | buffer pool, planes estables, gate ×10 (§4, §6) |
| RNF-005/020 | I1, I7 (locks cortos vía lookup rápido) |
| RNF-022 / RF-042 | UNIQUE idempotencia orden/pago (V2, P1, P2) |
| RF-040/041/051 | §2.3 |
| RF-050/060/064 | §2.4 |
| RF-070/080/090/100 | V4/G1, §2.7 |
| RNF-050/051/052 | slow log, outbox, correlation (§4, P4/P5) |
| D-03/D-04 | P1/P6/P4/P5 |

## 8. Pendientes (sin inventar)

- **DB-P07** réplica → redistribución de lecturas (§5).
- ~~**DB-P09** rediseño precio~~ → **CERRADO 2026-09-22** (precios por sucursal, C5 OK).
- **DB-P05** retención `core_auditoria` → tamaño/retención idx `fecha` (U2).
- TTL real de purga de carritos (RF-060 no lo define) → confirma G2.
- Datos reales de EXPLAIN → Paso 14/16; este documento es diseño estático de índices.

## 9. Criterios de salida

- [x] Cada índice del modelo mapeado a un patrón/consulta (§2).
- [x] Gaps acotados: G1 (alta), G2 (media), RED opcional (§2.8) — sin índice especulativo.
- [x] Carga p95 y 10x usados como gate de diseño (§1, §6).
- [x] Config InnoDB de rendimiento consolidada (§4).
- [x] RF-080 separado del camino crítico OLTP (§5).
- [x] `postgresql-table-design` no aplicable; sin DDL ejecutado.
- [x] Pendientes DB-P* marcados (§8).

**Estado:** Paso 11 COMPLETADO → siguiente: Paso 12 (`11_transacciones_concurrencia/transacciones_concurrencia.md`).

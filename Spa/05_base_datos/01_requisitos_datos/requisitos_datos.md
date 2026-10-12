# Paso 01 — Lectura y trazabilidad de requisitos de datos

**Workflow:** 02_database_workflow — Ingeniería de base de datos
**Skill utilizado:** `database-schema-designer` (instalado en `.agents/skills/`)
**Proyecto:** Sistema Tienda Omnicanal de Alimentos
**Fecha:** 2026-09-22
**Restricción de este paso:** no se crea SQL. Solo trazabilidad requisitos → modelo futuro.

## 1. Fuentes leídas

| Fuente | Archivos |
|---|---|
| Contexto | `00_contexto/descripcion.md`, `alcance.md`, `reglas_negocio.md`, `restricciones.md` |
| Requisitos | `01_requisitos/RF.md`, `RNF.md`, `criterios_aceptacion.md` |
| Configuración | `02_configuracion/perfil_carga.yaml` |
| Resultados previos | `03_resultados/04_database_analysis.md`, `08_recomendacion_final.md` |
| Decisiones | `04_decisiones/decisiones_base_datos.md` (lectura obligatoria previa) |

## 2. Regla de resolución de conflictos

- Toda decisión con estado **APROBADO** en `decisiones_base_datos.md` es vinculante.
- Si `08_recomendacion_final.md` y `decisiones_base_datos.md` difieren (p. ej. mención de PostgreSQL frente a MySQL 8.x APROBADO), prevalece `decisiones_base_datos.md` para la fase de base de datos.
- Las decisiones **PENDIENTE** (DB-P01…DB-P12) se modelan como parámetros configurables; no se inventan respuestas.

## 3. Inventario de requisitos → necesidades de datos

### 3.1 Trazabilidad RF → entidades/estructuras del modelo

| RF | Descripción | Entidades / estructuras derivadas |
|---|---|---|
| RF-001 | Usuarios, roles y permisos | `auth_users`, `auth_roles`, `auth_permissions`, relación usuario↔rol↔permiso |
| RF-002 | Administrar clientes | `customers` (modelo definido bajo DB-P08) |
| RF-010 | Sucursales y cajas/POS | `stores`, `pos_registers` |
| RF-020 | Productos, categorías, precios, promociones | `products`, `categories`, `prices`, `promotions` (alcance de precios bajo DB-P09) |
| RF-030 | Proveedores y órdenes de compra | `suppliers`, `purchase_orders`, `purchase_order_items` |
| RF-031 | Recepción parcial/total de mercadería | `purchase_receptions`, `purchase_reception_items` (RN-05) |
| RF-040 | Inventario por producto y sucursal | `inventory` (product_id, store_id, stock_available, stock_reserved, stock_sold, version) |
| RF-041 | Entradas, salidas, reservas, ajustes, transferencias | `inventory_movements` (append-only), `inventory_reservations`, `stock_adjustments`, `store_transfers`, `store_transfer_items` |
| RF-042 | Evitar doble descuento ante reintentos | `idempotency_keys` + UNIQUE (order_number, payment_reference) |
| RF-050 | Ventas presenciales y pagos | `sales_orders`, `sales_order_items`, `payments_transactions` |
| RF-051 | Descontar inventario al confirmar venta | movimiento en `inventory_movements` dentro de la misma transacción |
| RF-060 | Catálogo, carrito y pedido web | `sales_orders` con `channel = web`, `web_carts`/`web_cart_items` (carrito; ver §6) |
| RF-061 | Entrega o retiro en sucursal | `sales_orders.fulfillment_type`, dirección/fecha (detalles bajo DB-P10) |
| RF-062 | Validar/reservar stock antes de confirmar | `inventory_reservations` (status, expires_at; RN-03) |
| RF-063 | Pagos con interfaz desacoplada | `payments_transactions` + `outbox_events` (patrón Outbox APROBADO) |
| RF-064 | Sin sobreventa web vs físico | `inventory` con available/reserved + lock (D-01) |
| RF-070 | Devoluciones parciales/totales | `return_orders`, `return_order_items` (nueva transacción ligada a orden original; RN-08) |
| RF-080 | Reportes operativos | consultas sobre OLTP + estrategia de agregados/vistas (§ Decisión 13, con condición) |
| RF-090 | Auditar operaciones críticas | `audit_log` (+ triggers), `inventory_movements`, campos de auditoría obligatorios |
| RF-100 | Parámetros operativos sin redeploy | `system_config` (key, value, updated_at, updated_by) |

### 3.2 Trazabilidad RNF → propiedades exigidas al modelo

| RNF | Exigencia sobre datos | Respuesta prevista en fases siguientes |
|---|---|---|
| RNF-001 | 25.000 tx/hora | dimensionado de tipos, índices (Paso 11), pooling (§19) |
| RNF-002 | Capacidad configurable externamente | `system_config` |
| RNF-003/004 | tx ≠ sentencia SQL; picos ×3.0 | supuestos de carga en diseño físico y pruebas |
| RNF-005 | Integridad bajo concurrencia POS+web | locks de fila, available/reserved (Paso 12) |
| RNF-010/011 | Crecer sucursales/cajas sin rediseño; evaluar 10x | claves FK por sucursal, sin hardcodeo de tiendas |
| RNF-020 | Sin sobreventa | D-01 + CHECK/locks (Paso 08/12) |
| RNF-021 | Fronteras transaccionales dinero/inventario | ACID interno + Saga pagos (D-02) |
| RNF-022 | Sin duplicados ante reintentos | idempotency_keys + UNIQUE (D-03) |
| RNF-030…035 | Authz mínimo privilegio, TLS, hashes, secretos fuera de repo | Paso 09 seguridad; Argon2id (§15) |
| RNF-040/041 | Backup/restauración; RPO/RTO antes de producción | Paso 07/09; RPO/RTO aún `null` en perfil_carga (DB-P05) |
| RNF-042 | Timeouts/reintentos ante dependencias externas | Outbox + estados de pago (§16) |
| RNF-050…052 | Logs estructurados, métricas, correlación | audit_log, outbox con correlation_id (Paso 10) |
| RNF-060…062 | Linux, contenerizable, config separada por entorno | MySQL 8.x; `.env`/Docker Secrets (§14) |

### 3.3 Trazabilidad RN → reglas que el modelo debe garantizar

| RN | Regla | Mecanismo esperado (detalle en pasos 08/12) |
|---|---|---|
| RN-01 | Toda venta confirmada tiene origen de inventario | `inventory_movements` ligado a orden (FK + NOT NULL) |
| RN-02 | Stock no negativo por venta normal | columnas con CHECK ≥ 0 + `SELECT ... FOR UPDATE` |
| RN-03 | Reservas expiran por parámetro | `expires_at` + job; umbral en `system_config` |
| RN-04 | Ajustes con usuario, motivo, fecha y auditoría | `stock_adjustments` NOT NULL usuario/motivo + audit_log |
| RN-05 | Recepción actualiza inventario solo al confirmarse | estados de `purchase_receptions` |
| RN-06 | Transferencias con estados controlados | enum de estados en `store_transfers` |
| RN-07 | Operaciones críticas idempotentes | idempotency_keys + UNIQUE (D-03) |
| RN-08 | Transacción confirmada no se elimina físicamente | sin DELETE físico en tablas transaccionales; FK con RESTRICT |

### 3.4 Restricciones y criterios aplicables

- Linux, contenerizable, sin secretos en repo, tecnologías con mantenimiento activo (`restricciones.md`).
- CA-01: cada decisión mapeada a RF/RNF. CA-04: DBMS sin sesgo (evaluación real en Paso 06). CA-09: no introducir Kubernetes/microservicios sin justificación.
- Carga: 25.000 tx/h, pico ×3.0, crecimiento 10x, 8 sucursales, 6 cajas/sucursal, p95 stock/consulta ≤ 500 ms, venta sin pago externo ≤ 1200 ms, disponibilidad 99.9%.

## 4. Decisiones APROBADO que condicionan este diseño

Tomadas de `04_decisiones/decisiones_base_datos.md` (resumen; fuente autoritativa):

1. Persistencia relacional transaccional (integridad referencial, ACID, locks por fila).
2. Motor **MySQL 8.x/InnoDB** (alternativas consideradas: PostgreSQL, MySQL+InnoDB, PostgreSQL+Redis). Redis NO APLICA INICIALMENTE.
3. Una única instancia/base de datos; separación modular por prefijo de nombres de tablas (`auth_`, `inventory_`, `sales_`, `payments_`).
4. Concurrencia de inventario: reserva temporal + available/reserved + lock breve (APROBADO CON CONDICIÓN — DB-P01…P03).
5. ACID interno + Saga para pagos externos.
6. Idempotency Key + UNIQUE (retención inicial 30 días, validar en Paso 07).
7. Integridad en base de datos (PK/FK/UNIQUE/CHECK/NOT NULL/DEFAULT), trazable a RF/RN.
8. Auditoría + `inventory_movements` append-only; campos obligatorios en TODAS las tablas: `user_create`, `user_update`, `user_created_at`, `user_update_at`.
9. No eliminar físicamente transacciones confirmadas; devoluciones como registros nuevos (`return_orders`).
10. Outbox en la misma transacción de dominio.
11. `system_config` para RF-100.
12. Backup completo + binlog (RPO < 1 h / RTO < 4 h provisionales, DB-P05).
13. Índices derivados de patrones de acceso; particionamiento a evaluar superado ~1.000.000 de filas.
14. Seguridad: secretos en `.env`/Docker Secrets, TLS, Argon2id, `token_blacklist`.

## 5. Candidatas de entidades confirmadas vs pendientes

Las estructuras ya propuestas en el análisis previo (inventory, reservations, orders, payments, token_blacklist, system_config, inventory_movements, idempotency_keys, outbox) se **validan como candidatas**; el proceso formal (conceptual → lógico → normalización → físico) las completará o corregirá contra todos los RF/RNF.

Nuevas obligaciones detectadas en esta lectura y aún no presentes en el análisis previo:

- `purchase_*` (RF-030/031): proveedores, órdenes de compra, recepciones.
- `store_transfers` (RF-041, RN-06).
- `return_orders`/`return_order_items` (RF-070, RN-08, §22).
- `web_carts`/`web_cart_items` (RF-060) — **confirmado 2026-09-22: carrito persiste en BD** (TTL de purga sigue como parámetro, no inventar).
- `stock_adjustments` con motivo/usuario (RN-04).
- `pos_registers` (RF-010).
- Catálogo/precios/promociones (RF-020).

## 6. Decisiones pendientes que el diseño NO debe inventar

Mantener explícitas (DB-P01…DB-P12 de `decisiones_base_datos.md` §23), en especial las que tocan datos:

| ID | Pendiente | Incidencia en modelado |
|---|---|---|
| DB-P01 | ~~¿La reserva web bloquea stock para POS?~~ → **CERRADO: SÍ** | semántica de `stock_reserved` (bloqueo al crear reserva) |
| DB-P02 | ~~Comportamiento al expirar reserva~~ → **CERRADO: TTL 15 min** | estados y job de `inventory_reservations` |
| DB-P03 | Máximo de productos por transferencia | granularidad de locks |
| DB-P04 | Proveedor(es) de pago iniciales | estados Saga/Outbox |
| DB-P05 | RPO/RTO | estrategia de backup |
| DB-P06 | POS offline | sincronización/conflictos |
| DB-P07 | Latencia de reportes | agregados vs consulta directa |
| DB-P08 | Modelo definitivo de clientes | claves/alcance de `customers` |
| DB-P09 | ~~Precios globales vs por sucursal~~ → **CERRADO: por sucursal** | diseño de `prices` (`sucursal_id NOT NULL`) |
| DB-P10 | Fulfillment: entrega vs retiro | campos de `sales_orders` |
| DB-P11 | Herramienta de migraciones | Paso 13 |
| DB-P12 | Estrategia de eliminación por entidad maestra | soft delete vs estado vs vigencia |

Además, sin resolver en fuentes: ¿el carrito web persiste en BD? (RF-060 no lo especifica) — se modelará en Paso 02 y se marcará como supuesto o pendiente, no como decisión.

## 7. Criterios de salida de este paso

- [x] RF, RN, RNF, restricciones y decisiones leídos.
- [x] Trazabilidad RF → entidades, RNF → propiedades, RN → reglas.
- [x] Decisiones aprobadas respetadas; pendientes declaradas.
- [x] Sin SQL generado.

**Estado:** Paso 01 COMPLETADO → siguiente: Paso 02 (modelo conceptual).

# Paso 08 — Integridad

**Workflow:** 02_database_workflow — Paso 08
**Skill utilizado:** `database-schema-designer`
**Entrada:** `05_modelo_fisico/modelo_fisico.md`, `03_modelo_logico/modelo_logico.md`
**Objetivo:** política de integridad referencial, de dominio y de negocio; sin SQL de migración.

## 1. Capas de integridad

| Capa | Mecanismo | Cuándo |
|---|---|---|
| Dominio | tipos (`DECIMAL(12,2)`, `INT UNSIGNED`), `NOT NULL`, `CHECK` | toda fila |
| Referencial | `FOREIGN KEY` + `ON DELETE` | toda relación |
| Única | `UNIQUE` (explícita o PK) | claves naturales y de negocio |
| Transaccional | ACID InnoDB, `START TRANSACTION … COMMIT` | escrituras multi-tabla |
| Aplicación | validaciones de negocio (RN) | antes/tras la transacción |

**Regla:** la app no sustituye a la BD en lo que la BD puede garantizar (tipos, unicidad, FK, CHECK).

## 2. Integridad de dominio (resumen; detalle en modelo_fisico)

- Dinero: `DECIMAL(12,2)`; `monto/total/subtotal/precio >= 0` (CHECK); `pago.monto > 0`.
- Cantidades/stock: `INT UNSIGNED`; ítems `> 0`; `inv_movimiento_inventario.cantidad <> 0`; stock `>= 0`.
- Fechas: `DATETIME` (UTC por app).
- Cadenas: `VARCHAR(n)` con `n` acotado (email 254, sku 64, clave 80, correlación 64).

## 3. Integridad referencial (FK → `ON DELETE`)

**Política general:** `ON DELETE RESTRICT` en todo el modelo. Motivo: RN-08 prohíbe borrado físico de transaccionales; las maestras no deben borrarse si tienen historial. Ninguna FK usa `CASCADE` de borrado.

| Hijos → Padres | ON DELETE | Razón |
|---|---|---|
| detalle → orden/OC/recepción/transferencia | RESTRICT | histórico inmutable |
| línea → producto/categoría/sucursal/proveedor/cliente | RESTRICT | no romper reportes |
| inventario → producto/sucursal | RESTRICT | stock referencial |
| auditoría/movimiento → usuario | RESTRICT | trazabilidad |
| carrito → cliente | RESTRICT | cliente no se borra; se inactiva |
| `cat_categoria.categoria_padre_id` | RESTRICT + CHECK no self | jerarquía sin ciclos auto (ciclos profundos: ver §5) |

`cliente_id` en POS es nullable → `ON DELETE RESTRICT` sobre FK nullable aún aplica si existe fila; no se usa `SET NULL` porque el historial de ventas exige conservar la referencia (RN-08/RF-090).

## 4. Integridad única (UNIQUE / PK)

| Tabla | Restricción única | RN/RF |
|---|---|---|
| auth_usuario | email | RF-001 |
| auth_rol | nombre | — |
| auth_permiso | codigo | — |
| auth_usuario_rol | (usuario_id, rol_id) PK | — |
| auth_rol_permiso | (rol_id, permiso_id) PK | — |
| auth_token_blacklist | token_hash | §15 |
| core_caja_pos | (sucursal_id, codigo) | RF-010 |
| core_cliente | identificacion (nullable) | DB-P08 |
| cat_producto | sku | RF-020 |
| cat_precio | (sucursal_id, producto_id, vigente_desde) | DB-P09 cerrado: precios por sucursal (2026-09-22) |
| com_producto_promocion | (producto_id, promocion_id) PK | — |
| com_proveedor | nombre; codigo (nullable) | — |
| com_orden_compra | numero | RF-030 |
| com_orden_compra_item | (orden_id, producto_id) | RN-05 |
| inv_inventario | (producto_id, sucursal_id) | RF-040 |
| inv_transferencia_item | (transferencia_id, producto_id) | RN-06 |
| sales_orden_venta | numero; idempotency_key (nullable→web NOT NULL en app) | RN-07 |
| sales_orden_venta_item | (orden_id, producto_id) | RF-050 |
| sales_carrito_web | sesion_key | carrito persiste en BD (confirmado 2026-09-22) |
| sales_carrito_web_item | (carrito_id, producto_id) | — |
| pay_transaccion_pago | idempotency_key; (proveedor_pago, referencia_externa) | D-03, RF-063 |
| core_configuracion | clave | RF-100 |
| core_idempotency_key | key PK | D-03 |

**Nota MySQL:** `UNIQUE` permite múltiples `NULL`. Donde el NULL debe ser único-solo-uno (p.ej. `core_cliente.identificacion` si solo un cliente sin ID), usar `UNIQUE` + `CHECK (identificacion IS NOT NULL)` o app. `idempotency_key` nullable en POS: la app rellena en web; unicidad garantizada cuando existe.

## 5. Integridad de dominio de negocio (reglas RN → restricción)

| RN/RF | Regla | Garantía |
|---|---|---|
| RN-01 | movimiento de stock siempre ligado a inventario/producto/sucursal/usuario | FK NOT NULL |
| RN-02 | `stock_available >= 0`, reservas no cubren stock | CHECK + lógica transaccional (Paso 12) |
| RN-03 | reserva expira a los 15 min | `expira_en` + job (Paso 12/13); TTL fijo `reserva_ttl_minutos=15` en `core_configuracion`; CHECK no implica expiración |
| RN-04 | ajuste con motivo y usuario | `motivo TEXT NOT NULL`, `usuario_id NOT NULL` |
| RN-05 | solo recepción `confirmada` mueve inventario | estado ENUM + app (transacción Paso 12) |
| RN-06 | transferencia origen ≠ destino | CHECK `sucursal_origen_id <> sucursal_destino_id` |
| RN-07 | idempotencia web/pago | UNIQUE `idempotency_key`; app garantiza NOT NULL en web |
| RN-08 | sin UPDATE/DELETE de negocio en transaccionales; devolución crea registro nuevo | app + `RESTRICT`; triggers de protección: ver §6 |
| RF-090 | auditoría append-only | `core_auditoria`: solo INSERT (§6) |
| jerarquía categorías | sin self-FK | CHECK `categoria_padre_id <> id`; ciclos A→B→A: app (ver §5.1) |

### 5.1 Ciclos en jerarquía de categorías
CHECK solo evita self-loop. Un ciclo largo (A→B→A) no es detectable con CHECK simple. **Decisión:** app valida al re-parent (máx. profundidad N=5, camino ascendente). Documentado como límite de integridad delegado a app (no bloqueante).

## 6. Protección de tablas inmutables (append-only)

Append-only exigido: `inv_movimiento_inventario` (RN-01/RF-090), `core_auditoria` (RF-090). Opciones en MySQL:

| Opción | Elegida | Razón |
|---|---|---|
| Trigger `BEFORE UPDATE/DELETE` → `SIGNAL SQLSTATE` | **sí (Paso 13/14)** | refuerzo a nivel BD; app no es la única capa |
| GRANT sin UPDATE/DELETE al usuario de app | **sí (Paso 09)** | mínimo privilegio |
| Solo app | no | insuficiente para RNF de auditoría |

Nota: columnas de auditoría estándar (`user_update`, `user_update_at`) existen en estas tablas por convención de modelo; en append-only **no deben mutarse** — el trigger impide UPDATE completo. Declarar excepción: si se permite solo tocar `user_update*`, el trigger debe filtrar columnas; **decisión simplificada:** bloquear UPDATE y DELETE enteros sobre ambas tablas (el INSERT ya lleva `user_create*`).

## 7. Integridad de transacciones (referencia; detalle Paso 12)

- Escrituras multi-tabla (venta + ítems + stock + outbox + pago) → **una transacción InnoDB**.
- Deadlocks → retry acotado con rollback completo (Patrón mysql-patterns).
- Orden determinado de locks de fila (producto/inventario por id ASC).

## 8. Huecos / pendientes heredados (no resueltos aquí)

- **DB-P08** cliente: ¿identificación obligatoria en POS? UNIQUE parcial depende de decisión.
- ~~**DB-P09** precio global vs por sucursal~~ → **CERRADO 2026-09-22**: precio por sucursal; `cat_precio` con `sucursal_id NOT NULL`, UNIQUE `(sucursal_id, producto_id, vigente_desde)` ya aplicado en modelo.
- ~~**DB-P01/P02** reserva~~ → **CERRADOS 2026-09-22**: reserva web bloquea stock POS; TTL 15 min con job `SKIP LOCKED` (detalle Paso 12).
- **DB-P12** soft-delete: políticas de `estado` por entidad aún abiertas.
- TTL de purga de carrito (RF-060 no lo define) — no inventar.

## 9. Criterios de salida

- [x] FK con `ON DELETE` explícito en todo el modelo.
- [x] UNIQUE de claves naturales de negocio mapeadas a RN/RF.
- [x] CHECK de dominio (dinero, stock, cantidades, transferencia).
- [x] RN→restricción trazadas (incl. append-only y jerarquía).
- [x] Límites de integridad delegados a app declarados (ciclos de categoría, expiración de reserva).
- [x] Pendientes DB-P01/P02/P09 cerrados 2026-09-22; DB-P08/P12 y TTL carrito marcados sin inventar respuestas.
- [x] Sin SQL de migración.

**Estado:** Paso 08 COMPLETADO → siguiente: Paso 09 (`07_seguridad/seguridad.md`).

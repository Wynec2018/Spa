# Paso 02 — Modelo conceptual de datos

**Workflow:** 02_database_workflow — Paso 02
**Skill utilizado:** `database-schema-designer`
**Entrada:** `01_requisitos_datos/requisitos_datos.md`, decisiones APROBADAS.
**Restricción:** sin SQL, sin tipos de datos de motor, sin claves físicas. Solo entidades, atributos conceptuales, relaciones, cardinalidades y reglas.

## 1. Alcance

Dominio: tienda omnicanal de alimentos (POS + web, inventario compartido, compras, pagos, devoluciones, transferencias, auditoría). Fuera de alcance inicial: contabilidad, nómina, manufactura, marketplace, rutas, app móvil.

## 2. Diccionario de entidades

### 2.1 Identidad y acceso (RF-001, RNF-030/031/033)

| Entidad | Atributos conceptuales | Reglas |
|---|---|---|
| **Usuario** | id, nombre, email, hash contraseña (Argon2id), estado, fechas | email único; contraseñas nunca en texto plano (RN/RNF-033) |
| **Rol** | id, nombre, estado | nombre único |
| **Permiso** | id, código, descripción | código único |
| **Usuario–Rol** | usuario, rol | N:M |
| **Rol–Permiso** | rol, permiso | N:M |
| **TokenBlacklist** | hash token, expiración | hash único; borrado por expiración (§15) |

### 2.2 Organización (RF-002, RF-010)

| Entidad | Atributos conceptuales | Reglas |
|---|---|---|
| **Sucursal** | id, nombre, dirección, estado | pertenece a la empresa; crecimiento sin rediseño (RNF-010) |
| **Caja/POS** | id, sucursal, código, estado | código único por sucursal |
| **Cliente** | id, identificación/contacto, estado | modelo definitivo PENDIENTE (DB-P08); cliente opcional en venta POS |

### 2.3 Catálogo y precios (RF-020)

| Entidad | Atributos conceptuales | Reglas |
|---|---|---|
| **Categoría** | id, nombre, categoría padre (opcional) | jerarquía; nombre en su nivel |
| **Producto** | id, SKU/código, nombre, categoría, unidad, estado | código único; sin precios embebidos (2FN) |
| **Precio** | producto, sucursal, monto, moneda, vigencia | alcance **por sucursal** (DB-P09 CERRADO 2026-09-22: sin precio global) |
| **Promoción** | id, reglas, vigencia, estado | se aplica a productos/categorías |

### 2.4 Compras (RF-030, RF-031, RN-05)

| Entidad | Atributos conceptuales | Reglas |
|---|---|---|
| **Proveedor** | id, nombre, contacto, estado | identificador único |
| **OrdenCompra** | proveedor, número, estado, fechas, totales | número único |
| **OrdenCompraÍtem** | orden, producto, cantidad, precio pactado | 1:N con OrdenCompra |
| **Recepción** | orden de compra, estado, fecha, usuario | solo al confirmarse actualiza inventario (RN-05) |
| **RecepciónÍtem** | recepción, producto, cantidad recibida | admite parcial/total (RF-031) |

### 2.5 Inventario (RF-040/041/062/064, RN-01…RN-04, RN-06, RNF-005/020)

| Entidad | Atributos conceptuales | Reglas |
|---|---|---|
| **Inventario** | producto, sucursal, stock_available, stock_reserved, stock_sold, versión | unicidad producto↔sucursal; stock no negativo (RN-02); D-01 (available/reserved) |
| **Reserva** | orden, producto, sucursal, cantidad, estado, expira en, creado en | estados: pending, confirmed, expired, cancelled; TTL 15 min (`reserva_ttl_minutos`); reserva web bloquea stock POS (DB-P01/P02 CERRADOS) |
| **MovimientoInventario** | producto, sucursal, tipo (entrada/salida/reserva/ajuste/devolución/transferencia/recepción), cantidad, referencia origen, usuario, motivo, fecha | **append-only**; origen de toda venta confirmada (RN-01); auditabilidad (RF-090) |
| **AjusteStock** | producto, sucursal, cantidad, motivo, usuario, fecha | motivo/usuario obligatorios (RN-04) |
| **Transferencia** | origen, destino, estado, usuario, fechas | estados controlados (RN-06) |
| **TransferenciaÍtem** | transferencia, producto, cantidad | 1:N |

### 2.6 Ventas y pedidos (RF-050/051/060/061/062/064, RNF-021/022, RN-07/08)

| Entidad | Atributos conceptuales | Reglas |
|---|---|---|
| **OrdenVenta** | número, canal (POS/web), cliente (opcional en POS), sucursal origen/servicio, estado, total, key idempotencia, fulfillment (entrega/retiro), fechas | número único; idempotencia (RN-07/RF-042); sin DELETE físico (RN-08); fulfillment bajo DB-P10 |
| **OrdenVentaÍtem** | orden, producto, cantidad, precio unitario, subtotal | N con OrdenVenta |
| **CarritoWeb** | cliente/sesión, líneas, actualizado en | PERSISTENCIA EN BD confirmada 2026-09-22; TTL de purga = parámetro (ver §5) |
| **CarritoWebÍtem** | carrito, producto, cantidad | 1:N |

### 2.7 Pagos (RF-050/063, RNF-021/042, D-02/D-03)

| Entidad | Atributos conceptuales | Reglas |
|---|---|---|
| **PagoTransacción** | orden, proveedor pago, referencia externa, monto, estado, key idempotencia, fechas | referencia única; Saga con compensación; sin transacción SQL abierta esperando externo |
| **OutboxEvento** | agregado, tipo evento, payload, correlation id, estado, fechas | insertado en la MISMA transacción de negocio (§16) |
| **IdempotencyKey** | key (UUID), operación, respuesta/resultado, expiración | retención 30 días (validar en Paso 07); limpieza periódica |

### 2.8 Devoluciones (RF-070, RN-08, §22)

| Entidad | Atributos conceptuales | Reglas |
|---|---|---|
| **OrdenDevolución** | orden original, cliente, usuario, estado, motivo, monto, fechas | registro NUEVO relacionado; nunca modifica/destructa la original |
| **OrdenDevoluciónÍtem** | devolución, producto original, cantidad, monto | 1:N |

### 2.9 Reportes, configuración y auditoría (RF-080/090/100, RNF-050)

| Entidad | Atributos conceptuales | Reglas |
|---|---|---|
| **ConfiguraciónSistema** | clave, valor, actualizado en, actualizado por | clave única; RF-100; RNF-002; sin redeploy |
| **Auditoría** | acción, entidad afectada, datos previos/nuevos, usuario, fecha/hora, motivo, correlation id | operaciones críticas (RF-090) |

**Auditoría transversal:** toda entidad de negocio llevará los campos obligatorios `user_create`, `user_update`, `user_created_at`, `user_update_at` (decisión §9), tratados aquí como estándar de auditoría, no como atributo de negocio.

## 3. Relaciones y cardinalidades

| # | Relación | Cardinalidad | Nota |
|---|---|---|---|
| R1 | Sucursal — Caja/POS | 1:N | caja pertenece a una sucursal |
| R2 | Categoría — Categoría (padre) | 1:N opcional | jerarquía |
| R3 | Categoría — Producto | 1:N | producto tiene una categoría principal |
| R4 | Producto/Sucursal — Precio | 1:N (por producto y por sucursal) | versiones/vigencias de precio; alcance **por sucursal** (DB-P09 cerrado) |
| R5 | Producto ↔ Promoción | N:M | productos afectados |
| R6 | Proveedor — OrdenCompra | 1:N | |
| R7 | OrdenCompra — OrdenCompraÍtem | 1:N | |
| R8 | OrdenCompra — Recepción | 1:N | recepciones parciales múltiples |
| R9 | Recepción — RecepciónÍtem | 1:N | |
| R10 | Producto ↔ Sucursal → Inventario | 1:1 (por par) | unicidad producto-sucursal |
| R11 | OrdenVenta — Reserva | 1:N | reservas por línea/orden |
| R12 | OrdenVenta — OrdenVentaÍtem | 1:N | |
| R13 | OrdenVenta — PagoTransacción | 1:N | reintentos/métodos múltiples |
| R14 | OrdenVenta — OrdenDevolución | 1:N | devoluciones parciales sucesivas |
| R15 | OrdenDevolución — OrdenDevoluciónÍtem | 1:N | |
| R16 | OrdenVenta/Recepción/Ajuste/Transferencia — MovimientoInventario | 1:N vía referencia | el movimiento apunta a su documento origen |
| R17 | Transferencia — TransferenciaÍtem | 1:N | |
| R18 | Cliente — OrdenVenta | 1:N | cliente opcional (venta balda/POS) — DB-P08 |
| R19 | Sucursal — OrdenVenta | 1:N | sucursal de origen/servicio |
| R20 | Usuario — Auditoría / MovimientoInventario / Ajuste | 1:N | responsable |
| R21 | Rol ↔ Permiso | N:M | |
| R22 | Usuario ↔ Rol | N:M | |
| R23 | CarritoWeb — CarritoWebÍtem | 1:N | si se persiste (§5) |

## 4. Reglas de negocio incrustadas en el modelo

- RN-01: sin movimiento de inventario no hay venta confirmada → toda OrdenVenta confirmada debe tener movimientos de salida asociados.
- RN-02: `stock_available - stock_reserved ≥ 0` y no negativo tras ventas normales.
- RN-03: toda Reserva tiene `expira_en` derivado de parámetro configurable (`ConfiguraciónSistema`).
- RN-04: AjusteStock exige usuario, motivo, fecha; genera Auditoría y MovimientoInventario.
- RN-05: Recepción solo afecta Inventario en estado confirmado.
- RN-06: Transferencia con máquina de estados (p. ej. draft → in_transit → received/cancelled).
- RN-07: operaciones críticas llevan IdempotencyKey / constraints únicos.
- RN-08: OrdenVenta, PagoTransacción, MovimientoInventario y OrdenDevolución no se eliminan físicamente; la devolución es entidad nueva.

## 5. Supuestos y pendientes explícitos (no se inventan decisiones)

1. **Carrito web:** confirmado 2026-09-22 — se persiste en BD (`sales_carrito_web*`); TTL de purga de carrito sigue sin definirse en RF (parámetro, no inventar).
2. **DB-P01/P02 CERRADOS (2026-09-22):** la reserva web **sí bloquea** stock para POS (descuenta `stock_available` al crearse); expiración a **15 minutos** (`reserva_ttl_minutos` en `core_configuracion`); job `SKIP LOCKED` → `expired` + movimiento `liberacion`, idempotente por UPDATE condicional. Estados pending/confirmed/expired/cancelled confirmados.
3. **DB-P08:** alcance del modelo de Cliente (identificación única global vs por sucursal) pendiente.
4. **DB-P09 CERRADO (2026-09-22): precio por sucursal**, sin fallback global; el lookup de precio requiere `sucursal_id`.
5. **DB-P10:** campos exactos de fulfillment (entrega vs retiro) pendientes.
6. **DB-P12:** entidades maestras (Producto, Proveedor, Cliente, Categoría) no asumen soft delete; se decidirá por entidad.

## 6. Criterios de salida

- [x] Entidades, atributos conceptuales, relaciones y cardinalidades definidos.
- [x] Reglas RN-01…RN-08 reflejadas.
- [x] Pendientes DB-P* y supuestos declarados.
- [x] Sin SQL ni tipos de motor.

**Estado:** Paso 02 COMPLETADO → siguiente: Paso 03 (diagrama E-R Mermaid, `02_modelo_conceptual/diagrama_er.md`).

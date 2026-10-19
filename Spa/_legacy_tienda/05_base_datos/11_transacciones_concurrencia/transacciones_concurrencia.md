# Paso 12 — Transacciones y concurrencia

**Workflow:** 02_database_workflow — Paso 12
**Skills:** `databases` + `mysql-patterns` (motor = MySQL 8.x/InnoDB)
**Entrada:** `decisiones_base_datos.md` §4–§6, §16 (D-01…D-04), `modelo_fisico.md` (`inv_inventario.version`, UNIQUE idempotencia, outbox), `integridad.md` (RN-02/RN-03, §§ multi-tabla), `seguridad.md` (roles/GRANT), perfil_carga
**Salida:** este documento. **Sin DDL. Sin código de app** (solo fronteras y reglas de diseño).

## 1. Fuentes vinculantes

| Decisión | Contenido | RF/RNF |
|---|---|---|
| D-01 (§4) | Bloqueo de fila `SELECT … FOR UPDATE` (+ `SKIP LOCKED` si el diseño lo justifica); orden de acceso a filas/tablas para reducir deadlocks; pendientes DB-P01…P03 como **parámetros** | RF-062/064, RNF-005/020, CA-03 |
| D-02 (§5) | **ACID interno en transacciones cortas + Saga pagos** (no transacción SQL abierta esperando gateway) | RF-050/063, RNF-021/042 |
| D-03 (§6) | Idempotency Key + `UNIQUE`; retención 30 días (`core_idempotency_key.expiracion`) | RF-042, RNF-022 |
| D-04 (§16) | Outbox **en la misma transacción** que el evento de dominio | RNF-052 |
| RN-02/RN-03 | `stock_available >= 0` (CHECK) + lógica transaccional; expiración de reserva por job, no por CHECK | RF-062 |

**No definido (no inventar):** nivel de aislamiento global (§2 propone, queda sujeto a medición); TTL de carrito; DB-P03. **Cerrados 2026-09-22:** DB-P01 (bloqueo SÍ), DB-P02 (TTL 15 min).

## 2. Nivel de aislamiento

| Opción | Efecto | Uso previsto |
|---|---|---|
| `REPEATABLE READ` (default MySQL) | lecturas consistentes; next-key en SELECT no forzado (gaps) | aceptable; puede generar más gap locks que `READ COMMITTED` en escrituras concurrentes |
| **`READ COMMITTED`** (propuesta por diseño) | locks solo sobre filas evaluadas; menos争 contención en rutas hot (stock) | **recomendado como punto de partida** para el pool de la app OLTP |

**Regla:** no cambia la corrección de los flujos con `FOR UPDATE` + CHECK + UNIQUE (§3–§4); solo reduce bloqueos colaterales. **Confirmar/medir en staging (Paso 14)** — no fijar ciegamente en prod. Config:
- sesión de app: `SET TRANSACTION ISOLATION LEVEL READ COMMITTED;` por conexión del pool (o `transaction-isolation=READ-COMMITTED` en `my.cnf`); decisión de operación → DB-P11/DevOps.
- reportes (`rol_lectura`): default RR o RC es indiferente (lecturas; sin `FOR UPDATE`).

## 3. Fronteras transaccionales (RNF-021)

**Principio:** transacción = unidad de consistencia interna, **corta**, solo SQL local. La espera de red/gateway **nunca** está dentro.

| Flujo | Transacción ACID (una) | FUERA de la transacción |
|---|---|---|
| Reserva stock web (RF-062) | lock fila `inv_inventario` → validar `stock_available >= cantidad` → mover a `stock_reserved` → insertar `inv_reserva` (pending) → insertar `inv_movimiento_inventario` (reserva) → (si aplica orden primero: orden pending + items) | — |
| Confirmar venta POS (RF-050/051) | lock inventarios afectados (orden fijo §5) → validar/disminuir stock → `sales_orden_venta` + items → pago (si interno) → `inv_movimiento_*` (salida) → `core_auditoria` → outbox (si evento) | — |
| Venta web con pago externo (RF-063, Saga) | **T1:** reserva + orden pending + **outbox `payment_requested`** en misma T1 (D-04) | llamada gateway / worker outbox; webhook |
| Webhook/pago confirmado | **T2 corta:** `pay_transaccion_pago` (UNIQUE ref) + orden → `pagada/confirmada` + `inv_reserva.confirmed` + stock − | validación de firma del webhook antes de T2 |
| Pago fallido / timeout | **T2c compensatoria:** liberar reserva (`cancelled/expired`, `stock_reserved −`, `stock_available +`) + movimiento `liberacion` + orden `cancelada` | reintentos del worker (acotados, RNF-042) |
| Recepción de OC (RF-031) | lock inventarios de los ítems (orden) → stock + → `com_recepcion*` → `inv_movimiento` entrada | — |
| Devolución (RF-070) | lock inventario → stock + (o reingreso) → `sales_devolucion*` → movimiento `devolucion` | validación/regla de negocio previa |
| Transferencia | lock **origen** y **destino** en orden estable (§5) → saldos → `inv_transferencia*` + 2 movimientos | estado `in_transit` entre T_salida y T_entrada (dos T cortas, no una larga) |
| Login / blacklist token | lectura `token_hash` UNIQUE; insert blacklist en logout | — |
| Idempotencia de API | `INSERT core_idempotency_key` **primero** en T propia o al inicio: si `1062 DUPLICATE` → responder con `respuesta` cacheada sin reejecutar | — |

**Regla D-02 (reiterada):** prohibido mantener `BEGIN … COMMIT` abierto durante I/O externo (gateway pago, correo, etc.).

## 4. Protección anti-sobreventa (RNF-005/020, CA-03)

Tres capas, todas en diseño (no opcional alguna en el camino crítico):

1. **Lock pesimista de fila** en `inv_inventario` para la clave lógica `(producto_id, sucursal_id)` vía índice UNIQUE (I1 Paso 11):
   ```sql
   SELECT stock_available, stock_reserved, version
   FROM inv_inventario
   WHERE producto_id = ? AND sucursal_id = ?
   FOR UPDATE;   -- fila hot del producto×sucursal
   ```
2. **Predicado + CHECK:** la app solo hace `stock_available = stock_available - ?` si el SELECT previo garantiza `>= cantidad`; CHECK final `stock_available >= 0` es red de seguridad (integridad.md RN-02).
3. **Opción optimista alternativa** (si medición muestra contención de locks en pico):
   - usar columna `version` ya existente: `UPDATE … SET stock_available = …, version = version + 1 WHERE id = ? AND version = ?`;
   - filas afectadas = 0 → reintentar con backoff corto (acotado).
   - **Elegir UNA estrategia por ruta:** propuesta = **FOR UPDATE** en checkout/reserva (correctitud simple, decisión D-01); `version` queda como plan B documentado (y para updates no hot tipo catálogo).

**DB-P01 CERRADO (2026-09-22):** la reserva web **sí bloquea** stock para POS: al crear la reserva se descuenta `stock_available` (la app no necesita `reserva_web_bloquea_pos` como parámetro dual — regla fija). Lock de POS ve `stock_available` ya reducido; NO requiere subconsulta de reservas pendientes. El índice de reserva se apoya en `idx_inv_reserva (inventario_id)` + filtro residual por estado.

**DB-P02 CERRADO (2026-09-22): TTL = 15 minutos** (`reserva_ttl_minutos` en `core_configuracion`, seed V010). Job libera lote con `SKIP LOCKED` sobre `inv_reserva` (`estado='pending' AND expira_en <= NOW()`, idx `(estado, expira_en)`), por reserva: lock `inv_inventario` → mover contadores → estado `expired` → movimiento `liberacion`. Idempotente: `UPDATE inv_reserva SET estado='expired' WHERE id=? AND estado='pending'` (filas=0 → skip).

## 5. Orden de locks (anti-deadlock)

Regla global (decisión §4): **siempre por orden de PK/tabla en la misma secuencia; lockar inventarios ordenados por `inventario_id` ASC** (o `producto_id` ASC) antes de tocar ítems/orden.

| Flujo | Orden de acceso (diseño) |
|---|---|
| Checkout multi-producto | 1) `core_idempotency_key` (si aplica) → 2) `sales_orden_venta` (insert; no lock previo) → 3) `inv_inventario` de cada producto **ordenado por inventario_id** → 4) `sales_orden_venta_item` → 5) `inv_movimiento_inventario` → 6) `inv_reserva` → 7) `core_auditoria` → 8) `pay_outbox_evento` |
| Confirmación POS | misma regla de inventarios ordenados; `sales_*` → `pay_*` → auditoría → outbox |
| Transferencia | inventarios de la transferencia: **origen antes que destino** y ambos en ASC id → items → movimientos → transferencia header |
| Recepción OC | header recepción → inventarios ASC → items recepción → movimientos |
| Webhook pago | `pay_transaccion_pago` (UNIQUE detecta duplicado) → `sales_orden_venta` → `inv_reserva` → `inv_inventario` (si ajusta) → auditoría |

**Prohibido:** lockar `core_configuracion` o tablas de catálogo dentro del flujo de checkout (si hace falta leer config, leerla **antes** del BEGIN o con lectura no bloqueante fuera de la T).

**Deadlocks:** InnoDB los resuelve con rollback de una T — la app debe **reintentar** la transacción completa (no solo el statement) con backoff ≤ 3 intentos; métrica `innodb_deadlocks` en observabilidad (RNF-051).

## 6. Idempotencia y reintentos (RNF-022, D-03)

| Mecanismo | Aplicación | Notas |
|---|---|---|
| `core_idempotency_key` PK `CHAR(36)` | APIs críticas (checkout, pago, recepción) | INSERT al inicio; `1062` → devolver `respuesta` persistida; retención 30 días → job `WHERE expiracion < NOW()` usa `idx_core_idem_expiracion` |
| `uq_sales_orden_idempotency` | doble POST de orden web | doble capa con la tabla anterior (defensa en profundidad) |
| `uq_pay_pago_idempotency` + `uq_pay_pago_proveedor_ref` | webhooks duplicados del proveedor | segunda UNIQUE evita re-aplicar compensación dos veces |
| `uq_sales_orden_numero` / OC `numero` | correlación de negocio | app genera con estrategia de colisión → reintento con nuevo número, no reutilizar |
| Outbox worker | al menos una vez → handler **idempotente** | marcar `processed` solo tras efecto; `reintentos` + estado `failed` para DLQ operativa |

UNIQUE no sustituye la transacción: el `INSERT` de idempotencia y el del efecto pueden vivir en la misma T de negocio (si la T falla, todo se revierte y el cliente reintenta limpio) — **patrón elegido:** idempotencia **al inicio de la T** del flujo.

## 7. Saga de pagos (D-02 + D-04)

```text
T1 (ACID): validar → reservar stock → orden pending → outbox(pending) → COMMIT
Worker:    claim outbox (UPDATE estado con WHERE estado='pending' → fila única) → gateway (timeout acotado, RNF-042)
Webhook:   verificar firma → T2: pago + orden + reserva confirmada (UNIQUE ref)
Fallo:     worker reintentos con backoff; máximo → estado failed + alerta
           compensación: T2c libera reserva (idempotente por estado)
```

- Claim del outbox: `UPDATE pay_outbox_evento SET estado='processing', claimed_at=UTC_TIMESTAMP()… WHERE id=? AND estado='pending'` (filas=0 → otro worker lo tomó). **No** `SELECT FOR UPDATE` largo sobre el outbox.
- **Recuperación de `processing` huérfano (AR-01):** si el worker muere tras el claim, un job de sanidad reclama con `UPDATE pay_outbox_evento SET estado='pending', claimed_at=NULL, reintentos=reintentos+1 WHERE estado='processing' AND claimed_at < UTC_TIMESTAMP() - INTERVAL 5 MINUTE` (idempotente; ventana > timeout de gateway, RNF-042). Alerta si `reintentos` supera umbral → `failed` + alerta operativa.
- Ningún lock de negocio se mantiene entre T1 y T2.
- `correlation_id` propaga la traza entre orden/pago/outbox/auditoría (RNF-052).

## 8. Concurrencia por hot spot y escala

| Hot spot | Qué pasa | Mitigación de diseño |
|---|---|---|
| Mismo `producto×sucursal` en cajas colindantes + web | serialización sobre 1 fila `inv_inventario` | correcto y deseable; enfoque por sucursal mantiene granularidad fina (no lock global por producto) |
| Insert de movimientos en venta | +1–n inserts en `inv_movimiento` | sin PK de negocio conflictuante; no locka filas leídas |
| Outbox barrido | workers compiten | claim UPDATE + `SKIP LOCKED` opcional en lectura |
| Idempotency insert | colisión de key a propósito | UNIQUE corto; respuesta cacheada |
| 75k tx/h pico (~21/s) / 10x (~210/s) | — | pool de conexiones de app ≤ `max_connections` (seguridad §hardening); transacciones cortas ⇒ bajo tiempo de lock |

`innodb_deadlocks`, lock waits (`innodb_lock_wait_timeout` default 50 s — **reducir a 5–10 s en app/DB** para fallar rápido y reintentar; es operación, confirmar en Paso 14), reintentos de T → métricas RNF-051.

## 9. Jobs y consistencia de lote

| Job | Patrón |
|---|---|
| Expirar reservas (TTL 15 min, DB-P02 cerrado) | select `pending AND expira_en<=NOW` por `(estado, expira_en)` con `SKIP LOCKED` → por fila: lock inventario → liberar → `expired` (UPDATE condicional) |
| Purgar idempotency / blacklist / carritos (TTL) | lote `LIMIT n` por índice de fecha; **sin** lock de negocio; DELETE solo en tablas de purga (no transaccionales append-only) |
| Marcar outbox failed | por `reintentos` umbral; alerta |

Cada fila de lote = una transacción corta; no un BEGIN por todo el lote.

## 10. Check de diseño (gate Paso 14)

- [ ] Fronteras: ninguna T con I/O externo (§3, D-02).
- [ ] Sobreventa: FOR UPDATE + CHECK + UNIQUE stock (§4); DB-P01/P02 cerrados (bloqueo SÍ, TTL 15 min) — verificado.
- [ ] Orden de locks definido por flujo (§5); sin catálogo dentro del checkout.
- [ ] Idempotencia doble capa (tabla + UNIQUE) y reintentos acotados (§6).
- [ ] Outbox claim no bloqueante; handlers idempotentes (§7).
- [ ] Aislamiento: propuesta RC documentada, a validar con métricas (§2).
- [ ] Trazado a RNF-005/020/021/022/042/051/052, RF-042/051/062/063/064, D-01…D-04.
- [ ] Sin DDL ni código de aplicación generados.

## 11. Pendientes ligados (no resueltos aquí)

- **DB-P03** máximo ítems transferencia (granularidad de locks del flujo transferencia).
- **DB-P04** proveedor(es) de pago (variantes de la Saga).
- Nivel de aislamiento final y `innodb_lock_wait_timeout` → validación staging (§2, §8).
- TTL de carrito → si no hay job de purga, G2 del Paso 11 no se implementa.
- ~~**DB-P01**~~ cerrado 2026-09-22: bloqueo SÍ. ~~**DB-P02**~~ cerrado 2026-09-22: TTL 15 min.

**Estado:** Paso 12 COMPLETADO → siguiente: Paso 13 (`12_migraciones/plan_migraciones.md`).

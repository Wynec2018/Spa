# Revisión adversarial de arquitectura — Base de datos

**Skill:** `architecture-validation` (`.agents/skills/architecture-validation/SKILL.md`)
**Fecha:** 2026-09-22 · **Fase:** validación · **Implementación:** prohibida en esta fase
**Alcance (5 artefactos):**
1. `05_base_datos/05_modelo_fisico/modelo_fisico.md`
2. `05_base_datos/03_modelo_logico/modelo_logico.md`
3. `05_base_datos/11_transacciones_concurrencia/transacciones_concurrencia.md`
4. `05_base_datos/10_indices_rendimiento/indices_rendimiento.md`
5. `05_base_datos/06_integridad/integridad.md`

**Fuentes de verdad:** `01_requisitos/{RF,RNF,criterios_aceptacion}.md`, `04_decisiones/decisiones_base_datos.md`, `02_configuracion/perfil_carga.yaml`. Evidencia corroborativa: `13_sql/V001…V010`, `15_reportes/revision_dba.md`.

**No modifica ningún artefacto existente. No escribe código ni SQL. No instala dependencias.**

---

## 1. Evaluación

El diseño es **coherente, trazable y proporcional a la carga** (25.000 tx/h → ~210 tx/s a 10×; p95 500/1200 ms; 8 sucursales, 48 POS). Las decisiones vinculantes D-01…D-07 están reflejadas en los cinco artefactos revisados. El anti-sobreventa tiene tres capas verificables en el DDL (`FOR UPDATE` vía `uq_inv_inventario_producto_sucursal` modelo_fisico L352, `CHECK stock >= 0`, unicidad producto×sucursal). La idempotencia de pagos es fuerte (`uq_pay_pago_idempotency` L611, `uq_pay_pago_proveedor_ref` L612, `core_idempotency_key` L634 — D-03 sin regresión). No se detectó sobrearquitectura: no hay pre-particionado, no hay índices especulativos (regla explícita indices L114; solo G1/G2 condicionados), no se impuso Kubernetes/microservicios (CA-09 OK), y los pendientes DB-P03…P12 están declarados como parámetros, no como supuestos ocultos (CA-10).

Se detectó **1 hallazgo HIGH** (contradicción directa entre el patrón de claim del outbox documentado y el ENUM del esquema, propagada a `V006`), **7 MEDIUM** y **6 LOW**. Ningún hallazgo compromete la corrección del modelo relacional ni permite sobreventa. Los hallazgos MEDIUM son de defensa en profundidad (capa BD ausente donde la doc delega en la app), coherencia documental y una condición de carrera webhook↔TTL sin especificar. Ninguno es CRITICAL.

**Veredicto parcial: el diseño aguanta la revisión adversarial, con un hallazgo HIGH de consistencia doc↔esquema que debe cerrarse antes de aplicar SQL en Paso 15/producción.**

---

## 2. Matriz de trazabilidad RF / RNF

Cobertura = presencia de soporte en al menos uno de los 5 artefactos revisados (o declaración explícita de que el soporte vive en otro paso, señalado).

### 2.1 Requisitos funcionales

| RF | Soporte en los 5 artefactos | Evidencia clave | Estado |
|---|---|---|---|
| RF-001 usuarios/roles/permisos | lógico §2, físico `auth_*` (L64/76/89 UNIQUE), integridad §4 | `uq_auth_usuario_email`, PKs asociativas | cubierto |
| RF-002 clientes | físico `core_cliente`, integridad §4 L53 | UNIQUE identificación (DB-P08 = parámetro abierto) | cubierto (parámetro declarado) |
| RF-010 sucursales/cajas | físico `core_sucursal`, `core_caja_pos` | `uq_core_caja_pos_sucursal_codigo` L151; índice U5 | cubierto |
| RF-020 catálogo/precios/promos | lógico §4, físico `cat_*`/`com_producto_promocion` | `uq_cat_precio_sucursal_producto_desde` L218 — **DB-P09 CERRADO, sin regresión** | cubierto |
| RF-030 proveedores/OC | físico `com_*` | `uq_com_orden_compra_numero`, `uq_com_orden_item_orden_producto` L279/296 | cubierto |
| RF-031 recepción parcial/total | físico `com_recepcion(_item)`, transacciones §3 | lock de inventarios en recepción (L42) | cubierto (ver AR-09) |
| RF-040 inventario por producto/sucursal | físico `inv_inventario` | `uq_inv_inventario_producto_sucursal` L352 | cubierto |
| RF-041 entradas/salidas/reservas/ajustes/transf. | físico `inv_movimiento_inventario` ENUM 9 tipos L387, `inv_ajuste`, `inv_transferencia*` | índices §2.4; transacciones §5 | cubierto (ver AR-10, AR-14) |
| RF-042 no doble descuento ante reintentos | D-03 + `core_idempotency_key` + UNIQUE orden/pago | L489/611/612/634; transacciones §7 | **cubierto fuerte** |
| RF-050 ventas presenciales + pagos | transacciones §3 fila “Confirmar venta POS” L38 | orden de locks §5, pagos internos en la misma T | cubierto |
| RF-051 descuento al confirmar POS | misma fila; `CHECK stock>=0`; movimiento salida | 3 capas anti-sobreventa | cubierto |
| RF-060 catálogo/carrito/pedido web | físico `sales_carrito_web*` (B-3 CONFIRMADO) | `uq_sales_carrito_sesion`, `…_carrito_producto` L529/544 | cubierto (ver AR-07 anotación obsoleta) |
| RF-061 entrega o retiro | físico L478 `fulfillment_type ENUM('entrega','retiro')`, L480 `fecha_entrega` | — | cubierto |
| RF-062 validar/reservar stock web | transacciones §3 L37, §4, TTL §8; `inv_reserva` L361 | `idx_inv_reserva_estado_expira` L376 | cubierto (ver AR-03, AR-04) |
| RF-063 pagos desacoplados (Saga) | D-02 + outbox en misma T (L39) + `pay_outbox_evento` L624 | `idx_pay_outbox_correlation` (índices P5) | **con defecto — AR-01** |
| RF-064 sin sobreventa web vs físico | regla fija “la reserva web sí bloquea” (DB-P01 CERRADO) | una sola clave de inventario, sin clave dual | cubierto (ver AR-08) |
| RF-070 devoluciones | físico `sales_devolucion(_item)`, transacciones §3 L43 | ENUM estado de orden `devuelta` L475 | cubierto (ver AR-09) |
| RF-080 reportes | índices §2/§5/§6, G1, gate EXPLAIN | `idx_*_fecha`, V4 cierre diario | cubierto con **G1 sin materializar — AR-06** (ver AR-12) |
| RF-090 auditoría | `core_auditoria` + append-only + índices U1/U2 | triggers V008 solo sobre 2 tablas | cubierto parcial — **AR-05** |
| RF-100 parámetros operativos | `core_configuracion` + `uq_core_config_clave` L655 + semilla V010 | TTL 15 min ya sembrado | cubierto |

### 2.2 Requisitos no funcionales

| RNF | Soporte | Evidencia | Estado |
|---|---|---|---|
| RNF-001 25.000 tx/h | índices §1 (perfil_carga), §4 buffer pool/redo | ~21 tx/s inicial, ~210 tx/s 10× | cubierto |
| RNF-002 configurable externamente | `core_configuracion` + seeds V010 | `reserva_ttl_minutos` | cubierto |
| RNF-003 tx ≠ sentencia SQL | transacciones §3 fronteras multi-paso por T | — | cubierto |
| RNF-004 picos × multiplicador | índices §1 (×3.0 → 75k tx/h), §4 redo/io_capacity | — | cubierto |
| RNF-005 integridad bajo concurrencia física+web | FOR UPDATE + orden de locks §5 + READ COMMITTED propuesto | una clave de stock, sin bifurcación de canal | cubierto (ver AR-08) |
| RNF-010 crecer sucursales/cajas sin rediseño | PKs surrogate + FK a `core_sucursal`; modelo por relación | — | cubierto |
| RNF-011 escenario 10× | índices §6 gate ×10, §4 `max_connections` pool | — | cubierto (gate de medición pendiente = parámetro declarado) |
| RNF-020 sobreventa por carreras | 3 capas + plan B `version` (integridad, transacciones) | anti-finding verificado | cubierto (ver AR-03, AR-08) |
| RNF-021 fronteras transaccionales | transacciones §3 tabla T corta vs fuera; sin I/O en T | — | **cubierto fuerte** |
| RNF-022 duplicados ante reintentos | D-03 doble capa + UNIQUE condicional | — | cubierto (ver AR-02, AR-03) |
| RNF-030 auth administrativa | `auth_*` + JWT+RBAC (D-07) | refresh token **no modelado** | parcial — **AR-11** |
| RNF-031 mínimo privilegio | fuera del alcance de los 5 (Paso 09 `seguridad.md`) | GRANT INSERT-only planificado | fuera de alcance (declarado) |
| RNF-032 cifrado en tránsito | fuera de alcance (seguridad: `require_secure_transport`) | — | fuera de alcance (declarado) |
| RNF-033 contraseñas | fuera de alcance (Argon2id en contexto seguridad) | — | fuera de alcance (declarado) |
| RNF-034 secretos fuera de repo | fuera de alcance; sin secretos en artefactos revisados | revisión limpio | fuera de alcance (declarado) |
| RNF-035 controles API | fuera de alcance (Paso 09) | — | fuera de alcance (declarado) |
| RNF-040 backup/restauración | fuera de alcance de los 5 (seguridad §6); binlog ON | DB-P05 parámetro | fuera de alcance (declarado) |
| RNF-041 RPO/RTO antes de prod | DB-P05 abierto = condición C-1 de revision_dba | — | **parámetro abierto aceptado** |
| RNF-042 timeout/reintentos controlados | transacciones L103 (timeout acotado), §7 reintentos acotados, claim con filas=0 | — | cubierto |
| RNF-050 logs estructurados | fuera de alcance de los 5 (auditoría/observabilidad en Pasos 08/12) | `core_auditoria` + outbox | parcial fuera de alcance |
| RNF-051 métricas latencia/errores | índices §6 slow_query_log, gate p95 | — | cubierto |
| RNF-052 correlación distribuida | `idx_pay_outbox_correlation` (P5) + `correlation_id` | — | **afectado por AR-01** (claim no ejecutable) |
| RNF-060 Linux | contexto (D-06 Docker Compose) | — | fuera de alcance (declarado) |
| RNF-061 contenerizable | D-06 Docker Compose | — | fuera de alcance (declarado) |
| RNF-062 configs dev/qa/prod | fuera de alcance de los 5 (Paso 09/DevOps) | — | fuera de alcance (declarado) |

**Cobertura total: RF 21/21 con soporte (AR-01…AR-09 señalan defectos, no ausencias de soporte); RNF 30/30 con soporte o declaración explícita de alcance en otro paso. No se detectaron RF/RNF sin cobertura ni cambios silenciosos.**

---

## 3. Hallazgos

Escala: **CRITICAL** (impide operar / corrompe datos) · **HIGH** (defecto funcional documentado, cerrar antes de SQL/prod) · **MEDIUM** (defensa en profundidad o coherencia; cerrar en Paso 15/16) · **LOW** (deuda documental/consistencia, sin efecto funcional inmediato).

---

### AR-01 — Patrón de claim del outbox incompatible con el ENUM del esquema

| Campo | Contenido |
|---|---|
| **ID** | AR-01 |
| **Severidad** | **HIGH** |
| **Área** | Transacciones / consistencia doc↔esquema |
| **Requisito afectado** | RF-063, RNF-052, RNF-022, D-04 |
| **Evidencia** | `transacciones_concurrencia.md` L109: `UPDATE pay_outbox_evento SET estado='processing' … WHERE id=? AND estado='pending'`. Pero `pay_outbox_evento.estado ENUM('pending','processed','failed')` — `modelo_fisico.md` L624 y `13_sql/V006__init_pagos.sql` L35. MySQL rechaza el valor fuera del ENUM (error 1265). Contradicción interna: la propia L103 describe el claim solo como `WHERE estado='pending'` sin fijar el destino. |
| **Impacto** | El patrón de claim de D-04 no puede ejecutarse tal como está documentado: todo worker que reintente el claim falla o, si la app escribe `processed` directo, se pierde la distinción “en curso vs terminado” y la recuperación de eventos caídos (worker muerto a mitad de proceso). Afecta el Saga de RF-063 y la correlación RNF-052. |
| **Cambio necesario** | Unificar el contrato de estados en los tres lugares: (a) añadir `'processing'` al ENUM en `modelo_fisico.md` + `V006`, **o** (b) cambiar el claim documentado a `SET estado='processed'` en el mismo UPDATE (más simple, pero elimina la detección de eventos trabados). Recomendado (a) + política de **reclamo de `processing` huérfano** (ej. `WHERE estado='processing' AND claimed_at < NOW() - INTERVAL …` con columna de claim) para cerrar también la recuperación incompleta si el worker muere. Sincronizar L103 y L109. |
| **Criterio de cierre** | El ENUM del modelo físico, el `V006` y el patrón de claim de transacciones admiten los mismos estados; un UPDATE de claim escrito literalmente desde la doc aplica sin error 1265; la regla de reclamo de `processing` huérfano está especificada en transacciones. |

---

### AR-02 — RN-07: `idempotency_key` NOT NULL en web no garantizado por la BD

| Campo | Contenido |
|---|---|
| **ID** | AR-02 |
| **Severidad** | MEDIUM |
| **Área** | Integridad / idempotencia |
| **Requisito afectado** | RF-042, RNF-022, RN-07 |
| **Evidencia** | `sales_orden_venta.idempotency_key VARCHAR(64) NULL` (modelo_fisico L477) con `UNIQUE KEY uq_sales_orden_idempotency` (L489). `integridad.md` L82: “app garantiza NOT NULL en web”; L70 admite “la app rellena en web”. MySQL 8 soporta `CHECK (canal <> 'web' OR idempotency_key IS NOT NULL)`; no existe en el DDL. Contradice el principio propio de integridad L18: “la app no sustituye a la BD en lo que la BD puede garantizar”. |
| **Impacto** | Un bug o regresión en la app web permite crear órdenes web sin clave de idempotencia → reintentos del checkout duplican órdenes (justo lo que RN-07/D-03 existen para evitar). `UNIQUE` con NULL no lo detecta (MySQL permite múltiples NULL). |
| **Cambio necesario** | Añadir CHECK compuesto `canal <> 'web' OR idempotency_key IS NOT NULL` en `modelo_fisico.md` (y su migración aditiva correspondiente), o documentar formalmente la delegación como riesgo aceptado en integridad con responsable y prueba de revisión. Recomendado el CHECK: una línea, sin costo operativo. |
| **Criterio de cierre** | CHECK presente en el modelo físico y alineado con la regla RN-07, o excepción firmada como riesgo aceptado en `decisiones_base_datos.md`. |

---

### AR-03 — Sin unicidad a nivel BD para la reserva activa por orden

| Campo | Contenido |
|---|---|
| **ID** | AR-03 |
| **Severidad** | MEDIUM |
| **Área** | Concurrencia / anti-duplicación |
| **Requisito afectado** | RF-062, RF-064, RNF-020, RNF-022 |
| **Evidencia** | `inv_reserva` (modelo_fisico L361–380): solo KEYs de lectura (`orden`, `inventario`, `estado_expira`); **sin** `UNIQUE (orden_id, inventario_id)` filtrada a estados activos. Lista completa de 22 UNIQUEs verificada: `inv_reserva` no aparece. La defensa contra reservas dobles queda 100 % en `core_idempotency_key` + lógica de la API. |
| **Impacto** | Doble reserva del mismo stock para la misma orden si dos requests concurrentes pasan la comprobación de idempotencia en la ventana entre validación e insert (o si la app olvida la clave en un flujo interno). No hay sobreventa (el CHECK de stock la previene), pero sí stock **retenido dos veces**: inmoviliza inventario y puede agotar `stock_reserved` falsamente. |
| **Cambio necesario** | MySQL 8 no admite UNIQUE parcial. Opción mínima: columna generada `clave_activa` = `CASE WHEN estado IN ('pending','confirmed') THEN CONCAT(orden_id,':',inventario_id) END` + UNIQUE (los NULL múltiples de MySQL quedan permitidos solo en no-activos). Alternativa aceptable: dejar en capa app y **documentar el residuo** en integridad §4. |
| **Criterio de cierre** | UNIQUE generada implementada en el modelo, **o** residuo documentado explícitamente con prueba de test de concurrencia del flujo de reserva en Paso 15/16. |

---

### AR-04 — TTL de reserva depende exclusivamente del job, sin alarma ni fallback en BD

| Campo | Contenido |
|---|---|
| **ID** | AR-04 |
| **Severidad** | MEDIUM |
| **Área** | Recuperación incompleta / operación |
| **Requisito afectado** | RF-062, RNF-042, RNF-051, DB-P02 (cerrado con condición operativa) |
| **Evidencia** | `transacciones` L69/L129: expiración **solo** por job (`estado='pending' AND expira_en <= NOW()`, SKIP LOCKED, idempotente). El modelo tiene `expira_en` + índice (L367/376) pero ningún mecanismo que detecte “reservas vencidas hace > N minutos sin expirar”. El diseño reconoce la ventana (job con TTL paramétrico) sin definir la detención del job. |
| **Impacto** | Si el job cae o no está programado en el entorno (D-06 Compose sin cron), las reservas nunca expiran → `stock_reserved` sobreestimado de forma indefinida → el canal web ve menos stock disponible del real (baja disponibilidad de inventario, no sobreventa). Silencioso: ningún CHECK ni约束 lo delata. |
| **Cambio necesario** | Especificar en transacciones: (a) métrica/alerta “reservas `pending` con `expira_en < NOW() - umbral`” (RF-090/RNF-051), (b) el job como proceso supervisado en Compose con reintento, (c) opcionalmente vista de control `COUNT(*)` vencidas > umbral. No inventar plazo: umbral = parámetro en `core_configuracion` (consistente con RNF-002). |
| **Criterio de cierre** | Alerta o check de sanidad documentado con su parámetro; runbook de job en plan de operación (Paso 16/17). |

---

### AR-05 — Bypass de auditoría en tablas financieras de negocio

| Campo | Contenido |
|---|---|
| **ID** | AR-05 |
| **Severidad** | MEDIUM |
| **Área** | Auditoría / integridad |
| **Requisito afectado** | RF-090, RN-08 |
| **Evidencia** | Append-only (integridad §6, L90–100) cubre **solo** `core_auditoria` + `inv_movimiento_inventario` (triggers `SIGNAL` en `V008__triggers.sql` L11–39, verificado). El rol de app conserva UPDATE sobre `sales_orden_venta.total/devuelta`, `sales_devolucion.monto`, `pay_transaccion_pago.monto` (modelo_fisico L475+): un UPDATE directo sobre montos **no genera fila en `core_auditoria`** y no es bloqueado por ningún trigger. La app puede registrar auditoría, pero la capa BD no la fuerza en las tablas que más importan para dinero. |
| **Impacto** | Alteración de históricos financieros sin rastro append-only — debilita RF-090 y la evidencia de RF-070/RF-063. No es ataque remoto (requiere rol de app), es coherencia de defensa en profundidad: el diseño declara “app no es la única capa” (integridad L96) pero solo lo cumple en 2 de N tablas críticas. |
| **Cambio necesario** | Decidir y documentar una de: (a) triggers `AFTER UPDATE` sobre `sales_orden_venta`, `sales_devolucion`, `pay_transaccion_pago` que inserten diffs en `core_auditoria` (amplía V008), (b) columna `version` + app obligada a auditar en la misma T (a prueba de fallos solo si es transacción única), o (c) aceptación documentada del residuo con motivo (costo de triggers en hot path de venta). Recomendado (a) para tablas de dinero; (c) aceptable solo si queda en decisiones. |
| **Criterio de cierre** | Mecanismo elegido reflejado en integridad §6 + V008 (o residuo aceptado por escrito). |

---

### AR-06 — G1: índice de prioridad alta del hot path de reportes sin materializar

| Campo | Contenido |
|---|---|
| **ID** | AR-06 |
| **Severidad** | MEDIUM |
| **Área** | Rendimiento / índice faltante en camino caliente |
| **Requisito afectado** | RF-080, RNF-001, RNF-004, RNF-011 |
| **Evidencia** | `indices_rendimiento.md` L110: G1 `sales_orden_venta(sucursal_id, creado_en)` marcado prioridad **alta**, “accionables en Paso 13/15”. Verificado en el DDL: `sales_orden_venta` (L470–495) **no** tiene ese índice hoy; `V007__indices.sql` existe pero G1 queda como gap accionable (revisión DBA C-4). |
| **Impacto** | Cierre diario y reportes RF-080 por sucursal sobre tabla de escritura caliente (cada orden = fila nueva) harán full scan/rango largo → compiten con el OLTP del POS en el pico (RNF-001/004), riesgo directo al p95 500/1200 ms a 10×. |
| **Cambio necesario** | Materializar G1 en el DDL del modelo físico (o confirmar en evidencia que `V007` ya lo incluye) y verificarlo en el smoke del Paso 15 (`SHOW INDEX`). G2 queda condicionado al TTL de carrito (parámetro abierto — correcto no pre-optimizar). |
| **Criterio de cierre** | `SHOW INDEX FROM sales_orden_venta` incluye `(sucursal_id, creado_en)` en staging, con EXPLAIN del patrón V4 sin full scan (gate §6). |

---

### AR-07 — Deriva de estados en `modelo_logico.md` respecto a decisiones cerradas

| Campo | Contenido |
|---|---|
| **ID** | AR-07 |
| **Severidad** | MEDIUM |
| **Área** | Coherencia documental / trazabilidad |
| **Requisito afectado** | CA-01, CA-10, DB-P01/DB-P02 (cerrados 2026-09-22), B-3 carrito (confirmado) |
| **Evidencia** | `modelo_logico.md` L104: `### inv_reserva (DB-P01/P02 abiertos)` — pero DB-P01/P02 están **CERRADOS** (transacciones L18/L69, revision_dba B-2). L139/140: carrito marcado “**supuesto: ¿persistencia en BD?**” — pero B-3 **CONFIRMADO** (persiste en BD, V004). En cambio L62 **sí** fue actualizada (“DB-P09 CERRADO”) → actualización parcial. L191 checkbox de salida afirma trazabilidad declarada. |
| **Impacto** | Un lector del modelo lógico (fuente para futuras migraciones o para otro agente) reintroduce supuestos ya resueltos: riesgo de retrabajo, contradicción con revision_dba STATUS: APPROVED, y pérdida de trazabilidad RF/RNF→modelo exigida por CA-01. |
| **Cambio necesario** | Actualizar L104 a “DB-P01/P02 CERRADOS (bloqueo SÍ, TTL 15 min)” y L139–140 a “confirmado: persiste en BD (B-3); TTL de purga = parámetro abierto”. Revisar el resto del archivo por supuestos análogos. **Nota:** cambio documental en Paso de validación — requiere aprobación del solution-leader (fuera del alcance de este informe: se reporta, no se edita). |
| **Criterio de cierre** | Ninguna anotación “abierto/supuesto” en `modelo_logico.md` contradiga `decisiones_base_datos.md` ni `revision_dba.md`; checksum de coherencia en revisión de Paso 15. |

---

### AR-08 — Carrera webhook (pago tardío) ↔ job de TTL sin transición especificada

| Campo | Contenido |
|---|---|
| **ID** | AR-08 |
| **Severidad** | MEDIUM |
| **Área** | Condición de carrera / recuperación |
| **Requisito afectado** | RF-063, RF-064, RNF-005, RNF-020, RNF-042 |
| **Evidencia** | `transacciones` L40 (T2 webhook): `… + inv_reserva.confirmed + stock −` **sin** condición documentada `WHERE estado='pending'`. Job de expiración L69 **sí** usa `UPDATE … WHERE estado='pending'` (filas=0 → skip). Ventana: reserva `pending` vence → job T2c libera stock → webhook de pago llega después (timeout acotado del gateway, L103) → T2 sin condicional convierte una reserva ya `expired` a `confirmed` y descuenta stock dos veces o deja orden `pagada` con reserva liberada. La validación de firma (L40) no cubre el caso “pago válido, reserva ya expirada”. |
| **Impacto** | Inconsistencia orden↔inventario↔pago: o bien doble descuento de stock (crítico), o bien orden pagada con stock liberado (cliente cobra sin reserva). Depende del rate de expiración vs latencia de webhook — posible en pico. |
| **Cambio necesario** | Especificar T2 con transición condicional idempotente: `UPDATE inv_reserva SET estado='confirmed' … WHERE id=? AND estado='pending'`; si filas=0 → ruta de compensación definida (re-resevar si hay stock, o reembolso/estado `pagada_sin_reserva` con regla de negocio explícita). Documentar la rama en la tabla §3. **No inventar la regla aquí** — debe fijarla solution-leader con RF-063. |
| **Criterio de cierre** | La tabla de transiciones de `inv_reserva` (pending→confirmed / pending→expired / expired→?) incluye el arribo tardío de webhook con comportamiento y responsables definidos; test de caso límite en Paso 16. |

---

### AR-09 — UNIQUEs de detalle faltantes en recepción y devolución

| Campo | Contenido |
|---|---|
| **ID** | AR-09 |
| **Severidad** | LOW |
| **Área** | Integridad única |
| **Requisito afectado** | RF-031, RF-070, RN-05, RN-08 |
| **Evidencia** | Lista de 22 UNIQUEs verificada: existen `uq_com_orden_item_orden_producto` (L296), `uq_inv_trans_item_trans_producto` (L461), `uq_sales_item_orden_producto` (L510), `uq_sales_carrito_item_carrito_producto` (L544) — pero **no** `com_recepcion_item(recepcion_id, producto_id)` ni `sales_devolucion_item(devolucion_id, producto_orden_item_id)`. |
| **Impacto** | Un reintento o doble submit en recepción parcial o en devolución crea líneas duplicadas → montos/stock contados dos veces en esos documentos. Probabilidad baja (flujos internos, no de checkout), pero viola el patrón de unicidad ya establecido para todos los demás detalles. |
| **Cambio necesario** | Añadir los dos UNIQUE en `modelo_fisico.md` (aditivo, sin migración destructiva), coherente con el patrón de las otras 4 tablas de detalle. |
| **Criterio de cierre** | Ambos UNIQUE presentes en el DDL y en la migración correspondiente; smoke `SHOW CREATE TABLE`. |

---

### AR-10 — Sin cubo de stock “en tránsito” para transferencias

| Campo | Contenido |
|---|---|
| **ID** | AR-10 |
| **Severidad** | LOW |
| **Área** | Modelo de dominio / reportes |
| **Requisito afectado** | RF-041, RF-080, RN-06 |
| **Evidencia** | `inv_inventario` modela `available/reserved/sold` (decisión anti-sobreventa); transferencia = dos T cortas con estado `in_transit` en `inv_transferencia` (transacciones L44): entre T_salida y T_entrada el stock **no está** en origen ni en destino — invisible para `inv_inventario`. No existe columna/bucket de tránsito ni tabla de agregación reportable. |
| **Impacto** | Reportes RF-080 de inventario subestiman stock total de la empresa durante ventanas de transferencia; reconciliación sucursal↔empresa no cuadra. No afecta sobreventa. |
| **Cambio necesario** | Aceptar y documentar el residuo (transferencias cortas → ventana pequeña) **o** derivar “en tránsito” en reportes desde `inv_transferencia WHERE estado='in_transit'` (sin nuevo campo: una vista/consulta documentada en índices §5 alcanza). Preferida la segunda: cero DDL. |
| **Criterio de cierre** | Fórmula de “stock total” en reportes RF-080 documentada (inventario + in_transit) o residuo aceptado. |

---

### AR-11 — Almacenamiento de refresh tokens no modelado

| Campo | Contenido |
|---|---|
| **ID** | AR-11 |
| **Severidad** | LOW |
| **Área** | Cobertura de seguridad |
| **Requisito afectado** | RNF-030, D-07 |
| **Evidencia** | `decisiones_base_datos.md` L831: “se prevé almacenar refresh tokens con TTL controlado” — no existe tabla ni columna en `modelo_fisico.md` (solo `auth_token_blacklist` L124). Los 5 artefactos no lo mencionan. |
| **Impacto** | Si el POS/ web usan refresh tokens (patrón recomendado para JWT), la persistencia y rotación quedarán improvisadas en Paso 15/app, fuera del modelo y de los UNIQUE/TTL planificados. |
| **Cambio necesario** | Decidir: tabla `auth_refresh_token` (hash + usuario + expiración + UNIQUE familia) en migración aditiva, o declarar tokens stateless sin refresh en D-07. |
| **Criterio de cierre** | Decisión registrada y, si aplica, tabla en modelo físico con índice/TTL alineado a RF-001. |

---

### AR-12 — “Vistas materializadas” inexistentes en MySQL como mecanismo propuesto

| Campo | Contenido |
|---|---|
| **ID** | AR-12 |
| **Severidad** | LOW |
| **Área** | Decisión sin evidencia / portabilidad |
| **Requisito afectado** | RF-080, RNF-060/061, DB-P07 |
| **Evidencia** | `decisiones_base_datos.md` L717/L1093/L1115: “vistas materializadas”列为 alternativa de reportes — APROBADO **CON CONDICIÓN**. MySQL 8.x no tiene vistas materializadas nativas (requiere tabla agregada + refresh o réplica). `indices_rendimiento.md` §5 correctamente las difiere tras medición (coherente). |
| **Impacto** | Bajo **hoy** (está gateado por medición y DB-P07): el riesgo es que en Paso 16 alguien las implemente como “Vista” (V estándar, sin pre-agregación) esperando rendimiento que no llegaría. |
| **Cambio necesario** | Renombrar la alternativa en futuras revisiones a “tabla agregada refreshable / réplica de lectura (DB-P07)” para que el artefacto no nombre un mecanismo inexistente en el DBMS elegido. |
| **Criterio de cierre** | Término corregido en decisiones al retomar DB-P07; gate de medición §6 previo a cualquier implementación. |

---

### AR-13 — Recuento de tablas: 36 reales vs “37” declaradas

| Campo | Contenido |
|---|---|
| **ID** | AR-13 |
| **Severidad** | LOW |
| **Área** | Coherencia documental |
| **Requisito afectado** | CA-01, trazabilidad de `revision_dba.md` L34 y `plan_migraciones.md` L5 |
| **Evidencia** | Conteo de `CREATE TABLE` en `modelo_fisico.md`: **36**. `revision_dba.md` L34 y `plan_migraciones.md` afirman “37 tablas”. |
| **Impacto** | Los smoke checks “SHOW TABLES = 37” del Paso 15 fallarán o, peor, se ajustarán al número equivocado y taparán una tabla faltante real. |
| **Cambio necesario** | Recountar y unificar el número en ambos documentos (o hallar la tabla que se cayó de la enumeración: la discrepancia misma puede indicar una tabla planeada no modelada — verificar contra prefijos `auth_/core_/cat_/com_/inv_/sales_/pay_`). |
| **Criterio de cierre** | `36`/`37` coincidente entre modelo, plan y revision_dba, con lista explícita de tablas. |

---

### AR-14 — `inv_movimiento_inventario.cantidad` con signo libre: convención por `tipo` no impuesta

| Campo | Contenido |
|---|---|
| **ID** | AR-14 |
| **Severidad** | LOW |
| **Área** | Integridad de dominio |
| **Requisito afectado** | RF-041, RF-090, RN-01 |
| **Evidencia** | `cantidad INT NOT NULL` **firmada** (L388) con solo `CHECK (cantidad <> 0)` (L407). El ENUM `tipo` tiene 9 valores (L387); nada impide `tipo='entrada'` con `cantidad=-5` o `tipo='salida'` con `cantidad=+5`. El resto del modelo usa `INT UNSIGNED` para cantidades (L15 convención) — esta tabla es la excepción justificable (necesita firmas si se usara convención con signo) pero entonces el signo debe casar con `tipo`. |
| **Impacto** | Un bug de app invierte la dirección de un movimiento → el reporte de kardex (RF-080/RF-090) contradice `inv_inventario`; la auditoría queda técnicamente “append-only” pero semánticamente falsa. |
| **Cambio necesario** | O (a) CHECK por rango de `tipo`: positivos para entrada/devolucion/recepcion/transferencia_entrada, negativos solo si se adopta convención mixta (hoy no la hay → todos positivos: `CHECK (cantidad > 0)`), o (b) documentar en integridad §2 que la convención es **siempre valor absoluto + `tipo` como dirección** y que el CHECK `<> 0` es suficiente. Preferida (b) si la app ya escribe absolutos — una línea de doc; (a) si se quiere cierre en BD. |
| **Criterio de cierre** | Convención escrita en integridad/modelo físico y alineada con lo que escriben las migraciones de seed/prueba. |

---

## 4. Coberturas faltantes, supuestos ocultos y decisiones sin evidencia

| # | Tipo | Detalle | Hallazgo ref. |
|---|---|---|---|
| 1 | Supuesto oculto (parcialmente declarado) | App garantiza `idempotency_key NOT NULL` en canal web; BD no lo impone | AR-02 |
| 2 | Supuesto oculto | Job de TTL siempre desplegado y monitoreado en Compose; sin detección de fallo | AR-04 |
| 3 | Supuesto oculto | T2 de webhook asume reserva aún `pending`; caso webhook-tardío no especificado | AR-08 |
| 4 | Condición de carrera | claim `processing` vs ENUM del esquema (la carrera documentada ni siquiera es ejecutable) | AR-01 |
| 5 | Recuperación incompleta | política de reclamo de eventos outbox `processing` huérfanos ausente (deriva de AR-01) | AR-01 |
| 6 | Duplicación por reintentos | protegida en orden/pago (fuerte); **no** en reserva activa ni en líneas de recepción/devolución | AR-03, AR-09 |
| 7 | Requisitos sin cobertura | ninguno: 21/21 RF y 30/30 RNF con soporte o alcance declarado (§2) | — |
| 8 | Sobrearquitectura | **no detectada**: sin particionado prematuro, sin índices “por si acaso” (indices L114), sin microservicios/K8s (CA-09), RESTRICT-only policy uniforme, D-05/D-06 single-node coherente con el perfil | — |
| 9 | Decisión sin evidencia (declarada) | `READ COMMITTED` propuesto sin medición → correctamente gateado a staging (transacciones §2, C-3); “vistas materializadas” nombran mecanismo inexistente en MySQL (gateado) | AR-12 |
| 10 | Decisión sin evidencia (declarada) | DB-P03…P12 + TTL carrito + RPO/RTO = parámetros abiertos aceptados por revision_dba; **no** bloquean diseño, sí producción (C-1…C-10) | fuera de los 5 (contexto) |
| 11 | Deriva documental | estados obsoletos en modelo lógico; recuento 36≠37 tablas | AR-07, AR-13 |
| 12 | Cobertura parcial | RF-090 no cubierto a capa BD en tablas de dinero; refresh token ausente | AR-05, AR-11 |

---

## 5. Anti-hallazgos (verificado y correcto — se declara explícitamente)

- **Sin regresión DB-P09:** `cat_precio.sucursal_id NOT NULL` + `UNIQUE (sucursal_id, producto_id, vigente_desde)` presente (L218); índice antiguo de 3 col eliminado (G3 resuelto). ✔
- **Idempotencia de pagos (D-03/RNF-022):** UNIQUE `idempotency_key` + UNIQUE `(proveedor_pago, referencia_externa)` + `core_idempotency_key` con expiración 30 d e índice de purga (P6). ✔
- **Anti-sobreventa (CA-03/RNF-020):** `FOR UPDATE` sobre fila única por `uq_inv_inventario_producto_sucursal` + `CHECK stock>=0` + una sola clave de stock sin bifurcación web/POS (DB-P01 cerrado). ✔
- **Fronteras transaccionales (RNF-021):** ninguna T abierta espera gateway/webhook; Saga con outbox en misma T (D-04). ✔
- **Orden de locks y reintentos:** orden estable por flujo, claim no bloqueante, reintentos acotados con filas=0 (RNF-042). ✔
- **Índices sin especulación:** solo G1 (alta) y G2 (condicional al TTL); gate EXPLAIN ×10 antes de prod. ✔
- **RN-08 sin DELETE físico:** ENUM estado + RESTRICT uniforme + devolución como registro nuevo. ✔
- **D-07 seguridad (dentro del alcance):** sin secretos en artefactos revisados; blacklist de tokens modelada. ✔

---

## 6. Veredicto final

### **SOUND_WITH_RISKS**

**Justificación (CA-10):**

- **Sin hallazgos CRITICAL.** El modelo no admite sobreventa, no pierde durabilidad (redo commit=1), no tiene transacciones con I/O externo, no introduce stack injustificado.
- **1 HIGH (AR-01) es bloqueante solo para aplicar SQL/Saga tal como están documentados**: es una incoherencia ENUM↔patrón de una línea, cerrable sin retrabajo de modelo (mismo formato que B-1/B-2 cerrados el 2026-09-22). Hasta cerrarse, **no debe darse por bueno el flujo de outbox de D-04**.
- **7 MEDIUM** = defensa en profundidad y coherencia (AR-02…AR-08): no impiden el Paso 15 (generación/aplicación de scripts) pero deben cerrarse o aceptarse por escrito antes de `STATUS: APPROVED` de operación (Paso 17), siguiendo el precedente de C-1…C-10.
- **6 LOW** (AR-09…AR-14): deuda documental/de modelado, se agarran en Paso 15–16 con cambios aditivos.

**Orden de cierre recomendado:**

1. **Antes de aplicar SQL (Paso 15):** AR-01 (ENUM/claim), AR-06 (G1 en DDL), AR-09 (dos UNIQUE), AR-13 (recuento de tablas).
2. **Con solution-leader (decisión, no código):** AR-08 (rama webhook tardío), AR-05 (auditoría de dinero), AR-03 (UNIQUE generada vs residuo aceptado).
3. **Antes de operar (Paso 16–17):** AR-02 (CHECK), AR-04 (alerta TTL), AR-07 (actualizar modelo lógico), AR-10/AR-11/AR-12/AR-14 (documentación/decisiones menores).

**Trazabilidad final:** RF-001…RF-100 y RNF-001…RNF-062 → artefactos: sin omisiones, sin cambios silenciosos, 14 hallazgos trazados a requisito con evidencia de línea. El diseño es **sano con riesgos controlados y fechables**, no está roto.

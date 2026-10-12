# Paso 16 — Consolidación de validación (solution-leader)

**Workflow:** 02_database_workflow — Paso 16 final (consolidación)
**Fecha:** 2026-09-22
**Fuentes consolidadas:** `15_reportes/revision_dba.md`, `15_reportes/revision_seguridad.md`, `15_reportes/revision_arquitectura.md`, `15_reportes/revision_devops.md`, `12_migraciones/plan_migraciones.md`, `../04_decisiones/decisiones_base_datos.md`, `13_sql/V001–V010`, `.agents/state/database-workflow.json`.
**Alcance:** solo consolidación. No ejecuta SQL, no genera código de app, no instala dependencias, no altera RF/RNF, no modifica artefactos existentes.

---

## 1. Resumen ejecutivo

- El **diseño (Pasos 01–13) está aprobado por DBA** (`revision_dba.md`: **STATUS: APPROVED**) tras cerrar el 2026-09-22 los tres bloqueantes: B-1/DB-P09 (precios **por sucursal**), B-2/DB-P01+P02 (reserva web **sí bloquea** stock POS; **TTL = 15 min**, sembrado en V010) y B-3 (carrito **persiste en BD**, `sales_carrito_web*` en V004).
- Los **scripts V001–V010 (Paso 15) existen y son operables**: orden FK padre→hijo correcto, rollback/forward-fix por tipo, sin credenciales en repo, `FOREIGN_KEY_CHECKS=1` siempre; **SEC-01 aplicado** (REVOKE UPDATE sobre `core_auditoria`/`inv_movimiento_inventario` en V009, junto a GRANT INSERT-only = doble capa con triggers V008).
- Hallazgo **HIGH AR-01 corregido en artefactos**: `pay_outbox_evento.estado ENUM('pending','processing','processed','failed')` + `claimed_at` en V006 (claim ejecutable, sin error 1265). También corregidos AR-06 (G1 en V007), AR-07 (modelo lógico actualizado), AR-09 (dos UNIQUE en V003/V004) y AR-13 (recuento unificado en **36 tablas**).
- Estados de las cuatro revisiones: DBA **APPROVED** · Seguridad **APPROVED_WITH_CONDITIONS** (SEC-03/05 pendientes de validación) · Arquitectura **SOUND_WITH_RISKS** (0 CRITICAL; AR-02/04/05/08 MEDIUM abiertas) · DevOps **READY_WITH_CONDITIONS** (DO-01/02/03 de operación/pipeline). Cobertura: 21/21 RF y 30/30 RNF con soporte o alcance declarado; sin cambios silenciosos de RF/RNF; sin sobrearquitectura.
- Los pendientes **DB-P03…P12, TTL de carrito y nivel de aislamiento siguen abiertos como parámetros** (sin cierre documentado → no se inventan valores): no bloquean diseño ni scripts, pero varios condicionan la operación/producción vía C-1…C-10.

---

## 2. Matriz de revisiones

| Revisión | Archivo | Estado | Hallazgos abiertos relevantes |
|---|---|---|---|
| DBA | `revision_dba.md` | **APPROVED** | Condiciones C-1…C-10 sin cerrar (se trasladan a Paso 17): **C-1** DB-P05 RPO/RTO+drill · **C-2** DB-P11 herramienta · **C-3** aislamiento `READ COMMITTED` sin medir · **C-4** G1 (ya materializado en V007 → verificar smoke) · **C-5** DB-P03 · **C-6** DB-P08 · **C-7** DB-P12 · **C-8** DB-P07 · **C-9** DB-P06 · **C-10** retención `core_auditoria` sin política legal. Bloqueantes B-1/B-2/B-3 **CERRADOS** (2026-09-22). |
| Seguridad | `revision_seguridad.md` | **APPROVED_WITH_CONDITIONS** | **SEC-01** (HIGH): REVOKE **ya aplicado en V009** → verificar en smoke. **SEC-03**: privilegios binlog PITR — grants `REPLICATION CLIENT, BINLOG MONITOR` presentes en V009, falta validar/ajustar al método real en MySQL 8.4. **SEC-05**: DB-P05 + drill de restore sin ejecutar. **SEC-02** (MEDIUM): grants blanket vs matriz §3 → alinear o documentar desviación. **SEC-04** (MEDIUM): fila column-level no implementable en MySQL → corregir doc. SEC-06…SEC-09 (LOW): backlog de refinamiento. Sin secretos detectados. |
| Arquitectura | `revision_arquitectura.md` | **SOUND_WITH_RISKS** | 0 CRITICAL; HIGH **AR-01 corregido** (V006). Corregidos también AR-06 (V007), AR-07, AR-09 (V003/V004), AR-13 (36). **Abiertas (MEDIUM, condiciones):** **AR-02** CHECK `canal<>'web' OR idempotency_key IS NOT NULL` ausente · **AR-04** alerta/fallback TTL de reservas sin especificar · **AR-05** auditoría append-only solo en 2 tablas, no en tablas de dinero · **AR-08** carrera webhook tardío ↔ job TTL sin transición condicional. LOW en backlog: AR-03 (UNIQUE reserva activa / residuo), AR-10, AR-11, AR-12, AR-14. |
| DevOps | `revision_devops.md` | **READY_WITH_CONDITIONS** | **DO-01** (= C-2/DB-P11): herramienta de migraciones no elegida → CI manual hasta decidir. **DO-02** (= C-1/SEC-05): RPO/RTO provisionales + drill de restore pendiente. **DO-03**: jobs TTL/purge sin materializar en Compose (cron + restart + alerta). DO-04/DO-05/DO-06 (LOW): runbook tmp/INPLACE, sin auto-healing (aceptado CA-09), bootstrap de admin. |

---

## 3. Condiciones heredadas para Paso 17 (despliegue)

Ninguna condición bloquea esta consolidación; **todas deben cumplirse (o aceptarse por escrito) antes/durante el despliegue**:

| # | Condición | Origen | Criterio de cierre |
|---|---|---|---|
| 1 | **SEC-01 (smoke)** | seguridad | Verificar tras aplicar V009 que `app_rw` **no** tiene UPDATE sobre `core_auditoria` ni `inv_movimiento_inventario` (SELECT+INSERT sí); doble capa = REVOKE/GRANT INSERT (V009) + triggers SIGNAL (V008). |
| 2 | **SEC-03 + SEC-05** | seguridad | Validar en staging MySQL 8.4 los privilegios de binlog para PITR (ajustar `PROCESS`/`RELOAD` al método real) **y** cerrar DB-P05 (RPO/RTO finales, cadencia de binlog) + **1 drill de restore** antes de Paso 17. |
| 3 | **DO-01** | devops (= C-2/DB-P11) | Elegir herramienta de migraciones (Flyway/Liquibase/manual-firmado) **antes de automatizar CI**; hasta entonces, despliegue manual con checklist del plan §9. |
| 4 | **DO-02** | devops (= C-1/SEC-05) | Cerrar DB-P05 + drill de restore en test antes de prod (un restore no probado no es backup). |
| 5 | **DO-03** | devops (= AR-04) | Compose de operación con servicio/cron de TTL/purge, restart policy y alerta paramétrica (umbral de “reservas vencidas” en `core_configuracion` — valor no inventado aquí). |
| 6 | **AR-02** | arquitectura | CHECK compuesto `canal <> 'web' OR idempotency_key IS NOT NULL` (modelo + migración aditiva) **o** excepción firmada como riesgo aceptado en `decisiones_base_datos.md`. |
| 7 | **AR-04** | arquitectura | Alerta o check de sanidad de reservas vencidas > umbral documentado con su parámetro + runbook del job (Paso 16/17). |
| 8 | **AR-05** | arquitectura | Decisión documentada sobre auditoría en tablas de dinero (`sales_orden_venta`, `sales_devolucion`, `pay_transaccion_pago`): (a) triggers `AFTER UPDATE` → `core_auditoria`, (b) `version` + auditoría en misma T, o (c) residuo aceptado por escrito en decisiones. |
| 9 | **AR-08** | arquitectura | Transición del webhook tardío especificada: `UPDATE inv_reserva … WHERE estado='pending'`; filas=0 → ruta de compensación definida por solution-leader con RF-063 (no inventar la regla aquí); test de caso límite. |
| 10 | **C-1…C-10** | DBA | C-1 y C-2 ≡ DO-02 y DO-01. C-3: fijar aislamiento con medición en staging (gate EXPLAIN). C-4: verificar `SHOW INDEX` de G1 (ya en V007). C-5→DB-P03, C-6→DB-P08, C-7→DB-P12, C-8→DB-P07, C-9→DB-P06 antes de operar sus flujos; C-10: retención de `core_auditoria` correlacionada con DB-P05 (no inventar plazo). |

**Complemento documental (seguridad):** SEC-02 (alinear grants con matriz §3 o documentar la desviación) y SEC-04 (corregir fila column-level de `seguridad.md`) como condiciones menores heredadas del mismo informe.

**Reglas de ejecución heredadas (plan §5–§6 / workflow Paso 17):** ventana de bootstrap V001–V010 en BD nueva; rol `migraciones` (nunca el rol de la app); STOP si falla el backup; **sin `DROP`/`TRUNCATE` sin autorización explícita**.

---

## 4. Pendientes no bloqueantes (parámetros — valores NO inventados)

Sin cierre documentado → **siguen abiertos**. No se asigna ningún valor aquí.

| ID | Parámetro abierto | Efecto declarado |
|---|---|---|
| **DB-P03** | Máximo de ítems de productos por transferencia | Granularidad y orden de locks (C-5). Cerrar antes de transferencias multi-ítem de volumen grande. |
| **DB-P04** | Proveedor(es) de pago iniciales | Saga, Outbox, estados de `pay_*`. |
| **DB-P05** | RPO/RTO **finales**, cadencia de backup/binlog y drill de restore (los valores actuales `<1 h`/`<4 h` son **provisionales**, no SLA) | Gate C-1 / SEC-05 / DO-02: **exigible antes de prod**. |
| **DB-P06** | ¿POS opera offline? | Si se aprueba, requiere diseño de resincronización/conflictos fuera del modelo actual (C-9). |
| **DB-P07** | Latencia aceptable de reportes / réplica de lectura | Evaluar en staging con `slow_query_log` antes de tabla agregada o réplica (C-8); corregir luego el término “vistas materializadas” (AR-12). |
| **DB-P08** | Modelo definitivo de clientes / UNIQUE de identificación | CHECK/UNIQUE aditivo con requirements (C-6). |
| **DB-P10** | Detalle de fulfillment (entrega vs retiro) | Reservas, sucursales, estados. |
| **DB-P11** | Herramienta final de migraciones | Plan neutral; bloquea **automatización CI**, no el orden SQL (C-2/DO-01). |
| **DB-P12** | Estrategia de eliminación por entidad maestra | Soft-delete = migración aditiva futura; default actual = estado (C-7). |
| **TTL carrito** | Ventana de purga de `sales_carrito_web*` | No inventar; índice **G2 condicionado** a este valor. |
| **Aislamiento** | Nivel `READ COMMITTED` propuesto **sin medición**, no fijado en `my.cnf` | Validar en staging con métricas + gate EXPLAIN (C-3). |

**Cerrados (no reabrir):** DB-P01 (bloqueo SÍ), DB-P02 (TTL 15 min), DB-P09 (precios por sucursal) — decisión 2026-09-22.

---

## 5. Decisión final

# STATUS: APPROVED_FOR_DEPLOYMENT

**Justificación:** las cuatro revisiones aprueban el diseño y los scripts V001–V010 sin hallazgos CRITICAL, con el único HIGH (AR-01) ya corregido en `13_sql/V006` y SEC-01 aplicado en V009, y DevOps declara el set operable en D-06 — diseño y scripts están listos. El despliegue (Paso 17) queda **condicionado** al cumplimiento de los gates de la sección 3 (SEC-01 smoke, SEC-03+05, DO-01/02/03, AR-02/04/05/08, C-1…C-10), que son de operación/pipeline y no de diseño.

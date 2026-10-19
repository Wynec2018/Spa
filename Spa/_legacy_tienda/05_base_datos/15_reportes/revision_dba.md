# Paso 14 — Revisión DBA de los artefactos de base de datos

**Workflow:** 02_database_workflow — Paso 14
**Skills:** `database-schema-designer`, `databases`, `mysql-patterns` (MySQL 8.x/InnoDB)
**Alcance:** revisión de los artefactos de `proyecto/05_base_datos/` Pasos 01–13 + `04_decisiones/decisiones_base_datos.md` + requisitos (`01_requisitos/`).
**No despliega. No genera SQL. No se conecta a ninguna base.** (Pasos 15–17 quedan bloqueados hasta el STATUS.)

## 1. Artefactos revisados

| Paso | Artefacto | Veredicto |
|---|---|---|
| 01 | `01_requisitos_datos/requisitos_datos.md` | conforme |
| 02 | `02_modelo_conceptual/{modelo_conceptual,diagrama_er}.md` | conforme |
| 03 | `03_modelo_logico/modelo_logico.md` | conforme |
| 04 | `04_normalizacion/informe_normalizacion.md` | conforme |
| 05 | `05_modelo_fisico/seleccion_dbms.md` | conforme (MySQL vs PostgreSQL; coherente con decisión vinculante) |
| 07 | `05_modelo_fisico/modelo_fisico.md` | conforme con observaciones O-1…O-3 |
| 08 | `06_integridad/integridad.md` | conforme (FK RESTRICT, CHECK, UNIQUE, append-only) |
| 09 | `07_seguridad/seguridad.md` | conforme (5 roles, mínimo privilegio, hardening checklist) |
| 10 | `08_auditoria/auditoria.md` + `09_versionamiento/versionamiento.md` | conforme (delimitación auditoría/histórico/DDL; expand-contract, drift) |
| 11 | `10_indices_rendimiento/indices_rendimiento.md` | conforme (índice→consulta; G1/G2 gaps; sin pre-particionar) |
| 12 | `11_transacciones_concurrencia/transacciones_concurrencia.md` | conforme (ACID+Saga, FOR UPDATE, orden de locks, idempotencia doble capa) |
| 13 | `12_migraciones/plan_migraciones.md` | conforme (V001…V010, rollback/forward-fix por tipo, drop gated) |

## 2. Cumplimiento de decisiones vinculantes

| Decisión (estado) | ¿Reflejada? | Evidencia |
|---|---|---|
| MySQL 8.x/InnoDB, utf8mb4 (APROBADO) | sí | seleccion_dbms, modelo_fisico (ENGINE=InnoDB) |
| D-01 reserva + `FOR UPDATE` (APROBADO CON CONDICIÓN) | sí | transacciones §4; DB-P01/P02 **cerrados 2026-09-22** (bloqueo SÍ, TTL 15 min); **DB-P03 sigue abierto** (parámetro, C-5) |
| D-02 ACID + Saga (APROBADO) | sí | transacciones §3/§7; sin T abierta sobre gateway |
| D-03 Idempotency Key + UNIQUE, 30 d (APROBADO) | sí | `core_idempotency_key` + UNIQUEs de orden/pago; Purge en plan/jobs |
| D-04 Outbox en misma T (APROBADO) | sí | `pay_outbox_evento` insert en T1; claim no bloqueante |
| Prefijos por módulo + una BD (APROBADO) | sí | 36 tablas con `auth_`…`pay_` |
| Append-only `core_auditoria`/`inv_movimiento_inventario` (APROBADO) | sí | GRANT INSERT-only (Paso 09) + triggers V008 |
| `user_create*`/`user_update*` en tablas de negocio | sí | modelo_fisico (convención en todas) |
| Sin DELETE físico en transaccionales (RN-08) | sí | ENUM estado + RESTRICT + devolución como registro nuevo |
| `core_configuracion` (RF-100) | sí | tabla + UNIQUE clave + semilla de parámetros V010 |
| Backup completo + binlog, RPO/RTO provisorios (APROBADO CON VALIDACIÓN) | parcial | seguridad §6 (`log_bin=ON`); **valores finales = DB-P05** |
| D-05/D-06 single-node Docker Compose | sí (contexto) | fuera del alcance BD; no contradice artefactos |
| Conflicto `03_resultados/08` (PostgreSQL) no vinculante | sí | documentado; no se usó como fuente |

## 3. Hallazgos

### 3.1 Bloqueantes (ya resueltos 2026-09-22 — requisito de `STATUS: APPROVED`)

| ID | Hallazgo | Resolución |
|---|---|---|
| **B-1** | **DB-P09** precio global vs por sucursal | **CERRADO**: precio **por sucursal**; `cat_precio.sucursal_id NOT NULL` + UNIQUE `(sucursal_id, producto_id, vigente_desde)` aplicado en modelo conceptual/lógico/físico; índice antiguo de 3 col eliminado; sin retrabajo de migración. |
| **B-2** | **DB-P01/DB-P02** bloqueo reserva↔POS y expiración | **CERRADOS**: reserva web **sí bloquea** stock POS (regla fija, sin clave dual); **TTL = 15 min** (`reserva_ttl_minutos` seed V010) con job `SKIP LOCKED` idempotente; transacciones §4/§8/§11 actualizados. |
| **B-3** | **Supuesto de carrito web** | **CONFIRMADO**: carrito **persiste en BD** (`sales_carrito_web*` en V004). TTL de purga **sigue abierto** (no inventar) — no bloquea diseño; G2 condicionado. |

### 3.2 Condicionales (no bloquean diseño, bloquean pase a prod)

| ID | Hallazgo | Acción |
|---|---|---|
| C-1 | **DB-P05** RPO/RTO provisionales → frecuencia de backup/binlog y drill de restore sin cerrar. | Validar antes de Paso 17 (despliegue); checklist seguridad §6/§8. |
| C-2 | **DB-P11** herramienta de migraciones no elegida; plan es neutral. | Elegir antes de automatizar CI (criterios Paso 10 §5). |
| C-3 | Nivel de aislamiento propuesto (`READ COMMITTED`) **sin medición**; no fijado en my.cnf. | Validar en staging con métricas (Paso 11 gate EXPLAIN + Paso 12 §2). |
| C-4 | **G1** (`sales_orden_venta(sucursal_id, creado_en)`) gap de índice de prioridad alta aún no en el modelo. | Incluir en V007/V011 del plan (ya previsto; asegurar que Paso 15 lo materializa). |
| C-5 | **DB-P03** máximo ítems de transferencia → granularidad/orden de locks sin fijar. | Parámetro; cerrar antes de operar transferencias multi-ítem de volumen grande. |
| C-6 | **DB-P08** identificación de cliente / UNIQUE parcial depende de decisión. | Cerrar con requirements; puede añadir CHECK/UNIQUE aditivo. |
| C-7 | **DB-P12** soft-delete por maestra abierta. | Política por defecto bien definida (estado); cambios futuros = migración aditiva. |
| C-8 | **DB-P07** réplica de lectura pendiente; reportes RF-080 pueden competir con OLTP en 10x. | Evaluar en staging con slow_query_log antes de añadir vistas materializadas. |
| C-9 | **DB-P06** POS offline: sin modelar sincronización/conflictos. | Si se aprueba, requiere diseño de resincronización (fuera del modelo actual). |
| C-10 | Retención de `core_auditoria` sin política legal definida. | Correlacionar con DB-P05; no inventar plazo. |

### 3.3 Observaciones no bloqueantes

| ID | Observación | Tratamiento |
|---|---|---|
| O-1 | FK `inv_reserva.orden_id → sales_orden_venta` exige orden de CREATE; el plan lo resuelve (V005 al final) sin `FOREIGN_KEY_CHECKS` a medias. | OK — verificar en Paso 15 que el script final lo respeta. |
| O-2 | `sales_orden_venta_item` UNIQUE `(orden_id, producto_id)` no cubre rango fecha (índice V6 del Paso 11) — correcto no pre-optimizar. | Medir con EXPLAIN; ya documentado. |
| O-3 | `cat_categoria` self-FK: CHECK solo evita self-loop (A→B→A detectado en app, máx. profundidad 5). | Límite de integridad delegado ya declarado — aceptable con documentación en API. |
| O-4 | Append-only en columnas de autoría: trigger bloquea UPDATE completo (decisión simplificada del Paso 08). | Aceptable; el INSERT ya lleva `user_create*`. |
| O-5 | Postgres de `03_resultados/08` vs MySQL de decisiones: conflicto resuelto a favor de `04_decisiones` (vinculante). | Requiere que solution-leader/arquitecto **archiven o marquen** el PDF/MD de resultados para evitar confusión futura de equipo. |

### 3.4 Cobertura de seguridad (revisión enfocada)

- [x] Sin secretos/credenciales en artefactos (verificado en revisión).
- [x] Roles mínimo privilegio; app sin DELETE en append-only; sin SUPER/GRANT en app.
- [x] TLS (`require_secure_transport`), `caching_sha2_password`, Argon2id (contexto seguridad).
- [x] Anti-inyección: parametrización declarada como contrato app↔BD (Paso 09 §7).
- [ ] Drill de restore y cadencia final (→ C-1/DB-P05).
- [ ] Inyección de secret de `rol_migraciones` en pipeline (→ C-2/DB-P11).

### 3.5 Rendimiento y concurrencia (revisión enfocada)

- [x] Cobertura índice→patrón con estados OK/GAP/RED; regla “no índice por si acaso”.
- [x] Anti-sobreventa: 3 capas (FOR UPDATE + CHECK + UNIQUE) con plan B `version`.
- [x] Orden de locks por flujo; outbox claim no bloqueante; idempotencia doble capa.
- [x] Frontera transaccional sin I/O externo (RNF-021).
- [ ] G1 materializado (→ C-4); aislamiento medido (→ C-3); gate EXPLAIN ×10 pendiente de staging (sin datos vivos no se puede cerrar aquí).

## 4. Trazabilidad resumida

RF-001…RF-100 y RNF-001…RNF-062: cada decisión de `decisiones_base_datos.md` §23 aparece al menos en un artefacto de Pasos 06–13 con su estado real (cerrado / parámetro / abierto). No se detectaron cambios silenciosos de RF/RNF ni imposiciones de stack fuera del alcance.

## 5. Veredicto

El diseño está **coherente, completo y trazable**. Los tres bloqueantes (B-1 precio, B-2 reserva/expiración, B-3 carrito) fueron **cerrados el 2026-09-22** con decisión del usuario/solution-leader y los artefactos de Pasos 01–13 fueron actualizados en sitio (sin SQL ejecutado). Las condiciones C-1…C-10 **no impiden el Paso 15** y se trasladan como **condiciones de despliegue** a Pasos 16–17.

**Pendientes abiertos aceptados como parámetro (no bloquean SQL):** DB-P03, DB-P04, DB-P05, DB-P06, DB-P07, DB-P08, DB-P10, DB-P11, DB-P12, TTL de purga de carrito, nivel de aislamiento (medición staging).

---

**STATUS: APPROVED**

**Condicionantes cerrados:** B-1 (DB-P09 → por sucursal), B-2 (DB-P01/P02 → bloqueo SÍ + TTL 15 min), B-3 (carrito → persiste en BD). **Condiciones C-1…C-10** documentadas arriba: desplazar a plan de despliegue (Pasos 16–17) sin impedir el Paso 15 (generación de scripts SQL en `13_sql/`).

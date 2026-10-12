# Paso 20 — Validación final · Workflow 02_database_workflow

> **Fecha:** 2026-09-22  
> **Alcance:** consolidación de Pasos 01–20 (requisitos de datos → informe final)  
> **Entorno vivo:** MySQL Community Server 8.4.3 · `localhost:3306` · esquema `sistema`  
> **Credenciales:** solo desde `.env` (nunca en el repo)  
> **Fuente de verdad:** `proyecto/00_contexto/`, `01_requisitos/`, `02_configuracion/` + introspección de la BD desplegada

---

## 1. Estado del workflow

| Paso | Artefacto | Estado |
|---:|---|---|
| 01 | `01_requisitos_datos/requisitos_datos.md` | COMPLETO |
| 02 | `02_modelo_conceptual/modelo_conceptual.md` | COMPLETO |
| 03 | `02_modelo_conceptual/diagrama_er.md` | COMPLETO |
| 04 | `03_modelo_logico/modelo_logico.md` | COMPLETO |
| 05 | `04_normalizacion/informe_normalizacion.md` | COMPLETO |
| 06 | `05_modelo_fisico/seleccion_dbms.md` (MySQL 8.x LTS 8.4, D-06) | COMPLETO |
| 07 | `05_modelo_fisico/modelo_fisico.md` | COMPLETO |
| 08 | `06_integridad/integridad.md` | COMPLETO |
| 09 | `07_seguridad/seguridad.md` | COMPLETO |
| 10a/10b | `08_auditoria/auditoria.md` · `09_versionamiento/versionamiento.md` | COMPLETO |
| 11 | `10_indices_rendimiento/indices_rendimiento.md` | COMPLETO |
| 12 | `11_transacciones_concurrencia/transacciones_concurrencia.md` | COMPLETO |
| 13 | `12_migraciones/plan_migraciones.md` | COMPLETO |
| 14 | `15_reportes/revision_dba.md` | **STATUS: APPROVED** |
| 15 | `13_sql/V001__…` … `V010__seed.sql` (10 scripts) | APLICADO — EXIT:0 (`deploy_log_paso17.txt`) |
| 16a–d | `revision_seguridad.md` · `revision_arquitectura.md` · `revision_devops.md` · `validacion_consolidacion.md` | ver §2 |
| 17 | Despliegue + smoke | PASS (ver §4) |
| 18 | `15_reportes/documentacion_real.md` | PASS — gate 22/22 |
| 19 | `14_pruebas/resultados_pruebas.md` | **PASS — 24/24 tests** |
| 20 | `15_reportes/validacion_final.md` | este informe |

Total de artefactos en `proyecto/05_base_datos/`: **53 archivos** (incluye `.database-documentation/` con ground truth de introspección).

Checkpoint: `.agents/state/database-workflow.json` → `current_step: 21` (workflow cerrado tras Paso 20).

---

## 2. Revisiones (Pasos 14 y 16)

| Revisión | Veredicto | Condición clave |
|---|---|---|
| DBA (`revision_dba.md`) | **APPROVED** | B-1/B-2/B-3 cerrados 2026-09-22; C-1…C-10 → gates de despliegue |
| Seguridad (`revision_seguridad.md`) | **APPROVED_WITH_CONDITIONS** | SEC-01 obligatoria (aplicada en V009, verificada en smoke + T011); SEC-02/03/05/04 heredadas |
| Arquitectura (`revision_arquitectura.md`) | **SOUND_WITH_RISKS** | Sin CRITICAL; AR-01 corregido en V006; AR-02/04/05/08 abiertas |
| DevOps (`revision_devops.md`) | **READY_WITH_CONDITIONS** | D-06 operable; DO-01/02/03 → operación/pipeline |
| Consolidación (`validacion_consolidacion.md`) | **APPROVED_FOR_DEPLOYMENT** | Despliegue condicionado a gates §3 (operación, no diseño) |

Sin hallazgos CRITICAL en ninguna revisión. Único HIGH (AR-01) corregido antes de SQL.

---

## 3. Gate estructural vivo (Paso 18)

| Métrica | Diseño | Real | Diff |
|---|---:|---:|---:|
| Tablas | 36 | **36** | 0 |
| Columnas | 338 | **338** | 0 |
| InnoDB | 36/36 | **36/36** | 0 |
| non-utf8mb4 | 0 | **0** | 0 |
| FK | 48 | **48** | 0 |
| UNIQUE (índices secundarios) | 24 | **24** | 0 |
| CHECK | 22 | **22** | 0 |
| Triggers (append-only) | 4 | **4** | 0 |
| Definiciones de índice | — | **108** (36 PK + 72 secundarias) | — |
| Partes de índice | — | **131** | — |
| Columnas PRIMARY | — | **39** | — |
| Vistas / Rutinas / Eventos | 0 | **0 / 0 / 0** | 0 |

Nombres FK y CHECK: **1:1** entre diseño y vivo. **Gate: PASS (22/22 checks de documentación).**

Dominios: `auth_`(6) · `core_`(6) · `cat_`(4) · `com_`(6) · `inv_`(6) · `sales_`(6) · `pay_`(2).

---

## 4. Despliegue (Paso 17) — resumen

- V001–V010 aplicados con cliente MySQL (rol `migraciones`), **EXIT:0**.
- Fixes históricos documentados en `deploy_log_paso17.txt`: REVOKE idempotente (ERROR 1147), `USE sistema;` en V010 (ERROR 1046).
- Smoke post-V010: **PASS** — 36 tablas, 4 triggers, 131 partes de índice, TTL `reserva_ttl_minutos`=15, seed 9 permisos / 1 rol admin / 9 asignaciones, 5 roles creados, **SEC-01 verificado** (app_rw sin UPDATE en ambas tablas append-only).
- Pendientes de ese paso (siguen abiertos, ver §6): drill de restore (SEC-05/DO-02), job TTL (DO-03), herramienta de migraciones (DO-01/DB-P11).

---

## 5. Pruebas (Paso 19) — resumen

**24/24 PASS** — detalle completo en `14_pruebas/resultados_pruebas.md`.

| Bloque | Tests | Resultado |
|---|---:|---|
| Gate estructural (T001–T009, T015) | 10 | PASS |
| Permisos / roles / reglas FK (T010–T012) | 3 | PASS — SEC-01 en vivo |
| Migraciones (T013) | 1 | PASS* — hallazgo: sin tabla de histórico (DB-P11 abierta) |
| Integridad negativa FK/UNIQUE/NOT NULL/CHECK (T016–T019) | 4 | PASS — ERROR 1452 / 1062 / 1048 / 3819 |
| Transacciones rollback (T020) | 1 | PASS — before=1 → after=0 |
| Append-only triggers (T008, T014, T021–T022) | 5 | PASS — ERROR 1644/45000 en ambas tablas |

\* T013: `schema_migrations`/`flyway_schema_history` ausentes — consecuencia esperada de **DB-P11** (plan neutro a herramienta); no bloquea diseño ni despliegue.

**Evidencia de defensa en profundidad (SEC-01), 2 capas ambas PASS:**
1. DCL: REVOKE UPDATE en `core_auditoria` e `inv_movimiento_inventario` para `app_rw` (V009) → T011.
2. DDL: 4 triggers BEFORE INSERT/UPDATE SIGNAL 45000 (V008) → T021/T022.

Residual de pruebas documentado en `resultados_pruebas.md` §8 (aud=1, mov=1 bloqueados por triggers — comportamiento esperado). Sin `DROP`/`TRUNCATE` ejecutados.

---

## 6. Condiciones y pendientes abiertos (NO inventar valores)

Heredados de `validacion_consolidacion.md` §3–§4 — **no se cierran en este informe**:

### Operación / pipeline (antes de productivo)
| ID | Condición |
|---|---|
| DO-02 / SEC-05 / DB-P05 | RPO/RTO **finales** + drill de restore ejecutado |
| DO-01 / DB-P11 | Herramienta de migraciones elegida (hoy: plan neutral, sin histórico en BD) |
| DO-03 / AR-04 | Job TTL/purge de carrito en Compose + alerta paramétrica (TTL carrito aún sin definir) |
| C-3 / Aislamiento | Fijar nivel de aislamiento con medición en staging + gate EXPLAIN |

### Diseño / decisión (aditivas, no bloquean operación actual)
| ID | Condición |
|---|---|
| AR-02 | CHECK `canal <> 'web' OR idempotency_key IS NOT NULL` o excepción firmada |
| AR-05 | Política de auditoría en tablas de dinero (3 opciones en revisión) |
| AR-08 | Transición de webhook tardío + ruta de compensación (RF-063) + test de límite |
| SEC-02 / SEC-04 | Alinear grants con matriz §3 / corregir fila column-level de `seguridad.md` |
| DB-P03, P04, P06, P07, P08, P10, P12 | Parámetros abiertos (ver consolidación §4) |

**Cerrados (no reabrir):** DB-P01 (bloqueo SÍ) · DB-P02 (TTL 15 min) · DB-P09 (precios por sucursal) — decisión 2026-09-22.

**Reglas heredadas vigentes:** ventana bootstrap en BD nueva; rol `migraciones` nunca el de la app; STOP si falla el backup; **sin `DROP`/`TRUNCATE` sin autorización explícita**; credenciales solo `.env`.

---

## 7. Trazabilidad

- RF-001…RF-100 y RNF-001…RNF-062: sin cambios silenciosos (verificado en revisiones 14/16).
- Decisiones de `decisiones_base_datos.md` §23 mapeadas a artefactos de Pasos 06–13 con estado real.
- Ground truth de introspección en `proyecto/05_base_datos/.database-documentation/` (DDL, FK, CHECK, índices, triggers).
- Flujo de skills BD respetado: `SKILLS_SOURCES.md` + `02_database_workflow.md` + checkpoint por paso; skills externos de `.agents/skills/` (sin duplicar instalados).

---

## 8. Decisión final

# STATUS: WORKFLOW_COMPLETE

**El workflow `02_database_workflow` Pasos 01–20 está completo.**

- Diseño aprobado (DBA APPROVED, arquitectura SOUND_WITH_RISKS, seguridad APPROVED_WITH_CONDITIONS, devops READY_WITH_CONDITIONS → consolidación **APPROVED_FOR_DEPLOYMENT**).
- Scripts V001–V010 aplicados sin error; smoke PASS.
- Documentación de la BD real: gate **PASS 22/22** (36 tablas / 338 columnas / 48 FK / 22 CHECK / 24 UNIQUE / 4 triggers — 0 diff vs diseño).
- Pruebas: **24/24 PASS**, incluida defensa en profundidad SEC-01 en sus dos capas.
- Pendientes restantes son de **operación, pipeline y decisiones aditivas** (§6): no bloquean este workflow; **sí bloquean el pase a productivo** hasta cerrarse documentadamente.

**Siguiente paso fuera de este workflow:** cerrar gates DO-02/DO-01/DO-03 + C-3 y condiciones AR/SEC abiertas en el plan de despliegue a producción; ningún valor de esos parámetros se inventa aquí.

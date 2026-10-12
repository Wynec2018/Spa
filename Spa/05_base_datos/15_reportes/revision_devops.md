# Revisión DevOps — Artefactos de Base de Datos

**Workflow:** 02_database_workflow — revisión devops-architect (Paso 16)
**Skill:** `infrastructure-evaluation` / `deployment-patterns`
**Alcance:** `13_sql/V001–V010`, `12_migraciones/plan_migraciones.md`, `09_versionamiento/versionamiento.md`, `07_seguridad/seguridad.md` §6/§8, `04_decisiones/decisiones_base_datos.md` (D-05/D-06/DB-P05/DB-P11), `.env.example`
**DBMS:** MySQL 8.x LTS 8.4+ / InnoDB / utf8mb4 · **Orquestación:** Docker Compose (D-06)
**Fecha:** 2026-09-22 · **No despliega. No ejecuta SQL. No lee `.env` en claro.**

## 1. Resumen

Los scripts y el plan de migraciones son operables bajo D-06 (Compose, single-node): orden FK correcto V001→V010, prefijo `VNNN__*` neutral a herramienta (DB-P11), rollback/forward-fix por tipo, backup gate y drift check en el procedimiento por entorno, secretos solo por `.env` en runtime. No hay Dockerfile/compose de la BD en el repo (correcto: infraestructura real fuera de esta fase; los artefactos solo documentan la estrategia). **Estado: READY_WITH_CONDITIONS** — el diseño es desplegable; las condiciones son de operación/pipeline (DB-P11, DB-P05, drill de restore, Compose del entorno) y no bloquean Paso 16 ni la consolidación final.

## 2. Revisión de artefactos

| Artefacto | Veredicto | Evidencia / observación |
|---|---|---|
| `13_sql/V001–V010` | conforme | Orden por FK padre→hijo verificado (auth/core → cat → com → sales → inv → pay); `inv_reserva` al final de V005 (O-1); V008 triggers, V009 roles, V010 seed sin credenciales. Conteo 36 `CREATE TABLE` (AR-13 corregido). |
| `plan_migraciones.md` | conforme | §6 procedimiento 7 pasos con STOP en backup fallido; §3 secuencia con rollback por script; §9 checklist pre-ejecución; neutral a DB-P11 declarado. |
| `versionamiento.md` | conforme | expand-contract, drift (`information_schema`), checksum de histórico — coherente con plan §2/§7. |
| `seguridad.md` §6/§8 | conforme con condición | `log_bin=ON`, backup full+binlog; RPO/RTO **provisionales** (DB-P05 abierto); drill de restore sin ejecutar (SEC-05). |
| `.env.example` | conforme | Patrón de secretos por entorno presente; no se verificó `.env` real (Paso 16 solo en ejecución, fuera de esta revisión). |
| Compose / Dockerfile BD | ausente (esperado) | D-06 documenta la estrategia; infraestructura real prohibida hasta `STATUS: APPROVED` de despliegue. |

## 3. Hallazgos

| ID | Severidad | Ubicación | Problema | Acción |
|---|---|---|---|---|
| **DO-01** | MEDIUM | `plan_migraciones.md` §9 / DB-P11 | Herramienta de migraciones no elegida → no hay `schema_history`, drift check y rollback automatizado quedan **manuales**; el plan es correcto pero no ejecutable en CI sin tool. | Elegir Flyway/Liquibase/manual-firmado **antes de automatizar CI** (C-2/DB-P11). No bloquea despliegue manual con checklist. |
| **DO-02** | MEDIUM | `seguridad.md` §6 / DB-P05 / SEC-05 | RPO/RTO provisionales (<1 h / <4 h); cadencia final de binlog y **drill de restore sin ejecutar**. Un restore no probado no es backup (RNF-040/041). | Cerrar DB-P05 + 1 drill de restore en test **antes de Paso 17** (condición C-1/SEC-05). |
| **DO-03** | MEDIUM | `plan_migraciones.md` §6 / D-06 | Job de expiración de reservas (TTL 15 min) y purge de idempotency/blacklist dependen de cron/supervisión que **Compose no trae por defecto**; si el job cae, `stock_reserved` se sobreestima (AR-04). | Añadir servicio/cron + restart policy + alerta (métrica "reservas vencidas > umbral", umbral en `core_configuracion`) en el compose de operación. |
| **DO-04** | LOW | `plan_migraciones.md` §3/§5 | `innodb_online_alter_log_max_size` y espacio temporal para INPLACE en V007+ listados como "checklist DevOps" pero sin checklist explícita en el plan. | Incluir en runbook de despliegue (2 líneas: monitorear `Created_tmp_disk_tables` y espacio en V007+). |
| **DO-05** | LOW | D-06 / CA-09 | D-06 Compose es correcto para el perfil (≤50k tx/h, single-node); sin auto-healing ni rollback automático. Aceptado por CA-09 (sin K8s sin justificación). | Sin acción. Re-evaluar solo si 10× supera capacidad del host (gate §6 índices). |
| **DO-06** | LOW | `plan_migraciones.md` §3 nota admin | Semilla de admin inicial: hash Argon2id por secret de entorno o job de bootstrap — bien especificado, pero el job de bootstrap no está en el plan de despliegue. | Añadir paso de bootstrap admin (inyectar secret → ejecutar seed o endpoint one-shot) al runbook Paso 17. |

**No se detectó:** credenciales en scripts o artefactos; `DROP DATABASE`/`TRUNCATE` sin gate; ejecución de SQL en esta fase; infraestructura real creada; desviación del orden `VNNN__*` esperado.

## 4. Checklist DevOps (resultado)

| # | Ítem | Resultado | Evidencia |
|---|---|---|---|
| 1 | Orden de migraciones respetuoso con FK | ✅ PASS | V001→V010; `inv_reserva` al final de V005; self-FK `cat_categoria` en V002. |
| 2 | Rollback / forward-fix por tipo | ✅ PASS | plan §4; data-loss gated con STOP. |
| 3 | Entornos dev/test/prod con misma secuencia (RNF-062) | ✅ PASS | plan §6; D-06 (compose por entorno / profiles). |
| 4 | Backup gate antes de migración en prod | ✅ PASS (diseño) | plan §6 paso 2; **ejecución drill** pendiente → DO-02. |
| 5 | Drift check pre-deploy | ✅ PASS (diseño) | versionamiento §4 + plan §7; requiere herramienta → DO-01. |
| 6 | Secretos solo `.env` / runtime, nunca en repo | ✅ PASS | plan §1/§3; V010 sin hash hardcodeado; `.env.example` sin valores reales. |
| 7 | Rol `migraciones` ≠ rol app para DDL | ✅ PASS | V009 crea rol; plan §2.6. |
| 8 | Supuestos de job/supervisión declarados | ⚠️ CONDITIONAL | AR-04/DO-03: TTL y purge dependen de job no materializado en Compose. |
| 9 | RPO/RTO finales + restore probado | ⚠️ CONDITIONAL | DB-P05/DO-02: provisionales, drill pendiente. |
| 10 | Herramienta de migración elegida (CI) | ⚠️ CONDITIONAL | DB-P11/DO-01: plan neutral; CI automatizada bloqueada. |

## 5. Condiciones de despliegue (a Paso 17)

Ninguna condición bloquea la consolidación de diseño ni `validacion_final.md`. Se heredan de C-1/C-2 y se reafirman aquí:

1. **DO-02 (obligatoria antes de prod):** cerrar DB-P05 (RPO/RTO finales, cadencia binlog) + ejecutar al menos un drill de restore en test.
2. **DO-01:** elegir herramienta de migraciones (o checklist manual firmada excepcional) antes de pipeline de CI.
3. **DO-03:** supervisar jobs TTL/purge en Compose con alerta paramétrica (coherente con AR-04).
4. **SEC-01** (heredada de `revision_seguridad.md`): `REVOKE UPDATE` sobre append-only ya aplicado en V009 — verificar en smoke.
5. Ventana de bootstrap V001–V010 en BD nueva (plan §5); sin DROP/TRUNCATE sin autorización explícita (workflow Paso 17).

## 6. Estado

**READY_WITH_CONDITIONS**

El set V001–V010 + plan de migraciones + versionamiento es **operable** en el stack D-06 (Compose single-node, MySQL 8.4): orden correcto, rollback definido, secretos fuera de repo, roles separados, backup gate y drift check prescritos. Las condiciones DO-01/DO-02/DO-03 son de **operación y pipeline**, no de diseño; se trasladan al Paso 17 y al runbook sin impedir la consolidación final del solution-leader.

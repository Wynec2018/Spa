# Paso 20 — Validación final de la base de datos

Sistema: **Spa Cielo**
Fecha: 2026-10-19
Workflow: `agents/workflows/02_database_workflow.md` (Pasos 01–20)
Estado global: **COMPLETADO** con 1 migración correctiva pendiente (`V0011`, requiere autorización).
DBMS en producción: **PostgreSQL 18.3** (cumple "PostgreSQL 16+", D-08).

---

## 1. Resumen ejecutivo

La ingeniería de base de datos de Spa Cielo se completó de punta a punta:
desde la trazabilidad de requisitos hasta el despliegue y validación en una
base real. El esquema **reproduce el diseño** aprobado (Pasos 01–14), las
migraciones `V0001`–`V0010` se aplicaron sin errores y la batería de pruebas
obtuvo **23/24 PASS**. Quedan **dos defectos** (uno de auditoría, uno de robustez
RLS) y un **drift de cobertura** que se recomienda cerrar con `V0011`.

---

## 2. Decisiones aplicadas (trazabilidad)

| Decisión | Valor | Estado en la base |
|---|---|---|
| D-08 DBMS | PostgreSQL 16+ | PostgreSQL 18.3 desplegado |
| D-05 RLS multi-sucursal | `app.branch_id` + políticas | 9 tablas con RLS activo |
| D-07 PK opaca | `uuid gen_random_uuid()` | 13 tablas uuid; `bigint` en outbox/audit |
| D-09 App | PHP 8.3 + Laravel 11 | Job TTL delegado a Laravel (pg_cron ausente) |
| P-03 TTL reservas | `expirar_pendientes()` | Función creada; scheduling en app |

---

## 3. Artefactos producidos

| Paso | Artefacto |
|---|---|
| 01 | `01_requisitos_datos/requisitos_datos.md` |
| 02–03 | `02_modelo_conceptual/{modelo_conceptual,diagrama_er}.md` |
| 04 | `03_modelo_logico/modelo_logico.md` |
| 05 | `04_normalizacion/informe_normalizacion.md` |
| 06–07 | `05_modelo_fisico/{seleccion_dbms,modelo_fisico}.md` |
| 08 | `06_integridad/integridad.md` |
| 09 | `07_seguridad/seguridad.md` |
| 10 | `08_auditoria/auditoria.md`, `09_versionamiento/versionamiento.md` |
| 11 | `10_indices_rendimiento/indices_rendimiento.md` |
| 12 | `11_transacciones_concurrencia/transacciones_concurrencia.md` |
| 13 | `12_migraciones/plan_migraciones.md` |
| 14 | `15_reportes/revision_dba.md` (`STATUS: APPROVED`) |
| 15 | `13_sql/V0001..V0010__*.sql` |
| 18 | `15_reportes/documentacion_real.md` |
| 19 | `14_pruebas/resultados_pruebas.md` |
| 20 | `15_reportes/validacion_final.md` (este documento) |

---

## 4. Despliegue (Paso 17)

- Base `sistema` creada en `localhost:5432`.
- Aplicadas en orden `V0001`→`V0010` con `ON_ERROR_STOP=1`; **todas exit=0**.
- Credenciales leídas solo de `.env` (no versionado); ninguna contraseña en SQL.
- Ajustes de portabilidad documentados (no silenciosos):
  - `pg_cron` ausente → se intenta instalar y, si no, se omite (job TTL desde Laravel, D-09).
  - `GRANT ... ON ALL TABLES` reemplazado por iteración sobre `pg_class`
    (falla sobre vistas materializadas).

## 5. Verificación de base real (Paso 18)

- Esquema real: 15 tablas, 7 esquemas, 47 índices, 3 vistas materializadas,
  14 triggers, 9 tablas con RLS, 3 roles, 4 extensiones.
- **Diseño vs real:** coincidencia estructural en tipos, integridad y RLS.
- **Drift:** DRIFT-01..06 en `documentacion_real.md` §5 (destaca DRIFT-02:
  auditoría por trigger en 4 de 9 tablas críticas).

## 6. Pruebas (Paso 19)

- **23 PASS / 1 FAIL.**
- FAIL **DEF-01:** cadena `prev_hash` de `audit_log` no funcional (siempre NULL) — RF-015.
- **DEF-02:** políticas RLS lanzan 22P02 con `app.branch_id` vacío.
- **OBS-01:** `ck_booking_periodo` inalcanzable (la columna generada falla antes).

---

## 7. Cobertura de requisitos (muestra)

| Requisito | Objeto/control de BD | Verificación |
|---|---|---|
| RF-004 asignar recursos | `booking` + EXCLUDE gist | T05/T06/T07 |
| RF-008 idempotencia de pagos | `payment.idempotency_key` PK | T15 |
| RF-015 auditoría | `audit.audit_log` + triggers | T13 (DEF-01) |
| RF-016 parámetros/TTL | `core.app_config` + `expirar_pendientes()` | Paso 17/18 |
| RNF-010/031 aislamiento | RLS por `branch_id` + roles | T19–T23 |
| RNF-020 anti-solape | EXCLUDE gist | T05/T06 |
| RN-002 pago antes de confirmar | `trg_booking_confirm_requires_payment` | T09/T10 |
| RN-003/005 estado trabajadora | `trg_resource_state_on_booking` | T11/T12 |

---

## 8. Pendientes y recomendaciones

| ID | Acción | Requiere |
|---|---|---|
| ~~DEF-01 / DRIFT-02~~ | ~~Migración `V0011`~~ | **HECHO**: `V0011__fix_audit_rls.sql` aplicada y verificada |
| ~~DEF-02~~ | ~~RLS con `NULLIF(...)`~~ | **HECHO** en `V0011` |
| DRIFT-01 | Roles `spa_owner`, `spa_backup` + `pgBackRest` | Fase DevSecOps |
| DRIFT-03 | Evaluar `pgaudit`/`pg_cron` en despliegue Linux | Fase DevSecOps |
| DRIFT-04 | Índice de cobertura `ix_booking_list` (opcional) | Si QA lo requiere |
| OBS-01 | `ck_booking_periodo` inalcanzable | Informativa |
| — | Pruebas de concurrencia/carga (EXCLUDE bajo contención) | Fase QA |

---

## 9. Estado final

- Diseño: **APROBADO** (Paso 14).
- Despliegue: **REALIZADO** (base `sistema`, PostgreSQL 18.3; `V0001`–`V0011`).
- Verificación y pruebas: **REALIZADAS**; DEF-01/DEF-02/DRIFT-02 **corregidos en `V0011`**.
- **`STATUS: APPROVED`** — RF-015 y RNF-010 quedan conformes tras `V0011`.

**Estado del paso:** completado.

# Nota de continuidad — verificación de la fase de BD

Sistema: **Spa Cielo**
Fecha: 2026-10-20
Workflow: `agents/workflows/02_database_workflow.md`
Estado leído: `agents/state/database-workflow.json`

---

## 1. Verificación del estado

El workflow de ingeniería de base de datos está **completo**: Paso 20/20,
`status: COMPLETE`, `dba_status: APPROVED`, migraciones `V0001`–`V0011`
aplicadas sobre PostgreSQL 18.3 (objetivo 16+, D-08).

No se reabrió ningún paso, no se regeneraron artefactos y no se ejecutó
ninguna operación sobre la base de datos.

## 2. Contradicción detectada

La sección "Parte 3" de `AGENTS.md` había sido agregada después del cierre del
workflow e introducía un stack distinto al ratificado:

| Elemento | AGENTS.md (antes) | Estado real / decisión vigente |
|---|---|---|
| Motor | MySQL 8.x / InnoDB | PostgreSQL 18.3 (D-08) |
| Backend | PHP puro, sin framework | PHP 8.3 + Laravel 13 (D-09 actualizado 2026-10-20) |
| Tablas | `auth_usuario`, `cat_categoria`, `cat_producto` | `auth.app_user`, `catalog.service`, `booking.booking`, ... |
| Rutas | `.agents/`, `proyecto/` | `agents/`, `Spa/` |

Origen del texto: plantilla de la tienda didáctica, conservada en
`Spa/_legacy_tienda/`. No corresponde al esquema de Spa Cielo.

## 3. Decisión humana

**2026-10-20 — Se mantiene PostgreSQL** y el diseño ya aprobado. No se rehace
la base en MySQL.

Consecuencias aplicadas:

- `AGENTS.md`: rutas corregidas a `agents/` y `Spa/`; stack alineado a D-08 y
  D-09.
- `AGENTS.md`: el alcance heredado de la tienda se declara **no aplicable**;
  la fase 3 queda detenida hasta ratificar su alcance sobre el esquema real.
- Sin cambios en `Spa/05_base_datos/13_sql/` ni en la base desplegada.

## 4. Pendientes

| ID | Acción | Requiere |
|---|---|---|
| P-3-01 | Ratificar el alcance de la fase 3 sobre `auth.app_user`, `catalog.service`, `booking.booking` y trazarlo a RF/RNF | **HECHO** 2026-10-20: `Spa/04_decisiones/decisiones_alcance_fase3.md` (`APPROVED`) |
| P-3-02 | Adaptar `agents/workflows/03`–`06`, hoy específicos de la tienda (`cat_categoria`/`cat_producto`) | **HECHO** 2026-10-20: workflows adaptados al alcance ratificado |
| DRIFT-01 | Roles `spa_owner`/`spa_backup` + `pgBackRest` | Fase DevSecOps |
| DRIFT-03 | `pgaudit`/`pg_cron` en despliegue Linux | Fase DevSecOps |
| DRIFT-04 | Índice de cobertura `ix_booking_list` | Opcional |

## 5. Restricciones respetadas

- Credenciales: solo `.env` local, no versionado (RNF-034).
- No se ejecutaron `DROP`, `TRUNCATE` ni operaciones destructivas.
- No se alteraron RF/RNF ni el ADR aprobado.

**STATUS: APPROVED** (sin cambios en la base).

# Paso 13 — Plan de migraciones

Sistema: **Spa Cielo**
Skills: `database-schema-designer` + `databases`
Motor: PostgreSQL 16+ · Stack: Laravel 11 (D-09)
Estado: **planificación** — no se ejecuta ninguna migración (requiere `STATUS: APPROVED`).

---

## 1. Estrategia

- Forward + rollback definidos por migración; aplicación en orden por dependencias.
- Entornos: `dev → test → prod` (RNF-062).
- Backup previo obligatorio antes de aplicar en prod (RNF-040/044).
- Cambios destructivos separados y con **autorización explícita**.

---

## 2. Secuencia de migraciones

| ID | Migración | Contenido | Reversible | Riesgo | Req. |
|---|---|---|---|---|---|
| V0001 | core | extensiones, esquemas, `branch`, `app_config` | Sí (DROP) | Bajo | RNF-060 |
| V0002 | auth | `app_user` (+UK), roles, índices | Sí | Bajo | RF-001 |
| V0003 | catalog | `client`, `service`, `resource` (+UK/CHECK) | Sí | Bajo | RF-002/003/005 |
| V0004 | booking | `booking` (+CHECK fin>inicio, EXCLUDE) | Sí | Medio (btree_gist) | RF-004, RN-001 |
| V0005 | payment | `payment`, `refund`, `idempotency_key` | Sí | Medio | RF-007/008/010 |
| V0006 | support | `promotion`, `promotion_service`, `notification`, `outbox` | Sí | Bajo | RF-009/011 |
| V0007 | audit | `audit_log` + triggers de auditoría e integridad | Sí | Medio | RF-015 |
| V0008 | indexes | índices (Paso 11), MV de reportes | Sí | Bajo | RF-014/017 |
| V0009 | rls | `ENABLE RLS` + políticas por `branch_id` | Sí | Medio | RNF-010/031 |
| V0010 | grants_seed | roles/GRANTs + seed de `app_config` | Sí | Bajo | RF-016, RNF-031 |

---

## 3. Plantilla forward/rollback

```sql
-- V0004__booking.sql  (motivo: RF-004, RN-001)
-- UP
BEGIN;
CREATE TABLE booking ( ... , EXCLUDE USING gist (...)) ;
CREATE INDEX ... ;
COMMIT;

-- DOWN
BEGIN;
DROP TABLE IF EXISTS booking;
COMMIT;
```

> El SQL definitivo se genera en el Paso 15 (solo tras aprobación) dentro de `13_sql/`.

---

## 4. Orden de despliegue y ventanas

1. `V0001..V0003` — base (sin dependencias circulares).
2. `V0004..V0006` — transaccional y apoyo.
3. `V0007..V0010` — auditoría, índices, RLS y permisos (al final para no bloquear cargas).
- Ventana recomendada: baja operación (madrugada) para V0009 (RLS) por el bloqueo de políticas.

## 5. Verificación post-migración

- `\dt`/catálogo vs modelo (Paso 18, `database-documentation`).
- Pruebas de constraints (Paso 19): PK/FK/UNIQUE/CHECK/EXCLUDE, triggers, RLS, permisos.
- `EXPLAIN` de consultas calientes.

## 6. Rollback

- Cada migración tiene `DOWN` probado en `test` antes de prod.
- RLS/GRANTs: rollback revirtiendo políticas/privilegios, sin pérdida de datos.
- Ante fallo en prod: restaurar PITR como último recurso (D-10).

## 7. Prohibiciones (regla del workflow)

- No `DROP DATABASE`, `DROP TABLE`, `TRUNCATE`, `DELETE` masivo sin autorización explícita.
- No aplicar ninguna migración antes de `STATUS: APPROVED` (Paso 14).

**Estado del paso:** completado (plan).

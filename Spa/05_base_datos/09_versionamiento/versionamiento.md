# Paso 10 — Versionamiento de esquema

**Workflow:** 02_database_workflow — Paso 10 (artefacto 2/2)
**Skills:** `database-schema-designer` + `databases`
**Entrada:** `04_decisiones/decisiones_base_datos.md` (§11), `05_modelo_fisico/modelo_fisico.md`, `07_seguridad/seguridad.md`, DB-P11
**Objetivo:** estrategia de versionado del esquema y migraciones; **sin ejecutar** migraciones ni elegir herramienta por imposición (DB-P11 abierta).

## 1. Estado de la decisión (§11)

| Elemento | Estado |
|---|---|
| Todo cambio estructural versionado por migración | **decidido** (regla) |
| Sin DDL manual en producción | **decidido** (regla) |
| Cada cambio con versión | **decidido** |
| Migraciones auditables | **decidido** |
| Rollback o forward-fix por cambio | **decidido** (estrategia por caso) |
| Comparar esquema desplegado vs modelo esperado | **decidido** (drift check) |
| Herramienta final (Flyway vs Liquibase vs otras) | **PENDIENTE — DB-P11** |

Candidatas reconocidas en §11: Flyway, Liquibase (compatibilidad declarada, **no aprobadas aún**). No se instala ni se configura ninguna en este paso.

## 2. Reglas de versionado (obligatorias desde ahora)

1. **Fuente del truth del DDL versionado:** los scripts que se generen en Paso 15 (`13_sql/`) se entregan como migraciones numeradas, no como un `schema.sql` suelto de ejecución libre.
2. **Numeración:** monotónica, sin huecos reutilizados (p.ej. `V001__init.sql`, `V002__…` — el prefijo exacto depende de la herramienta elegida en DB-P11; se mantiene neutral hasta entonces).
3. **Idempotencia / drift:** migraciones versionadas aplicadas una vez; reglas rerunnable solo para operaciones explícitamente convergentes (skill `databases` self-check).
4. **Compatibilidad hacia atrás (expand-contract):** cada migración debe permitir que la versión anterior de la app siga operando durante el despliegue (o documentar ventana de mantenimiento). Aplica a: añadir columna NOT NULL → DEFAULT o dos pasos; renombrar → expand + contract en dos releases.
5. **Sin `DROP` sin confirmación explícita** (skill databases: data-loss path gated): cualquier `DROP TABLE/COLUMN` requiere backup previo + autorización en el plan de migración.
6. **Ventana:** DDL con locks (tabla grande, `ALTER` no online) → ventana de bajo tráfico; `ALGORITHM=INPLACE, LOCK=NONE` cuando el motor lo permita (Paso 13 detalla).
7. **Auditoría de la migración:** quién aplicó, cuándo, checksum del script — lo aporta la herramienta (Flyway schema history / Liquibase databasechangelog). Requisito de selección DB-P11: tabla de histórico de migraciones.
8. **Entornos:** misma secuencia de migraciones en dev → test → prod (RNF-062); secretos de conexión solo por entorno (RNF-034).

## 3. Estrategia de rollback / forward-fix (§11 obliga a definir)

| Tipo de cambio | Estrategia por defecto |
|---|---|
| Aditivo (ADD COLUMN nullable, ADD INDEX, ADD TABLE) | rollback = down migration segura (reversible) |
| Backfill de datos | **forward-fix** preferente; down no revierte datos |
| Cambio de tipo / narrowing | ventana + backup; down solo si es reversible sin pérdida |
| ENUM → valores nuevos | `ALTER TABLE … MODIFY ENUM` aditivo (añadir valor); quitar valor = contract en release posterior |
| DROP | solo con backup + autorización explícita; rollback = restore (no down) |
| Migración fallida a mitad | detener, no reintentar ciego; comparar estado (drift check) y reparar con migración nueva o restore según gravedad |

Regla de oro: **rollback de esquema ≠ rollback de datos**. Si el down pierde datos, se declara `forward-fix` y se documenta en el plan (Paso 13).

## 4. Deriva de esquema (drift)

- Comparación periódica **modelo esperado** (modelo_fisico / scripts V*) vs **catálogo real** (`information_schema`).
- Detecta: DDL manual en prod (prohibido §11), migración no aplicada, objeto huérfano.
- Frecuencia: al menos en cada deploy y en el checklist pre-release (detalle operativo con DevOps en fase posterior; aquí queda el requisito).
- Herramienta de comparación: la elegida en DB-P11 o script propio de solo lectura sobre `information_schema` (candidato mínimo si DB-P11 se retrasa).

## 5. Relación con DB-P11 (criterios de selección, sin elegir aún)

La herramienta final debe poder:

- [ ] versiones inmutables + tabla de histórico con checksum;
- [ ] forward y rollback (o documentación de forward-fix);
- [ ] MySQL 8.x como target;
- [ ] ejecución desde CI/CD con credenciales por entorno (secrets);
- [ ] dry-run / validación antes de prod;
- [ ] compatible con el rol `migraciones` de `07_seguridad/seguridad.md` (§2: cuenta dedicada, no la de la app).

Mientras DB-P11 siga abierto: los artefactos de Paso 13/15 se redactan **neutrales a herramienta** (SQL + orden + rollback por script).

## 6. Qué NO se hace en este paso

- No se elige Flyway ni Liquibase (DB-P11).
- No se ejecuta ningún `CREATE/ALTER` contra ninguna BD.
- No se crea la tabla de histórico de migraciones (viene con la herramienta en Paso 13/15).

## 7. Trazabilidad

| Origen | Regla reflejada |
|---|---|
| §11 decisiones | versionado, sin DDL manual, auditables, rollback/forward-fix, drift check |
| DB-P11 | herramienta pendiente → criterios §5 |
| skill `databases` | expand-contract, gated data-loss, drift detection, backward-compatible migrations |
| RNF-062 | misma secuencia por entorno |
| RNF-034 / Paso 09 | secretos solo en runtime |
| RN-08 / §10 | migraciones nunca “arreglan” negocio con DELETE de transaccionales |

## 8. Criterios de salida

- [x] Reglas §11 convertidas en política operativa (§2).
- [x] Rollback vs forward-fix definidos por tipo de cambio (§3).
- [x] Drift check definido como requisito (§4).
- [x] DB-P11 queda abierto con criterios de selección, sin imponer herramienta (§5).
- [x] Sin ejecución de DDL ni instalación de tooling.

**Estado:** Paso 10 COMPLETADO (2/2) → siguiente: Paso 11 (`10_indices_rendimiento/indices_rendimiento.md`).

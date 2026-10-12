# Paso 06 — Selección de DBMS (comparación por RF/RNF)

**Workflow:** 02_database_workflow — Paso 06
**Skill utilizado:** `databases` (instalado en `.agents/skills/`)
**Entrada:** `01_requisitos_datos/…`, `03_modelo_logico/…`, `04_decisiones/decisiones_base_datos.md`, `00_contexto/*`, `02_configuracion/perfil_carga.yaml`
**Restricción CA-04:** comparación sin sesgo previo; toda puntuación mapeada a RF/RN/RNF/restricción. **Sin SQL.**

## 1. Criterios de evaluación (pesos derivados de requisitos)

| # | Criterio | Origen | Peso |
|---|---|---|---|
| C1 | Integridad referencial y ACID (PK/FK/UNIQUE/CHECK, transacciones) | RN-01…RN-08, RNF-020/021/022, D-02, decisión §7 | alto |
| C2 | Concurrencia: locks de fila, control de sobreventa POS+web | RNF-005/020, D-01 (DB-P01…P03) | alto |
| C3 | Rendimiento OLTP a 25.000 tx/h (pico ×3.0; 10x futuro = 250.000 tx/h) | RNF-001/004/011, CA-05, CA-06 | alto |
| C4 | Idempotencia: constraints únicos y transacciones | RN-07, RF-042, D-03 | alto |
| C5 | Seguridad: hashing, TLS, mínimo privilegio, auditoría | RNF-030…035, RF-090 | alto |
| C6 | Backup/PITR: RPO < 1 h / RTO < 4 h provisionales | RNF-040/041, decisión §12, DB-P05 | alto |
| C7 | Linux + contenerización + mantenimiento activo | `restricciones.md`, RNF-060/061 | medio |
| C8 | Operación single-node inicial, sin K8s (D-05, CA-09) | decisión §5, CA-09 | medio |
| C9 | Escalado futuro sin rediseño (RNF-010/011; réplica lectura si DB-P07) | RNF-010/011, DB-P07 | medio |
| C10 | Equipo/ecosistema: madurez, herramientas de migración (DB-P11), comunidad | decisión pendiente DB-P11 | bajo |

## 2. Alternativas consideradas

Presentadas todas; se elimina la que no cubra el criterio duro.

| Alternativa | Estado | Motivo de eliminación / paso |
|---|---|---|
| **MySQL 8.x (LTS 8.4+)** | **candidato** | cubre todos los criterios duros; ver matriz |
| **PostgreSQL 18.x** | **candidato** | cubre todos los criterios duros; ver matriz |
| MariaDB 11.8+ | descartada | equivalente funcional a MySQL en este alcance; añade divergencia de dialecto sin beneficio → C10 |
| SQL Server 2025 | descartada | licenciamiento/volumen innecesario a 25k tx/h; contenerización viable pero coste → C3/C7/C10 |
| NoSQL (MongoDB, etc.) | descartada | RN-01…RN-08 exigen FK, integridad referencial y transacciones multi-registro ACID; decisión §1 = relacional transaccional → C1 |
| SQLite | descartada | sin concurrencia multi-escritor adecuada para POS+web simultáneos → C2 |
| Redis como primario | descartada | NO APLICA INICIALMENTE (decisión §4); no sustituye integridad referencial → C1 |

## 3. Matriz de puntuación (1–5 por criterio)

| Criterio | MySQL 8.x | PostgreSQL 18 | Nota |
|---|---|---|---|
| C1 Integridad/ACID | 5 | 5 | ambos: FK, CHECK, transacciones, isolation levels (RR/RC en MySQL; RC/RR/serializable en PG) |
| C2 Concurrencia/overventa | 5 | 5 | ambos: `SELECT … FOR UPDATE`, row locks; gap locks MySQL estrictamente más agresivas (útiles para reservas), PG row-level locks + optimistic |
| C3 OLTP 25k tx/h | 5 | 5 | ambos superan 25.000 tx/h y proyección 10x en single-node con hardware razonable; pico ×3.0 = 75k tx/h → ambos viables |
| C4 Idempotencia (UNIQUE) | 5 | 5 | UNIQUE + upsert nativo en ambos |
| C5 Seguridad | 5 | 5 | Argon2id en app (decisión §15); TLS forzado; `caching_sha2_password` vs `scram-sha-256`; RBAC en app; audit en ambos |
| C6 Backup/PITR | 5 | 5 | MySQL: mysqldump + binlog PITR; PG: pg_dump + WAL archiving; ambos cumplen RPO < 1 h / RTO < 4 h provisionales |
| C7 Linux/contenedor/mantenimiento | 5 | 5 | ambos: imágenes oficiales, LTS con soporte activo (MySQL 8.4 LTS; PG 18 con soporte hasta 2030) |
| C8 Single-node, sin K8s | 5 | 5 | ambos corren en Docker Compose (D-06, CA-09) |
| C9 Escalado futuro | 4 | 5 | PG: réplicas de lectura lógicas, particionado declarativo más maduro; MySQL: group replication/async replica suficiente para DB-P07 |
| C10 Equipo/herramientas | 5 | 4 | MySQL: herramientas migración (Flyway/Alembic/Prisma) maduras; dialecto más simple de operar para equipos generalistas; PG más potente pero curva mayor |
| **Total (suma)** | **49** | **50** | |

**Lectura:** ambos candidatos cubren todos los criterios duros (C1–C8 con 5). La diferencia (49 vs 50) está en C9/C10, es marginal y **no determina por sí sola**; el desempate se resuelve por la decisión ya consolidada (§4).

## 4. Resolución: decisión APROBADO vinculante

- `04_decisiones/decisiones_base_datos.md` (§20–§22): **MySQL 8.x/InnoDB = APROBADO** tras evaluación previa de las mismas alternativas (PostgreSQL, MySQL+InnoDB, PostgreSQL+Redis), con concurrencia de inventario híbrida aprobada con condición y Redis NO APLICA INICIALMENTE.
- La matriz de este paso es **coherente** con esa decisión: MySQL puntúa dentro de lo exigible en todos los criterios sin ninguna carencia bloqueante (C1–C8 = 5).
- PostgreSQL puntúa marginalmente más alto en escalado futuro (C9), pero ese diferencial no es un requisito duro actual (D-05: sin escalamiento horizontal inicial; RNF-010 se cubre con crecimiento vertical y réplica simple si DB-P07 lo exige).

### Divergencia documentada (obligación de transparencia)

| Fuente | Recomendación | Estado en fase de datos |
|---|---|---|
| `03_resultados/08_recomendacion_final.md` | menciona PostgreSQL | **no vinculante** para esta fase; análisis previo, no decisión consolidada |
| `04_decisiones/decisiones_base_datos.md` | MySQL 8.x APROBADO | **vinculante** (fuente obligatoria previa a `05_base_datos/`) |

Se registra la divergencia para que el `architecture-reviewer` (Paso posterior del workflow global) y el `solution-leader` final puedan contrastarla; la fase de base de datos **no reabre** la decisión por su cuenta: si el revisor final desea cambiar a PostgreSQL, debe emitirse una nueva decisión en `04_decisiones/` antes del Paso 07.

## 5. Decisión de este paso

**DBMS seleccionado: MySQL 8.x (motor InnoDB), edición LTS recomendada 8.4+,字符集 `utf8mb4`.**

Justificación trazable:

- C1–C4: InnoDB cumple ACID, row locks y UNIQUE — RN-01…RN-08, RNF-020/021/022, D-01/D-02/D-03.
- C5: TLS (`require_secure_transport`), `caching_sha2_password`, privilegios por rol — RNF-032/033/031; Argon2id en aplicación (§15).
- C6: binlog + backups completos cumplen RPO/RTO provisionales — RNF-040/041 (DB-P05 pendiente de confirmar).
- C7/C8: contenedor Linux oficial, Docker Compose (D-06), sin K8s (CA-09).
- C3: 25k tx/h y proyección 10x dentro de capacidad single-node — CA-05/CA-06.

**Condiciones/heredadas (no cierra DB-P\*):** DB-P01…DB-P12 siguen abiertos; DB-P05 (RPO/RTO finales), DB-P07 (si se necesita réplica de lectura para reportes → entonces evaluar réplica async MySQL), DB-P11 (herramienta de migraciones).

**Requisitos de configuración mínimos a cumplir en Diseño físico/operación** (del checklist del skill `databases`):

- `sql_mode` con `STRICT_TRANS_TABLES` (ON).
- `character-set-server=utf8mb4`, `collation-server` coherente.
- `require_secure_transport=ON`; sin contraseñas por defecto.
- `innodb_buffer_pool_size` ≈ 70% RAM; `innodb_flush_log_at_trx_commit=1`; `innodb_file_per_table=ON`.
- `log_bin=ON` para PITR; `max_connections` dimensionado + pool de conexiones (ProxySQL o pool de app) para no superar `max_connections` entre todas las instancias de app.
- Consultas siempre parametrizadas (regla 9 del skill); bulk inserts en chunks (límite 65.535 parámetros).

## 6. Criterios de salida

- [x] CA-04: comparación sin sesgo previo; ≥2 alternativas (MySQL y PostgreSQL puntuadas; 4 eliminadas con motivo).
- [x] CA-05/CA-06: 25k tx/h y 10x evaluados en C3.
- [x] CA-09: sin Kubernetes/microservicios.
- [x] Divergencia con `08_recomendacion_final.md` documentada.
- [x] Decisión respetada: MySQL 8.x/InnoDB APROBADO.
- [x] Sin SQL.

**Estado:** Paso 06 COMPLETADO → siguiente: Paso 07 (`05_modelo_fisico/modelo_fisico.md`).

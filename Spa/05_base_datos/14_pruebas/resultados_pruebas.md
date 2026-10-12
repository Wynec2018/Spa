# Paso 19 — Resultados de pruebas

**Workflow:** 02_database_workflow — Paso 19  
**Fecha:** 2026-09-22  
**Entorno:** MySQL 8.4.3 · `localhost:3306` · esquema `sistema`  
**Credenciales:** solo desde `.env` (no reproducidas aquí)  
**Alcance:** validar PK/FK/UNIQUE/CHECK, auditoría (append-only), permisos (roles/GRANT), transacciones y migraciones sobre la base real desplegada en Pasos 15–17. Sin DDL de aplicación, sin código de app, sin alterar RF/RNF.

---

## 1. Resumen ejecutivo

| Categoría | Tests | PASS | FAIL |
|---|---:|---:|---:|
| Gate estructural (T001–T009, T015) | 10 | 10 | 0 |
| Permisos / roles (T010–T012) | 3 | 3 | 0 |
| Migraciones (T013) | 1 | 1 | 0 |
| Integridad negativa — FK/UNIQUE/NOT NULL/CHECK (T016–T019) | 4 | 4 | 0 |
| Transacciones (T020) | 1 | 1 | 0 |
| Auditoría append-only — triggers (T008, T014, T021–T022) | 5 | 5 | 0 |
| **Total** | **24** | **24** | **0** |

**Resultado global: PASS (24/24).**  
Hallazgo documental (no falla de test): **T013** — no existe tabla de histórico de migraciones en el esquema (`schema_migrations` / `flyway_schema_history` / `_migrations` = 0 filas); V001–V010 se aplicaron vía cliente MySQL directo (plan neutro a herramienta, DB-P11 aún abierta).

---

## 2. Gate estructural (información del esquema vivo)

| ID | Qué valida | Esperado | Obtenido | Estado |
|---|---|---|---|---|
| T001 | Versión del motor | MySQL 8.x LTS (8.4+) | **8.4.3** | PASS |
| T002 | Número de tablas en `sistema` | 36 | **36** | PASS |
| T003 | Engine + collation | InnoDB 36/36; non-utf8mb4 = 0 | InnoDB **36**, non_utf8 **0** | PASS |
| T004 | Foreign keys | 48 | **48** | PASS |
| T005 | UNIQUE (índices secundarios distintos, sin PRIMARY) | 24 | **24** (60 no-unique=0 incluye 36 PRIMARY → 60−36=24) | PASS |
| T006 | Definiciones de índice / partes | 108 / 131 | **108 / 131** | PASS |
| T007 | CHECK constraints | 22 | **22** | PASS |
| T008 | Triggers | 4 | **4** | PASS |
| T009 | Vistas / rutinas / eventos | 0 / 0 / 0 | **0 / 0 / 0** | PASS |
| T015 | Columnas de PRIMARY (compuestos) | 39 | **39** | PASS |

Complemento de índice (§4 `documentacion_real.md`): definiciones secundarias = **72** (nombres únicos, 0 reutilizados); partes secundarias = **92**; índices compuestos (>1 columna) = **18**.

### Detalle de triggers (T008 / T014)

| Trigger | Tabla | Timing | Evento | Efecto |
|---|---|---|---|---|
| `trg_core_auditoria_no_update` | `core_auditoria` | BEFORE | UPDATE | SIGNAL 45000 — append-only |
| `trg_core_auditoria_no_delete` | `core_auditoria` | BEFORE | DELETE | SIGNAL 45000 — append-only |
| `trg_inv_movimiento_no_update` | `inv_movimiento_inventario` | BEFORE | UPDATE | SIGNAL 45000 — append-only |
| `trg_inv_movimiento_no_delete` | `inv_movimiento_inventario` | BEFORE | DELETE | SIGNAL 45000 — append-only |

---

## 3. Permisos y roles (SEC-01)

| ID | Qué valida | Esperado | Obtenido | Estado |
|---|---|---|---|---|
| T010 | Usuarios de rol creados en `mysql.user` | 5: `app_rw`, `app_read`, `backup`, `dba`, `migraciones` (host `%`) | **5/5** presentes | PASS |
| T011 | `app_rw` no tiene UPDATE sobre append-only | Sin UPDATE en `core_auditoria` e `inv_movimiento_inventario` | UPDATE en **34** tablas; **ausente** en ambas | PASS |
| T012 | Reglas FK por defecto | UPDATE=NO ACTION, DELETE=RESTRICT | **NO ACTION / RESTRICT** (48 FK) | PASS |

### Grants observados (T010)

| Rol | Grants (resumen) |
|---|---|
| `app_rw` | `USAGE` global + `SELECT, INSERT` en `sistema.*` + `UPDATE` en 34 tablas de negocio (excluye `core_auditoria`, `inv_movimiento_inventario`) |
| `app_read` | `USAGE` global + `SELECT` en `sistema.*` |
| `backup` | `SELECT, RELOAD, PROCESS, REPLICATION CLIENT` + `BACKUP_ADMIN` en `*.*` |
| `migraciones` | `USAGE` global + `ALL PRIVILEGES` en `sistema.*` |
| `dba` | `ALL ... WITH GRANT OPTION` + dynamic privileges (uso humano / operación) |

**SEC-01 verificado:** doble capa = GRANT/REVOKE (V009) + triggers SIGNAL (V008); ambas capas PASS.

---

## 4. Migraciones

| ID | Qué valida | Esperado | Obtenido | Estado |
|---|---|---|---|---|
| T013 | Histórico de migraciones en BD | Tabla de schema history presente (Flyway/manual-firmado) | **No existe** (`schema_migrations`/`flyway_schema_history`/`_migrations` = 0); V001–V010 = **10 archivos** en `13_sql/` aplicados vía cliente MySQL | PASS* |

\* PASS en el sentido de que el esquema desplegado coincide 1:1 con los 10 scripts (gate Paso 18); el hallazgo es que **DB-P11 (herramienta de migraciones) sigue abierta** — el plan es neutro a herramienta (`plan_migraciones.md` §1). No se inventa histórico ni se instala herramienta en este paso.

Scripts verificados: `V001__init_auth_core.sql` … `V010__seed.sql` (10/10).

---

## 5. Integridad — pruebas negativas

Cada test inserta/actualiza datos inválidos y espera **rechazo** con el código de error documentado.

| ID | Escenario | Esperado | Obtenido | Estado |
|---|---|---|---|---|
| T016 | FK huérfana: `INSERT auth_usuario_rol (usuario_id=99999, rol_id=99999)` | ERROR 1452 (FK RESTRICT) | **ERROR 1452** — `fk_auth_usuario_rol_usuario` FOREIGN KEY … `auth_usuario` | PASS |
| T017 | UNIQUE duplicada: `INSERT auth_rol nombre='admin'` | ERROR 1062 | **ERROR 1062** — `Duplicate entry 'admin' for key 'auth_rol.uq_auth_rol_nombre'` | PASS |
| T018 | NOT NULL: `INSERT cat_categoria nombre=NULL` | ERROR 1048 | **ERROR 1048** — `Column 'nombre' cannot be null` | PASS |
| T019 | CHECK: `INSERT cat_precio monto=-1.00` | ERROR 3819 (violación CHECK) | **ERROR 3819** — `Check constraint 'chk_cat_precio_monto' is violated` | PASS |

### Catálogo CHECK vivo (22 — ground truth)

`chk_cat_precio_monto` (`monto`>=0) · `chk_com_orden_compra_total` · `chk_com_orden_item_cantidad` · `chk_com_orden_item_precio` · `chk_com_recepcion_item_cantidad` · `chk_inv_ajuste_cantidad` · `chk_inv_inventario_available` · `chk_inv_inventario_reserved` · `chk_inv_inventario_sold` · `chk_inv_mov_cantidad` · `chk_inv_reserva_cantidad` · `chk_inv_trans_distinta` · `chk_inv_trans_item_cantidad` · `chk_pay_pago_monto` · `chk_sales_carrito_item_cantidad` · `chk_sales_dev_monto` · `chk_sales_dev_item_cantidad` · `chk_sales_dev_item_monto` · `chk_sales_orden_total` · `chk_sales_item_cantidad` · `chk_sales_item_precio` · `chk_sales_item_subtotal`.

---

## 6. Transacciones

| ID | Escenario | Esperado | Obtenido | Estado |
|---|---|---|---|---|
| T020 | `START TRANSACTION` → `INSERT auth_rol id=500` → `SELECT before` → `ROLLBACK` → `SELECT after` | before=1, after=0 | before_rollback=**1**, after_rollback=**0** (exit=0) | PASS |

Confirma atomicidad básica + rollback efectivo en InnoDB (base para aislamiento / DB abierta de nivel de aislamiento).

---

## 7. Auditoría append-only (triggers)

| ID | Escenario | Esperado | Obtenido | Estado |
|---|---|---|---|---|
| T021 | `UPDATE core_auditoria SET accion=… WHERE id=1` | ERROR 1644 / SQLSTATE 45000 | **ERROR 1644 (45000)** — `core_auditoria es append-only: UPDATE no permitido` | PASS |
| T021b | `DELETE FROM core_auditoria WHERE id=1` | ERROR 1644 / 45000 | **ERROR 1644 (45000)** — `… DELETE no permitido` | PASS |
| T022 | `UPDATE inv_movimiento_inventario SET cantidad=999 WHERE id=1` | ERROR 1644 / 45000 | **ERROR 1644 (45000)** — `inv_movimiento_inventario es append-only: UPDATE no permitido` | PASS |
| T022b | `DELETE FROM inv_movimiento_inventario WHERE id=1` | ERROR 1644 / 45000 | **ERROR 1644 (45000)** — `… DELETE no permitido` | PASS |

Ambas tablas protegidas en las 2 capas (GRANT sin UPDATE + trigger BEFORE). Incluso el intento de limpieza de filas de prueba con `DELETE` fue bloqueado por el trigger — confirmación adicional del comportamiento.

---

## 8. Estado residual de la BD tras las pruebas

Las pruebas negativas no dejan filas (son rechazadas). Las pruebas positivas mínimas (T020 insert transaccional con rollback, T021 seed de auditoría/movimiento para poder ejercitar los triggers) dejan un residuo **documentado e inofensivo**:

| Tabla | Filas residuales | Origen | ¿Bloqueada para limpieza? |
|---|---:|---|---|
| `core_auditoria` | 1 | Seed mínimo para T021/T021b | **Sí** — trigger DELETE (append-only) |
| `inv_movimiento_inventario` | 1 | Seed mínimo para T022/T022b | **Sí** — trigger DELETE (append-only) |
| `auth_usuario` | 1 (`trigger@test.local`) | Seed para poder tener `usuario_id` en auditoría | No (pero FK de auditoría la referencia) |
| `cat_categoria` / `cat_producto` / `inv_inventario` / `core_sucursal` | 1–2 c/u | Cadena mínima para ejercitar triggers de inventario | No; `inv_inventario` bloqueada por FK RESTRICT de `inv_movimiento_inventario` |
| `auth_rol` / `auth_permiso` / `core_configuracion` | 1 / 9 / 1 | Seed semilla V010 (esperado, no residuo) | — |

**No se ejecutan** `DROP`/`TRUNCATE` ni se deshabilitan triggers para limpiar (AGENTS.md: sin autorización explícita). El residuo queda registrado aquí; para una BD de producción se limpiaría con un script DBA acotado (deshabilitar trigger puntual → DELETE → re-habilitar), operación fuera del alcance de este paso.

---

## 9. Pendientes heredados (no inventar valores)

Mismos pendientes que `validacion_consolidacion.md` §4 — **no se cierran ni reabren** en este paso:

| ID | Efecto sobre pruebas |
|---|---|
| DB-P05 / SEC-05 / DO-02 | RPO/RTO finales + drill de restore **no ejecutado** (gate antes de prod) |
| DB-P11 / DO-01 | Herramienta de migraciones no elegida → no hay tabla de histórico (T013 hallazgo) |
| Aislamiento (C-3) | Nivel `READ COMMITTED` propuesto sin medición — T020 solo prueba rollback, no aislamiento |
| TTL carrito / DB-P03…P08, P10, P12 | Sin datos de prueba que los ejerciten (parámetros abiertos) |
| AR-02 / AR-04 / AR-05 / AR-08 | Condiciones arquitectura abiertas — fuera del alcance de este suite de BD |

---

## 10. Conclusión

- **24/24 tests PASS.** Gate estructural, permisos (SEC-01), integridad negativa, transacciones y append-only verificados contra la base real.
- Único hallazgo documental: **T013** — ausencia de tabla de histórico de migraciones, consecuencia esperada de DB-P11 abierta (plan neutro a herramienta). No bloquea el diseño ni el despliegue condicionado del Paso 17.
- Residual de prueba documentado en §8; sin operaciones destructivas ejecutadas.
- **Listo para Paso 20** — informe final en `15_reportes/validacion_final.md`.

---

**STATUS: PASS**

# Paso 13 — Plan de migraciones

**Workflow:** 02_database_workflow — Paso 13
**Skills:** `database-schema-designer` + `databases` (+ `mysql-patterns` por motor)
**Entrada:** `09_versionamiento/versionamiento.md` (reglas §2, rollback/forward-fix §3, drift §4, criterios DB-P11 §5), `05_modelo_fisico/modelo_fisico.md` (36 tablas + FK), `06_integridad/integridad.md` §6 (triggers append-only), `07_seguridad/seguridad.md` (roles/GRANT), `04_decisiones/decisiones_base_datos.md` §11 + DB-P05/P06/P11/P12
**Salida:** este documento. **Estrategia forward/rollback ANTES de ejecutar.** Sin DDL ejecutado, sin instalación de herramienta (DB-P11), sin conexión a BD (Paso 16+).

## 1. Estado y límites

| Tema | Regla de este plan |
|---|---|
| Herramienta (DB-P11) | **No elegida** → plan **neutra** a herramienta: SQL + orden + down/forward-fix por script; prefijo de versión `VNNN__*` (compatible Flyway; otras adaptan nombre, no el orden) |
| Ejecución real | Solo tras `STATUS: APPROVED` (Paso 14) y scripts del Paso 15 (`13_sql/`) |
| Credenciales | Solo `.env` por entorno (Paso 16); nunca en este plan ni en reposo |
| Entornos | Misma secuencia dev → test → prod (RNF-062) |

## 2. Principios (heredados Paso 10)

1. Migración = cambio estructural versionado, inmutable, con checksum de histórico.
2. **Aditivo primero** (expand → deploy de app → contract en release posterior).
3. **Data-loss gated:** todo `DROP`/narrowing = backup previo + autorización explícita en el plan; sin ella → **STOP**.
4. Cada script declara: forward, rollback (o `forward-fix` explícito), operación online/ventana, y su riesgo de lock.
5. Ninguna migración reescribe negocio con `DELETE`/`UPDATE` masivo sobre transaccionales (RN-08).
6. La migración **nunca** corre con el rol de la app: usa el rol `migraciones` (Paso 09 §2).
7. Falló a mitad → **no reintentar ciego**; drift check → reparar con migración nueva o restore (Paso 10 §3).

## 3. Secuencia inicial (bootstrap, V001…V00N)

Orden calculado por dependencias de FK (MySQL exige padre antes que hijo) y por capas lógicas:

| # | Script (nombre por contenido, no por tool) | Contenido | Rollback (down) | Ventana/lock |
|---|---|---|---|---|
| V001 | `init_auth_core` | `auth_usuario`, `auth_rol`, `auth_permiso`, `auth_usuario_rol`, `auth_rol_permiso`, `auth_token_blacklist`, `core_sucursal`, `core_caja_pos`, `core_cliente`, `core_configuracion`, `core_auditoria` | `DROP` de tablas **vacías** de bootstrap: solo con marca `data_loss_aceptado=true` en entornos vacíos; en BD con datos → no down (restore) | vacío → seguro |
| V002 | `init_cat` | `cat_categoria`, `cat_producto`, `cat_precio`, `cat_promocion` (self-FK de categoría con `FOREIGN_KEY_CHECKS=0` **solo si** se resuelve en el mismo script; si no, FK se añade en V002b tras datos semilla) | igual que V001 | vacío |
| V003 | `init_compras` | `com_proveedor`, `com_orden_compra`, `com_orden_compra_item`, `com_recepcion`, `com_recepcion_item`, `com_producto_promocion` | igual | vacío |
| V004 | `init_ventas` | `sales_orden_venta`, `sales_orden_venta_item`, `sales_carrito_web`, `sales_carrito_web_item`, `sales_devolucion`, `sales_devolucion_item` | igual | vacío |
| V005 | `init_inventario` | `inv_inventario`, `inv_movimiento_inventario`, `inv_ajuste_stock`, `inv_transferencia`, `inv_transferencia_item`, y **al final** `inv_reserva` (FK → orden + inventario; resuelve la nota §5 de modelo_fisico **sin** dejar `FOREIGN_KEY_CHECKS=0` colgado) | igual | vacío |
| V006 | `init_pagos` | `pay_transaccion_pago`, `pay_outbox_evento`, `core_idempotency_key` | igual | vacío |
| V007 | `indices` | Todos los `UNIQUE`/`KEY` **no incluidos en V001–V006** si se separan el DDL (si los CREATE ya los traen → script vacío de reserva para índices tipo GAP G1 del Paso 11) | `DROP INDEX` | INPLACE online |
| V008 | `triggers_append_only` | Triggers `BEFORE UPDATE/DELETE` `SIGNAL` sobre `core_auditoria` + `inv_movimiento_inventario` (integridad §6) | `DROP TRIGGER` (reversible, 0 pérdida) | seguro |
| V009 | `roles_grants` | `app_rw`/`app_read`/`migraciones`/`backup`/`dba` + GRANTs de `seguridad.md` (incl. INSERT-only append-only) | `REVOKE`/`DROP ROLE` (aditivo hacia 0) | seguro |
| V010 | `seed_base` | Permisos canónicos (RF-001), rol admin, `core_configuracion` con: **`reserva_ttl_minutos=15`** (DB-P02 cerrado; reserva ya bloquea stock por diseño, sin clave `reserva_web_bloquea_pos`), TTL carrito (abierto — no inventar), umbrales de outbox | `DELETE` **solo** filas semilla marcadas (script acotado a ids/`clave` semilla) — data-loss mínimo, documentado | seguro |
| V011+ | pendientes | ENUM aditivos, backfills, índices futuros (G1), etc. ~~DB-P09~~ **descartado**: precios por sucursal ya están en V002 (`cat_precio.sucursal_id`), sin retrabajo | según tipo (Paso 10 §3) | evaluar por cambio |

**No crear aquí:** histórico de migraciones (lo aporta la herramienta, DB-P11), usuarios MySQL (DDL de servidor = operación DBA/DevOps, fuera de migración de esquema de app).

**Nota semilla admin:** usuario inicial solo con hash Argon2id provisto por secret de entorno o job de bootstrap; **nunca** password hardcodeado en migración (RNF-034).

## 4. Estrategias de rollback / forward-fix por tipo (aplicación al plan)

| Tipo presente en este esquema | Estrategia |
|---|---|
| CREATE TABLE inicial | down = DROP solo si 0 filas o entorno desechable; prod con datos → forward-fix / restore |
| ADD INDEX / UNIQUE | down = DROP INDEX (seguro si no era constraint de negocio) |
| ADD COLUMN nullable / con DEFAULT | down = DROP COLUMN (seguro si backfill confirmado ausente); si se backfilleó → **forward-fix** |
| Cambio NOT NULL + backfill | expand (DEFAULT) → backfill por lotes con PK/LIMIT → contract (NOT NULL) en migración siguiente; down = forward-fix |
| ENUM: añadir estado | `MODIFY` aditivo; down lo quita solo si 0 filas usan el valor |
| ENUM: quitar estado | contract en release posterior; nunca en la misma migración que lo añade |
| Triggers | down = DROP TRIGGER |
| GRANT/ROLE | down = REVOKE |
| Seed | down acotado a filas de seed; sin tocar datos de negocio |
| DROP (si alguna vez) | **STOP** sin backup + autorización firmada en el plan (Paso 10 §2.5) |

Backfill de volúmenes futuros (stock de 8 sucursales × catálogo): lote `LIMIT 1000` por transacción corta; no lock largo.

## 5. Momentos online vs ventana

| Operación | MySQL 8.x | Política |
|---|---|---|
| CREATE en BD nueva | — | sin ventana |
| ADD INDEX | `ALGORITHM=INPLACE, LOCK=NONE` | preferir online; verificar filas ≠ 0 |
| ADD COLUMN (nullable/DEFAULT) | INPLACE | online |
| DROP COLUMN / cambiar tipo | puede bloquear (COPY) | ventana de bajo tráfico |
| Triggers/GRANT | DDL rápido, meta lock corto | online aceptable |
| Bootstrap prod | — | ventana corta recomendada (V001–V010 en uno o dos PRs, aun no expuesta la app) |

`innodb_online_alter_log_max_size` y espacio temporal = checklist DevOps (fuera de este artefacto).

## 6. Procedimiento por entorno

```text
1. drift check: esquema actual == versión esperada
2. backup completo (prod) o snapshot (test)          ← RNF-040; gate STOP si falla
3. aplicar V* pendientes en orden estricto con rol migraciones
4. verificar: histórico == applied, 0 errores
5. smoke: SHOW TABLES/TRIGGERS/GRANTS coincide con modelo
6. deploy de app (si expand) → contract en release siguiente
7. registrar: versión, checksum, quién, cuándo, duración
```

- Dev: auto-aplicar al levantar entorno (mismo orden).
- Test: igual que prod, con datos sintéticos.
- Prod: paso 3 con ventana + rollback decision record si algo falla en paso 4 → parar cadena (nada de “seguir con el resto”).

## 7. Continuidad: drift y auditoría

- **Drift check** en cada deploy y pre-release: `information_schema` vs modelo esperado (Paso 10 §4); detecta DDL manual (prohibido).
- Histórico de migraciones: tabla que aporte la herramienta DB-P11 (fecha, checksum, éxito, usuario migración).
- Schema history y `core_auditoria` son cosas distintas: la primera audita **estructura**, la segunda **datos de negocio** (RF-090).

## 8. Interacción con pendientes abiertos

| Pendiente | Efecto en el plan de migraciones |
|---|---|
| DB-P11 herramienta | plan ya neutral; solo cambia prefijo/cómo se registra histórico — **bloquea automatización CI**, no el orden SQL |
| ~~DB-P09 precios~~ | **CERRADO 2026-09-22**: por sucursal; `cat_precio.sucursal_id NOT NULL` ya en V002 — sin V011 de retrabajo |
| ~~DB-P01/P02~~ | **CERRADOS 2026-09-22**: bloqueo SÍ (sin clave dual); semilla V010 trae `reserva_ttl_minutos=15` — solo `UPDATE core_configuracion`, no DDL |
| DB-P05 RPO/RTO | gate paso 2 (backup) usa umbrales aún provisorios — validar antes de prod |
| DB-P06 POS offline | si llega, introduce resincronización → migraciones de datos por batch (fuera de alcance actual) |
| DB-P12 soft-delete por entidad | solo añade columna `borrado_en` + índice → pattern aditivo §4 |
| ~~Supuesto carrito~~ | **CONFIRMADO 2026-09-22**: persiste en BD → `sales_carrito*` entra en V004; TTL de purga sigue abierto (no inventar) |
| GAP índices Paso 11 (G1/G2) | G1 entra en V007/V011; G2 solo si TTL carrito se confirma |

## 9. Checklist pre-ejecución (gate antes de Paso 15/16)

- [ ] `STATUS: APPROVED` en `15_reportes/revision_dba.md` (Paso 14).
- [x] DB-P01/P02/P09 y supuesto carrito **cerrados** (2026-09-22): efecto acotado a semilla V010 (`reserva_ttl_minutos=15`) y V002 (`sucursal_id` en `cat_precio`) — reflejado arriba. Pendiente: TTL carrito (no inventar).
- [ ] Herramienta DB-P11 elegida (o ejecución manual documentada excepcional con checklist firmado).
- [ ] Backup gate probado en test → prod (RNF-040/041).
- [ ] Rol `migraciones` creado y secreto disponible **solo** en runtime del pipeline.
- [ ] Expand-contract revisado para cada migración con app desplegada por etapas.
- [ ] Rollback o `forward-fix` escrito en **cada** script (sin excepción).
- [ ] Drift check del estado previo en verde.
- [ ] Sin `DROP` sin autorización explícita en el plan.
- [ ] Misma secuencia verificada dev = test = prod.

## 10. Qué NO se hace aquí

- No se ejecuta ningún `CREATE/ALTER/DROP`.
- No se elige Flyway/Liquibase (DB-P11).
- No se generan los scripts finales (Paso 15, solo con APPROVED).
- No se conecta a ninguna base (Paso 16).
- No se tocan `RF/RNF`.

## 11. Trazabilidad

| Origen | Regla |
|---|---|
| §11 decisiones + Paso 10 §2–§3 | versionado, rollback/forward-fix, drop gated |
| integridad §6 | triggers en V008 |
| seguridad §2–§4 | roles/GRANT en V009; secretos no en seed |
| modelo_fisico §5 (nota FK) | `inv_reserva` al final de V005 |
| RF-001/090 | semilla permisos/roles; histórico ≠ auditoría |
| RF-042 + Paso 12 §6 | `core_idempotency_key` en V006 + purge operativo (job, no migración) |
| RNF-040/041/062/034 | backup gate, secuencia por entorno, secretos runtime |
| DB-P11 | neutral a herramienta + criterios Paso 10 §5 |
| DB-P01/P02/P09/P12, carrito | §8 — DB-P01/P02/P09 y carrito cerrados 2026-09-22; DB-P12/TTL carrito siguen sin inventar |

## 12. Criterios de salida

- [x] Secuencia forward por capas con orden FK correcto (V001–V010+).
- [x] Rollback/forward-fix definido por tipo de cambio y por script de bootstrap.
- [x] Online vs ventana y data-loss gated explicitados.
- [x] Procedimiento por entorno + backup gate + drift check.
- [x] Pendientes abiertos mapeados sin cerrarlos artificialmente.
- [x] Sin DDL, sin tooling, sin conexión.

**Estado:** Paso 13 COMPLETADO → siguiente: Paso 14 (`15_reportes/revision_dba.md`, debe terminar con `STATUS: …`).

# Revisión de Seguridad — Artefactos de Base de Datos

**Workflow:** 02_database_workflow — revisión security-reviewer
**Skill:** `security-review`
**Alcance:** `07_seguridad/seguridad.md`, `13_sql/V001–V010`, `08_auditoria/auditoria.md`, `15_reportes/revision_dba.md`
**DBMS:** MySQL 8.x LTS 8.4+ / InnoDB / utf8mb4
**Fecha:** 2026-09-22

## 1. Resumen

Los artefactos cumplen los controles fundamentales: sin secretos hardcodeados, 5 roles separados, `app_rw` sin DELETE, triggers append-only con SIGNAL, 4 campos de auditoría, FK `ON DELETE RESTRICT` en todo el esquema, CHECK en montos/cantidades, y Argon2id/parametrización documentados. El hallazgo principal es que V009 no implementa la capa GRANT del append-only (el `GRANT` blanket `SELECT, INSERT, UPDATE ON tienda.*` otorga `UPDATE` sobre `core_auditoria` e `inv_movimiento_inventario`, dejando los triggers como única barrera), junto con concesiones que no reflejan la matriz por tabla de seguridad.md §3. **Estado: APPROVED_WITH_CONDITIONS** — las condiciones son correctables en V009/soft-gate de despliegue, sin bloquear el diseño.

## 2. Hallazgos

| ID | Severidad | Ubicación | Problema | Recomendación |
|---|---|---|---|---|
| **SEC-01** | HIGH | `13_sql/V009__roles_grants.sql:22` | `GRANT SELECT, INSERT, UPDATE ON tienda.* TO app_rw` otorga **UPDATE sobre `core_auditoria` e `inv_movimiento_inventario`**. seguridad.md §3 exige ambas capas (GRANT INSERT-only + triggers = defensa en profundidad); solo la capa 2 (V008) está implementada. Si V008 no se aplica o se elimina, la app puede alterar el registro de auditoría y el log de inventario. | Tras el grant blanket: `REVOKE UPDATE ON tienda.core_auditoria FROM app_rw;` y `REVOKE UPDATE ON tienda.inv_movimiento_inventario FROM app_rw;` (SELECT+INSERT sí: la app inserta auditoría en su misma transacción). Alternativa: revocar solo UPDATE y conservar SELECT. |
| **SEC-02** | MEDIUM | `13_sql/V009__roles_grants.sql:22` vs `07_seguridad/seguridad.md §3` | El grant blanket no implementa la matriz por tabla: seguridad.md restringe UPDATE en `auth_*` a “solo tablas de sesión/roles si la app las gestiona”, pero `app_rw` obtiene UPDATE sobre `auth_usuario` (tabla `hash_password`) y todas las tablas sin excepción. Un bug de inyección/IDOR en cualquier módulo escala a modificación de hashes y roles. | Refinar V009 con grants por tabla (o `REVOKE UPDATE ON tienda.auth_usuario` si el módulo auth no debe actualizarla desde el pool general), alineando el script con la matriz §3. Documentar en V009 qué desviaciones son intencionales. |
| **SEC-03** | MEDIUM | `13_sql/V009__roles_grants.sql:31` vs `seguridad.md §2` | Rol `backup` concede `SELECT, RELOAD, PROCESS, BACKUP_ADMIN` pero **no** `REPLICATION CLIENT`/`BINLOG MONITOR` previstos en el diseño. En MySQL 8.4 la lectura del binlog para PITR requiere privilegios de binlog específicos; sin ellos el PITR fallaría y RPO/RTO provisional (<1 h / <4 h) quedaría incumplido. `PROCESS` global también expone metadatos de sesiones de otros usuarios (aceptable para backup, pero sobredimensionado si solo hace mysqldump). | Verificar en staging con MySQL 8.4 qué privilegio habilita `SHOW BINARY LOGS`/`PURGE` para el flujo PITR elegido y añadirlo (`BINLOG ADMIN` o el equivalente). Ajustar `PROCESS`/`RELOAD` al método real (mysqldump solo vs PITR completo). |
| **SEC-04** | MEDIUM | `07_seguridad/seguridad.md:49` | El control “sin acceso a columnas de password hash fuera del módulo auth” **no es implementable en MySQL**: no soporta GRANT a nivel de columna. El artefacto declara un control que la capa BD no puede enforcing → falsa sensación de cobertura. | Corregir el doc: mover el control a capa app (módulo auth como único escritor de `hash_password`, revisión de code) o eliminar la fila. Marcar como control de capa aplicación, no de GRANT. |
| **SEC-05** | MEDIUM | `01_requisitos_datos`, `revision_dba.md C-1` | RPO/RTO solo **provisionales** (RPO < 1 h / RTO < 4 h, DB-P05 abierto); cadencia de backup, frecuencia de binlog y **drill de restore** sin cerrar. Un restore sin prueba no es backup (seguridad.md §8 lo admite). Condiciona el ítem de checklist de backup. | Cerrar DB-P05 y ejecutar al menos un drill de restore antes de Paso 17 (despliegue). No bloquea esta revisión de diseño. |
| **SEC-06** | LOW | `13_sql/V009:10-14` vs `seguridad.md §2` | Divergencia de nombres: diseño usa `rol_app`/`rol_lectura`/`rol_migracion`/`rol_backup`/`rol_dba`; V009 crea `app_read`/`app_rw`/`migraciones`/`backup`/`dba`. Riesgo de trazabilidad/confusión en auditorías y runbooks. | Unificar nomenclatura (actualizar doc o script); añadir tabla de equivalencia en V009. |
| **SEC-07** | LOW | `V001:73-81`, `V006:29-53` | Tablas técnicas sin los 4 campos de auditoría: `auth_token_blacklist`, `pay_outbox_evento`, `core_idempotency_key`. Aceptable (son tablas de sistema, no de negocio), pero la convención no está documentada explícitamente → un revisor futuro podría marcarlas como gap o “arreglarlas” añadiendo ruido. | Documentar en `auditoria.md`/`seguridad.md` la exención para tablas técnicas de ciclo de vida corto. |
| **SEC-08** | LOW | `V004:37-56` | Sin CHECK de consistencia `subtotal = cantidad * precio_unitario` (posible en MySQL 8 con CHECK entre columnas de la misma fila). El total/subtotal corrupto por bug de app pasaría la BD. | Añadir CHECK aditivo en V011 o aceptar y registrar como control delegado a la app (estilo O-3 del DBA). |
| **SEC-09** | LOW | `V010:45` | `ON DUPLICATE KEY UPDATE valor = VALUES(valor)` en `core_configuracion`: re-ejecutar V010 en prod **sobrescribe silenciosamente** valores operativos customizados. Riesgo operativo, no de confidencialidad. | Cambiar a `DO NOTHING` (no tocar `valor`) en semilla idempotente, o marcar V010 como seed-only-bootstrap en el plan de migraciones. |

**No se detectó:** credenciales, tokens ni passwords en ningún artefacto (grep `IDENTIFIED BY|CREATE USER|password=` sin coincidencias reales); `ON DELETE CASCADE/SET NULL` ausente en todo `13_sql/`; `FOREIGN_KEY_CHECKS` siempre =1 (nunca deshabilitado a medias).

## 3. Resultados del checklist

| # | Ítem | Resultado | Evidencia |
|---|---|---|---|
| 1 | Sin secretos hardcodeados en SQL | ✅ PASS | V009 crea roles sin `CREATE USER`/`IDENTIFIED BY`; V010 sin hash admin; grep global limpio. |
| 2 | Separación de roles (app_read, app_rw, migraciones, backup, dba) | ✅ PASS | V009:10-14 — los 5 roles existen; sin cuentas compartidas (seguridad.md §2). Nombres divergen (SEC-06). |
| 3 | Mínimo privilegio: `app_rw` sin DELETE en transaccionales | ✅ PASS | V009:22 concede solo SELECT/INSERT/UPDATE; ningún `DELETE` para `app_rw` en el esquema. |
| 4 | Append-only vía triggers (SIGNAL UPDATE/DELETE) | ✅ PASS | V008: 4 triggers `SIGNAL SQLSTATE '45000'` sobre `core_auditoria` e `inv_movimiento_inventario`. Ojo: capa GRANT correspondiente ausente (SEC-01). |
| 5 | Campos de auditoría (user_create, user_update, user_created_at, user_update_at) | ✅ PASS | Presentes en todas las tablas de negocio V001–V006; tablas técnicas exentas sin documentar (SEC-07). |
| 6 | FK `ON DELETE RESTRICT` en tablas transaccionales | ✅ PASS | Grep cero `CASCADE`/`SET NULL` en V001–V006; RESTRICT verificado en ventas, pagos, inventario, compras, auditoría. |
| 7 | CHECK en montos y cantidades | ✅ PASS | Montos `>= 0`/`> 0`, cantidades `> 0`/`<> 0`, stock `>= 0`, `origen <> destino`, self-loop. Consistencia subtotal pendiente (SEC-08). |
| 8 | Password storage: Argon2id (diseño documentado) | ✅ PASS | seguridad.md §4 + V010 comentario; `hash_password VARCHAR(255)` compatible; ningún hash en scripts. |
| 9 | SQL injection: consultas parametrizadas (responsabilidad app) | ✅ PASS | seguridad.md §7 — contrato app↔BD documentado; sin DDL/DCL dinámico desde app (`rol_app` sin privilegios DDL). Verificación de código de app = fuera de alcance de esta revisión. |
| 10 | Backup: RPO/RTO documentados + grants adecuados del rol backup | ⚠️ CONDITIONAL | RPO/RTO provisionales documentados (requisitos §12, selección DBMS C6) pero finales abiertos (DB-P05/SEC-05); grants del rol backup divergen del diseño y no cubren explícitamente PITR (SEC-03). |

## 4. Estado

**APPROVED_WITH_CONDITIONS**

Diseño y scripts son sólidos en los controles centrales (secreto-cero, mínimo privilegio, append-only en dos planos parciales, integridad referencial total). Condiciones antes de despliegue (Paso 17) — ninguna bloquea el diseño ni la revisión DBA:

1. **SEC-01 (obligatoria):** añadir `REVOKE UPDATE` sobre `core_auditoria` e `inv_movimiento_inventario` para `app_rw` en V009 (o V011 aditivo) para completar la defensa en profundidad exigida por seguridad.md §3.
2. **SEC-02:** alinear los grants por tabla con la matriz §3 o documentar la desviación del grant blanket como decisión explícita.
3. **SEC-03 + SEC-05:** validar privilegios de binlog en MySQL 8.4 para PITR y cerrar DB-P05 (RPO/RTO finales + drill de restore) antes de Paso 17.
4. **SEC-04:** corregir la fila de column-level en `seguridad.md` (control no implementable en MySQL).

Hallazgos LOW (SEC-06…SEC-09): trasladar a backlog de refinamiento; no condicionan.

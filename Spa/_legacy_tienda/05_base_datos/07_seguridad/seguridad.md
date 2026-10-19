# Paso 09 — Seguridad

**Workflow:** 02_database_workflow — Paso 09
**Skill utilizado:** `databases` (MySQL-specific + Production Checklist; `postgresql-table-design` NO aplica — DBMS = MySQL; `sqlserver-security` NO aplica)
**Entrada:** `05_modelo_fisico/{modelo_fisico.md,seleccion_dbms.md}`, `06_integridad/integridad.md`, `04_decisiones/decisiones_base_datos.md`, `01_requisitos/RNF.md`, `00_contexto/*`
**DBMS:** MySQL 8.x LTS (8.4+) / InnoDB — APROBADO vinculante (Paso 06)
**Objetivo:** política de seguridad de la capa de datos; sin SQL de migración, sin credenciales reales.

## 1. Trazabilidad RF/RNF → control

| RNF/RF | Requisito | Control en BD |
|---|---|---|
| RNF-030 | autenticación de administración | usuarios MySQL dedicados por rol; sin cuentas compartidas; `caching_sha2_password` |
| RNF-031 | mínimo privilegio | GRANT por rol de BD (app / DBA / backup / migraciones); sin `ALL PRIVILEGES` a la app |
| RNF-032 | cifrado en tránsito | `require_secure_transport=ON`; TLS 1.2+ obligatorio |
| RNF-033 | sin contraseñas en claro | hashing MySQL `caching_sha2_password`; contraseñas de app solo en `.env`/secrets; Argon2id en app para usuarios finales (§15 decisiones) |
| RNF-034 | secretos fuera del repo | `.env` / Docker Secrets; jamás en `my.cnf` versionado ni en artefactos `05_base_datos/` |
| RNF-035 | controles para APIs | la app se conecta con usuario propio de BD; APIs no hablan MySQL directamente; SQL siempre parametrizado |
| RF-001 | roles/permisos | RBAC en app (`auth_rol`/`auth_permiso`); en BD: roles separados app vs admin |
| RF-090 | auditoría crítica | `core_auditoria` append-only + GRANT INSERT-only al rol app (ver §4) |
| CA-09 / D-06 | Docker Compose, sin K8s | secretos por env/Compose secrets; red interna del compose no expone 3306 al host en prod |

## 2. Autenticación y usuarios de BD

**Regla:** mínimo privilegio; una cuenta de BD por función, nunca root desde la app.

| Cuenta BD | Rol MySQL | Privilegios | Uso |
|---|---|---|---|
| `app_rw` | `rol_app` | SELECT, INSERT, UPDATE en tablas de negocio; INSERT en `core_auditoria`, `inv_movimiento_inventario`, outbox | aplicación (POS/web/API) |
| `app_read` | `rol_lectura` | SELECT en todas las tablas | reportes/BI (RF-080); candidata a réplica si DB-P07 |
| `migraciones` | `rol_migracion` | DDL completo sobre el esquema de la app | pipeline de migraciones (DB-P11) |
| `backup` | `rol_backup` | SELECT + RELOAD, PROCESS, REPLICATION CLIENT, BINLOG MONITOR | mysqldump/PITR |
| `dba` | `rol_dba` | administración (uso humano, con MFA/SSH bastión si aplica) | operación |

- Auth MySQL: `caching_sha2_password` (default 8.x); **prohibido** `mysql_native_password` en instalaciones nuevas salvo incompatibilidad documentada.
- Sin cuentas compartidas ni anónimas: revocar `''@'localhost'` y eliminar usuarios por defecto en el checklist de endurecimiento.
- Passwords de cuentas BD: generados aleatoriamente, rotables, solo en secrets runtime; **nunca** en el repo (RNF-034, AGENTS.md).
- `root` solo reachable desde red local/contenedor DBA; no desde redes de aplicación.

## 3. Autorización (GRANT) — detalle por capa

| Objeto | `rol_app` | `rol_lectura` | Nota |
|---|---|---|---|
| tablas `auth_*` | SELECT/INSERT/UPDATE* | SELECT | *UPDATE solo tablas de sesión/roles si la app las gestiona; no `auth_token_blacklist` UPDATE masivo |
| tablas `core_*`, `cat_*`, `com_*`, `inv_*`, `sales_*`, `pay_*` | SELECT/INSERT/UPDATE | SELECT | DELETE: ver §3.1 |
| `core_auditoria` | **solo INSERT** | SELECT | RF-090 append-only |
| `inv_movimiento_inventario` | **solo INSERT** | SELECT | RN-01/RF-090 append-only |
| `core_outbox` (si existe) | SELECT/INSERT/UPDATE | SELECT | outbox: la app marca procesado (decisión D-04) |
| column-level | sin acceso a columnas de password hash fuera de módulo auth | — | refuerzo defensivo |
| `*.*` | **denegado** | — | sin SUPER/PROCESS/GRANT OPTION en app |

\* DELETE: la app no necesita DELETE a nivel BD en transaccionales (RN-08); si alguna entidad usa soft-delete por `estado` (DB-P12), basta UPDATE. Si una maestra de baja requiere borrado, ese privilegio queda fuera de `rol_app` (operación DBA).

**Append-only (cierra decisión Paso 08 §6 — capa GRANT):**

- `rol_app` sobre `core_auditoria` e `inv_movimiento_inventario`: `INSERT` + `SELECT` únicamente.
- Refuerzo en capa 2: triggers `BEFORE UPDATE/DELETE` → `SIGNAL SQLSTATE` (Paso 13/14). Ambas capas = defensa en profundidad.
- Decisión consolidada (integridad §6): bloquear UPDATE y DELETE enteros sobre ambas tablas (sin excepción de columnas `user_update*`).

## 4. Cifrado

| Capa | Decisión | Origen |
|---|---|---|
| En tránsito | TLS obligatorio: `require_secure_transport=ON`; certificados del contenedor/CA interna o CA de confianza; clientes MySQL con `--ssl-mode=VERIFY_IDENTITY` o equivalente del driver | RNF-032, skill databases (regla 4) |
| En reposo | **Disco/volumen del host o del volumen Docker** (LUKS/cloud encryption del proveedor de hosting). MySQL 8.x no ofrece TDE nativo open-source → cifrado a nivel de volumen. Si el hosting no lo da, documentar como limitación en Paso 14. | decisión; PCI-like no aplica (no hay PAN almacenado — pagos vía proveedor externo RF-063) |
| Contraseñas usuarios finales | Argon2id en aplicación; la BD solo ve hashes (§15 decisiones) | RNF-033 |
| Datos sensibles en columnas | email/identificación en claro son necesarios para operación (UNIQUE, login); no hay token de pago ni CVV en la BD (desacoplado RF-063) | RF-063 |

**Nota pagos:** `pay_transaccion_pago` guarda referencia/idempotency/montos/estado — **no** números de tarjeta completos. Si el proveedor devuelve un PAN tokenizado, solo el token/últimos 4.

## 5. Separación de entornos y redes

- Dev/test/prod: BDs y usuarios distintos; **nunca** volcados de prod con datos reales sin enmascarar en dev (RNF-034/035 implícito en higiene de datos).
- Docker Compose (D-06): puerto 3306 publicado solo en entorno dev; en prod, red interna del compose + app y BD en misma red user-defined; sin publish a 0.0.0.0.
- Backup artifacts: cifrados en destino; credenciales de almacenamiento de backup en secrets.

## 6. Hardening MySQL (config; hereda Paso 06 §5)

Checklist obligatorio al desplegar (skill `databases` Production Checklist MySQL):

- [ ] `sql_mode` incluye `STRICT_TRANS_TABLES` (ON)
- [ ] `character-set-server=utf8mb4` + `collation-server` coherente
- [ ] `require_secure_transport=ON` (TLS no opcional)
- [ ] `default_authentication_plugin` / usuarios con `caching_sha2_password`
- [ ] sin cuentas por defecto ni passwords de ejemplo
- [ ] `innodb_buffer_pool_size` ≈ 70% RAM
- [ ] `innodb_flush_log_at_trx_commit=1`
- [ ] `innodb_file_per_table=ON`
- [ ] `log_bin=ON` (PITR — RNF-040/041, DB-P05)
- [ ] `max_connections` dimensionado; pool de app ≤ `max_connections` (considerar ProxySQL si multi-instancia app)
- [ ]慢 query log / `long_query_time` operativo
- [ ] patches de motor LTS al día (MySQL 8.4.x actual; verificar CVE del mes antes de cada upgrade)

## 7. Protección anti-inyección y manejo de datos (límite app ↔ BD)

- Toda consulta **parametrizada** (skill databases regla 9; RNF-035): la BD recibe placeholders; el driver no concatena.
- Sin DDL/DCL dinámico desde la app (`rol_app` sin privilegios de ejecución de DDL lo refuerza).
- Exportes/volcados: sin secretos; rotación de passwords de BD tras cualquier fuga de artefacto (protocolo seguridad INSTRUCTIONS.md).
- Retención: `core_idempotency_key` / `auth_token_blacklist` con purga según política (30 días idempotencia — D-03); purga = operación DBA/app con privilegio puntual, no DELETE libre.

## 8. Mapping pendientes (no inventar respuestas)

| Pendiente | Impacto en seguridad |
|---|---|
| DB-P05 RPO/RTO finales | define frecuencia de backup/binlog y drills de restore (skill: restore sin prueba = no es backup) |
| DB-P07 réplica lectura | si aplica: `rol_lectura` apunta a réplica; separar usuario de réplicación |
| DB-P11 herramienta de migraciones | quién usa `rol_migracion` y cómo se inyecta el secret |
| DB-P08 identificación cliente | si se almacena identificación, evaluar minimización/exposición en consultas |
| DB-P09 precio global vs sucursal | no afecta seguridad directamente; riesgo de rediseño (Paso 14) |

## 9. Criterios de salida

- [x] RNF-030…035 y RF-001/090 trazados a controles concretos (§1).
- [x] Mínimo privilegio: roles BD separados; app sin DDL ni DELETE en append-only (§2–§3).
- [x] Append-only: GRANT INSERT-only en `core_auditoria` + `inv_movimiento_inventario` (decisión Paso 08 §6 cerrada en capa GRANT).
- [x] TLS forzado + Argon2id app + secretos fuera del repo (§4).
- [x] Hardening MySQL alineado con Paso 06 y skill `databases` (§6).
- [x] Sin credenciales reales ni SQL de migración en el artefacto.
- [x] Pendientes DB-P* marcados sin inventar (§8).

**Estado:** Paso 09 COMPLETADO → siguiente: Paso 10 (`08_auditoria/auditoria.md` + `09_versionamiento/versionamiento.md`).

# Paso 10 — Auditoría e histórico

**Workflow:** 02_database_workflow — Paso 10 (artefacto 1/2)
**Skills:** `database-schema-designer` + `databases`
**Entrada:** `05_modelo_fisico/modelo_fisico.md`, `06_integridad/integridad.md`, `07_seguridad/seguridad.md`, `04_decisiones/decisiones_base_datos.md` (§9 auditoría, §10 histórico), RF-090, RNF-050/052
**Objetivo:** distinguir auditoría de cambios, histórico temporal de negocio y outbox; sin SQL de migración.

## 1. Tres mecanismos (delimitación)

| Mecanismo | Qué responde | Artefacto/tabla | Origen decisión |
|---|---|---|---|
| **Auditoría de cambios** | quién hizo qué, cuándo, sobre qué entidad | `core_auditoria` (append-only) | §9 decisiones, RF-090 |
| **Histórico temporal de negocio** | evolución de stock/ventas/pagos sin depender del estado actual | `inv_movimiento_inventario`, `sales_orden_venta_item`, `pay_transaccion_pago`, devoluciones como registros nuevos | §9/§10 decisiones, RN-01, RN-08 |
| **Versionamiento de esquema** | cómo evolucionó el DDL | migraciones versionadas → `versionamiento.md` (artefacto 2/2) | §11 decisiones, DB-P11 |

**Regla de separación:** los campos `user_create*`/`user_update*` en cada tabla de negocio son **autoría de fila** (quién creó/modificó por última vez). El **historial completo** de cambios vive en `core_auditoria`. No se confunden.

## 2. Auditoría de cambios — `core_auditoria`

Estructura (modelo_fisico §4): `accion`, `entidad_tipo`, `entidad_id`, `datos_previos`, `datos_nuevos`, `usuario_id`, `motivo`, `fecha`, `correlation_id`, columnas de autoría.

| Aspecto | Decisión |
|---|---|
| Modelo | append-only: solo INSERT (GRANT §3 seguridad + trigger Paso 13/14) |
| Qué auditar (mínimo RF-090 + §9 decisiones) | login/logout fallidos y exitosos, cambios de roles/permisos, cambios en `core_configuracion`, creación/baja de usuarios, órdenes anuladas, ajustes de inventario, devoluciones, cambios de precios/promociones, acceso a datos sensibles de clientes |
| Quién escribe | la **aplicación** en la misma transacción de negocio cuando la operación es crítica (coherencia); NO triggers sobre todas las tablas (evita doble volumen innecesario — decisión §9: “triggers cuando corresponda”) |
| Triggers de auditoría | solo donde la app podría no dejar rastro y el riesgo lo justifique: cambios en `core_configuracion` y `auth_rol_permiso` (candidatos; confirmar en Paso 13) |
| `datos_previos`/`datos_nuevos` | JSON/TEXT con campos sensibles **enmascarados** (hash de password nunca; email/identificación solo si RNF lo permite — minimización) |
| `correlation_id` | alineado con `pay_outbox_evento.correlation_id` y RNF-052 (correlación de operaciones distribuidas) |
| Retención | indefinida o por política de cumplimiento (no hay plazo legal definido en RF → **pendiente**: no inventar; correlacionar con DB-P05 backup retention) |
| Índices | ya en modelo: `(entidad_tipo, entidad_id, fecha)`, `usuario_id`, `fecha` |

## 3. Histórico de inventario — `inv_movimiento_inventario`

- Log **append-only** de cada entrada/salida/reserva/ajuste/transferencia (RF-041, RN-01).
- El stock actual (`inv_inventario.stock*`) es el **estado materializado**; el log es la **verdad histórica**. Reconciliación: `stock = Σ movimientos` (aserción operativa en Paso 11/13; tolerar reservas si modeladas aparte).
- Protección: GRANT INSERT-only (Paso 09) + trigger anti UPDATE/DELETE (Paso 13/14).
- No se “corrige” un movimiento: un error se compensa con un **nuevo** movimiento de ajuste con `motivo` (RN-04).

## 4. Histórico de ventas, devoluciones y pagos

| Caso | Regla (§10 decisiones) | Implementación modelo |
|---|---|---|
| Venta/orden confirmada | sin DELETE físico | estado ENUM + FK RESTRICT |
| Devolución | **registro nuevo** ligado a la orden original | `sales_devolucion*` con FK → orden; nunca UPDATE/DELETE de la venta original |
| Pago | conservar para trazabilidad | `pay_transaccion_pago` inmutable en estados finales; reintentos → misma fila o nuevo intento según idempotencia D-03 |
| Ajuste de precio histórico | precios versionados | `cat_precio` con `(producto_id, vigente_desde)` UNIQUE |
| Transferencia/OC/recepción | histórico inmutable | FK RESTRICT desde ítems |

**Soft delete / maestras (DB-P12):** no decidido por entidad en este paso. Política general: transaccionales = nunca borrar; maestras = `estado`/inactivación por defecto; borrado físico solo si DB-P12 lo aprueba explícitamente (§10: “no se aplica soft delete automáticamente a todas”).

## 5. Bitácora de aplicación y binlog (fuera de tablas de negocio)

Complemento §9 decisiones (no sustituye a `core_auditoria`):

| Canal | Uso | Regla |
|---|---|---|
| Logs de app (RNF-050) | diagnóstico operativo, correlación | estructurados; sin secretos ni PII completo |
| MySQL `log_bin` | PITR + trazabilidad operativa de escrituras | `log_bin=ON` (Paso 06); acceso solo `rol_dba`/`rol_backup` |
| Slow query log | rendimiento (Paso 11) | no contiene datos de negocio completos habitualmente |

## 6. Outbox — `pay_outbox_evento` (no es auditoría)

- Propósito: D-04 — eventos de pago/publicación **en la misma transacción** que el estado de negocio; no historial de auditoría.
- Estados `pending/processed/failed` + `reintentos`; barrido por `(estado, creado_en)`.
- Purga de eventos `processed` antiguos: política de retención a definir con DB-P05 (no inventar plazo).

## 7. Trazabilidad RF → mecanismo

| RF/RNF | Mecanismo |
|---|---|
| RF-090 operaciones críticas | `core_auditoria` INSERT-only |
| RF-041 movimientos | `inv_movimiento_inventario` + `motivo`/usuario |
| RF-070 devoluciones | registros nuevos con referencia a orden original |
| RF-100 config operativa | auditoría de cambios en `core_configuracion` (+ trigger candidato) |
| §10 no borrar transacciones | ENUM estado + RESTRICT + sin DELETE en `rol_app` |
| RNF-052 correlación | `correlation_id` en auditoría y outbox |
| RNF-050/051 | logs app + métricas (capa DevOps; referencia aquí) |

## 8. Pendientes (sin inventar)

- Retención legal de `core_auditoria` y de binlog → ligado a **DB-P05**.
- Triggers de auditoría sobre `core_configuracion` / `auth_rol_permiso`: candidatos, confirmar en **Paso 13**.
- **DB-P12** política por entidad maestra (physical/soft/vigencia).
- Enmascarado de PII en `datos_previos`/`datos_nuevos`: definir campo a campo en Paso 13/14 si seguridad lo exige.

## 9. Criterios de salida

- [x] Auditoría ≠ histórico ≠ versionamiento, delimitados (§1).
- [x] `core_auditoria` append-only con alcance de acciones RF-090/§9 (§2).
- [x] Inventario: log + estado materializado; correcciones por movimiento compensatorio (§3).
- [x] §10 decisiones: sin DELETE de transaccionales; devoluciones como registro nuevo (§4).
- [x] Binlog y logs de app situados como complemento, no como sustituto (§5).
- [x] Outbox distinguido de auditoría (§6).
- [x] Pendientes DB-P* marcados (§8).
- [x] Sin SQL de migración.

**Estado:** Paso 10 (1/2) COMPLETADO → `09_versionamiento/versionamiento.md`.

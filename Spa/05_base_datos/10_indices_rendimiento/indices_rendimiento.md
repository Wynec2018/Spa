# Paso 11 — Índices y rendimiento

Sistema: **Spa Cielo**
Skills: `database-schema-designer`, `databases`, `postgresql-table-design`
Motor: PostgreSQL 16+
Regla: **todo índice se vincula a una consulta o patrón de acceso** (no indexar "por si acaso").

---

## 1. Índices por patrón de acceso

| Índice | Tabla / columnas | Tipo | Patrón | Req. |
|---|---|---|---|---|
| `ix_booking_cama` | booking(cama_id) | B-tree | A-01/A-02 | RF-004, RNF-020 |
| `ix_booking_trabajadora` | booking(trabajadora_id) | B-tree | A-01/A-02 | RN-003 |
| `ix_booking_branch_inicio` | booking(branch_id, inicio) | B-tree compuesto | A-03 | RF-004 |
| `ix_booking_estado` | booking(estado) WHERE estado IN ('pendiente','confirmada') | **Parcial** | A-03/A-07 | RF-016 |
| `ux_booking_cama_slot` | booking(cama_id, periodo) | **EXCLUDE gist** | A-02 | RN-001, RNF-020 |
| `ux_booking_trab_slot` | booking(trabajadora_id, periodo) | **EXCLUDE gist** | A-02 | RN-003 |
| `ix_resource_branch_tipo_estado` | resource(branch_id, tipo, estado) | B-tree compuesto | A-01 | RF-005/006 |
| `ix_payment_booking` | payment(booking_id) | UNIQUE | A-04 | RF-007 |
| `ix_payment_estado` | payment(estado) WHERE estado = 'pendiente' | **Parcial** | A-04 | RN-002 |
| `ix_idem_expires` | idempotency_key(expires_at) | B-tree | A-08 | RF-008 |
| `ix_outbox_pending` | outbox(publicado, created_at) WHERE publicado = false | **Parcial** | A-09 | RNF-042 |
| `ix_audit_entidad` | audit_log(entidad, entidad_id) | B-tree | Auditoría | RF-015 |
| `ix_audit_created` | audit_log(creado_en) | BRIN | Retención/partición | RF-015 |
| `ix_notification_estado` | notification(estado) WHERE estado = 'pendiente' | **Parcial** | RF-011 | RF-011 |
| `ix_app_user_contacto` | app_user(branch_id, contacto) | UNIQUE | Login/búsqueda | RF-001 |
| `gin_promotion_reglas` | promotion USING gin(reglas) | **GIN** | Reglas JSON | RF-009 |
| `gin_config_valor` | app_config USING gin(valor) | **GIN** | Consultas de config | RF-016 |

- **Toda FK tiene índice** (requisito PostgreSQL; el motor no lo crea) — verificación del Paso 14.

## 2. Índices de cobertura (opcional)

- `ix_booking_list` `(branch_id, inicio) INCLUDE (estado, cama_id, trabajadora_id)`
  para el listado del día (A-03) sin volver a la tabla.

## 3. Vistas materializadas (reportes, RF-014/017)

| Vista | Contenido | Refresh |
|---|---|---|
| `mv_ocupacion_diaria` | ocupación por cama/trabajadora/día | 5 min (P-09) |
| `mv_ingresos_periodo` | ingresos por servicio/sucursal | 5 min |
| `mv_rendimiento_trabajadora` | servicios atendidos, ingresos | 5 min |

Índice único sobre la clave de agrupación para `REFRESH MATERIALIZED VIEW CONCURRENTLY`.
Latencia objetivo < 2 s (RF-014).

## 4. Anti-patrones evitados

- Sin índices redundantes sobre columnas ya cubiertas por PK/UNIQUE.
- Sin `SELECT *` en vistas de reporte (columnas explícitas, sin PII).
- Sin indexar columnas de baja selectividad salvo como parcial/compuesto con líder selectivo.

## 5. Validación

- Verificación con `EXPLAIN (ANALYZE, BUFFERS)` en Pruebas (Paso 19).
- Criterio de cierre: consultas A-01..A-05 usan índice (sin *seq scan* en tablas calientes).

**Estado del paso:** completado.

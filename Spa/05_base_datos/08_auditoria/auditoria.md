# Paso 10a — Auditoría e histórico

Sistema: **Spa Cielo**
Skills: `database-schema-designer` + `databases`
Trazabilidad: RF-015, RNF-031.

> El workflow distingue tres conceptos que aquí se separan explícitamente:
> **auditoría de cambios**, **historial temporal** y **versionamiento de esquema** (10b).

---

## 1. Auditoría de cambios (`audit_log`)

- **Qué**: operaciones críticas — pagos, asignaciones de recursos, cambios de estado,
  registro/modificación de usuarios y parámetros.
- **Estructura**: `actor_user_id`, `accion`, `entidad`, `entidad_id`, `datos_previos`,
  `datos_nuevos`, `correlation_id`, `prev_hash`, `creado_en`.
- **Inmutabilidad**: `UPDATE`/`DELETE` revocados a `spa_app`; encadenamiento por `prev_hash`.
- **Origen**: triggers `AFTER INSERT/UPDATE/DELETE` en tablas críticas + `pgaudit` (DDL).

Tablas auditadas: `app_user`, `client`, `service`, `resource`, `booking`, `payment`,
`refund`, `promotion`, `app_config`.

## 2. Historial temporal

| Necesidad | Estrategia |
|---|---|
| Estado *actual* de recursos (RF-005) | Columnas de estado en `resource` (rápido de leer) |
| Evolución de estado de una reserva | Secuencia de eventos en `audit_log` (`entidad='booking'`) + estados |
| Cambios de parámetros | `audit_log` sobre `app_config` (valor previo/nuevo) |

No se implementa *temporal tables* (system-versioning) en el MVP; el historial se deriva
de `audit_log`. Se documenta como extensión futura si se requiere *time-travel*.

## 3. Retención y privacidad

- Auditoría y datos de citas/pagos se conservan (RF-015, RNF-044).
- PII en logs enmascarada; `documento` nunca en claro.
- Partición por `creado_en` de `audit_log` si supera umbral (Paso 07).

## 4. Requisitos → control

| Requisito | Control |
|---|---|
| RF-015 Auditar operaciones críticas | `audit_log` + triggers |
| RNF-031 Mínimo privilegio | `REVOKE` de escritura en `audit_log` |
| RNF-051 Correlación | `correlation_id` por operación |

**Estado del paso:** completado.

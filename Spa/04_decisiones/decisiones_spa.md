# Decisiones — Spa Cielo

Registro de decisiones arquitectónicas y de producto del sistema Spa Cielo.
Estado: `HUMAN_STATUS: APPROVED` (ratificado por el responsable del proyecto, 2026-10-18).
Fuente narrativa completa: `../03_resultados/08_recomendacion_final.md`.

## Decisiones arquitectónicas (D-01..D-12)

| # | Decisión | Elección | Trazabilidad |
|---|---|---|---|
| D-01 | Concurrencia de camas/trabajadoras | `SELECT FOR UPDATE SKIP LOCKED` + `pg_advisory_xact_lock` + índices únicos parciales | RNF-005/020, RN-001/003/004 |
| D-02 | Modelo transaccional de pagos | Saga orquestada + TX atómica reserva+pago+recurso+auditoría; outbox para webhook | RN-002 |
| D-03 | Idempotencia | `Idempotency-Key` + tabla `idempotency_key` (unique `scope:key`) | RF-008, RNF-022 |
| D-04 | Tiempo real | SSE disparado por `LISTEN/NOTIFY` | CA-001 |
| D-05 | Multi-sucursal | `branch_id` + RLS desde día 1; operar una sede | RNF-010/012 |
| D-06 | Configuración externa | Tabla `app_config` (JSONB) + cache TTL | RF-016, RNF-002 |
| D-07 | Auth/authz | JWT RS256 + RBAC + MFA TOTP (superusuario) + RLS | RNF-030/031 |
| D-08 | Motor de base de datos | PostgreSQL 16+ | ACID, MVCC, JSONB, RLS |
| D-09 | Stack backend | **PHP 8.3 + Laravel 11** | RNF-006/060/061 |
| D-10 | Despliegue | VM + Docker Compose (+ réplica síncrona) | RNF-060..062 |
| D-11 | Observabilidad | Vector → Loki + Prometheus + Grafana | RNF-050..052 |
| D-12 | Reportes | Vistas materializadas (refresh 5 min) | RF-014/017 |

> **D-09 actualizado 2026-10-20**: PHP 8.3 + **Laravel 13** (Laravel 11 quedó
> bloqueado por avisos de seguridad). Ver `decisiones_stack_fase3.md`.

## Preguntas de producto resueltas (P-01..P-09)

| # | Pregunta | Resolución adoptada |
|---|---|---|
| P-01 | “Transacción de negocio” | 1 reserva completada (booking + recursos asignados + pago verificado) |
| P-02 | ¿Reserva web bloquea el recurso? | Sí, durante el TTL; la secretaría ve ocupación real y puede liberar (RN-002) |
| P-03 | Expiración de reserva “pendiente” | TTL 15 min configurable; liberación automática (pg_cron) + notificación |
| P-04 | Proveedor QR | Adapter desacoplado + validación humana; webhook+HMAC al elegir proveedor |
| P-05 | RPO/RTO | RPO=0 (réplica síncrona), RTO <15 min |
| P-06 | Multi-sucursal día 1 | Modelado desde día 1; operación en una sede |
| P-07 | Cambio de nº de camas/trabajadoras | Solo superusuario; auditado; valida reservas activas |
| P-08 | Notificaciones | Adapter async (outbox); proveedor WhatsApp/correo a definir |
| P-09 | Reportes | Batch cercano a tiempo real (refresh 5 min), latencia <2s |

## ADR

- `ADR-001` — Arquitectura del Sistema Integral de Gestión del Spa Cielo — **Aceptado** (ver `../03_resultados/08_recomendacion_final.md`, §14).

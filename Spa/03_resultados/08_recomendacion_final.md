# Recomendación Final — Spa Cielo / Sistema Integral de Gestión y Atención Virtual

Fecha: 2026-10-18
Autor: solution-leader
Skill: `decision-consolidation`
Fuentes: `03_resultados/01` a `07` (Spa Cielo), `00_contexto/`, `01_requisitos/RF.md`, `01_requisitos/RNF.md`, `01_requisitos/criterios_aceptacion.md`, `02_configuracion/perfil_carga.yaml`
Estado: `HUMAN_STATUS: PENDING` — requiere validación humana de las decisiones P-01..P-09 antes del ADR definitivo (D-09 ya confirmado: PHP 8.3 + Laravel 11).

---

## 1. Resumen ejecutivo

Spa Cielo automatiza reservas (web y presencial), asignación de recursos finitos
(1-6 camas, 1-10 trabajadoras), pagos por QR con validación humana y monitoreo en
tiempo real. El problema central es la **integridad de recursos escasos bajo
concurrencia** entre la reserva web y la recepción física, más la **validación
humana de pagos** (RN-002).

**Recomendación:** **Monolito Modular** (Alternativa A) con **Clean Architecture**
interna y **PostgreSQL 16+**, desplegado con **Docker Compose sobre una VM**,
tiempo real vía **SSE + LISTEN/NOTIFY**, concurrencia con **`SELECT FOR UPDATE
SKIP LOCKED`** y **índices únicos parciales**, idempotencia por token + tabla de
deduplicación, y auditoría append-only.

**Lo que NO se hace (y está bien):** microservicios, Event Sourcing/CQRS completo,
BFF, Redis cache, Kubernetes.

---

## 2. Alternativas evaluadas y descartadas

| Alternativa | Puntaje | Estado | Motivo |
|---|---|---|---|
| A. Monolito Modular | 4.45 | **Elegida** | ACID, baja operación, equipo pequeño (C-05), portable |
| C. Modular desplegable | 4.00 | Base de A | Se adopta la organización por módulos, sin extraer servicios |
| B. Monolito + CQRS ligero | 3.65 | Post-MVP | Solo si los reportes lo exigen; hoy innecesario |
| D. BFF + monolito | 3.15 | Descartada | Un solo frontend web responsive; BFF sería sobrearquitectura |
| E. Event Sourcing + CQRS | 2.45 | Descartada | Over-engineering severo para 50-500 tx/h |
| F. Microservicios | 1.95 | Descartada | Sin justificación de escala; complejidad operativa alta |

Kubernetes: **descartado explícitamente** (contenedor ≠ orquestador; equipo pequeño
y capacitación de 30 min; carga trivial). Revisar solo si aparecen múltiples equipos
de plataforma.

---

## 3. Matriz de decisión por dominio

| Decisión | Elección | Alternativa descartada | Justificación |
|---|---|---|---|
| **D-01** Concurrencia de camas/trabajadoras | Pesimista por fila: `SELECT FOR UPDATE SKIP LOCKED` + `pg_advisory_xact_lock` + índices únicos parciales; TX `REPEATABLE READ`, orden determinista por ID | Optimista (peor UX por reintentos), cola/Event Sourcing (complejidad) | Recursos escasos (6×10). Evita doble asignación (RNF-005/020, RN-001/003/004) |
| **D-02** Modelo transaccional de pagos | Saga **orquestada** (state machine en capa de aplicación) + TX atómica multi-tabla para reserva+pago+recurso+auditoría; **outbox** para webhook | ACID único end-to-end (no aplica por validación humana externa), 2PC (no recomendado) | RN-002: la confirmación es humana y no atómica end-to-end |
| **D-03** Idempotencia | Token `Idempotency-Key` + tabla `idempotency_key` (unique `scope:key`) + constraints naturales | Lock distribuido (no cubre reintentos tras expirar) | RF-008/RNF-022 |
| **D-04** Tiempo real | **SSE** (server→client) disparado por `LISTEN/NOTIFY` | WebSockets (bidireccional innecesario), polling (carga/latencia) | CA-001 (<3s), ~10 usuarios concurrentes |
| **D-05** Multi-sucursal | Modelar `branch_id` + **RLS** desde día 1, operación inicial en una sede | Migración futura (riesgo de rediseño) | RNF-010/012; P-06 |
| **D-06** Configuración externa | Tabla `app_config` (JSONB) + cache con TTL | Consul/etcd (complejidad) | RF-016/RNF-002 |
| **D-07** Autenticación/autorización | **JWT RS256** (access 15 min) + refresh con rotación + **RBAC** (superusuario, secretaria, trabajadora, cliente) + RLS; **MFA TOTP obligatorio para superusuario** | Sesiones en servidor (menos portable), OAuth2/OIDC (sin necesidad) | RNF-030/031; roles del contexto |
| **D-08** Motor de base de datos | **PostgreSQL 16+** | MySQL (SKIP LOCKED/RLS menos maduros), SQL Server (licencia), NoSQL (consistencia) | ACID, MVCC, JSONB, RLS, `pg_audit`, `pg_cron`, WAL/PITR |
| **D-09** Stack backend | **PHP 8.3 + Laravel 11** | Node/NestJS, Python/FastAPI, .NET | Confirmado: continuidad del entorno del equipo, maduro y contenerizable (RNF-006/060/061) |
| **D-10** Despliegue | **VM + Docker Compose** (app, pg-primary, pg-standby, pgBackRest, Caddy, observabilidad) | K8s (descartado), serverless (riesgo SSE) | RNF-060..062; C-08 |
| **D-11** Observabilidad | **Vector → Loki + Prometheus + Grafana**; `correlation_id`/W3C Trace Context | ELK/Datadog (peso/costo) | RNF-050/051/052 |
| **D-12** Reportes | Vistas materializadas (refresh 5 min) + consultas directas; réplica de lectura si crece | OLAP separado | RF-014/017; latencia <2s |

---

## 4. Arquitectura recomendada

### Alternativa A — Monolito Modular con Clean Architecture

```
┌──────────────────────────────────────────────────────────┐
│                    Front Controller / Router              │
├──────────────────────────────────────────────────────────┤
│  Módulos (carpeta por dominio)                            │
│  ┌─────────┐ ┌──────────┐ ┌──────────┐ ┌───────────────┐ │
│  │ Auth    │ │ Reservas │ │ Recursos │ │ Pagos (QR)    │ │
│  │ (RBAC)  │ │ (Booking)│ │ (Cama/   │ │ (saga +        │ │
│  │         │ │          │ │ Trabaj.) │ │  outbox)      │ │
│  └────┬────┘ └────┬─────┘ └────┬─────┘ └──────┬────────┘ │
│       └───────────┴────────────┴───────────────┘          │
│         Eventos internos in-process + Outbox              │
│  Capas por módulo: API / Application / Domain / Infra     │
├──────────────────────────────────────────────────────────┤
│  PostgreSQL 16 (schemas por módulo) + RLS por branch_id   │
│  SSE ← LISTEN/NOTIFY                                      │
└──────────────────────────────────────────────────────────┘
```

**Por qué:** un despliegue, una BD, una transacción por operación crítica; límites
de módulo claros (preparado para extraer servicios si crece); ACID para
reserva+recurso+pago; SSE para estado de recursos en <3s. `branch_id` + RLS
preparan multi-sucursal sin rediseño.

---

## 5. Backend

> D-09 (stack) **confirmado por el equipo**: PHP 8.3 + Laravel 11.
> Justificación: continuidad del entorno, madurez y buena contenerización (RNF-006, RNF-060..062).

| Capa | Recomendación | Alternativas |
|---|---|---|
| Lenguaje/Framework | **PHP 8.3 + Laravel 11** | Node.js LTS + NestJS; Python + FastAPI |
| ORM / migraciones | Eloquent + migraciones Laravel | SQL versionado para cambios de alto riesgo |
| Auth | JWT RS256 + middleware RBAC | — |
| Validación | Validadores en el borde de confianza | — |
| Transacciones | ACID para reserva+recurso+pago; saga para QR | — |
| Idempotencia | Middleware `Idempotency-Key` + tabla | — |
| Config | Tabla `app_config` (JSONB) | — |

Flujo: `Request → Router → Controller → Validator → Service (caso de uso) →
Repository → PDO/ORM → PostgreSQL`, con SSE como canal de salida.

---

## 6. Persistencia

**Motor:** PostgreSQL 16+ (D-08).

### Modelo de datos (dominio spa)

```
branch(id, nombre, ...)
resource(id, branch_id, tipo[cama|trabajadora], nombre, estado[libre|ocupada|no_disponible], version)
user(id, branch_id, rol[superusuario|secretaria|trabajadora|cliente], hash_password, estado)
client(id, branch_id, nombre, documento_cifrado, contacto, ...)
service(id, nombre, duracion_min, precio, estado)
booking(id, branch_id, client_id, service_id, cama_id, trabajadora_id,
        inicio, fin, estado[pendiente|confirmada|cancelada|expirada|atendida],
        payment_id, ...)
payment(id, booking_id, proveedor, qr_payload, monto, estado[pendiente|verificado|rechazado|reembolsado],
        verificado_por, ...)
promotion(id, reglas, vigencia, ...)
idempotency_key(clave PK "scope:key", request_hash, response, expires_at)
outbox(id, tipo_evento, payload, publicado)
notification(id, destinatario, canal, plantilla, estado)
audit_log(id, actor, accion, entidad, datos_previos, datos_nuevos, correlation_id, prev_hash)
app_config(clave PK, valor JSONB, updated_by, updated_at)
```

Concurrencia: `SELECT FOR UPDATE SKIP LOCKED` + `pg_advisory_xact_lock`;
índices únicos parciales para impedir doble reserva activa de cama y de trabajadora;
job `pg_cron` de expiración de reservas “pendiente” (TTL configurable, 15 min por
defecto).

### Backup / recuperación
- Réplica en streaming **síncrona** (RPO=0) para el escenario recomendado.
- `pgBackRest`: full diario + incremental 6 h + WAL continuo; cifrado AES-256; destino local + S3/MinIO.
- RTO objetivo < 15 min; pruebas de restore periódicas.

> Nota VAL-A-01: `04_database_analysis.md` aún mezcla entidades de tienda
> (`product`, `stock_movement`). Debe depurarse a este modelo antes de implementar.

---

## 7. Integraciones

| Integración | Patrón | Notas |
|---|---|---|
| Pago QR | Adapter `PaymentProvider` desacoplado + webhook (HMAC-SHA256, timestamp ±5 min, nonce anti-replay) | RN-002: validación humana obligatoria; auto-rechazo por timeout; proveedor pendiente (P-04) |
| Notificaciones (WhatsApp/correo) | Adapter `NotificationProvider` async vía outbox/cola; reintentos con backoff | RF-011; proveedor pendiente (P-08) |
| Tiempo real | SSE ← `LISTEN/NOTIFY` | CA-001 (<3s) |
| Reportes | Vistas materializadas / consultas directas | RF-014/017 |

Resiliencia (RNF-042): HTTP con retry (3, backoff exponencial), circuit breaker en
el proveedor de pago, dead-letter para fallos, timeouts por operación.

---

## 8. Seguridad

| RNF | Implementación |
|---|---|
| RNF-030 | JWT RS256 (access 15 min, refresh con rotación); MFA TOTP obligatorio para superusuario |
| RNF-031 | RBAC (superusuario, secretaria, trabajadora, cliente) + RLS por `branch_id`; mínimo privilegio |
| RNF-032 | TLS 1.3 (mín. 1.2) extremo a extremo; `sslmode=verify-full` en PostgreSQL |
| RNF-033 | Argon2id (64 MB, 3 iteraciones, 4 hilos) |
| RNF-034 | Secretos en Docker Secrets/Vault/Cloud SM, inyección en runtime, rotación 90 días, nunca en Git |
| RNF-035 | Rate limiting (login 5/min, API 100/min, webhook 50/min); validación server-side; sin stack traces en prod |

Extras: `audit_log` append-only con hash encadenado (no repudio); cifrado de DNI
en reposo (AES-256-GCM); enmascarado de PII en logs; UUID v7 para evitar
enumeración.

---

## 9. Infraestructura

- **VM única + Docker Compose** (Opción 1): app, `pg-primary`, `pg-standby`,
  `pgBackRest`, Caddy (TLS), Vector + Prometheus + Grafana. 2-4 vCPU / 8-16 GB.
- **Entornos:** `.env.dev`, `.env.test`, `.env.prod` (RNF-062).
- **Kubernetes: NO** (justificación explícita en §2).
- **Observabilidad:** logs estructurados (JSON) → Loki; métricas → Prometheus/Grafana; correlación por `correlation_id`.

Si el equipo prefiere descargar la operación de BD: **PostgreSQL gestionado**
(Opción 2), con el mismo diseño de aplicación.

---

## 10. Capacidad

| Métrica | Valor |
|---|---|
| Transacciones de negocio/hora (base) | 50 |
| Pico (×2.0) | 100 |
| Escenario a evaluar (10×) | 500 |
| Escrituras de BD/hora | 250-500 (→ 2.500-5.000 a 10×) |
| Usuarios concurrentes en tiempo real | ~10 |
| Tamaño BD | ~500 MB año 1 → ~50 GB año 3 |

**Conclusión:** un solo nodo PostgreSQL con pooling cubre 10× sin sharding ni
réplica de lectura para el MVP. La recomendación de réplica es por **HA**
(RPO=0), no por capacidad.

---

## 11. Riesgos

| # | Riesgo | Prob. | Impacto | Mitigación |
|---|---|---|---|---|
| 1 | Doble asignación de cama/trabajadora (web vs presencial) | Alta | Crítico | Locks + índices únicos parciales + tests de concurrencia |
| 2 | Ventana de inconsistencia pago “pendiente” vs validación | Media | Alto | TTL + job de expiración + state machine con revival |
| 3 | Proveedor QR no definido | Media | Alto | Adapter desacoplado + mock + contrato |
| 4 | Single VM = SPOF frente a 99.8% | Media | Alto | Réplica síncrona + script de failover + runbook |
| 5 | Stack sin decidir (D-09) | Alta | Alto | Confirmar antes de implementar |
| 6 | Regulación PII no definida | Media | Alto | Asumir Bolivia; cifrar DNI; definir retención |

---

## 12. Preguntas pendientes (P-01..P-09) — propuesta por defecto

| # | Pregunta | Propuesta por defecto | Decisión |
|---|---|---|---|
| P-01 | ¿Qué es una “transacción de negocio”? | 1 reserva completada (booking + recursos asignados + pago verificado) | Requiere confirmación |
| P-02 | ¿Reserva web bloquea el recurso? | Reserva web crea `booking` que ocupa el recurso durante el TTL; la secretaría ve la ocupación real y puede cancelar/liberar (RN-002) | Requiere confirmación |
| P-03 | Expiración de reserva “pendiente” | TTL 15 min configurable; liberación automática (`pg_cron`); notificación al cliente | Requiere confirmación |
| P-04 | Proveedor QR | Adapter + validación humana (secretaría); webhook+HMAC al elegir proveedor | Requiere confirmación |
| P-05 | RPO/RTO | RPO=0 (réplica síncrona), RTO <15 min | Requiere confirmación |
| P-06 | Multi-sucursal día 1 | Modelar `branch_id` + RLS; operar una sede al inicio | Requiere confirmación |
| P-07 | Cambio de nº de camas/trabajadoras | Solo superusuario; auditado; validar que no rompa reservas activas | Requiere confirmación |
| P-08 | Notificaciones | Adapter async (outbox); proveedor de WhatsApp/correo a definir | Requiere confirmación |
| P-09 | Reportes | Batch cercano a tiempo real (refresh 5 min); latencia <2s | Requiere confirmación |

Defaults conservadores: no bloquean el diseño, pero **deben ratificarse** antes del ADR definitivo.

---

## 13. Trazabilidad RF/RNF → decisión

| Requisito | Decisión | Estado |
|---|---|---|
| RF-001 (usuarios, roles, permisos) | D-07 | Cubierto |
| RF-002 (clientes, identidad) | D-08 + cifrado DNI | Cubierto |
| RF-003 (catálogo de servicios) | D-08 | Cubierto |
| RF-004 (reserva con cama/trabajadora) | D-01, D-08 | Cubierto |
| RF-005 (estado de recursos en tiempo real) | D-04, D-01 | Cubierto |
| RF-006 (atención presencial sin reserva) | D-01 (disponibilidad real) | Cubierto |
| RF-007 (pago QR + validación humana) | D-02 | Cubierto (proveedor P-04) |
| RF-008 (idempotencia) | D-03 | Cubierto |
| RF-009 (promociones) | D-08 | Parcial (motor de reglas a definir) |
| RF-010 (devoluciones/cancelaciones) | D-02 (state machine) | Parcial (flujo a detallar) |
| RF-011 (notificaciones) | D-03 + adapter | Cubierto (proveedor P-08) |
| RF-012 (integración QR desacoplada) | D-02 + adapter | Cubierto (contrato a definir) |
| RF-013 (sin sobreasignación web/físico) | D-01 | Cubierto |
| RF-014 (reportes operativos) | D-12 | Cubierto |
| RF-015 (auditoría) | D-05(seg)/audit_log | Cubierto |
| RF-016 (parámetros sin código) | D-06 | Cubierto |
| RF-017 (dashboard superusuario) | D-12 | Cubierto |
| RNF-001/003/004 (capacidad) | §10 | Cubierto (falta prueba de carga) |
| RNF-002 (config externa) | D-06 | Cubierto |
| RNF-005/020/021/022 (integridad) | D-01, D-02, D-03 | Cubierto |
| RNF-010/011/012 (escalabilidad) | D-05, §10 | Cubierto (falta prueba 10×) |
| RNF-030..035 (seguridad) | D-07 + §8 | Cubierto (MFA/cifrado a implementar) |
| RNF-040..044 (disponibilidad/backup) | D-10 + §6 | Parcial (RPO/RTO P-05) |
| RNF-050..052 (observabilidad) | D-11 | Cubierto |
| RNF-060..062 (portabilidad) | D-10 | Cubierto |

---

## 14. ADR propuesto

```markdown
# ADR-001: Arquitectura del Sistema Integral de Gestión del Spa Cielo

## Estado: Propuesto (pendiente de ratificación humana)

## Contexto
Sistema de gestión de spa con recursos finitos (1-6 camas, 1-10 trabajadoras),
reservas web y presenciales concurrentes, pagos QR con validación humana (RN-002)
y disponibilidad objetivo del 99.8% (RNF-043).

## Decisión
- Arquitectura: Monolito Modular con Clean Architecture (Alternativa A).
- Persistencia: PostgreSQL 16+ (schemas por módulo, RLS por branch_id).
- Concurrencia: SELECT FOR UPDATE SKIP LOCKED + índices únicos parciales.
- Pagos: saga orquestada + outbox; validación humana obligatoria.
- Tiempo real: SSE sobre LISTEN/NOTIFY.
- Seguridad: JWT RS256 + RBAC + MFA (superusuario) + Argon2id + TLS.
- Despliegue: VM + Docker Compose; PostgreSQL con réplica síncrona.
- Observabilidad: Loki + Prometheus + Grafana.

## Consecuencias
- Operación simple; preparado para multi-sucursal (branch_id + RLS).
- Stack backend (D-09) confirmado: PHP 8.3 + Laravel 11.
- Kubernetes, microservicios, Event Sourcing y Redis NO se incluyen.

## Alternativas consideradas
- CQRS ligero (B): post-MVP si los reportes lo exigen.
- BFF (D), Event Sourcing (E), Microservicios (F): descartadas.
- Kubernetes: descartado por complejidad vs carga y tamaño de equipo.

## Riesgos conocidos
- SPOF de VM única mitigado con réplica + failover manual.
- RPO/RTO y proveedor QR pendientes de confirmación (P-04, P-05).
```

---

## 15. Plan de pruebas antes de implementar

| Prueba | Objetivo | Criterio pass |
|---|---|---|
| Concurrencia de recursos | RNF-005/020, RN-001/003/004 | 0 doble asignación en 10.000 iteraciones |
| Expiración de reserva | RN-002, RNF-021 | Recursos liberados, booking `expirada`, notificación |
| Idempotencia | RF-008, RNF-022 | 1 sola entidad por clave en 500 reintentos |
| State machine de pago | RN-002 | `pendiente→verificado→confirmada` y revival tras expiración |
| Webhook QR | RF-012, RNF-035/042 | HMAC válido, replay rechazado, async procesado |
| SSE tiempo real | CA-001 | Notificación <3s en 99% de casos |
| Walk-in presencial | RN-004, CA-002 | Secretaría ve disponibilidad real y asigna si hay libre |
| Carga 10× | RNF-011 | 500 tx/h: p99 <500 ms, 0 errores 5xx |
| Failover PostgreSQL | RNF-043 | App recupera <30s, RPO=0 |
| Restore/PITR | RNF-040/041/044 | Restore <30 min, datos consistentes |
| AuthZ/IDOR | RNF-030/031 | 0 bypass, 0 IDOR |
| Secretos | RNF-034 | 0 secretos en código, imagen, logs o git history |
| Multi-sucursal (RLS) | RNF-010/012 | Sede A no ve datos de sede B |

### Criterio de aprobación
- [ ] P-01..P-09 ratificadas por el humano
- [ ] D-09 (stack) confirmado
- [ ] 0 doble asignación en pruebas de concurrencia
- [ ] Flujo de pago QR completo (incl. expiración/revival) sin inconsistencias
- [ ] RPO/RTO validados con restore real

---

## 16. Próximo paso

`architecture-reviewer` → re-validar esta consolidación y los artefactos contra el
`RF.md`/`RNF.md` de Spa Cielo, y cerrar los hallazgos de `09_validacion_arquitectura.md`.

**Estado: `HUMAN_STATUS: PENDING`.**

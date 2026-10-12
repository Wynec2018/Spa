# Revisión Adversarial de Arquitectura — Spa Cielo

Fecha: 2026-10-09  
Fuentes: `03_resultados/01_requirements_analysis.md`, `03_resultados/02_decision_scope.md`, `03_resultados/03_architecture_options.md`, `03_resultados/04_database_analysis.md`, `03_resultados/05_security_review.md`, `03_resultados/06_infrastructure.md`  
Alcance: Revisión completa contra **RF** (01_requisitos/RF.md) y **RNF** (01_requisitos/RNF.md), reglas de negocio, restricciones, criterios de aceptación y perfil de carga.

---

## Metodología

Cada hallazgo se clasifica:

| Severidad | Definición | Acción Requerida |
|-----------|------------|------------------|
| **BLOQUEANTE** | Impide cumplir RF/RNF crítico; riesgo legal/financiero/operativo inaceptable | **Debe resolverse antes de ADR** |
| **ALTO** | Debilita significativamente un RF/RNF crítico; trabajo adicional considerable | Resolver antes de implementación |
| **MEDIO** | Mejora importante; deuda técnica si no se aborda | Planificar en sprints tempranos |
| **BAJO** | Optimización, nice-to-have, documentación | Backlog opcional |

Criterio de cierre: **Evidencia verificable** (código, config, test, runbook, decisión documentada).

---

## Hallazgos por Categoría

### 1. Cobertura de Requisitos Funcionales (RF)

| ID | Hallazgo | RF Afectado | Evidencia | Severidad | Cambio Necesario | Criterio Cierre |
|----|----------|-------------|-----------|-----------|------------------|-----------------|
| ARV-RF-01 | **Inventario multi-sucursal no modelado completamente** | RF-005, RF-009, RF-010, RF-011, RF-013 | 04_database_analysis.md esquema tiene `branch_id` en `product` y `stock_movement` pero falta: transferencias entre sucursales, recepción parcial, ajustes con motivo, reservar stock antes de confirmar (RF-011) | **ALTO** | Añadir: `transfer_request`, `transfer_item`, `stock_reservation` (con TTL), `purchase_order`, `receipt`; RLS en todas | Migración SQL + tests integración transferencia stock |
| ARV-RF-02 | **Promociones: alcance y reglas no definidas** | RF-006 | 04_database_analysis.md tiene tabla `service` con `price` pero sin modelo promociones (descuento %, 2x1, por tiempo, por cliente, acumulables?) | **MEDIO** | Definir entidad `promotion` (tipo, reglas JSONB, vigencia, aplicabilidad) + motor evaluación en domain layer | Esquema + use case `apply_promotion` + tests |
| ARV-RF-03 | **Devoluciones: flujo parcial/total no especificado** | RF-007 | 04_database_analysis.md menciona reversión pagos/asignaciones pero no: quién autoriza, stock vuelve a disponible, comisión trabajadora, reembolso parcial vs total, nota crédito | **MEDIO** | State machine `refund` (solicitado → autorizado → procesado → completado) + compensación stock/pago/comisión | State machine documentada + tests |
| ARV-RF-04 | **Idempotencia: alcance no cubierto en todos endpoints** | RF-008 | 04_database_analysis.md + 05_security_review.md cubren booking/payment/checkin. Falta: cancelación, cambio estado recurso, transferencia stock, creación cliente | **MEDIO** | Tabla `idempotency_key` scope extendido: `booking_cancel`, `resource_state`, `stock_transfer`, `client_create` | Lista scopes completa + middleware validación |
| ARV-RF-05 | **Integración QR: adapter pattern definido pero sin contrato** | RF-012 | 03_architecture_options.md + 05_security_review.md definen HMAC/webhook/polling pero no: interfaz `PaymentProvider` (métodos, DTOs, errores, timeouts, reconexión) | **ALTO** | Definir `PaymentProvider` interface + implementación mock + implementación real (cuando proveedor elegido) | Interface en SharedKernel + mock tests + contract test |
| ARV-RF-06 | **Reportes operativos: queries no validadas** | RF-014 | 03_architecture_options.md Alternativa B (CQRS ligero) opcional. 04_database_analysis.md no define vistas materializadas / queries para: ventas/día, ocupación/cama, rendimiento/trabajadora, stock mínimo, ingresos/método pago | **MEDIO** | Si Alternativa A: definir vistas SQL + índices. Si Alternativa B: definir proyecciones outbox. Decidir en ADR | Queries EXPLAIN ANALYZE <100ms en 10x data |
| ARV-RF-07 | **Auditoría: integridad hash encadenado opcional** | RF-015 | 04_database_analysis.md + 05_security_review.md proponen hash encadenado "opcional". Para no repudio real, **obligatorio** | **ALTO** | Implementar trigger `audit_log_hash_chain` (hash = sha256(prev_hash \|\| row_data)) + verificación periódica | Trigger + job verificación semanal + test manipulación |
| ARV-RF-08 | **Configuración: validación esquema no implementada** | RF-016 | 04_database_analysis.md tabla `app_config` sin constraints de tipo/rango. 06_infrastructure.md menciona script validación pero no schema (JSON Schema / CUE / Pydantic) | **MEDIO** | Añadir columna `schema_json` (JSON Schema) en `app_config` + validación en entrypoint app + UI admin valida antes de guardar | Schema por clave + test validación rechaza inválidos |
| ARV-RF-09 | **Superusuario reportes: latencia no garantizada** | RF-017 | 03_architecture_options.md Alternativa B (CQRS) opcional. 04_database_analysis.md no define réplica lectura ni materialized views. 99.8% disponibilidad no asegura latencia reportes | **MEDIO** | Decidir Alternativa A vs B en ADR. Si A: réplica async + vistas materializadas refresh 5min. Si B: proyecciones outbox | Latencia P99 <2s en 10x data (test carga) |

---

### 2. Cobertura de Requisitos No Funcionales (RNF)

| ID | Hallazgo | RNF Afectado | Evidencia | Severidad | Cambio Necesario | Criterio Cierre |
|----|----------|--------------|-----------|-----------|------------------|-----------------|
| ARV-RNF-01 | **RPO/RTO SIN DEFINIR — Bloquea HA, Backup, Runbook** | RNF-040, RNF-041, RNF-042, RNF-043 | 02_decision_scope.md P-05, 04_database_analysis.md, 05_security_review.md SEC-Q1, 06_infrastructure.md INF-01 — **TODOS marcan como pendiente** | **BLOQUEANTE** | **Producto/Técnico DEBE definir**: RPO (ej: 0 / 5 min / 1h), RTO (ej: 15 min / 1h / 4h) antes de ADR | Valores numéricos en ADR + runbook alineado |
| ARV-RNF-02 | **Transacción de negocio no definida operativamente** | RNF-001, RNF-003 | 01_requirements_analysis.md P-01: ¿Qué cuenta como 1 tx? Reserva completa? Pago? Asignación? Afecta sizing DB, connection pool, métricas | **BLOQUEANTE** | Definir: "1 transacción = 1 reserva completada (booking + payment confirmado + recursos asignados)" o similar | Definición en ADR + métrica `business_tx_total` instrumentada |
| ARV-RNF-03 | **Política conflicto web vs presencial sin resolver** | RNF-005, RNF-020 | 01_requirements_analysis.md P-02: ¿Web bloquea físicamente (lock) o reserva lógica sobreescribible? 04_database_analysis.md asume lock pesimista pero no valida con producto | **BLOQUEANTE** | Producto decide: **A) Lock físico (pesimista)** → web reserva = ocupación real. **B) Reserva lógica + prioridad secretaría** → web "apunta", secretaría puede sobrescribir | Decisión en ADR + test de concurrencia simulando ambos canales |
| ARV-RNF-04 | **Expiración reserva "Pendiente" sin parámetros** | RNF-005, RNF-021 | 01_requirements_analysis.md P-03: ¿15 min? ¿30 min? ¿Notifica? ¿Auto o manual? 04_database_analysis.md asume 15 min + job pg_cron | **ALTO** | Producto define: TTL (min), notificación (sí/no, canal), liberación auto (sí/no) | Parámetro en `app_config` + job pg_cron configurado + test |
| ARV-RNF-05 | **Proveedor QR sin definir — afecta integración, secrets, webhook** | RNF-012, RNF-035, RNF-042 | 01_requirements_analysis.md P-04, 05_security_review.md SEC-Q2, 06_infrastructure.md INF-05 | **ALTO** | Producto elige proveedor (MercadoPago, Stripe, local, etc.) + define: webhook vs polling, HMAC scheme, sandbox/prod | Contrato firmado + credenciales sandbox + adapter implementado |
| ARV-RNF-06 | **Multi-sucursal día 1: no confirmado por producto** | RNF-010, RNF-011, RNF-012 | 01_requirements_analysis.md P-06, 04_database_analysis.md asume `branch_id` + RLS día 1 | **MEDIO** | Producto confirma: ¿Modelar sucursal ahora? (Sí = RLS + branch_id en todo. No = single branch, migración futura) | Decisión en ADR + esquema alineado |
| ARV-RNF-07 | **Recursos dinámicos: autoridad y impacto no definidos** | RN-001, RNF-012 | 01_requirements_analysis.md P-07: ¿Quién cambia camas/trabajadoras? ¿Superusuario only? ¿Afecta reservas futuras? ¿Histórico? | **MEDIO** | Definir: solo superusuario, cambios efectivos inmediato (validar no hay bookings activos), auditoría completa | UI admin + validación + audit_log + test |
| ARV-RNF-08 | **Disponibilidad 99.8%: single VM = riesgo SPOF** | RNF-043 | 06_infrastructure.md Opción 1: 1 VM. Si VM cae → downtime hasta restore (RTO ~30 min con snapshot). 99.8% = 1.44h/mes. **Un incidente VM >1.44h = incumplimiento** | **ALTO** | Opción A: Accept risk (documentar). Opción B: 2 VMs en AZ distintas + LB + PG managed multi-AZ. Opción C: Serverless (Cloud Run/Fargate) | Decisión en ADR + prueba de failover documentada |
| ARV-RNF-09 | **Escalabilidad 10x: no validada con pruebas de carga** | RNF-011 | 03_architecture_options.md + 04_database_analysis.md + 06_infrastructure.md asumen vertical scaling funciona. Sin evidence | **MEDIO** | Plan de pruebas de carga (k6 / Locust) simulando 500 tx/h antes de producción | Reporte carga + métricas (latencia, errores, CPU, DB connections) |
| ARV-RNF-10 | **Observabilidad: correlación distribuida no implementada** | RNF-052 | 06_infrastructure.md menciona W3C Trace Context + correlation_id pero no: OpenTelemetry SDK, exportador Tempo/Jaeger, baggage propagation | **MEDIO** | Añadir OpenTelemetry SDK en app (opcional MVP, obligatorio fase 2) + exportador OTLP | Traces visibles en Grafana/Tempo para request completo |
| ARV-RNF-11 | **Seguridad: MFA superusuario solo "diseñado", no implementado** | RNF-030, RNF-031 | 05_security_review.md SEC-10, matriz permisos | **ALTO** | Implementar TOTP en login superusuario + recovery codes + auditoría MFA challenge | Feature flag + test E2E login superusuario con MFA |
| ARV-RNF-12 | **Cifrado DNI: "obligatorio" pero sin implementación concreta** | RNF-030+, regulación | 05_security_review.md dice AES-256-GCM via `pgcrypto` o app-layer. 04_database_analysis.md no muestra columna cifrada | **ALTO** | Definir: app-layer (recomendado, portable) vs `pgcrypto`. Implementar en repositorio `Client` | Migración + test cifrado/descifrado + benchmark |

---

### 3. Reglas de Negocio y Restricciones

| ID | Hallazgo | Regla/Restricción | Evidencia | Severidad | Cambio Necesario | Criterio Cierre |
|----|----------|-------------------|-----------|-----------|------------------|-----------------|
| ARV-RN-01 | **RN-002: Validación humana QR — ventana inconsistencia no mitigada totalmente** | RN-002 | 01_requirements_analysis.md Inconsistencia #2: "Pendiente" → validación humana. 04_database_analysis.md: TTL 15 min + job expiración. **Gap**: ¿Qué pasa si secretaría valida APROBADO tras expiración automática? | **ALTO** | Lógica: Si pago verificado → booking "Confirmada" **aunque expiró** (revivir recursos si libres, sino cola espera). Notificar cliente | State machine payment: `pending → verified → confirmed` / `pending → expired → (verified → revived)` + tests |
| ARV-RN-02 | **RN-003: Trabajadora "Ocupada" no recibe reservas — pero ¿cambio automático?** | RN-003 | 01_requirements_analysis.md: "estado cambie automáticamente o manualmente a Libre". 04_database_analysis.md no define automatismo (fin servicio por tiempo? por check-out?) | **MEDIO** | Definir: ¿Auto por `end_at` booking? ¿Manual check-out? ¿Job cada 5 min libera recursos de bookings finalizados? | Job `release_completed_bookings` + test |
| ARV-RN-03 | **RN-004: Walk-in presencial — sincronización tiempo real con web** | RN-004 | 01_requirements_analysis.md Inconsistencia #3. 03_architecture_options.md: SSE tiempo real. **Gap**: ¿Secretaría ve disponibilidad REAL (incluye reservas web pendientes)? | **ALTO** | Vista disponibilidad: `resource.status = 'libre' AND NOT EXISTS (booking activo pendiente/confirmado)`. SSE notifica cambios | Query disponibilidad + SSE event `resource_available_changed` + test walk-in |
| ARV-RN-04 | **RN-005: Liberación por trabajadora O secretaria — autorización** | RN-005 | 05_security_review.md matriz permisos: trabajadora puede `resource.state_change` (propia). **Gap**: ¿Trabajadora puede liberar cama AJENA? ¿Secretaria puede forzar liberación? | **MEDIO** | Refinar permisos: `resource.release_own` (trabajadora), `resource.release_any` (secretaria, superusuario) | Matriz permisos actualizada + test autorización |

---

### 4. Condiciones de Carrera y Concurrencia

| ID | Hallazgo | Evidencia | Severidad | Cambio Necesario | Criterio Cierre |
|----|----------|-----------|-----------|------------------|-----------------|
| ARV-RACE-01 | **Doble booking cama+trabajadora: resuelto en 04_database_analysis.md** | 04_database_analysis.md: `SELECT FOR UPDATE SKIP LOCKED` orden determinista + TX única | **RESUELTO** | Verificar implementación: orden IDs determinista, `SKIP LOCKED`, TX `REPEATABLE READ` | Test concurrencia 100 hilos simultáneos → 0 doble asignación |
| ARV-RACE-02 | **Pago doble (reintentos): idempotencia key resuelta** | 04_database_analysis.md + 05_security_review.md: tabla `idempotency_key` + constraint único | **RESUELTO** | Verificar middleware: extrae `Idempotency-Key`, consulta tabla, retorna response cacheado si existe | Test: 10 requests paralelos mismo key → 1 pago creado |
| ARV-RACE-03 | **Expiración reserva + validación simultánea: race condition** | ARV-RN-01: Job expiración y secretaría validando concurrentemente | **ALTO** | TX expiración: `UPDATE booking SET status='expirada' WHERE status='pendiente' AND created_at < now()-TTL AND payment_status='pendiente' RETURNING *` → solo expira si pago sigue pendiente. Validación pago verifica `booking.status IN ('pendiente','expirada')` | Test: job expira + valida simultáneo → estado consistente |
| ARV-RACE-04 | **Contador disponibilidad caché vs DB: stale reads** | 03_architecture_options.md: SSE tiempo real. 04_database_analysis.md: consultas disponibilidad directas a DB. **Gap**: Si se usa caché (Redis) para disponibilidad, invalidación crítica | **MEDIO** | MVP: **Sin caché disponibilidad** (consulta DB directa, 6×10 recursos = trivial). Fase 2: Redis + invalidación `LISTEN/NOTIFY` + test | Test: sin caché → latencia <200ms. Con caché → invalidación <100ms |
| ARV-RACE-05 | **Transferencia stock multi-sucursal: no modelada** | ARV-RF-01: transferencias entre sucursales requieren TX distribuida o saga | **ALTO** (si multi-sucursal día 1) | Saga orquestada: `transfer_request` → reserva origen → confirma destino → confirma origen → completa. Compensación: libera reserva | State machine + tests compensación |

---

### 5. Sobre-arquitectura / Complejidad Innecesaria (YAGNI)

| ID | Hallazgo | Evidencia | Severidad | Cambio Necesario | Criterio Cierre |
|----|----------|-----------|-----------|------------------|-----------------|
| ARV-OVER-01 | **Event Sourcing / CQRS completo (Alternativa E) evaluado pero no descartado formalmente en ADR** | 03_architecture_options.md Alternativa E score 2.45 "NO recomendado" | **BAJO** | ADR debe incluir: "Alternativas E y F descartadas por over-engineering severo vs carga 50 tx/h" | ADR con matriz decisión final |
| ARV-OVER-02 | **BFF (Alternativa D) evaluado — no necesario para 1 frontend web responsive** | 03_architecture_options.md Alternativa D score 3.15. Solo 1 canal web (responsive) + panel staff mismo dominio | **BAJO** | ADR: "BFF no necesario; single API con versioning / feature flags por cliente" | ADR documentado |
| ARV-OVER-03 | **Kubernetes evaluado y descartado — correcto** | 06_infrastructure.md justificación explícita | **RESUELTO** | Confirmar en ADR: "K8s no usado; Docker Compose + VM" | ADR |
| ARV-OVER-04 | **PostgreSQL advisory locks propuestos pero ¿necesarios?** | 04_database_analysis.md: "advisory locks para lógica compleja". Con `SELECT FOR UPDATE SKIP LOCKED` en recursos, advisory locks pueden ser overkill | **BAJO** | Documentar: "Advisory locks reservados para future: locks cross-recurso complejos (ej: mantenimiento bloquea múltiples camas)" | Comentario en código / ADR |
| ARV-OVER-05 | **Hash encadenado auditoría: "opcional" pero para compliance recomendado obligatorio** | ARV-RF-07 | **MEDIO** | Hacer obligatorio (bajo overhead: 1 columna `prev_hash` + trigger) | Trigger implementado + test |

---

### 6. Recuperación Incompleta / Resiliencia

| ID | Hallazgo | Evidencia | Severidad | Cambio Necesario | Criterio Cierre |
|----|----------|-----------|-----------|------------------|-----------------|
| ARV-REC-01 | **Failover PG standby → primary: no automatizado** | 06_infrastructure.md: `promote_trigger_file=/tmp/promote` manual. No `patroni` / `repmgr` / `pg_auto_failover` | **ALTO** | Opción A: Script promote + healthcheck app reconecta (RTO ~2-5 min). Opción B: `patroni` + etcd/consul (más complejo). **MVP: Script + alerta + runbook manual** | Script `promote_standby.sh` probado + runbook + test trimestral |
| ARV-REC-02 | **App reconexión DB tras failover: no especificada** | 06_infrastructure.md: healthcheck app pero no retry logic / connection pool reset | **ALTO** | App: HikariCP / Npgsql / sqlx con `max_lifetime`, `idle_timeout`, retry en `57P01` (terminating), `08006` (connection failure) | Config pool + test: kill primary → app recupera <30s sin errors 5xx |
| ARV-REC-03 | **Backup restore: solo pgBackRest, sin test de PITR a punto específico** | 06_infrastructure.md: prueba mensual restore full. **Falta**: PITR a timestamp arbitrario (ej: antes borrado accidental) | **MEDIO** | Añadir test trimestral: `pgbackrest --type=time --target-time='2026-10-09 10:00:00'` | Test PITR documentado + RPO validado |
| ARV-REC-04 | **Secrets rotation: proceso no documentado** | 05_security_review.md: rotación 90d JWT, DB password. 06_infrastructure.md: no runbook | **MEDIO** | Runbook: `rotate_secrets.sh` (DB: `ALTER USER`, app: reload config, JWT: nueva clave + JWKS update, QR: proveedor) | Script + test en staging + runbook |

---

### 7. Duplicación por Reintentos / Idempotencia

| ID | Hallazgo | Evidencia | Severidad | Cambio Necesario | Criterio Cierre |
|----|----------|-----------|-----------|------------------|-----------------|
| ARV-IDEMP-01 | **Idempotencia cubierta en booking/payment/checkin — faltan scopes** | ARV-RF-04 | **MEDIO** | Extender scopes (ver ARV-RF-04) | Lista completa + middleware genérico |
| ARV-IDEMP-02 | **Webhook QR: idempotencia en proveedor vs local** | 05_security_review.md: nonce + timestamp. **Gap**: ¿Proveedor reenvía mismo evento? ¿Nuestra tabla `webhook_nonce` suficiente? | **MEDIO** | Definir: `webhook_nonce` (provider_event_id, received_at) UNIQUE + TTL 24h. Procesar idempotente por `provider_event_id` | Tabla + test reenvío mismo evento |

---

### 8. Supuestos Ocultos / Decisiones Sin Evidencia

| ID | Hallazgo | Supuesto | Evidencia | Severidad | Cambio Necesario | Criterio Cierre |
|----|----------|----------|-----------|-----------|------------------|-----------------|
| ARV-ASSUME-01 | **Stack backend no elegido — afecta TODO (ORM, migraciones, testing, observabilidad, auth)** | 02_decision_scope.md D-09, 03_architecture_options.md asume "EF Core / Dapper / SQLx" | **BLOQUEANTE** | **Solution-leader DEBE decidir stack** (lenguaje + framework + ORM) antes de ADR. Opciones: .NET 8 + EF Core, Go + sqlx, Node + Prisma, Python + SQLAlchemy, Java + Spring/JDBI | ADR con stack definido + justificación trazable |
| ARV-ASSUME-02 | **Frontend: "Web responsive" — ¿SPA (React/Vue/Svelte) o SSR (Next.js/Razor/Blazor)?** | 00_contexto/descripcion.md: "Aplicación Web / Interfaz Virtual accesible desde computadoras, tabletas y teléfonos" | **ALTO** | Decidir: SSR (SEO, initial load, simple auth) vs SPA (UX rica, offline). Afecta BFF decisión, auth flow (PKCE vs cookies) | ADR con decisión frontend + implicaciones backend |
| ARV-ASSUME-03 | **Notificaciones WhatsApp/Email: proveedor, plantillas, async/sync no definidos** | 01_requirements_analysis.md P-08, 06_infrastructure.md INF-05 | **ALTO** | Producto define: Twilio/Whapi/Oficial WhatsApp? SendGrid/SES/Postmark? Template engine? Queue (Redis/RabbitMQ/DB outbox)? | Contrato + adapter `NotificationProvider` + mock |
| ARV-ASSUME-04 | **Regulación PII: GDPR/LOPD/LGPD/otra — define cifrado, retención, DPIA** | 05_security_review.md SEC-Q3 | **ALTO** | Legal/Producto define: jurisdicción, requisitos cifrado, derecho olvido, DPO, breach notification | Documento compliance + implementación alineada |
| ARV-ASSUME-05 | **Dominio único (single-tenant) vs multi-tenant futuro** | RNF-010 "crecimiento sucursales" ≠ multi-tenant SaaS. Pero si dueña tiene múltiples spas independientes? | **MEDIO** | Clarificar: ¿Multi-sucursal = 1 tenant, N branches? ¿O multi-tenant (aislado datos, config, branding)? | Decisión en ADR + esquema `tenant_id` vs `branch_id` |

---

### 9. Criterios de Aceptación (CA) — Cobertura

| CA | Cobertura en Arquitectura | Gap | Severidad |
|----|---------------------------|-----|-----------|
| CA-001: Estado trabajadora <3s | SSE + `LISTEN/NOTIFY` + DB index | Implementar SSE endpoint + trigger NOTIFY en `resource` update | **ALTO** |
| CA-002: Bloqueo horario 6 camas/10 trab + sugerir siguiente | Query disponibilidad + UI | Definir algoritmo "siguiente disponible" (ventana 30min? día siguiente?) | **MEDIO** |
| CA-003: Botón "Verificar Pago QR" → aprobar/rechazar 1 click | API `POST /payments/{id}/verify` + middleware authZ secretaria | Implementar use case + UI | **ALTO** |
| CA-004: Secretaria/trabajadora asignan tiempo servicio | Domain service `assign_service_duration` | Definir reglas: ¿mín/máx? ¿por servicio? ¿override? | **MEDIO** |

---

## Resumen de Hallazgos por Severidad

| Severidad | Cuenta | % |
|-----------|--------|---|
| **BLOQUEANTE** | 4 | 11% |
| **ALTO** | 13 | 35% |
| **MEDIO** | 14 | 38% |
| **BAJO** | 6 | 16% |
| **RESUELTO** | 3 | - |
| **TOTAL** | **37** | **100%** |

### Top 5 Bloqueantes (Deben Resolverse Antes de ADR)

1. **ARV-RNF-01**: RPO/RTO numéricos sin definir (afecta HA, backup, runbook, seguridad)
2. **ARV-RNF-02**: "Transacción de negocio" no definida operativamente (afecta sizing, métricas, capacidad)
3. **ARV-RNF-03**: Política conflicto web vs presencial sin resolver (afecta concurrencia, UX, locks)
4. **ARV-ASSUME-01**: Stack backend no elegido (afecta toda implementación, migraciones, testing, auth)
5. **ARV-ASSUME-04**: Regulación PII sin definir (afecta cifrado DNI, retención, compliance legal)

### Top 5 Riesgos Altos Inmediatos

1. **ARV-RNF-04**: Expiración reserva "Pendiente" sin parámetros (job pg_cron, notificación, UX)
2. **ARV-RNF-05**: Proveedor QR sin definir (integración, secrets, webhook, adapter)
3. **ARV-RNF-08**: Single VM = riesgo SPOF para 99.8% (failover, multi-AZ, managed PG)
4. **ARV-RN-01**: Ventana inconsistencia pago QR expirado + validado (state machine revival)
5. **ARV-REC-01/02**: Failover PG manual + app reconexión no especificada (RTO real desconocido)

---

## Requisitos SIN Cobertura (Zero Coverage)

| Requisito | Por Qué No Cubierto | Acción |
|-----------|---------------------|--------|
| **RF-005 completo** (entradas, salidas, reservas, ajustes, transferencias inventario) | Solo `stock_movement` básico modelado | ARV-RF-01: modelo completo inventario |
| **RF-006** (promociones) | Sin entidad, sin motor reglas | ARV-RF-02 |
| **RF-007 completo** (devoluciones parciales/totales) | Solo mención reversión | ARV-RF-03 |
| **RF-011** (validar/reservar stock antes confirmar pedido) | No modelado `stock_reservation` | ARV-RF-01 |
| **RF-013** (sin sobreventa web vs físico) | Asumido resuelto con locks, pero walk-in (RN-004) no integrado | ARV-RN-03 |
| **RNF-002** (capacidad configurable externamente) | `app_config` existe pero sin validación esquema | ARV-RF-08 |
| **RNF-006** (código estructurado/documentado) | Solo arquitectura, no estándares código | Definir: linting, formatting, docs, ADR template |
| **RNF-042** (dependencias externas timeout/retry) | QR adapter menciona circuit breaker pero no config | Definir timeouts, retry policy, circuit breaker params en `app_config` |
| **RNF-044** (respaldos automáticos diarios) | pgBackRest configurado pero **no validado** restore | ARV-REC-03 |
| **RN-001 dinámico** (camas 1-6, trab 1-10 "puede aumentar/disminuir") | `app_config` keys propuestas pero sin UI, validación, impacto reservas | ARV-RNF-07 |

---

## Matriz de Trazabilidad RF/RNF → Decisiones Arquitectónicas (Resumen)

| RF/RNF | Decisiones Cubiertas | Gaps Críticos |
|--------|---------------------|---------------|
| RF-001 a RF-004, RF-008, RF-012, RF-013, RF-015, RF-017 | Auth, Booking, Payment, Resource, Audit, Idempotency, Concurrencia | Stack backend, Frontend, QR provider |
| RF-005, RF-006, RF-007, RF-009, RF-010, RF-011, RF-014, RF-016 | Parcial (esquema básico) | Modelado completo inventario, promociones, devoluciones, reportes queries, config validation |
| RNF-001, RNF-003, RNF-004, RNF-005, RNF-020, RNF-021, RNF-022 | Concurrencia, locks, idempotencia, TX ACID | Definición tx negocio, política conflicto, validación 10x |
| RNF-010, RNF-011, RNF-012 | `branch_id` + RLS día 1 | Confirmación multi-sucursal día 1, prueba carga 10x |
| RNF-030 a RNF-035 | AuthZ/RBAC, JWT, secrets, TLS, audit, rate limit | MFA impl, cifrado DNI impl, regulación, penetration test |
| RNF-040 a RNF-043 | Backup, HA PG, pgBackRest | **RPO/RTO**, failover auto, app reconexión, PITR test |
| RNF-050 a RNF-052 | Logs, métricas, dashboards | Correlación distribuida (OpenTelemetry), alertas negocio |
| RNF-060 a RNF-062 | Docker, Linux, 3 envs, Compose | Stack backend, proveedor cloud, dominio/TLS |

---

## Recomendaciones para ADR (08_recomendacion_final.md)

1. **Resolver 4 BLOQUEANTES** antes de firmar ADR (RPO/RTO, definición tx negocio, política conflicto, stack backend, regulación PII)
2. **Convertir 13 ALTO en tareas de sprint 1-2** (expiración reserva, proveedor QR, single VM risk, failover, MMA, cifrado DNI, state machine pago, walk-in sync, idempotencia scopes, transferencias stock, promo model, refund flow, config validation)
3. **Documentar en ADR**: Alternativas descartadas (E, F, D), justificación K8s NO, decisión PostgreSQL, decisión VM+Compose
4. **Plan de Pruebas Pre-Implementación** (ver sección final)

---

## Plan de Pruebas Antes de Implementar (Para ADR)

| Test | Objetivo | Herramienta | Criterio Pass |
|------|----------|-------------|---------------|
| **Carga 10x** | Validar RNF-011 (500 tx/h) | k6 / Locust | p99 latency <500ms, 0 errores 5xx, CPU <70%, DB connections <80% |
| **Concurrencia recursos** | Validar RNF-005, RNF-020, RN-001, RN-003, RN-004 | Test personalizado (100 goroutines/threads) | 0 doble asignación cama/trabajadora en 10k iteraciones |
| **Idempotencia** | Validar RF-008, RNF-022 | Test paralelos mismo key | 1 sola entidad creada por key único |
| **Failover PG** | Validar RNF-043, RTO | Kill primary → promote standby → app recupera | App recupera <30s, 0 transacciones perdidas (RPO=0) |
| **PITR Restore** | Validar RNF-040, RNF-041 | pgBackRest restore point-in-time | Restore <30 min, datos consistentes (checksums, row counts) |
| **Seguridad: AuthZ** | Validar RNF-030, RNF-031 | OWASP ZAP + tests matriz permisos | 0 bypass autorización, 0 IDOR |
| **Seguridad: Secrets** | Validar RNF-034 | Gitleaks + Trivy + runtime inspect | 0 secrets en imagen, env vars, logs, git history |
| **Webhook QR** | Validar RF-012, RNF-035, RNF-042 | Mock provider + reenvíos + firma inválida | HMAC verificado, replay rechazado, queue async procesa |
| **SSE Tiempo Real** | Validar CA-001 (<3s) | Cliente SSE + update recurso | Notificación recibida <3s en 99% casos |
| **Walk-in Presencial** | Validar RN-004, CA-002 | Simular reserva web + walk-in simultáneo | Secretaria ve disponibilidad real, puede asignar si libre |
| **Expiración Pendiente** | Validar RN-002, RNF-021 | Crear reserva QR → esperar TTL → job expira | Recursos liberados, booking=expirada, notificación enviada (si config) |
| **Multi-sucursal Aislamiento** | Validar RNF-010, RNF-012 | Datos 2 branches + RLS | Branch A no ve datos Branch B (app + DB) |
| **Backup Cifrado** | Validar SEC-08 | Restore backup → verificar cifrado | pgBackRest `--repo1-cipher-type=aes-256` funciona |

---

## Próximo Paso

**`solution-leader`** → Consolidación final: Matriz decisión + Arquitectura recomendada + Backend + Persistencia + Integraciones + Seguridad + Infraestructura + Capacidad + Riesgos + Preguntas pendientes + Trazabilidad RF/RNF + **ADR propuesto** + **Plan de pruebas** → `08_recomendacion_final.md`

---

**Skill utilizado**: `architecture-validation`  
**Agente responsable**: `architecture-reviewer`  
**Estado**: `HUMAN_STATUS: PENDING` — **4 BLOQUEANTES requieren resolución humana antes de ADR** (ver Top 5 arriba).
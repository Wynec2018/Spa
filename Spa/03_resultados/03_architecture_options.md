# Alternativas Arquitectónicas — Spa Cielo

Fecha: 2026-10-09  
Fuente: `03_resultados/02_decision_scope.md`, `03_resultados/01_requirements_analysis.md`

---

## Criterios de Comparación

| Criterio | Peso | Descripción |
|----------|------|-------------|
| **Complejidad** | 25% | Curva de aprendizaje, cognitiva, operativa |
| **Escalabilidad** | 20% | Crecimiento carga, sucursales, recursos |
| **Consistencia** | 20% | Integridad recursos finitos, pagos, auditoría |
| **Costo Operativo** | 20% | Infraestructura, licencias, personal ops |
| **Mantenibilidad** | 15% | Cambios, testing, debugging, onboarding |

Puntuación: 1 (malo) a 5 (excelente) por criterio.

---

## Alternativa A: Monolito Modular (Baseline Recomendada)

**Estilo**: Monolito único deployable, módulos internos por dominio (users, bookings, payments, resources, inventory, reports). Clean Architecture dentro del monolito.

### Componentes
- **API Layer**: REST + WebSockets/SSE (Controller/Handlers)
- **Application Layer**: Use cases / Application Services (orchestración)
- **Domain Layer**: Entidades, Value Objects, Domain Services, Domain Events
- **Infrastructure Layer**: Repositorios (EF Core / Dapper / SQLx), QR Adapter, Notificaciones, Auth
- **Shared Kernel**: Config, Exceptions, Extensions

### Decisiones Clave
| Decisión | Elección | Justificación |
|----------|----------|---------------|
| Concurrencia recursos | Lock pesimista (SELECT FOR UPDATE) + advisory locks PG | Simple, ACID nativo, 6×10 recursos = baja contención |
| Pagos QR | Saga orquestada (state machine) en Application Layer | Visible, testeable, compensación explícita (liberar recursos) |
| Idempotencia | Tokens idempotencia (header/clave) + tabla deduplicación | Estándar, funciona HTTP + WebSocket |
| Tiempo real | SSE (Server-Sent Events) | Unidireccional (servidor→cliente), simple, HTTP/2, 10 usuarios concurrentes |
| Auth | JWT stateless + refresh tokens (rotación) + roles en claim | Sin session store, portable, roles en token |
| Config | Tabla `app_config` en DB + cache en memoria (refresco TTL) | Sin dependencias externas, admin por superusuario |
| Multi-sucursal | Columna `branch_id` en todas las entidades + RLS (Row Level Security) | Día 1, aislamiento datos, migración trivial |

### Puntuación
| Criterio | Score | Notas |
|----------|-------|-------|
| Complejidad | 5 | Un solo deploy, debugging trivial, onboarding rápido |
| Escalabilidad | 3 | Vertical hasta ~500 tx/h; horizontal requiere extracción módulos |
| Consistencia | 5 | ACID nativo, transacciones locales, auditoría simple |
| Costo Operativo | 5 | 1 contenedor, 1 DB, 0 orquestación compleja |
| Mantenibilidad | 4 | Módulos claros, testing unitario fácil, refactoring seguro |
| **Total Ponderado** | **4.45** | |

### Riesgos
- Límite escalabilidad vertical (mitigar: módulos listos para extracción)
- Single point of failure (mitigar: HA activo-pasivo DB + app)

---

## Alternativa B: Monolito Modular + CQRS Ligero (Lectura Separada)

**Estilo**: Alternativa A + modelo de lectura separado (Materialized Views / Proyecciones) para reportes y dashboards tiempo real.

### Diferencias vs A
- **Escritura**: Domain model completo (igual que A)
- **Lectura**: Proyecciones asíncronas (outbox pattern) → vistas materializadas / tabla `read_model`
- **Reportes superusuario**: Consultan `read_model` (latencia <100ms, no bloquean escritura)
- **Tiempo real**: SSE notifica cambios → proyección actualiza → cliente refresca

### Decisiones Adicionales
| Decisión | Elección |
|----------|----------|
| Sincronización escritura→lectura | Transactional Outbox (tabla `outbox_events` + background worker) |
| Proyecciones | 1 worker por proyección (idempotente, ordenado por `sequence`) |
| Consistencia lectura | Eventual (ms-segundos), aceptable para reportes |

### Puntuación
| Criterio | Score | Notas |
|----------|-------|-------|
| Complejidad | 3 | Outbox + workers + proyecciones = más piezas móviles |
| Escalabilidad | 4 | Lectura escala independientemente; reportes no impactan escritura |
| Consistencia | 4 | Escritura fuerte; lectura eventual (documentar en UX) |
| Costo Operativo | 4 | 1-2 workers extra; misma DB; bajo overhead |
| Mantenibilidad | 3 | Dual model requiere disciplina; testing de proyecciones |
| **Total Ponderado** | **3.65** | |

### Cuándo elegir
- Si reportes superusuario (RF-017) requieren agregaciones complejas sobre datos vivos
- Si se anticipa crecimiento rápido de consultas analíticas

---

## Alternativa C: Arquitectura Modular con Módulos Desplegables Independientemente

**Estilo**: Monolito físico, pero módulos compilados como librerías separadas con contratos definidos (interfaces). Preparado para extraer a microservicios si necesario.

### Estructura
```
src/
├── Spa.Host/                 # Entry point, DI, hosting
├── Spa.Modules.Users/        # Librería: users, roles, auth
├── Spa.Modules.Bookings/     # Librería: reservas, disponibilidad
├── Spa.Modules.Payments/     # Librería: pagos, QR, idempotencia
├── Spa.Modules.Resources/    # Librería: camas, trabajadoras, estados
├── Spa.Modules.Inventory/    # Librería: stock, movimientos
├── Spa.Modules.Reports/      # Librería: proyecciones, dashboards
└── Spa.SharedKernel/         # Contratos, eventos, exceptions
```

### Decisiones Clave
| Decisión | Elección |
|----------|----------|
| Comunicación módulos | In-process (DI), Domain Events publicados en bus interno (MediatR / custom) |
| Transacciones cross-módulo | `TransactionScope` / `DbTransaction` compartido (misma DB) |
| Extracción futura | Módulo → Servicio: añadir API Gateway, message broker, DB propia |
| Tiempo real | Módulo `Resources` expone SSE endpoint; `Bookings` suscribe a eventos |

### Puntuación
| Criterio | Score | Notas |
|----------|-------|-------|
| Complejidad | 3 | Límites de módulo estrictos, DI complejo, testing contratos |
| Escalabilidad | 4 | Extracción incremental sin reescritura; cada módulo escalable |
| Consistencia | 5 | Misma DB, transacciones ACID cross-módulo |
| Costo Operativo | 4 | 1 deploy hoy; coste extracción futuro conocido |
| Mantenibilidad | 4 | Límites claros, ownership por módulo, refactoring contenido |
| **Total Ponderado** | **4.00** | |

### Cuándo elegir
- Si hay certeza de crecimiento a multi-equipo o multi-sucursal con autonomía
- Si se quiere "pagar" complejidad ahora para reducir deuda técnica futura

---

## Alternativa D: Backend-for-Frontend (BFF) + Monolito Interno

**Estilo**: API Gateway / BFF ligero (Node.js/Go/.NET minimal) expone API optimizada por cliente (Web App, Secretaría Panel, Trabajadora App, Superusuario Dashboard). Detrás: Monolito Modular (Alternativa A) como servicio interno.

### Componentes
- **BFF Web (Cliente)**: Agrega `Bookings + Payments + Resources` → API fluida para reserva web
- **BFF Staff (Secretaría/Trabajadora)**: Tiempo real, acciones rápidas, estado recursos
- **BFF Admin (Superusuario)**: Reportes, auditoría, configuración
- **Core API (Monolito Modular)**: Lógica de negocio, persistencia, dominio

### Decisiones Clave
| Decisión | Elección |
|----------|----------|
| Comunicación BFF→Core | gRPC (contract-first) o REST interno + shared DTOs |
| Autenticación | BFF valida JWT, re-emite token interno con scopes; Core confía en BFF (zero-trust interno opcional) |
| Tiempo real | BFF Staff mantiene WebSockets/SSE; Core emite eventos → BFF propaga |
| Rate limiting / WAF | En BFF (edge) |

### Puntuación
| Criterio | Score | Notas |
|----------|-------|-------|
| Complejidad | 2 | 4 deployables, contratos, versionado, latencia extra hop |
| Escalabilidad | 4 | BFFs escalan independientemente; Core escala vertical |
| Consistencia | 5 | Core mantiene ACID; BFF sin lógica de negocio |
| Costo Operativo | 2 | 4 contenedores + LB + infra red; ops overhead |
| Mantenibilidad | 3 | Cambios cross-cutting tocan múltiples BFFs; duplicación DTOs |
| **Total Ponderado** | **3.15** | |

### Cuándo elegir
- Si UX difiere drásticamente por cliente (web vs móvil nativo vs kiosko)
- Si hay equipos separados por frontend

---

## Alternativa E: Arquitectura Orientada a Eventos (Event-Driven) con Event Sourcing

**Estilo**: Event Sourcing + CQRS completo. Estado = secuencia de eventos. Proyecciones para lectura. Kafka / RabbitMQ / EventStoreDB como event store.

### Componentes
- **Command Side**: Aggregates (Booking, Resource, Payment) emiten eventos
- **Event Store**: Almacena eventos inmutables (EventStoreDB / PostgreSQL `event_store` table)
- **Query Side**: Proyecciones asíncronas → Read Models (PostgreSQL / MongoDB / Elasticsearch)
- **Process Managers**: Sagas para pagos, asignaciones cross-recurso

### Decisiones Clave
| Decisión | Elección |
|----------|----------|
| Event Store | PostgreSQL (tabla `events` con JSONB) — evita dependencia externa |
| Proyecciones | Workers consumiendo por `stream_id` / `correlation_id` |
| Snapshotting | Cada N eventos para performance aggregates grandes |
| Tiempo real | Proyección `ResourceState` → SSE/WebSocket desde read model |
| Auditoría | Nativa (eventos = log inmutable) |

### Puntuación
| Criterio | Score | Notas |
|----------|-------|-------|
| Complejidad | 1 | Curva alta: event modeling, versionado eventos, debugging temporal |
| Escalabilidad | 5 | Lectura/escritura separadas; replay para nuevas proyecciones |
| Consistencia | 3 | Eventual por defecto; fuerte requiere sync projections (complejo) |
| Costo Operativo | 2 | Event store, workers, replay, monitoring, schema evolution |
| Mantenibilidad | 2 | Cambios de esquema eventos = migración compleja; testing difícil |
| **Total Ponderado** | **2.45** | |

### Cuándo elegir
- Si auditoría temporal completa es requisito legal/regulatorio (no es el caso)
- Si dominio es inherentemente event-centric (trading, IoT, logistics)
- **NO recomendado para Spa Cielo** — over-engineering severo

---

## Alternativa F: Microservicios (Solo para Comparación)

**Estilo**: 6-7 servicios independientes (Users, Bookings, Payments, Resources, Inventory, Reports, Notifications), cada uno con DB propia, comunicación async (message broker) + sync (API Gateway).

### Puntuación
| Criterio | Score | Notas |
|----------|-------|-------|
| Complejidad | 1 | Distributed systems problems: red, consistencia, debugging, testing |
| Escalabilidad | 5 | Cada servicio escala independientemente |
| Consistencia | 2 | Saga distribuida obligatoria; 2PC no viable; eventual consistency |
| Costo Operativo | 1 | 7+ contenedores, message broker, service mesh, observabilidad distribuida |
| Mantenibilidad | 2 | Versionado APIs, breaking changes, ownership difuso, onboarding lento |
| **Total Ponderado** | **1.95** | |

### Veredicto
**Descartada por defecto**. Solo viable si:
- Equipo >15 desarrolladores en 3+ squads autónomos
- Requisito de deploy independiente por dominio de negocio
- Escalamiento real e independiente por servicio (no es el caso: 50 tx/h)

---

## Matriz Resumen

| Alternativa | Complejidad (25%) | Escalabilidad (20%) | Consistencia (20%) | Costo Op (20%) | Mantenibilidad (15%) | **Total** | Veredicto |
|-------------|-------------------|---------------------|--------------------|----------------|----------------------|-----------|-----------|
| **A: Monolito Modular** | **5** | 3 | **5** | **5** | 4 | **4.45** | ✅ **Recomendada** |
| B: Monolito + CQRS Ligero | 3 | 4 | 4 | 4 | 3 | 3.65 | Si reportes complejos |
| C: Modular Desplegable | 3 | 4 | 5 | 4 | 4 | 4.00 | Si certeza crecimiento org |
| D: BFF + Monolito | 2 | 4 | 5 | 2 | 3 | 3.15 | Si UX muy diferenciada |
| E: Event Sourcing + CQRS | 1 | 5 | 3 | 2 | 2 | 2.45 | ❌ Over-engineering |
| F: Microservicios | 1 | 5 | 2 | 1 | 2 | 1.95 | ❌ No aplicar |

---

## Recomendación del Arquitecto

**Alternativa A (Monolito Modular)** como baseline, con **preparación para C (Módulos Desplegables)**:
- Estructura de carpetas por módulo desde día 1 (Alternativa C)
- Domain Events internos para desacoplar módulos
- Sin extraer a servicios hasta necesidad real (YAGNI)
- CQRS ligero (Alternativa B) solo si reportes lo exigen tras MVP

**Justificación trazable**:
- D1 (concurrencia recursos) → ACID monolito + locks PG (score 5 consistencia)
- D2 (pagos saga) → Orquestación in-process simple (score 5 consistencia)
- D4 (tiempo real 10 usuarios) → SSE en monolito (score 5 complejidad)
- C-05 (equipo pequeño, 30 min training) → Complejidad 5, Costo 5
- RNF-060 a RNF-062 → 1 contenedor Docker, portable
- RNF-010/011/012 → `branch_id` + RLS prepara multi-sucursal sin rediseño

---

## Próximos Pasos

1. **`database-specialist`** → Evaluar motores y estrategia persistencia (04_database_analysis.md)
2. **`security-reviewer`** → Revisar seguridad integral (05_security_review.md)
3. **`devops-architect`** → Evaluar infraestructura, K8s sí/no (06_infrastructure.md)

---

**Skill utilizado**: `architecture-design`  
**Agente responsable**: `architect`
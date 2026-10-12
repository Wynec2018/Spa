# Alcance de Decisiones Arquitectónicas — Spa Cielo

Fecha: 2026-10-09  
Fuente: `03_resultados/01_requirements_analysis.md`

---

## Decisiones que Deben Tomarse

| # | Decisión | Driver(es) | Impacto | Prioridad |
|---|----------|------------|---------|-----------|
| D-01 | **Mecanismo de concurrencia para recursos finitos (camas/trabajadoras)** | D1, RNF-005, RNF-020 | Crítico: evita doble asignación | Bloqueante |
| D-02 | **Modelo transaccional para pagos QR + validación humana** | D2, RNF-021, RN-002 | Crítico: integridad dinero + recursos | Bloqueante |
| D-03 | **Estrategia de idempotencia en reservas/pagos** | D3, RF-008, RNF-022 | Alto: tolerancia a reintentos | Alta |
| D-04 | **Arquitectura de tiempo real para estado de recursos** | D4, CA-001 (<3s) | Alto: UX secretaría/trabajadoras | Alta |
| D-05 | **Modelo de datos multi-sucursal desde día 1 vs migración futura** | D8, RNF-010, RNF-011, RNF-012 | Medio: escalabilidad horizontal | Media |
| D-06 | **Patrón de integración QR (polling vs webhook + anti-correlación)** | D2, RF-012, Restricciones | Medio: complejidad integración | Media |
| D-07 | **Estrategia de autenticación/autorización (JWT, sessions, OAuth2, OIDC)** | D5, RNF-030 a RNF-035 | Alto: seguridad | Alta |
| D-08 | **Configuración externalizada: DB tabla vs archivo vs Consul/etcd** | D6, RF-016 | Medio: operabilidad | Media |
| D-09 | **Stack tecnológico backend (lenguaje, framework, ORM)** | D1-D9, RNF-060 a RNF-062 | Alto: todo el desarrollo | Alta |
| D-10 | **Motor de base de datos (relacional vs documento vs híbrido)** | D1, D2, D8, RNF-020, RNF-021 | Crítico: consistencia, auditoría | Bloqueante |
| D-11 | **Estrategia de despliegue: VM, contenedores, K8s, serverless** | D7, RNF-060 a RNF-062 | Alto: operaciones | Alta |
| D-12 | **Observabilidad: logging, métricas, tracing (stack propio vs managed)** | D9, RNF-050 a RNF-052 | Medio: operabilidad | Media |

---

## Especialistas Necesarios

| Rol | Responsabilidad en esta fase | Decisiones involucradas |
|-----|------------------------------|------------------------|
| **Arquitecto de Software** | Diseñar alternativas, comparar trade-offs, definir estilo arquitectónico | D-01 a D-12 |
| **Especialista en Bases de Datos** | Evaluar motores, modelado, concurrencia, auditoría, backup | D-01, D-02, D-03, D-10 |
| **Experto en Seguridad** | AuthZ/AuthN, secretos, APIs, fraude, auditoría, compliance | D-07, D-08 |
| **DevOps / Ingeniero de Plataforma** | Contenedores, CI/CD, observabilidad, despliegue, K8s sí/no | D-11, D-12 |
| **Líder de Producto/Dominio** | Resolver preguntas abiertas, validar políticas de negocio | Preguntas pendientes #1-9 |

---

## Alternativas que Deben Compararse (para architect)

### Estilo Arquitectónico
1. **Monolito modular** (recomendado como baseline por RNF-001 baja, equipo pequeño)
2. **Monolito modular + módulos desacoplados internamente** (preparado para extracción futura)
3. **Arquitectura por capas limpias (Clean Architecture) en monolito**
4. **Microservicios** (solo si justificación fuerte: multi-equipo, escalamiento independiente real)

### Concurrencia Recursos Finitos (D-01)
1. **Lock pesimista a nivel fila (SELECT FOR UPDATE)** — simple, fuerte consistencia
2. **Lock optimista con versión/timestamp** — mejor throughput, retry en conflicto
3. **Cola de comandos (event sourcing / outbox)** — consistencia eventual, complejidad alta
4. **Redis + Lua scripts para atomicidad** — baja latencia, dependencia externa

### Transaccionalidad Pagos (D-02)
1. **Transacción ACID única (reserva + pago + asignación)** — simple, bloquea recursos
2. **Saga coreografiada (eventos)** — desacoplado, complejidad compensación
3. **Saga orquestada (state machine)** — control central, visible, testing fácil
4. **Two-phase commit (2PC)** — fuerte consistencia, no recomendado para externo

### Tiempo Real (D-04)
1. **WebSockets (Socket.io, ws)** — bidireccional, estado servidor
2. **Server-Sent Events (SSE)** — unidireccional, simple, HTTP/2
3. **Polling corto (2-5s)** — simple, mayor carga, latencia aceptable para 10 usuarios
4. **SignalR / Ably / Pusher (managed)** — offload complejidad, costo

### Base de Datos (D-10)
1. **PostgreSQL** — ACID, JSONB, advisory locks, auditoría nativa, maduro
2. **MySQL 8** — ACID, CTE, window functions, JSON, amplio soporte
3. **SQL Server** — ACID, temporal tables, change tracking, enterprise
4. **MongoDB + transacciones multi-documento** — flexible, consistencia eventual por defecto
5. **CockroachDB / YugabyteDB** — SQL distribuido, fuerte consistencia, multi-región

### Despliegue (D-11)
1. **VMs + Docker Compose** — simple, control total, bajo costo
2. **Kubernetes (managed: EKS/GKE/AKS)** — escalabilidad, complejidad operativa alta
3. **Container Apps / Cloud Run / Fargate** — serverless containers, pay-per-use
4. **PaaS (Render, Railway, Fly.io)** — menor control, deploy simple

---

## Restricciones Críticas

| # | Restricción | Fuente | Efecto en decisiones |
|---|-------------|--------|---------------------|
| C-01 | **6 camas × 10 trabajadoras = recursos finitos y pequeños** | RN-001, Alcance | Concurrencia simple viable; no requiere sharding |
| C-02 | **Validación humana obligatoria en pagos QR** | RN-002, RF-004 | Transacción no atómica end-to-end; requiere saga/estado |
| C-03 | **Disponibilidad 99.8% (24/7 reservas web)** | RNF-043 | Single-node no aceptable; HA mínimo activo-pasivo |
| C-04 | **Linux, contenedorizable, 3 ambientes separados** | RNF-060 a RNF-062 | Stack debe ser portable; Docker obligatorio |
| C-05 | **Equipo pequeño, capacitación 30 min** | Restricciones org. | Complejidad operativa baja; evitar K8s salvo justificación |
| C-06 | **RPO/RTO sin definir** | RNF-041 null | Bloquea decisión de backup/replicación; resolver antes de D-11 |
| C-07 | **Proveedor QR sin definir** | Restricciones técnicas | Integración debe ser desacoplada (anti-correlación) |
| C-08 | **Costo operativo contenido (spa pequeño)** | Contexto implícito | Evitar over-engineering; managed services si reducen ops |

---

## Preguntas Pendientes (bloqueantes para decisiones)

| # | Pregunta | Decisión(es) bloqueada(s) | Dueño |
|---|----------|---------------------------|-------|
| P-01 | ¿Qué constituye una "transacción de negocio" para 50/hora? | D-01, D-09, D-10 (sizing) | Producto |
| P-02 | ¿Reserva web bloquea recurso físicamente (lock) o reserva lógica sobreescribible? | D-01, D-04 (concurrencia + tiempo real) | Producto |
| P-03 | ¿Timeout expiración reserva "Pendiente"? ¿Liberación automática? ¿Notificación? | D-02, D-03 (pagos + idempotencia) | Producto |
| P-04 | ¿Proveedor QR inicial? ¿Validación automática (webhook) + humana, o solo visualizador? | D-06 (integración QR) | Producto/Técnico |
| P-05 | ¿RPO/RTO numéricos objetivo? | D-10, D-11 (DB + despliegue) | Producto/Técnico |
| P-06 | ¿Multi-sucursal modelada desde día 1 en datos? | D-05, D-10 (modelo datos) | Producto |
| P-07 | ¿Quién autoriza cambio de nº camas/trabajadoras? ¿Afecta reservas futuras? | D-05, D-01 (recursos dinámicos) | Producto |
| P-08 | ¿Notificaciones WhatsApp/Email: proveedor, plantillas, sincrónico/asíncrono? | D-09, D-12 (stack + observabilidad) | Producto/Técnico |
| P-09 | ¿Reportes superusuario: tiempo real o batch? ¿Latencia tolerable? | D-10, D-12 (DB réplica + métricas) | Producto |

---

## Próximos Pasos

1. **`architect`** → Proponer 4-6 alternativas arquitectónicas completas (03_architecture_options.md)
2. **`database-specialist`** → Evaluar motores y estrategia persistencia (04_database_analysis.md)
3. **`security-reviewer`** → Revisar seguridad integral (05_security_review.md)
4. **`devops-architect`** → Evaluar infraestructura, K8s sí/no (06_infrastructure.md)
5. **`architecture-reviewer`** → Revisión adversarial (07_architecture_review.md)
6. **`solution-leader`** → Consolidación final + ADR (08_recomendacion_final.md)

---

**Skill utilizado**: `decision-consolidation` (para estructurar el alcance)  
**Agente responsable**: `solution-leader` (fase de alcance de decisiones)
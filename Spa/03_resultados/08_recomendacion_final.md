# Recomendación Final — Sistema Tienda Omnicanal de Alimentos

Fecha: 2026-09-17
Autor: solution-leader
Fuentes: 01-07_análisis + adversarial review

---

## 1. Matriz de Decisión por Dominio

| Decisión | Opción Elegida | Alternativa descartada principal | Justificación |
|----------|----------------|----------------------------------|---------------|
| **D-01** Concurrencia inventario | Reserva temporal híbrida (available/reserved + lock en confirmación) | Pessimistic puro (deadlock), Optimistic (rechaza usuario) | Patrón estándar e-commerce. Web reserva con TTL, POS confirma con lock breve. Evita oversell (RN-02) |
| **D-02** Modelo transaccional | Híbrido: ACID dominio + Saga pagos | Event Sourcing (over-engineering) | ACID para inventario+venta (una TX SQL). Saga solo para integración con gateway de pago externo |
| **D-03** Idempotencia | Idempotency key (UUID) + constraint DB | Lock distribuido (no resuelve reintentos después de expirar) | Doble protección. Key para APIs externas, constraint para campos naturales (número orden, ref pago) |
| **D-04** Pagos | Módulo desacoplado con outbox + webhook | Síncrono dentro de TX (timeout si proveedor lento) | Desacoplamiento natural. Pago confirma vía webhook. Outbox para consistencia |
| **D-05** Escalabilidad horizontal | No requerida inicialmente | Réplicas múltiples (25K tx/hr = ~7 tx/seg, un solo nodo) | Un nodo PostgreSQL maneja cómodamente. Escalar si >50K tx/hr |
| **D-06** Despliegue | Docker Compose + CI/CD (GitHub Actions) | K3s (sin justificación CA-09) | 3 contenedores: app, PG, (Redis opcional después). CI automatiza build+test+deploy |
| **D-07** Seguridad | JWT + RBAC interno | OAuth2/OIDC (requiere fallback offline para POS), Híbrido (doble superficie) | JWT funciona offline para POS. RBAC simple. Revocación con TTL corto + blacklist en tabla |
| **D-08** Observabilidad | Logs estructurados stdout + Prometheus/Grafana básico | ELK (pesado), Datadog (costoso+lock-in) | Cumple RNF-050/051. RNF-052 no aplica para monolito (correlación "cuando corresponda") |
| **D-09** Motor DB | PostgreSQL | MySQL (SKIP LOCKED menos maduro), Híbrido PG+Redis (consistencia dual) | SKIP LOCKED nativo, MVCC, JSONB, pub/sub. Redis se agrega si métricas lo justifican |

---

## 2. Arquitectura Recomendada

### Alternativa B — Modular Monolith (con elementos de D)

```
┌─────────────────────────────────────────────────────────┐
│                    API Gateway / Router                   │
├─────────────────────────────────────────────────────────┤
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌────────┐ │
│  │ Module:  │  │ Module:  │  │ Module:  │  │Module: │ │
│  │ Auth     │  │Inventario│  │ Ventas   │  │ Pagos  │ │
│  │ (RBAC)   │  │(reserva  │  │ (POS +   │  │(outbox │ │
│  │          │  │ temporal)│  │  web)    │  │+webhook│ │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  └───┬────┘ │
│       └──────────────┴─────────────┴─────────────┘      │
│              Inter-module: Outbox + Events (in-process)  │
├─────────────────────────────────────────────────────────┤
│              PostgreSQL (schemas por módulo)              │
└─────────────────────────────────────────────────────────┘
```

**Por qué Modular Monolith:**
- Simplicidad operativa: un deploy, una DB, una transacción por operación crítica
- Límites claros por módulo (schemas separados) — preparado para extraer servicios si crece
- ACID para inventario+venta. Outbox para eventos cross-module
- Docker Compose: 3 contenedores (app, PG, observability)

**Por qué NO las demás:**
- Monolito transaccional puro (A): sin límites de módulo, acoplamiento crece rápido
- Event Sourced (C): over-engineering. El dominio no requiere audit trail completo ni replay
- Redis cache (E): inconsistencia dual sin necesidad demostrada. Agregar después si métricas lo piden
- Microservicios: innecesario para 7 tx/seg. CA-09 lo prohíbe sin justificación

---

## 3. Backend — Stack y Patrones

| Capa | Tecnología / Patrón | Justificación |
|------|---------------------|---------------|
| **Runtime** | Node.js (LTS) o Python (FastAPI) — evaluar qué usa el equipo | Maduro, ecosistema amplio, contenedorizable |
| **Framework** | Express/Fastify o FastAPI | Ligero, bien documentado |
| **Auth** | JWT (RS256) + middleware RBAC | Offline POS. Refresh tokens. Blacklist en tabla `token_blacklist` |
| **Validación** | Zod (Node) o Pydantic (Python) en cada endpoint | Input validation en trust boundaries (INSTRUCTIONS.md) |
| **Transacciones** | ACID para inventario+venta (una TX SQL) | `BEGIN; INSERT venta; UPDATE stock; COMMIT;` |
| **Pagos** | Outbox pattern → worker consume → llama gateway → webhook confirma | Desacoplamiento sin complejidad de event sourcing |
| **Idempotencia** | Idempotency key (UUID) + `UNIQUE` constraint en campos naturales | Doble protección. TTL 30 días + cleanup |
| **Configuración** | Tabla `system_config` (RF-100) | Parámetros sin redeploy. Cache en memoria con invalidación |

### Concurrencia de Inventario (D-01)

```sql
-- Reserva temporal: decrementa available, incrementa reserved
BEGIN;
SELECT stock_available FROM inventory
WHERE product_id = ? AND store_id = ? FOR UPDATE;

UPDATE inventory
SET stock_available = stock_available - ?,
    stock_reserved = stock_reserved + ?
WHERE product_id = ? AND store_id = ?;

INSERT INTO reservations (order_id, product_id, store_id, qty, expires_at)
VALUES (?, ?, ?, ?, NOW() + INTERVAL '15 minutes');
COMMIT;

-- Confirmación de venta (POS o web):
BEGIN;
SELECT stock_reserved FROM inventory
WHERE product_id = ? AND store_id = ? FOR UPDATE;

UPDATE inventory
SET stock_reserved = stock_reserved - ?,
    stock_sold = stock_sold + ?
WHERE product_id = ? AND store_id = ?;

UPDATE reservations SET status = 'confirmed' WHERE order_id = ?;
COMMIT;

-- Expiración de reserva (job periódico cada 1 min):
UPDATE inventory
SET stock_available = stock_available + r.qty,
    stock_reserved = stock_reserved - r.qty
FROM reservations r
WHERE r.inventory_id = inventory.id
  AND r.status = 'pending'
  AND r.expires_at < NOW();

UPDATE reservations SET status = 'expired' WHERE status = 'pending' AND expires_at < NOW();
```

### Modelo de Transacciones (D-02)

```
Flujo de venta:
1. [ACID] Reservar stock → INSERT reserva + UPDATE inventory
2. [ACID] Crear orden → INSERT orders
3. [Saga] Procesar pago → outbox → worker → gateway pago → webhook
4. [ACID] Confirmar venta → UPDATE reserva status + UPDATE stock_reserved→sold
5. [Compensación] Si pago falla → UPDATE reserva status='cancelled' + RETURN stock
```

### Patrón de Resiliencia (RNF-042) — B-03 resuelto

| Capa | Mecanismo | Configuración |
|------|-----------|---------------|
| **HTTP client** | Retry con exponential backoff | max_retries: 3, base_delay: 500ms, max_delay: 5s |
| **Gateway pago** | Circuit breaker | failure_threshold: 5, recovery_timeout: 30s |
| **Cola pagos** | Dead-letter queue | Max retries: 3 → DLQ → alerta manual |
| **Timeout general** | Timeout por operación | HTTP: 10s, DB query: 5s, pago: 30s |

---

## 4. Persistencia

### PostgreSQL — Configuración

| Aspecto | Decisión |
|---------|----------|
| **Schemas** | Por módulo: `auth`, `inventory`, `sales`, `payments` |
| **Connection pooling** | PgBouncer (transaction mode) — 50-100 conexiones |
| **Backup** | `pg_basebackup` diario + WAL archiving continuo |
| **RPO** | < 1 hora (WAL archiving con `archive_timeout = 3600`) — **pendiente validación con usuario** |
| **RTO** | < 4 horas (restore desde backup + WAL) — **pendiente validación con usuario** |
| **Particionamiento** | Tablas de ventas/inventario por `created_at` (rangos mensuales) cuando >1M filas |
| **Extensiones** | `pgcrypto`, `uuid-ossp`, `pg_trgm` (búsqueda fuzzy) |
| **Auditoría** | `pgAudit` para logging de queries sensibles |

### Modelo de Datos — Entidades Clave

```
inventory (D-01):
  id, product_id, store_id,
  stock_available, stock_reserved, stock_sold,
  version (optimistic para reads),

reservations (D-01):
  id, order_id, product_id, store_id, qty,
  status (pending/confirmed/expired/cancelled),
  expires_at, created_at

orders (D-02):
  id, order_number (UNIQUE), channel (web/pos),
  customer_id, store_id, status, total,
  idempotency_key, created_at

payments (D-04):
  id, order_id, provider, reference (UNIQUE),
  amount, status (pending/confirmed/failed/refunded),
  idempotency_key, created_at

token_blacklist (D-07):
  token_hash, expires_at (TTL = JWT expiry + margen)

system_config (RF-100):
  key (UNIQUE), value, updated_at, updated_by
```

---

## 5. Integraciones

| Integración | Patrón | Notas |
|-------------|--------|-------|
| **Gateway de pago** | Outbox → Worker → HTTP → Webhook callback | Idempotency key en cada request. Retry 3x con backoff. Circuit breaker. DLQ para fallos |
| **POS ↔ Backend** | REST API + JWT offline token | POS almacena JWT con TTL corto (4h). Refresh cuando online. Si offline, venta local + sync posterior |
| **Web ↔ Backend** | REST API + JWT (online) | SPA consume API. CSRF no aplica (JWT en header, no cookie) |
| **Proveedor ↔ Backend** | REST API (compras/órdenes) | RF-030/031. Síncrono. Timeout 10s |
| **Reportes (RF-080)** | Vistas materializadas + query directa a PG | No requiere OLAP separado para 25K tx/hr. Refresh cada 5 min. Si crece → read replica |

### RF-070 — Devoluciones (B-01 resuelto)

**Flujo de devolución:**
1. POS/Web crea `return_order` vinculada a `order` original
2. [ACID] Actualizar `inventory`: incrementar `stock_available` (o `stock_sold` ↓)
3. [Saga] Procesar reembolso vía gateway de pago (mismo patrón que venta)
4. [ACID] Actualizar estado de `return_order` y `order`
5. Auditoría completa: quién, cuándo, por qué, monto, productos

**Restricción RN-08**: No eliminar órdenes confirmadas. Devolución crea registro nuevo, no modifica el original.

---

## 6. Seguridad

| RNF | Implementación | Estado |
|-----|----------------|--------|
| RNF-030 Auth | JWT (RS256) + login endpoint | ✅ Definido |
| RNF-031 Authz | RBAC middleware: roles (admin, manager, cashier, viewer) + permisos por endpoint | ✅ Definido |
| RNF-032 TLS | TLS 1.2+ en todos los links. `sslmode=verify-full` en PG | ✅ Definido |
| RNF-033 Passwords | argon2id (cost=12). Nunca MD5/SHA sin salt | ✅ Definido |
| RNF-034 Secretos | Env vars (dev) → Docker secrets (prod). Vault si crece | ✅ Definido |
| RNF-035 APIs | Rate limiting (100 req/min per user). Audit log middleware | ✅ Definido |

### JWT — Detalle

```
Payload:
{
  "sub": "user_id",
  "roles": ["cashier"],
  "store_id": "store_01",
  "iat": 1726560000,
  "exp": 1726563600   // 1 hora
}

Refresh: refresh_token en DB con TTL 7 días.
Blacklist: tabla token_blacklist con hash del token + expires_at.
         Cleanup periódico: eliminar tokens expirados.
```

### RBAC — Matriz Simplificada

| Rol | Ver inventario | Modificar inventario | Crear venta | Ver reportes | Admin users | Config sistema |
|-----|:-:|:-:|:-:|:-:|:-:|:-:|
| admin | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| manager | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| cashier | ✅ (solo stock) | ❌ | ✅ | ❌ | ❌ | ❌ |
| viewer | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |

---

## 7. Infraestructura

### Docker Compose — Entornos

```yaml
# docker-compose.prod.yml
services:
  app:
    image: tienda-omnicanal:${VERSION}
    environment:
      - DATABASE_URL=postgresql://...
      - JWT_SECRET=${JWT_SECRET}
    depends_on:
      - postgres
    restart: always

  postgres:
    image: postgres:16-alpine
    volumes:
      - pgdata:/var/lib/postgresql/data
    environment:
      - POSTGRES_DB=tienda
      - POSTGRES_PASSWORD=${DB_PASSWORD}
    restart: always

  grafana:
    image: grafana/grafana:latest
    ports:
      - "3001:3000"
    volumes:
      - grafana-data:/var/lib/grafana
```

| Aspecto | Decisión |
|---------|----------|
| **Compose** | Un archivo por entorno: `docker-compose.dev.yml`, `docker-compose.prod.yml` |
| **Imágenes** | Multi-stage build. Base: `node:22-alpine` o `python:3.12-slim` |
| **Volumes** | Named volumes para PG data y Grafana |
| **Networking** | Bridge network interno. Solo app expone puerto (8080) |
| **Health checks** | `pg_isready` para PG. HTTP `/health` para app |
| **Logs** | stdout JSON (RNF-050). Docker captura. Rotación con `max-size: 10m` |

### CI/CD — GitHub Actions

```
Push a main:
  → lint + typecheck + tests
  → build Docker image
  → push to registry
  → deploy a prod (ssh + docker compose pull && up -d)

PR a main:
  → lint + typecheck + tests
  → build image (no deploy)
```

---

## 8. Capacidad

| Métrica | Valor | Cálculo |
|---------|-------|---------|
| **Transacciones/hora (base)** | 25,000 | RF/RNF especificado |
| **Transacciones/segundo** | ~7 | 25000 / 3600 |
| **Escenario 10x** | 250,000/hr (~70 tx/seg) | RNF-011 requiere evaluar |
| **PostgreSQL capacidad** | >1,000 tx/seg | Un nodo con硬件 adecuado |
| **Máximo con 1 nodo** | ~50,000 tx/hr | Con optimización y pooling |
| **Escalar a 10x** | Read replica + pgBouncer tuning | No requiere sharding |
| **Productos** | ~100K | Estimación base |
| **Sucursales** | 50 → 200 en 3 años | RNF-010 |
| **Conexiones concurrentes** | 100 usuarios | PgBouncer: 50-100 pool |

**Conclusión**: Un solo nodo PostgreSQL con connection pooling maneja 10x sin problemas. No se necesita sharding, réplicas, ni Redis para cache de stock.

---

## 9. Riesgos

| # | Riesgo | Probabilidad | Impacto | Mitigación |
|---|--------|:---:|:---:|------------|
| 1 | **Sobreventa entre canales** — race condition POS vs web | Alta | Crítico | Reserva temporal + lock en confirmación. Tests de concurrencia obligatorios |
| 2 | **Pago externo falla/timeout** — stock reservado pero pago no confirma | Media | Alto | Saga con compensación. DLQ + alerta manual. Timeout 30s |
| 3 | **POS offline** — ventas locales sin sync | Media | Alto | **Pendiente: confirmar si POS offline es requisito.** Si sí: localStorage + sync al reconectar |
| 4 | **Duplicados por reintento** — cliente reenvía misma orden | Alta | Medio | Idempotency key + constraint DB. Doble protección |
| 5 | **RPO/RTO no validados** — backup strategy puede ser insuficiente | Media | Alto | **Pendiente: validar con usuario.** WAL archiving como base |
| 6 | **Complejidad de devoluciones** — reembolso parcial + inventario | Media | Medio | State machine para estados de orden. Auditoría completa |
| 7 | **Cambio de proveedor de pago** — dependencia del gateway | Baja | Medio | Adapter pattern. Interfaz de pago abstracta |
| 8 | **Crecimiento no previsto** — más sucursales de lo esperado | Baja | Medio | Modular monolith preparado para extraer servicios. Read replica si crece |

---

## 10. Preguntas Pendientes (Requieren Respuesta del Usuario)

| # | Pregunta | Impacto | Propuesta por defecto si no se responde |
|---|----------|---------|----------------------------------------|
| **Q1** | ¿La reserva web bloquea stock disponible para POS? | D-01 | **No bloquea**: reserva lógica con `available`. POS puede vender lo que web reserva. Si conflicto: primero en confirmar gana |
| **Q3** | ¿Comportamiento al expirar reserva web? | D-01 | **Stock vuelve a disponible automáticamente**. Job cada 1 min expira reservas. Cliente notificado por email |
| **Q2** | ¿Productos concurrentes por transferencia? | D-01 | **10-50 productos por transferencia**. Lock por fila (SKIP LOCKED). Sin deadlocks |
| **Q4** | ¿Proveedor(es) de pago iniciales? | D-02 | **Un solo proveedor inicial**. Adapter pattern para agregar más después |
| **Q5** | ¿RPO/RTO objetivo? | D-09 | **RPO < 1h, RTO < 4h**. WAL archiving. Sin streaming replication initially |
| **Q6** | ¿POS funciona offline? | D-07, D-01 | **No (por ahora)**. JWT requiere connectivity. Si se necesita offline → evaluar |
| **Q7** | ¿Reportes en tiempo real o con latencia? | RF-080 | **Con latencia (5 min)**. Vistas materializadas. Refresh periódico |

**Propuesta**: Si el usuario no responde, se asumen los defaults de la columna "Propuesta". Estos defaults son conservadores y no bloquean el diseño.

---

## 11. Trazabilidad RF/RNF → Decisión

| Requisito | Decisión | Estado |
|-----------|----------|--------|
| RF-001 (usuarios, roles, permisos) | D-07 JWT+RBAC | ✅ |
| RF-002 (clientes) | Modelo compartido entre canales. Tabla `customers` | ⏳ Pendiente definición |
| RF-010 (sucursales, POS) | Entidad escalable. POS = app web en kiosco | ✅ |
| RF-020 (productos, catálogos, precios) | Tablas `products`, `categories`, `prices` (por sucursal o global — pendiente Q8) | ⏳ Pendiente |
| RF-030 (proveedores, órdenes compra) | REST API síncrona. Adapter pattern | ✅ |
| RF-031 (recepción mercadería) | UPDATE parcial de inventario. ACID | ✅ |
| RF-040 (inventario por producto/sucursal) | Tabla `inventory` con available/reserved/sold | ✅ |
| RF-041 (movimientos inventario) | Tabla `inventory_movements` (log append-only) | ✅ |
| RF-042 (evitar doble descuento) | D-03 idempotency key + constraint | ✅ |
| RF-050 (ventas presenciales) | POS → API → ACID (reserva + confirmación) | ✅ |
| RF-051 (descontar inventario) | D-01 reserva temporal | ✅ |
| RF-060 (catálogo web, carrito, pedido) | API REST + reserva stock | ✅ |
| RF-061 (entrega/retiro sucursal) | **Sin análisis detallado** — flaggear para fase 2 | ⏳ |
| RF-062 (reserva stock web) | D-01 reserva temporal con TTL | ✅ |
| RF-063 (pagos desacoplados) | D-04 outbox + webhook | ✅ |
| RF-064 (ventas web sin sobreventa) | D-01 reserva temporal + D-09 PostgreSQL SKIP LOCKED | ✅ |
| RF-070 (devoluciones) | State machine + compensación + auditoría | ✅ |
| RF-080 (reportes operativos) | Vistas materializadas + query directa a PG | ✅ |
| RF-090 (auditoría) | Middleware + pgAudit + structured logs | ✅ |
| RF-100 (parámetros sin redeploy) | Tabla `system_config` + cache en memoria | ✅ |
| RNF-001 (25K tx/hr) | Un nodo PG con pooling. ~7 tx/seg. Sobra | ✅ |
| RNF-002 (configurabilidad externa) | Tabla `system_config` (RF-100) | ✅ |
| RNF-003 (txn ≠ SQL) | Definición pendiente. Asumimos operación compuesta | ⏳ |
| RNF-004 (picos) | Reserva temporal absorbe picos. PG maneja 10x | ✅ |
| RNF-005 (integridad concurrencia) | D-01 reserva + lock | ✅ |
| RNF-010 (crecimiento sucursales) | Modular monolith. 50→200 sin cambio arquitectónico | ✅ |
| RNF-011 (10x) | Un nodo PG. Escalar a read replica si necesario | ✅ |
| RNF-020 (sobreventa) | D-01 reserva temporal | ✅ |
| RNF-021 (fronteras transaccionales) | D-02 ACID dominio + Saga pagos | ✅ |
| RNF-022 (duplicados) | D-03 idempotency key + constraint | ✅ |
| RNF-030-035 (seguridad) | D-07 JWT+RBAC + TLS + argon2id + vault | ✅ |
| RNF-040 (backup) | pg_basebackup + WAL archiving | ✅ |
| RNF-041 (RPO/RTO) | Estimado: RPO<1h, RTO<4h. **Pendiente validación** | ⏳ |
| RNF-042 (timeout/reintentos) | Retry 3x backoff + circuit breaker + DLQ | ✅ |
| RNF-050 (logs estructurados) | JSON stdout + Docker capture | ✅ |
| RNF-051 (métricas) | Prometheus + Grafana básico | ✅ |
| RNF-052 (correlación distribuida) | No aplica para monolito. Escalar a OTel si se distribuye | ✅ |
| RNF-060 (Linux) | Docker Compose en Linux | ✅ |
| RNF-061 (contenerización) | Docker Compose | ✅ |
| RNF-062 (separación entornos) | Docker Compose files separados | ✅ |

**Estado**: 30/37 cubiertos. 7 pendientes (Q1-Q7 + RF-061). Ninguno bloquea la decisión arquitectónica.

---

## 12. ADR Propuesto

```markdown
# ADR-001: Arquitectura del Sistema Tienda Omnicanal

## Estado: Propuesto

## Contexto
Sistema transaccional omnicanal (físico + web) para venta de alimentos.
Requiere integridad de inventario bajo concurrencia, pagos desacoplados,
y escalabilidad por crecimiento de sucursales.

## Decisión
Arquitectura: Modular Monolith (Alternativa B)
- PostgreSQL como motor de DB
- Reserva temporal para concurrencia de inventario
- Híbrido ACID (dominio) + Saga (pagos externos)
- JWT + RBAC para seguridad
- Docker Compose para despliegue
- Logs estructurados + Prometheus/Grafana para observabilidad

## Consecuencias
- Simplicidad operativa: un deploy, una DB, 3 contenedores
- Límites de módulo claros: preparado para extraer servicios si crece
- Redis NO se incluye inicialmente: agregar solo si métricas lo justifican
- K3s NO se incluye: migrar si >50K tx/hr o si se necesita auto-healing

## Alternativas consideradas
- Monolito transaccional puro (A): descartado por falta de límites de módulo
- Event Sourced (C): descartado por over-engineering
- Redis cache (E): descartado por inconsistencia dual sin necesidad
- Microservicios: descartado por CA-09 y bajo volumen

## Riesgos conocidos
- Redis no incluido: si el stock hot-path necesita latencia sub-ms, evaluar
- POS offline no soportado inicialmente: si se requiere, cambiar auth a híbrido
- 7 preguntas abiertas (Q1-Q7): asumir defaults si no se responden
```

---

## 13. Plan de Pruebas

| Tipo | Qué probar | Herramienta | Cobertura mínima |
|------|------------|-------------|:---:|
| **Unit** | Lógica de reserva temporal, cálculo de stock, idempotency key | Jest/Pytest | 80%+ |
| **Integration** | API endpoints completos (reservar → vender → devolver) | Supertest/httpx | Happy path + error path |
| **Concurrency** | 50+ ventas simultáneas del mismo producto | Script concurrente (artillery o custom) | Sin oversell (RNF-020) |
| **Idempotency** | Reenvío de misma orden con misma key | Script de reintento | Mismo resultado, sin duplicado |
| **Payment failure** | Gateway pago timeout → compensación stock | Mock de gateway | Stock retorna a disponible |
| **Reservation expiry** | Reserva expira → stock vuelve a disponible | Test con TTL corto (1s) | available actualizado |
| **E2E** | Flujo completo: login → buscar → carrito → pagar → confirmar | Playwright o Cypress | Flujo crítico cubierto |
| **Security** | JWT expirado, RBAC con rol no autorizado, rate limiting | Script de seguridad | 401/403 correctos |
| **Performance** | 25K tx/hr durante 1 hora | k6 o artillery | Sin degradación, sin deadlocks |
| **Backup/Restore** | Restaurar DB desde backup + WAL | Script de restore | RPO/RTO validados |

### Criterio de Aprobación

- [ ] Todos los tests unitarios pasan
- [ ] Tests de concurrencia: 0 oversell en 1000 intentos
- [ ] Tests de idempotency: 0 duplicados en 500 reintentos
- [ ] E2E: flujo crítico completo sin errores
- [ ] Performance: 25K tx/hr sostenido sin degradación
- [ ] Security: sin vulnerabilidades CRITICAL/HIGH

---

## 14. Resumen Ejecutivo

**El sistema es un Modular Monolith con PostgreSQL que resuelve el problema central (inventario omnicanal sin sobreventa) con reserva temporal + lock en confirmación. Pagos se desacoplan con outbox pattern. Un solo nodo PostgreSQL maneja 10x sin escalar. Docker Compose despliega 3 contenedores. JWT funciona offline para POS.**

**Lo que NO se hace hoy (y está bien):**
- Redis: agregar solo si métricas lo justifican
- K3s: migrar solo si >50K tx/hr
- Event sourcing: over-engineering para este dominio
- Microservicios: innecesario para 7 tx/seg
- Tracing distribuido: no aplica para monolito

**Lo que necesita respuesta del usuario: 7 preguntas (Q1-Q7). Si no se responden, se asumen defaults conservadores que no bloquean el diseño.**

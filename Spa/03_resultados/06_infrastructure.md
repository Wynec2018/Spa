# Evaluación de Infraestructura y Despliegue — Spa Cielo

Fecha: 2026-10-09  
Fuente: `03_resultados/01_requirements_analysis.md`, `03_resultados/03_architecture_options.md`, `03_resultados/04_database_analysis.md`, `03_resultados/05_security_review.md`  
Arquitectura objetivo: **Monolito Modular (Alternativa A)** + **PostgreSQL 16+**

---

## Requisitos Operativos (RNF) que Impulsan Decisiones

| RNF | Requisito | Implicación Infra |
|-----|-----------|-------------------|
| RNF-001 | 50 tx/hora (pico 100, eval 10x = 500) | **Single-node app + DB sobra**; no requiere cluster |
| RNF-002 | Capacidad configurable externamente | Config en DB (`app_config`) + cache app |
| RNF-040-043 | Backup diario, 99.8% disponibilidad, RPO/RTO definidos | **HA PostgreSQL** (réplica síncrona/asíncrona), PITR, runbook |
| RNF-050-052 | Logs estructurados, métricas, correlación | Stack observabilidad (logs + métricas + traces) |
| RNF-060-062 | Linux, contenedorizable, 3 ambientes (dev/test/prod) | Docker obligatorio, multi-stage builds, config por entorno |
| C-05 (restricción) | Equipo pequeño, capacitación 30 min | **Complejidad operativa mínima**; evitar K8s salvo justificación fuerte |

---

## Comparación de Opciones de Despliegue

### Criterios de Evaluación

| Criterio | Peso | Descripción |
|----------|------|-------------|
| **Simplicidad Operativa** | 30% | Curva aprendizaje, mantenimiento diario, troubleshooting |
| **Costo Total (infra + tiempo equipo)** | 25% | Recursos cloud/VM, managed services, horas ops |
| **Confiabilidad / HA** | 20% | Cumple 99.8%, RPO/RTO, recuperación probada |
| **Escalabilidad (hasta 10x)** | 15% | Crecer a 500 tx/h sin rediseño mayor |
| **Seguridad / Compliance** | 10% | Secrets, network isolation, audit, encryption |

Puntuación: 1 (malo) a 5 (excelente).

---

### Opción 1: **VM Única + Docker Compose (Recomendada para MVP)**

**Arquitectura**: 1 VM (2-4 vCPU, 8-16 GB RAM) + Docker Compose (app + pg-primary + pg-standby + pgbackrest + reverse proxy)

| Componente | Detalle |
|------------|---------|
| **App** | 1 contenedor (monolito), healthcheck, restart policy |
| **DB Primaria** | PostgreSQL 16, `wal_level=replica`, `synchronous_commit=on` |
| **DB Standby** | Réplica streaming **síncrona** (RPO=0) en misma VM (distinto disco) o VM separada |
| **Backup** | `pgBackRest` contenedor → repo local (disco aparte) + réplica a S3/MinIO |
| **Reverse Proxy** | **Caddy** (TLS automático Let's Encrypt, config simple) o **Traefik** |
| **Observabilidad** | **Vector** (logs) + **Prometheus** (métricas) + **Grafana** (dashboards) — todo en contenedores |
| **CI/CD** | GitHub Actions / GitLab CI → build image → push registry → `ssh` deploy (compose pull + up -d) |

| Criterio | Score | Notas |
|----------|-------|-------|
| Simplicidad | **5** | 1 VM, compose up, logs `docker compose logs -f`, backup script simple |
| Costo | **5** | ~$20-40/mes (VM 2-4 vCPU, 16GB, disco 100-200GB). Zero managed services premium |
| Confiabilidad | **4** | HA local (standby síncrono). RPO=0. RTO ~5-15 min (promote standby). Single AZ risk |
| Escalabilidad | **3** | Vertical hasta 8-16 vCPU. Horizontal requiere re-arquitectura (load balancer + app replicas) |
| Seguridad | **4** | Secrets en Docker secrets (tmpfs), network isolate (user-defined networks), TLS auto |
| **Total Ponderado** | **4.35** | |

**Riesgos**: Single AZ (VM caída = downtime). Mitigación: Snapshot VM diario + restore <30 min. Para 99.8% (1.44h downtime/mes) es aceptable si RTO <30 min.

---

### Opción 2: **Managed Services (Cloud SQL / RDS / Neon) + VM App (Recomendada si equipo prefiere menos ops DB)**

**Arquitectura**: App en VM (Docker Compose) + **PostgreSQL Managed** (Cloud SQL, RDS, Azure Database, Neon, Supabase)

| Componente | Detalle |
|------------|---------|
| **App** | 1 VM + Docker Compose (app + pgbackrest sidecar + reverse proxy + observabilidad) |
| **DB** | Managed PostgreSQL (HA automático, backups automáticos, PITR, read replicas) |
| **Backup** | Nativo managed (PITR 7-35 días) + `pgBackRest` opcional a bucket propio |
| **Reverse Proxy** | Caddy / Traefik en VM |
| **Observabilidad** | Vector + Prometheus + Grafana en VM (o managed: CloudWatch, Datadog, Grafana Cloud) |

| Criterio | Score | Notas |
|----------|-------|-------|
| Simplicidad | **5** | Zero ops DB (patches, HA, backup, PITR automáticos). Solo app en VM |
| Costo | **3** | Managed DB 2-3x costo VM equivalente. ~$50-100/mes total |
| Confiabilidad | **5** | HA multi-AZ nativo, SLA 99.95%, PITR point-and-click, backups testeados |
| Escalabilidad | **4** | Read replicas 1-click, vertical resize, storage auto-grow |
| Seguridad | **5** | Encryption at rest/default, VPC, IAM, certs managed, audit logs |
| **Total Ponderado** | **4.40** | |

**Mejor si**: Equipo no quiere operar PostgreSQL (backups, réplicas, tuning, patches). Costo extra justificado por reducción riesgo operacional.

---

### Opción 3: **Container Apps / Cloud Run / Fargate (Serverless Containers)**

**Arquitectura**: App como container serverless + Managed PostgreSQL

| Componente | Detalle |
|------------|---------|
| **App** | **Google Cloud Run / AWS Fargate / Azure Container Apps** — scale to zero, pay per request |
| **DB** | Managed PostgreSQL (Cloud SQL, RDS, Azure DB, Neon) |
| **Observabilidad** | Nativa del proveedor (Cloud Logging/Monitoring, CloudWatch, Azure Monitor) |
| **CI/CD** | Nativa (Cloud Build, CodePipeline, GitHub Actions + deploy action) |

| Criterio | Score | Notas |
|----------|-------|-------|
| Simplicidad | **4** | No VM management. Pero: cold starts, límites memoria/CPU, debugging remoto, vendor lock-in |
| Costo | **4** | Pay-per-use. Muy barato en bajo uso. Impredecible en picos sostenidos |
| Confiabilidad | **5** | Multi-AZ automático, SLA alto |
| Escalabilidad | **5** | Scale to zero → miles de requests sin config |
| Seguridad | **4** | Buena por defecto. Menos control network (no VPC peering simple en algunos) |
| **Total Ponderado** | **4.35** | |

**Gap Spa Cielo**: **SSE (Server-Sent Events)** requiere conexiones largas. Serverless containers a menudo limitan duración request (Cloud Run: 60 min max, pero conexiones idle pueden cerrarse). WebSockets/SSE necesitan configuración especial. **Riesgo técnico medio**.

---

### Opción 4: **Kubernetes (EKS/GKE/AKS) — Explícitamente NO Recomendado**

**Justificación de NO uso (RNF-060 a RNF-062, C-05, RNF-001):**

| Argumento | Evidencia |
|-----------|-----------|
| **Overhead operativo brutal** | Control plane, nodes, CNI, CSI, Ingress, Cert-manager, ExternalDNS, Operators, Upgrades, Security patching |
| **Equipo pequeño (30 min training)** | K8s requiere meses de expertise. "Capacitación 30 min" incompatible |
| **Carga trivial (50 tx/h)** | 1 contenedor app + 1 DB. K8s diseñado para 100s de microservicios, autoscaling complejo |
| **Costo** | Mínimo 3 nodes (HA control plane) + managed control plane fee (~$70-150/mes solo control plane) + nodes |
| **Complejidad observabilidad** | Requiere stack completo (Prometheus Operator, Loki, Tempo, Grafana) vs 3 contenedores en compose |
| **CI/CD** | ArgoCD / Flux / Helm / Kustomize — curva alta vs `docker compose up -d` |
| **RNF-061 (contenedorizable) ≠ K8s** | Docker Compose, Cloud Run, Fargate, VM + Docker **son** contenedorizado |

**Cuándo SÍ considerar K8s en futuro**:
- Equipo >5 devs dedicados a plataforma
- >10 servicios desplegables independientemente
- Necesidad real de: blue/green, canary, autoscaling HPA/VPA, multi-cluster, gitops
- Requisito compliance que exija K8s (raro)

**Veredicto**: **NO usar Kubernetes**. Violenta principio de proporción (RNF-062, C-05, AGENTS.md "evitar complejidad no sustentada").

---

### Opción 5: **PaaS Tradicional (Heroku, Render, Railway, Fly.io)**

| Criterio | Score | Notas |
|----------|-------|-------|
| Simplicidad | **5** | `git push` deploy. Zero infra management |
| Costo | **3** | Premium por simplicidad. ~$50-100/mes para specs modestas |
| Confiabilidad | **4** | Buena, pero menos control HA/backup que managed DB dedicado |
| Escalabilidad | **3** | Vertical fácil. Horizontal limitado/opaco |
| Seguridad | **3** | Menos control network, secrets, audit logs |
| **Total Ponderado** | **3.70** | |

**Gap**: Menos control sobre PostgreSQL (extensiones, config, réplica síncrona, pgBackRest, `pg_audit`). Para Spa Cielo con requisitos específicos PG (advisory locks, RLS, `pg_cron`, `pg_audit`), **managed PostgreSQL dedicado (Opción 2) superior**.

---

## Matriz Resumen Despliegue

| Opción | Simplicidad (30%) | Costo (25%) | Confiabilidad (20%) | Escalabilidad (15%) | Seguridad (10%) | **Total** | Veredicto |
|--------|-------------------|-------------|---------------------|---------------------|-----------------|-----------|-----------|
| **1. VM + Docker Compose** | **5** | **5** | 4 | 3 | 4 | **4.35** | ✅ **Recomendada MVP** |
| **2. VM App + Managed PG** | **5** | 3 | **5** | 4 | **5** | **4.40** | ✅ **Recomendada si menos ops DB** |
| 3. Serverless Containers | 4 | 4 | **5** | **5** | 4 | 4.35 | ⚠️ Riesgo SSE/WebSocket |
| 4. **Kubernetes** | 1 | 2 | **5** | **5** | 4 | **2.85** | ❌ **NO** |
| 5. PaaS (Render/Fly) | **5** | 3 | 4 | 3 | 3 | 3.70 | ⚠️ Menos control PG |

---

## Recomendación: **Opción 1 (VM + Docker Compose) como Baseline** con migración planificada a **Opción 2 (Managed PG)** si ops DB carga al equipo.

### Justificación Trazable

| Driver | Decisión | RF/RNF |
|--------|----------|--------|
| RNF-001/004/011 (carga baja) | Single VM sobra (2-4 vCPU, 16GB) | RNF-001, RNF-004, RNF-011 |
| RNF-043 (99.8%) + RNF-040/041 (backup, RPO/RTO) | PG réplica síncrona (RPO=0) + pgBackRest (RTO <15 min) | RNF-040, RNF-041, RNF-042, RNF-043 |
| RNF-060/061/062 (Linux, contenedor, 3 envs) | Docker Compose multi-env (`.env.dev`, `.env.test`, `.env.prod`) | RNF-060, RNF-061, RNF-062 |
| C-05 (equipo pequeño, 30 min training) | **Compose > K8s**. 1 comando deploy. Logs unificados. | C-05 |
| D7 (portabilidad) | Imágenes estándar, compose portable a cualquier Docker host | D7 |
| SEC-01/02/08 (secretos, backups cifrados) | Docker secrets (tmpfs) + pgBackRest aes-256 + repo S3/MinIO | SEC-01, SEC-02, SEC-08 |

---

## Arquitectura de Despliegue Detallada (Opción 1)

### Topología de Red (Docker Networks)

```yaml
# docker-compose.yml (extracto redes)
networks:
  frontend:      # Reverse proxy ↔ App (puerto 80/443)
    driver: bridge
  backend:       # App ↔ DB (puerto 5432)
    driver: bridge
    internal: true  # Sin salida internet
  backup:        # pgBackRest ↔ DB
    driver: bridge
    internal: true
  monitoring:    # Prometheus ↔ App/DB exporters
    driver: bridge
    internal: true
```

### Servicios (docker-compose.yml)

```yaml
services:
  # ──────────────────────────────────────────────
  # REVERSE PROXY — Caddy (TLS auto, simple config)
  # ──────────────────────────────────────────────
  caddy:
    image: caddy:2-alpine
    ports: ["80:80", "443:443", "443:443/udp"]
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile:ro
      - caddy_data:/data
      - caddy_config:/config
    networks: [frontend]
    depends_on: [app]
    healthcheck:
      test: ["CMD", "caddy", "validate", "--config", "/etc/caddy/Caddyfile"]
      interval: 30s

  # ──────────────────────────────────────────────
  # APP — Monolito Modular (build multi-stage)
  # ──────────────────────────────────────────────
  app:
    build:
      context: .
      dockerfile: Dockerfile
      target: runtime
    environment:
      - DATABASE_URL=postgres://postgres:{{DOCKER-SECRET:db_password}}@pg-primary:5432/spa?sslmode=disable
      - JWT_PRIVATE_KEY_PATH=/run/secrets/jwt_private_key
      - QR_PROVIDER_SECRET_PATH=/run/secrets/qr_provider_secret
      - APP_ENV=production
      - APP_CORRELATION_ID_HEADER=x-correlation-id
    secrets:
      - db_password
      - jwt_private_key
      - qr_provider_secret
    networks: [frontend, backend, monitoring]
    depends_on:
      pg-primary:
        condition: service_healthy
    healthcheck:
      test: ["CMD", "wget", "-q", "--spider", "http://localhost:8080/health"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 20s
    deploy:
      resources:
        limits:
          cpus: '2.0'
          memory: 1G
        reservations:
          cpus: '0.5'
          memory: 512M

  # ──────────────────────────────────────────────
  # POSTGRESQL PRIMARY
  # ──────────────────────────────────────────────
  pg-primary:
    image: postgres:16-alpine
    command: >
      -c wal_level=replica
      -c max_wal_senders=10
      -c max_replication_slots=10
      -c synchronous_commit=on
      -c synchronous_standby_names='pg-standby'
      -c shared_preload_libraries='pg_stat_statements,pg_audit,pg_cron'
      -c pg_audit.log='ddl,role,misc,read,write'
      -c pg_cron.enabled=on
    environment:
      - POSTGRES_PASSWORD_FILE=/run/secrets/db_password
      - POSTGRES_USER=postgres
      - POSTGRES_DB=spa
    secrets: [db_password]
    volumes:
      - pgdata:/var/lib/postgresql/data
      - ./init.sql:/docker-entrypoint-initdb.d/init.sql:ro
    networks: [backend, backup, monitoring]
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U postgres"]
      interval: 10s
      timeout: 5s
      retries: 5
    deploy:
      resources:
        limits:
          cpus: '2.0'
          memory: 2G

  # ──────────────────────────────────────────────
  # POSTGRESQL STANDBY (Síncrono — RPO=0)
  # ──────────────────────────────────────────────
  pg-standby:
    image: postgres:16-alpine
    command: >
      -c wal_level=replica
      -c hot_standby=on
      -c primary_conninfo='host=pg-primary port=5432 user=replicator password={{DOCKER-SECRET:replicator_password}} application_name=pg-standby'
      -c promote_trigger_file=/tmp/promote
    secrets: [replicator_password]
    volumes:
      - pgdata_standby:/var/lib/postgresql/data
    networks: [backend, backup]
    depends_on:
      pg-primary:
        condition: service_healthy
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U postgres"]
      interval: 10s
      timeout: 5s
      retries: 5
    deploy:
      resources:
        limits:
          cpus: '1.0'
          memory: 1G

  # ──────────────────────────────────────────────
  # BACKUP — pgBackRest (repo local + S3/MinIO)
  # ──────────────────────────────────────────────
  pgbackrest:
    image: pgbackrest/pgbackrest:latest
    environment:
      - PGBACKREST_CONFIG=/etc/pgbackrest/pgbackrest.conf
    volumes:
      - ./pgbackrest.conf:/etc/pgbackrest/pgbackrest.conf:ro
      - pgbackrest-spool:/var/spool/pgbackrest
      - pgbackrest-repo:/var/lib/pgbackrest
    networks: [backup]
    depends_on: [pg-primary]
    # Cron via pg_cron en primary o systemd timer en host

  # ──────────────────────────────────────────────
  # OBSERVABILIDAD — Vector + Prometheus + Grafana
  # ──────────────────────────────────────────────
  vector:
    image: timberio/vector:latest-alpine
    volumes:
      - ./vector.yaml:/etc/vector/vector.yaml:ro
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - /var/lib/docker/containers:/var/lib/docker/containers:ro
    networks: [monitoring]

  prometheus:
    image: prom/prometheus:latest
    volumes:
      - ./prometheus.yml:/etc/prometheus/prometheus.yml:ro
      - prometheus-data:/prometheus
    networks: [monitoring]
    command:
      - '--config.file=/etc/prometheus/prometheus.yml'
      - '--storage.tsdb.path=/prometheus'
      - '--web.console.libraries=/etc/prometheus/console_libraries'
      - '--web.console.templates=/etc/prometheus/consoles'

  grafana:
    image: grafana/grafana:latest
    environment:
      - GF_SECURITY_ADMIN_PASSWORD_FILE=/run/secrets/grafana_password
      - GF_INSTALL_PLUGINS=grafana-piechart-panel
    secrets: [grafana_password]
    volumes:
      - ./grafana/dashboards:/etc/grafana/provisioning/dashboards:ro
      - ./grafana/datasources:/etc/grafana/provisioning/datasources:ro
      - grafana-data:/var/lib/grafana
    networks: [monitoring, frontend]
    depends_on: [prometheus]
```

### Secrets (Docker Secrets — tmpfs, no disco, no env vars)

```bash
# Crear secrets (una vez, en host)
openssl rand -base64 32 | docker secret create db_password -
openssl genrsa -out jwt_private_key.pem 2048 && docker secret create jwt_private_key jwt_private_key.pem
openssl rand -base64 32 | docker secret create qr_provider_secret -
openssl rand -base64 32 | docker secret create replicator_password -
openssl rand -base64 32 | docker secret create grafana_password -
```

### Caddyfile (TLS Automático, Security Headers)

```caddyfile
{
    admin off
    email admin@spa-cielo.com  # Para Let's Encrypt
}

spa-cielo.com {
    # Security headers
    header {
        Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
        X-Content-Type-Options "nosniff"
        X-Frame-Options "DENY"
        Referrer-Policy "strict-origin-when-cross-origin"
        Permissions-Policy "geolocation=(), microphone=()"
        -Server
    }

    # Rate limiting (global + login específico)
    rate_limit {
        zone api 10r/s burst 20
        key {remote_host}
    }

    @login path /api/v1/auth/login
    rate_limit @login {
        zone login 5r/m burst 10
        key {remote_host}
    }

    # Proxy a app
    reverse_proxy app:8080 {
        header_up X-Forwarded-For {remote_host}
        header_up X-Forwarded-Proto {scheme}
        header_up X-Correlation-ID {header.x-correlation-id}
    }
}
```

---

## Observabilidad (RNF-050 a RNF-052)

### Stack: **Vector (logs) → Loki (storage) + Prometheus (métricas) → Grafana (viz)**

| Componente | Qué Recopila | Retención |
|------------|--------------|-----------|
| **Vector** (sidecar/daemon) | Logs stdout/stderr contenedores (JSON), logs nginx/Caddy, logs PostgreSQL (via `pg_audit` syslog) | 30 días (Loki) |
| **Prometheus** | Métricas app (`/metrics`), `pg_stat_statements`, `node_exporter` (host), `caddy` metrics | 15 días (local) / 1 año (downsampling) |
| **Grafana** | Dashboards: Request rate/latency/errors (RED), DB conexiones/queries/replication lag, Business: bookings/hora, occupancy, pagos pendientes | N/A |

### Métricas Críticas (Alertas)

| Métrica | Alerta | Umbral | Acción |
|---------|--------|--------|--------|
| `http_requests_total{status=~"5.."}` | **Error rate 5xx** | >1% por 5 min | PagerDuty / Telegram |
| `pg_replication_lag` | **Replica lag** | >10 MB / >30 s | Investigar standby |
| `booking_active_count` | **Ocupación 100%** | = 6 camas | Info (normal en pico) |
| `payment_pending_age_minutes` | **Pagos pendientes viejos** | >30 min | Alerta secretaria (webhook) |
| `container_memory_usage_bytes` | **OOM risk** | >85% limit | Scale vertical / restart |
| `pg_backrest_last_backup_age_hours` | **Backup fresco** | >24h (full) / >6h (incr) | Alerta ops |

### Correlación (Distributed Tracing)

- **W3C Trace Context** (`traceparent`, `tracestate`) propagado en headers
- App inyecta `correlation_id` (UUID v7) en cada request → logs + `audit_log` + DB `pg_stat_activity` (`application_name`)
- **OpenTelemetry SDK** en app (opcional MVP, obligatorio 10x) → exporta a Tempo/Jaeger si se añade

---

## CI/CD Pipeline (GitHub Actions / GitLab CI)

### Stages

```yaml
# .github/workflows/ci-cd.yml (resumen)
stages:
  - lint-test          # Código: lint, typecheck, unit tests, integration tests (Testcontainers PG)
  - build-image        # Multi-stage Dockerfile → image tag: sha, semver
  - security-scan      # Trivy (image), gitleaks (secrets), dependency audit (npm/cargo/pip/maven/go)
  - deploy-staging     # Auto a staging (VM staging) — compose pull + up -d
  - integration-e2e    # Post-deploy smoke tests (Playwright / curl + jq)
  - deploy-prod        # **Manual approval** → VM prod (compose pull + up -d --remove-orphans)
```

### Gates de Seguridad (Obligatorios)

| Gate | Herramienta | Fallo = Block |
|------|-------------|---------------|
| **SAST** | CodeQL / Semgrep / SonarQube | Sí (high/critical) |
| **SCA (Deps)** | Dependabot / Renovate + `npm audit` / `cargo audit` / `trivy fs` | Sí (critical) |
| **Container Scan** | Trivy / Grype / Syft (SBOM) | Sí (critical/high en runtime deps) |
| **Secrets Scan** | Gitleaks / TruffleHog | Sí (cualquier hallazgo) |
| **IaC Scan** | Checkov / tfsec (si Terraform para VM) | Sí (high) |

### Estrategia de Despliegue (Zero-Downtime en Compose)

```bash
# En VM (script deploy.sh)
docker compose pull app
docker compose up -d --no-deps --scale app=2 app  # Inicia nueva versión
# Healthcheck pasa → Caddy detecta 2 upstreams → round-robin
docker compose up -d --no-deps --scale app=1 app  # Quita versión vieja
docker image prune -f
```

**Rollback**: `docker compose up -d --no-deps app` (imagen anterior en cache) → <30 segundos.

---

## Backup y Recuperación (RNF-040 a RNF-043)

### Estrategia (Basada en 04_database_analysis.md)

| Componente | Frecuencia | Retención | Ubicación | Cifrado |
|------------|------------|-----------|-----------|---------|
| **Full Backup** (pgBackRest) | Diario 02:00 | 14 días | Disco local (distinto a PG) + **S3/MinIO (replicado)** | AES-256 (pgBackRest `--repo1-cipher-type=aes-256`) |
| **Incremental** | Cada 6h | 7 días | Mismo repo | AES-256 |
| **WAL Archive** | Continuo (streaming) | 7 días | Mismo repo | AES-256 |
| **Config/Secrets** | Git (secrets en Vault) | Histórico | Git + Vault | N/A (secrets no en Git) |

### Prueba de Restauración (Obligatoria)

| Frecuencia | Escenario | RTO Objetivo |
|------------|-----------|--------------|
| **Mensual** | Restore full + incremental + WAL → nueva VM/DB → validar integridad (checksums, row counts) | **< 30 min** |
| **Trimestral** | Failover real: promote standby → app reconecta → validar escritura | **< 10 min** |
| **Anual** | Disaster recovery: región distinta (snapshot VM + restore S3) | **< 2 horas** |

**Runbook documentado** en `docs/runbooks/` (promote standby, restore backup, recreate VM, rotate secrets).

---

## Configuración por Entorno (RNF-062, D6)

| Archivo | Contenido | Ejemplo |
|---------|-----------|---------|
| `.env.dev` | `APP_ENV=development`, `LOG_LEVEL=debug`, `DB_POOL=5`, `JWT_EXPIRY=1h` | Local dev |
| `.env.test` | `APP_ENV=test`, `LOG_LEVEL=warn`, `DB_POOL=10`, `JWT_EXPIRY=15m`, `QR_PROVIDER=mock` | CI/CD test |
| `.env.prod` | `APP_ENV=production`, `LOG_LEVEL=info`, `DB_POOL=20`, `JWT_EXPIRY=15m`, `QR_PROVIDER=mercadopago` | Prod (secrets aparte) |

**Carga en Compose**: `docker compose --env-file .env.prod up -d`

**Validación**: Script `validate-config.sh` en entrypoint app (falla rápido si falta config crítica).

---

## Escalabilidad a 10x (RNF-011) — Plan de Evolución

| Fase | Trigger | Cambio Infra | Esfuerzo |
|------|---------|--------------|----------|
| **1. Vertical** | CPU >70% sostenido, memoria >80% | Resize VM (4→8→16 vCPU, 16→32→64 GB) | Bajo (minutos, reboot) |
| **2. Read Replica PG** | Latencia consultas reportes >500ms | Añadir `pg-read-replica` (async) → app usa réplica para reportes | Medio (config PG + app) |
| **3. App Horizontal** | Latencia p99 >1s, 500 tx/h sostenido | **Load Balancer (HAProxy/Traefik) + 2-3 app replicas** + sticky sessions para SSE | Medio (session affinity, shared cache Redis) |
| **4. Managed PG** | Ops DB carga equipo | Migrar a Cloud SQL / RDS / Neon (pg_dump/pg_restore o replicación) | Alto (planificado, ventana mantenimiento) |
| **5. Multi-AZ** | RPO=0 estricto, 99.95% SLA | VMs en 2 AZs + PG managed multi-AZ | Alto (arquitectura red) |

**No se salta a fase 3+ sin necesidad real** (YAGNI). Monolito modular (Alternativa C preparado) permite extraer módulos si fase 3+ llega.

---

## Decisiones Pendientes (Bloqueantes para Infra Final)

| # | Decisión | Dueño | Impacto |
|---|----------|-------|---------|
| INF-01 | **RPO/RTO numéricos confirmados** (ver P-05, SEC-Q1) | Producto/Técnico | Define réplica síncrona vs asíncrona, frecuencia backup, runbook |
| INF-02 | **Proveedor cloud / VM** (Hetzner, DigitalOcean, AWS Lightsail, Azure, GCP, on-prem) | Técnico/Gestión | Define costo, latencia, SLA, backup region |
| INF-03 | **Observabilidad managed vs self-hosted** (Grafana Cloud / Datadog / Loki+Prom local) | Técnico | Costo vs control. Self-hosted en VM suficiente para inicio |
| INF-04 | **Dominio / TLS** (subdominio, wildcard, cert management) | Producto/Técnico | Caddy auto (Let's Encrypt) funciona, pero validar rate limits LE |
| INF-05 | **Notificaciones WhatsApp/Email provider** (ver P-08) | Producto | Define secrets, queue, retry, dead letter |

---

## Próximos Pasos

1. **`architecture-reviewer`** → Revisión adversarial completa contra RF/RNF (07_architecture_review.md)
2. **`solution-leader`** → Consolidación + Matriz decisión + ADR + Plan de pruebas (08_recomendacion_final.md)

---

**Skill utilizado**: `infrastructure-evaluation`  
**Agente responsable**: `devops-architect`  
**Estado**: `HUMAN_STATUS: PENDING` — Requiere **INF-01 (RPO/RTO)** e **INF-02 (proveedor cloud/VM)** para finalizar runbooks y sizing definitivo.
# Análisis DevOps — D-06 Despliegue y D-08 Observabilidad

Fecha: 2026-09-17
Fuente: RNF.md, 02_decision_scope.md, 03_architecture_options.md

---

## D-06. Estrategia de Despliegue

**Drivers**: RNF-060 (Linux), RNF-061 (contenerización), RNF-062 (separación de entornos), CA-09 (sin K8s sin justificación), RNF-011 (evaluar 10x)

**Contexto del sistema**: Modular monolith (Alternativa B), posiblemente con Redis para cola de pagos. Dos a tres contenedores máximo: app, PostgreSQL, Redis (opcional).

---

### Opción 1: Docker Compose (despliegue directo)

**Cómo funciona**: Un `docker-compose.yml` por entorno (o uno con profiles). App + DB + Redis empaquetados. Deploy con `docker compose up -d`.

| Aspecto | Evaluación |
|---------|------------|
| **Complejidad operativa** | Baja. Un archivo YAML, comandos familiares, sin dependencias externas |
| **Separación de entornos** | Archivos separados (`docker-compose.dev.yml`, `docker-compose.prod.yml`) o variable `COMPOSE_FILE`. Cumple RNF-062 |
| **Escalabilidad a 10x** | Vertical: más CPU/RAM al host. Horizontal: `--scale app=3` + load balancer externo. Funciona hasta ~50k tx/hora con host adecuado. Más allá, requiere migrar a orquestador |
| **Rollback** | Manual: `docker compose down` + `docker compose up` con imagen anterior. Sin health checks automáticos |
| **Portabilidad** | Alta en Linux (RNF-060). Sin dependencias de cloud |
| **Costo** | Cero licencias. Infraestructura mínima |

**Trade-offs**:
- Pro: Arranca en minutos. Ideal para dev/test. Pocos puntos de fallo
- Contra: Sin auto-healing. Rollback manual. Escalado manual. Sin service discovery automático

**Riesgos**:
- Si el host falla, no hay reprogramación automática
- A 10x (250k tx/hora), el monolito en un solo host puede quedarse corto sin estrategia de réplicas

**Aplicabilidad**: Suficiente para MVP y entornos dev/test. Production viable si el tráfico se mantiene bajo 50k tx/hora y se acepta manualidad en operaciones.

---

### Opción 2: Docker + Orquestador ligero (K3s/k3d)

**Cómo funciona**: K3s (Kubernetes ligero, binario único, <100MB) o k3d (K3s en Docker). Orquestación real pero sin la complejidad de K8s completo.

| Aspecto | Evaluación |
|---------|------------|
| **Complejidad operativa** | Media. Requiere conocimiento de Kubernetes (pods, services, deployments, ingress). Curva de aprendizaje significativa si el equipo no conoce K8s |
| **Separación de entornos** | Namespaces por entorno (`dev`, `test`, `prod`). ConfigMaps/Secrets por namespace. Cumple RNF-062 con ventaja |
| **Escalabilidad a 10x** | Horizontal nativa: HPA (Horizontal Pod Autoscaler), réplicas automáticas. Soporta 250k tx/hora con 3-5 réplicas del monolito + DB separada |
| **Rollback** | Nativo: `kubectl rollout undo`. Health checks automáticos. Rolling updates sin downtime |
| **Portabilidad** | K3s corre en cualquier Linux (RNF-060). k3d útil para dev en macOS/Windows |
| **Costo** | Cero licencias. Pero costo de aprendizaje y mantenimiento de manifests YAML |

**Trade-offs**:
- Pro: Auto-healing, rolling updates, service discovery, secrets management nativo. Preparado para crecimiento
- Contra: Complejidad significativa para 2-3 contenedores. Over-engineering si el sistema nunca escala horizontalmente

**Riesgos**:
- **CA-09**: Requiere justificación. Si el sistema se mantiene como monolito con <50k tx/hora, K3s es innecesario
- Mantenimiento de manifests YAML agrega superficie de error
- Debugging en K8s es más complejo que en Docker Compose
- Si el equipo no tiene experiencia K8s, el tiempo de setup y troubleshooting puede superar el beneficio

**Justificación necesaria**: Solo si se espera escalar horizontalmente (múltiples réplicas del monolito) o si se necesitan features específicas de K8s (auto-scaling, self-healing, rolling updates automáticos).

---

### Opción 3: Docker + CI/CD Pipeline (GitHub Actions / GitLab CI)

**Cómo funciona**: Docker para empaquetar, pipeline CI/CD para build → test → push imagen → deploy automatizado. Puede combinarse con Opción 1 o 2.

| Aspecto | Evaluación |
|---------|------------|
| **Complejidad operativa** | Media. Requiere configurar pipeline, registries, secrets en CI/CD |
| **Separación de entornos** | Pipeline con stages por entorno. Promoción automática de dev → test → prod. Cumple RNF-062 con trazabilidad |
| **Escalabilidad a 10x** | Independiente del deploy target. Funciona con Compose o K3s |
| **Rollback** | Automatizado: redeploy de imagen anterior. Tags en registry permiten rollback inmediato |
| **Portabilidad** | Depende del runner CI. GitHub Actions/Linux runners cumplen RNF-060 |
| **Costo** | GitHub Actions: gratuito para repos públicos, 2000 min/mes gratis para privados. GitLab CI: 400 min/mes gratis |

**Trade-offs**:
- Pro: Trazabilidad completa, rollback automatizado, consistencia entre entornos. El pipeline es el sistema de deploy
- Contra: Agrega dependencia del servicio CI/CD. Setup inicial no trivial

**Riesgos**:
- Lock-in al proveedor CI/CD (mitigable con GitLab auto-hosted o Gitea)
- Si el pipeline falla, el deploy se bloquea
- Secrets en CI/CD requieren configuración cuidadosa (RNF-034)

**Aplicabilidad**: Complemento casi obligatorio para cualquier opción. No es mutuamente excluyente con Opción 1 o 2.

---

### Opción 4: Nix/Flox (entornos reproducibles)

**Cómo funciona**: Nix define entornos declarativamente. Flox agrega capa de usuario sobre Nix. Garantiza que dev, test y prod ejecuten exactamente lo mismo.

| Aspecto | Evaluación |
|---------|------------|
| **Complejidad operativa** | Alta. Curva de aprendizaje de Nix es pronunciada. Ecosistema menos maduro que Docker |
| **Separación de entornos** | Entornos declarativos y reproducibles. Cumple RNF-062 con garantía fuerte |
| **Escalabilidad a 10x** | No escala por sí solo. Es complemento, no reemplazo de Docker |
| **Rollback** | Nativo: Nix mantiene generaciones. Rollback instantáneo del sistema completo |
| **Portabilidad** | Nix corre en Linux (RNF-060). Flox/Nix en macOS. Pero no es contenedor (RNF-061 requiere Docker) |
| **Costo** | Cero licencias. Pero costo de aprendizaje alto |

**Trade-offs**:
- Pro: Reproducibilidad garantizada. Rollback instantáneo. Sin "works on my machine"
- Contra: No contenedor (RNF-061). Curva de aprendizaje alta. Comunidad más pequeña. Over-engineering para el 90% de los casos

**Riesgos**:
- **No cumple RNF-061 directamente** — requiere combinarse con Docker para contenerización
- Equipo puede no tener experiencia con Nix
- Ecosistema de paquetes puede no cubrir todas las dependencias

**Aplicabilidad**: No recomendado como estrategia principal. Considerar solo si el equipo ya usa Nix/Flox o si la reproducibilidad extrema es critical.

---

### Matriz Comparativa D-06

| Criterio | Docker Compose | K3s/k3d | CI/CD Pipeline | Nix/Flox |
|----------|:-:|:-:|:-:|:-:|
| Cumple RNF-061 (contenedor) | ✅ | ✅ | ✅ | ❌ directo |
| Cumple RNF-062 (entornos) | ✅ básico | ✅ namespaces | ✅ stages | ✅ declarativo |
| Cumple RNF-060 (Linux) | ✅ | ✅ | ✅ | ✅ |
| CA-09 (sin K8s sin justificación) | ✅ | ⚠️ requiere justificación | ✅ | ✅ |
| Escalabilidad 10x | ⚠️ manual | ✅ nativa | ✅ (depende del target) | ❌ |
| Rollback | ⚠️ manual | ✅ nativo | ✅ automatizado | ✅ nativo |
| Complejidad | Baja | Media-Alta | Media | Alta |
| Curva de aprendizaje | Mínima | Alta | Media | Muy alta |

---

## D-08. Estrategia de Observabilidad

**Drivers**: RNF-050 (logs estructurados), RNF-051 (métricas), RNF-052 (correlación distribuida), costo, complejidad operativa

**Contexto**: Modular monolith con 2-3 contenedores. No es sistema distribuido complejo, pero RNF-052 pide correlación "cuando corresponda" — aplica si se separan workers de pagos o si POS y web son procesos distintos.

---

### Opción 1: OpenTelemetry + Backend (Grafana Stack o SigNoz)

**Cómo funciona**: OpenTelemetry como estándar de instrumentación (SDK en la app). Exporta logs, métricas y traces a un backend: Grafana (Loki + Tempo + Prometheus) o SigNoz (all-in-one basado en OTel).

| Aspecto | Evaluación |
|---------|------------|
| **Cumple RNF-050 (logs estructurados)** | ✅. OTel SDK genera logs estructurados. Loki los indexa |
| **Cumple RNF-051 (métricas)** | ✅. OTel Metrics → Prometheus → Grafana dashboards. Latencia, errores, disponibilidad, volumen |
| **Cumple RNF-052 (correlación)** | ✅. OTel traces propagan contexto entre servicios. Grafana Tempo permite correlación trace ↔ log |
| **Complejidad operativa** | Media. OTel SDK es instrumentación no invasiva. Backend (Grafana stack) requiere 3-4 contenedores adicionales |
| **Costo** | Cero licencias (open source). Costo de infra: 2-4GB RAM adicionales para stack de observabilidad |
| **Vendor lock-in** | Bajo. OTel es estándar CNCF. Backend intercambiable |

**Trade-offs**:
- Pro: Estándar abierto, preparado para crecimiento, correlación nativa. Grafana es industry standard
- Contra: Stack de observabilidad (Loki + Tempo + Prometheus + Grafana) son 4 contenedores más. Para un monolito con 2-3 servicios puede ser excesivo

**Riesgos**:
- Overhead de OTel SDK en la app (~2-5% CPU)
- Si solo se necesitan logs básicos, el stack completo es over-engineering
- Retención de traces en Tempo requiere almacenamiento

**SigNoz como alternativa simplificada**: Un solo contenedor que integra logs + métricas + traces. Menor complejidad que Grafana stack separado, pero menos flexible.

**Aplicabilidad**: Recomendado si se espera crecimiento distribuido o si RNF-052 es estricto.

---

### Opción 2: ELK Stack (Elasticsearch + Logstash + Kibana)

**Cómo funciona**: App escribe logs JSON. Logstash/Filebeat los procesa. Elasticsearch los indexa. Kibana los visualiza.

| Aspecto | Evaluación |
|---------|------------|
| **Cumple RNF-050 (logs estructurados)** | ✅. Excelente en logs. Elasticsearch es el mejor motor de búsqueda de logs |
| **Cumple RNF-051 (métricas)** | ⚠️ Limitado. ELK no es herramienta de métricas. Necesita complemento (Metricbeat, Prometheus) |
| **Cumple RNF-052 (correlación)** | ⚠️ Parcial. Correlación por trace ID en logs, pero no tiene tracing nativo como OTel |
| **Complejidad operativa** | Alta. Elasticsearch es pesado (mínimo 2-4GB RAM solo para él). Logstash consume CPU. 3+ contenedores |
| **Costo** | Cero licencias (open source, license SSPL en versiones recientes). Infra costosa: Elasticsearch necesita disco SSD y RAM significativa |
| **Vendor lock-in** | Medio. Elasticsearch tiene ecosistema propio |

**Trade-offs**:
- Pro: Excelente para logs y búsqueda. Maduro, battle-tested. Kibana es potente para análisis de logs
- Contra: Pesado para un sistema de 2-3 contenedores. No es solución de métricas. Complejidad operativa alta

**Riesgos**:
- Elasticsearch consume recursos significativos (RAM, disco, CPU)
- Licencia SSPL puede限制 uso comercial en versiones recientes
- Para un monolito, ELK es over-engineering claro

**Aplicabilidad**: No recomendado. Diseñado para sistemas con miles de fuentes de logs, no para un monolito con 2-3 contenedores.

---

### Opción 3: Solución Managed (Datadog / New Relic / Dynatrace)

**Cómo funciona**: Agent instalado en el host o contenedor. Envía logs, métricas y traces a la plataforma cloud. Dashboard y alertas en la UI del vendor.

| Aspecto | Evaluación |
|---------|------------|
| **Cumple RNF-050 (logs estructurados)** | ✅. Ingesta de logs estructurados con parsing automático |
| **Cumple RNF-051 (métricas)** | ✅. Dashboards pre-construidos, alertas, SLI/SLO |
| **Cumple RNF-052 (correlación)** | ✅. Traces, logs y métricas correlacionados en una UI |
| **Complejidad operativa** | Baja. Un agente, configuración mínima. Todo managed |
| **Costo** | **Alto**. Datadog: ~$23/host/mes (infra) + $0.10/GB logs + $31/host/mes (APM). Para 3 hosts: ~$200-500/mes. Escala rápido con volumen |
| **Vendor lock-in** | Alto. Migrar fuera de Datadog es doloroso |

**Trade-offs**:
- Pro: Setup en minutos. Best-in-class UX. Sin mantenimiento de infra de observabilidad
- Contra: Costo que escala con hosts + volumen de logs. Vendor lock-in fuerte. Datos salen del entorno (RNF-034 puede aplicar a logs sensibles)

**Riesgos**:
- **Costo**: A 10x volumen, los costos de logs y traces pueden ser significativos ($1000+/mes)
- **Dependencia externa**: Si Datadog tiene outage, se pierde visibilidad
- **Privacidad de datos**: Logs con datos de clientes salen de la infraestructura propia
- **RNF-034**: Secretos en logs deben filtrarse antes de enviar al vendor

**Aplicabilidad**: Viable si el presupuesto lo permite y se prioriza tiempo de setup sobre costo. No recomendado si el sistema debe ser autónomo.

---

### Opción 4: Logs Estructurados + Métricas Básicas (mínimo)

**Cómo funciona**: App escribe logs JSON a stdout (RNF-050). Métricas básicas con un endpoint `/metrics` que Prometheus scrapea (RNF-051). Sin tracing distribuido (RNF-052 se cumple "cuando corresponda" — para monolito, no aplica).

| Aspecto | Evaluación |
|---------|------------|
| **Cumple RNF-050 (logs estructurados)** | ✅. Logs JSON a stdout. Docker/K8s los captura |
| **Cumple RNF-051 (métricas)** | ✅ básico. Endpoint `/metrics` + Prometheus + Grafana básico (1 contenedor) |
| **Cumple RNF-052 (correlación)** | ⚠️ No. Sin tracing distribuido. Para monolito, esto es aceptable — RNF-052 dice "cuando corresponda" |
| **Complejidad operativa** | Baja. Logs a stdout. Prometheus + Grafana (2 contenedores) para métricas |
| **Costo** | Cero. Todo open source. Infra mínima |
| **Vendor lock-in** | Cero |

**Trade-offs**:
- Pro: Mínimo operativo. Cumple RNF-050 y RNF-051. Sin overhead significativo
- Contra: Sin correlación distribuida. Debugging de requests cross-module requiere grep en logs. Sin dashboards avanzados sin configuración adicional

**Riesgos**:
- Si el sistema se distribuye (workers separados, microservicios), la falta de tracing será un problema
- Logs a stdout pueden perderse si no hay rotación/recolección

**Aplicabilidad**: Recomendado como punto de partida para monolito. Escalar a Opción 1 si se distribuye.

---

### Matriz Comparativa D-08

| Criterio | OTel + Grafana/SigNoz | ELK Stack | Managed (Datadog) | Logs + Métricas básicas |
|----------|:-:|:-:|:-:|:-:|
| RNF-050 (logs) | ✅ | ✅ | ✅ | ✅ |
| RNF-051 (métricas) | ✅ | ⚠️ limitado | ✅ | ✅ básico |
| RNF-052 (correlación) | ✅ | ⚠️ parcial | ✅ | ❌ |
| Complejidad operativa | Media | Alta | Baja | Baja |
| Costo infra | Medio | Alto (RAM/disco) | Alto (suscripción) | Bajo |
| Vendor lock-in | Bajo | Medio | Alto | Ninguno |
| Overhead en app | ~2-5% CPU | Bajo (async) | Bajo (agent) | Mínimo |

---

## Interacción D-06 × D-08

La estrategia de observabilidad afecta la de despliegue:

| Combinación | Impacto |
|-------------|---------|
| Docker Compose + Logs básicos | Mínimo: 2-3 contenedores totales. Simple y barato |
| Docker Compose + Grafana Stack | 6-7 contenedores. Puede ser pesado paraCompose |
| K3s + OTel/Grafana | Natural: K3s maneja bien los contenedores de observabilidad como DaemonSets |
| Cualquier deploy + Datadog | Un agente. Independiente del orquestador |
| CI/CD + cualquier obs | Complemento. Pipeline puede configurar alertas y dashboards |

---

## Resumen y Recomendación Preliminar

### D-06 — Despliegue

**Punto de partida**: Docker Compose para dev/test/prod. Cumple RNF-061, RNF-062, RNF-060. CA-09 no aplica (sin K8s).

**Escalar a K3s solo si**: Se necesitan múltiples réplicas del monolito ( >50k tx/hora), auto-healing, o rolling updates automáticos. Justificar en ADR.

**CI/CD**: Casi obligatorio como complemento. GitHub Actions o GitLab CI. Agrega trazabilidad sin cambiar el deploy target.

**Nix/Flox**: Descartar. No cumple RNF-061 directamente. Over-engineering para el caso.

### D-08 — Observabilidad

**Punto de partida**: Logs estructurados a stdout + Prometheus/Grafana básico para métricas. Cumple RNF-050, RNF-051. RNF-052 no aplica para monolito (correlación "cuando corresponda").

**Escalar a OTel + Grafana/SigNoz solo si**: Se distribuye el sistema (workers separados, múltiples servicios) o si RNF-052 se vuelve obligatorio.

**ELK**: Descartar. Pesado, costoso, over-engineering para este sistema.

**Datadog**: Viable si hay presupuesto y se prioriza tiempo. Riesgo de lock-in y costos crecientes.

---

## Preguntas para el Solution Leader

1. ¿El equipo tiene experiencia con Kubernetes? Si no, K3s agrega riesgo sin beneficio claro
2. ¿Se espera que el sistema se distribuya en servicios separados en el futuro? Determina si RNF-052 aplica hoy o es YAGNI
3. ¿Hay presupuesto para soluciones managed (Datadog)? Define si se descarta Opción 3
4. ¿Cuántos hosts/serveres están disponibles? Afecta si se puede correr stack de observabilidad local o se necesita managed

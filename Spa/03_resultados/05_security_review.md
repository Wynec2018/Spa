# Revisión de Seguridad Arquitectónica — Spa Cielo

Fecha: 2026-10-09  
Fuente: `03_resultados/01_requirements_analysis.md`, `03_resultados/03_architecture_options.md`, `03_resultados/04_database_analysis.md`  
Arquitectura objetivo: **Monolito Modular (Alternativa A)** + **PostgreSQL 16+**

---

## Activos y Superficies de Ataque

### Activos Críticos (CIA: Confidencialidad, Integridad, Disponibilidad)

| Activo | Clasificación | CIA | Justificación |
|--------|---------------|-----|---------------|
| **Credenciales usuarios** (hash password, refresh tokens) | Crítico | C, I | Acceso total al sistema si comprometidos |
| **Datos de pagos QR** (payload, estado, comprobantes) | Crítico | C, I, A | Fraude financiero, pérdida dinero |
| **Asignación recursos** (cama/trabajadora, booking) | Alto | I, A | Sobreventa, doble asignación, caos operativo |
| **Datos clientes** (DNI, nombre, teléfono, email) | Alto | C, I | PII, regulación protección datos |
| **Auditoría / Trazabilidad** (audit_log, pg_audit) | Alto | I | No repudio, forense, compliance |
| **Configuración operativa** (app_config: timeouts, límites, proveedores) | Medio | I, A | Cambios maliciosos → denegación servicio o bypass controles |
| **Secretos infraestructura** (DB password, JWT secret, QR provider keys) | Crítico | C, I | Compromiso total sistema |

### Superficies de Ataque (Entry Points)

| Superficie | Protocolo | Autenticación | Riesgo Inherent |
|------------|-----------|---------------|-----------------|
| **API Web Pública** (reservas cliente) | HTTPS/REST | JWT (cliente) / Anónima (consulta disponibilidad) | Alto: expuesta a internet, bots, scraping |
| **API Staff** (secretaría, trabajadoras) | HTTPS/REST + SSE | JWT (roles: secretaria, trabajadora) | Medio: red interna/VPN, pero dispositivos personales |
| **API Admin** (superusuario) | HTTPS/REST | JWT (role: superusuario) + MFA | Crítico: control total |
| **Webhook / Callback QR** | HTTPS/POST | HMAC / Signature verification | Alto: entrada no autenticada usuario, proveedor externo |
| **Admin DB directo** | PostgreSQL wire | Certificados TLS + usuario/contraseña | Crítico: bypass app layer |
| **Contenedor / Host** | SSH / Docker API | Claves SSH, rootless containers | Medio: infra compromise |

---

## Autenticación y Autorización (RNF-030 a RNF-035, RF-001, D5)

### Modelo de Roles (RBAC + Claims)

| Rol | Permisos (Resumen) | MFA Requerido | Sesión Máx |
|-----|-------------------|---------------|------------|
| **Superusuario** (Dueña) | CRUD todo, reportes globales, auditoría, gestión usuarios, config, backup trigger | **Sí (TOTP)** | 4h / 30d refresh |
| **Secretaria** | Check-in, validar pagos QR, ver estado recursos, gestionar bookings, clientes | No (opcional) | 8h / 30d refresh |
| **Trabajadora** | Ver sus bookings, cambiar estado (libre/ocupada/no disp), ver cliente asignado | No | 12h / 30d refresh |
| **Cliente** | Crear/ver/cancelar sus reservas, ver servicios, pagar QR, notificaciones | No | 24h / 90d refresh |

### Estrategia JWT (Stateless, Rotación)

| Componente | Especificación |
|------------|----------------|
| **Algoritmo** | RS256 (clave asimétrica) — permite rotación sin invalidar todos; HS256 solo si single-instance |
| **Access Token** | 15 min expiry, `aud`, `iss`, `sub` (user_id), `role`, `branch_id`, `permissions` (array), `jti` |
| **Refresh Token** | 30-90 días, almacenado en DB (`refresh_token` table: hash, user_id, device_fingerprint, revoked_at, expires_at), **rotación obligatoria** (nuevo refresh en cada uso, invalidar anterior) |
| **Revocation** | Lista negra (blocklist) en Redis/DB por `jti` para access tokens; refresh token revocado en DB |
| **Almacenamiento cliente** | **HttpOnly Secure SameSite=Strict Cookie** (no localStorage) — mitiga XSS |
| **Key Rotation** | Clave privada RSA rotada cada 90 días; JWKS endpoint público para validación distribuida |

### Autorización (Mínimo Privilegio)

- **API Gateway / Middleware**: Validación JWT + verificación `role` + `branch_id` (aislamiento multi-sucursal)
- **Capa Aplicación (Use Cases)**: `AuthorizationService.can(user, action, resource)` — decisiones finas (ej: secretaria solo valida pagos de su sucursal)
- **Base de Datos**: **RLS (Row Level Security)** en PostgreSQL — política `branch_id = current_setting('app.current_branch_id')` + `role` para defensa en profundidad
- **Principio**: Denegar por defecto; permitir solo lo explícito en matriz permisos

### Matriz de Permisos (Ejemplo)

| Acción | Superusuario | Secretaria | Trabajadora | Cliente |
|--------|-------------|------------|-------------|---------|
| `booking.create` (web) | ✅ | ✅ | ❌ | ✅ (propio) |
| `booking.create` (walk-in) | ✅ | ✅ | ❌ | ❌ |
| `booking.cancel` | ✅ | ✅ | ✅ (propio) | ✅ (propio, antes TTL) |
| `payment.qr_verify` | ✅ | ✅ | ❌ | ❌ |
| `resource.state_change` | ✅ | ✅ | ✅ (propia cama/trabajadora) | ❌ |
| `user.manage` | ✅ | ❌ | ❌ | ❌ |
| `config.manage` | ✅ | ❌ | ❌ | ❌ |
| `audit.read` | ✅ | ❌ | ❌ | ❌ |
| `report.view` | ✅ | ✅ (operativos) | ❌ | ❌ |

---

## Gestión de Secretos (RNF-034)

### Clasificación y Almacenamiento

| Secreto | Tipo | Almacenamiento | Rotación | Acceso App |
|---------|------|----------------|----------|------------|
| **DB Password** | Credencial | **Docker Secret** / Vault / Cloud Secret Manager | 90 días | Runtime via file `/run/secrets/db_password` |
| **JWT Private Key** | Clave asimétrica | **Vault / HSM** / Docker Secret (PEM) | 90 días | Carga inicio + cache memoria (no disco) |
| **QR Provider Secret/Key** | API Key / HMAC | **Vault / Secret Manager** | Según proveedor | Runtime via env/file |
| **WhatsApp/Email API Key** | API Key | **Vault / Secret Manager** | 90 días | Runtime |
| **TLS Cert/Key** | Certificado | **Let's Encrypt (auto)** / Cert Manager / Vault | 90 días (auto) | Terminación en Reverse Proxy (nginx/Traefik/Caddy) |

### Reglas Obligatorias
- **NUNCA** en código, config files versionados, variables de entorno en Dockerfile, ni logs
- **Siempre** inyección en runtime (Docker secrets, K8s secrets, Vault Agent, Cloud provider)
- **Auditoría** de acceso a secretos (Vault audit log, CloudTrail)
- **Rotación automatizada** donde sea posible (DB password, TLS certs)

---

## Seguridad de APIs (RNF-035, RF-012)

### Hardening REST Endpoints

| Control | Implementación |
|---------|----------------|
| **TLS 1.2+** | Obligatorio en todo entorno (dev incluido). Terminación en Reverse Proxy. HSTS, CSP headers |
| **Rate Limiting** | Por IP + por usuario (token bucket). Login: 5 req/min. API general: 100 req/min. Webhook QR: 50 req/min por proveedor |
| **Input Validation** | Schema validation (Zod / Joi / Pydantic / FluentValidation) en **entrada** de cada use case. Never trust client |
| **Output Encoding** | JSON encoding automático (framework). No HTML en API |
| **CORS** | `Access-Control-Allow-Origin`: solo dominios permitidos (web app, admin panel). No `*` en prod |
| **Security Headers** | `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: geolocation=(), microphone=()` |
| **Error Handling** | **Nunca** stack traces en respuesta. Error genérico cliente + log detallado servidor (correlation_id) |
| **Idempotency** | Header `Idempotency-Key` obligatorio en `POST /bookings`, `POST /payments`, `POST /checkin`. Tabla deduplicación (ver 04_database_analysis.md) |

### Webhook / Callback QR (Proveedor Externo)

| Riesgo | Mitigación |
|--------|------------|
| **Spoofing** (falso callback) | **Verificación firma HMAC** (SHA256) con secreto compartido. Timestamp + nonce en payload para replay protection |
| **Replay attacks** | Timestamp ±5 min + nonce único (tabla `webhook_nonce` con TTL 10 min) |
| **Disponibilidad** | Queue interno (outbox pattern) → procesamiento async. 200 OK inmediato a proveedor |
| **Timeout proveedor** | Circuit breaker + retry exponencial (max 3) + dead letter queue para reconciliación manual |

---

## Auditoría y Trazabilidad (RF-015, RNF-030+, D5, 04_database_analysis.md)

### Qué Auditar (Eventos Críticos)

| Categoría | Eventos | Detalle Mínimo |
|-----------|---------|----------------|
| **Autenticación** | Login éxito/fallo, logout, refresh token uso/rotación, MFA challenge, bloqueo cuenta | user_id, IP, user_agent, resultado, timestamp, correlation_id |
| **Autorización** | Acceso denegado (403), escalada privilegio intento | user_id, recurso, acción, policy evaluada |
| **Pagos** | Creación pago, verificación QR (aprobado/rechazado), reembolso, idempotencia hit | payment_id, booking_id, monto, estado, actor (secretaria/sistema), correlation_id |
| **Reservas/Recursos** | Booking create/update/cancel/expire, asignación/liberación cama+trabajadora, check-in/out | booking_id, resource_ids, estado anterior/nuevo, actor |
| **Usuarios/Clientes** | Creación, modificación rol, desactivación, cambio password, MFA enable/disable | target_user_id, actor, campos cambiados |
| **Configuración** | Cambio `app_config` (timeouts, límites, proveedores) | key, valor anterior/nuevo, actor |
| **Administración** | Backup trigger, restore, migración, cambio esquema | actor, comando, resultado |

### Implementación (Defensa en Profundidad)

1. **Nivel Aplicación**: Middleware `AuditMiddleware` → escribe `audit_log` (tabla) con `correlation_id` propagado desde request header
2. **Nivel Base de Datos**: `pg_audit` configurado para `SESSION` (DDL, ROLE, MISC) + `OBJECT` (SELECT/INSERT/UPDATE/DELETE en tablas sensibles: `payment`, `booking`, `resource`, `user`, `audit_log`)
3. **Nivel Infraestructura**: Logs de acceso (nginx/Traefik) + logs contenedor (stdout JSON structured) → agregación centralizada (Loki/ELK)

### Protección de Auditoría
- Tabla `audit_log`: **Append-only** (sin UPDATE/DELETE permitidos por app). Solo `TRUNCATE` por job de retención (superusuario + auditoría del truncate)
- `pg_audit` logs: Escritura solo en syslog/file, no en BD app
- **Integridad**: Hash encadenado (hash fila anterior + datos fila actual) opcional para detección manipulación

---

## Protección de Datos (PII, Pagos)

### Datos Personales (Clientes, Usuarios)

| Dato | Tratamiento |
|------|-------------|
| **Password** | **Argon2id** (memory=64MB, iterations=3, parallelism=4) — nunca bcrypt/scrypt/PBKDF2 |
| **Email/Telefono** | Cifrado en reposo (columna `pgcrypto` / `ENCRYPT()`) si riesgo alto; sino pseudonimización en logs |
| **DNI / Documento** | **Cifrado en reposo obligatorio** (AES-256-GCM via `pgcrypto` o app-layer). Índice hash para lookup (no reversible) |
| **Nombre** | Plano (necesario operación), pero no en logs |

### Datos de Pago

| Dato | Tratamiento |
|------|-------------|
| **QR Payload** | Plano (necesario validación), pero **no loggear completo** (mask en logs) |
| **Comprobante imagen** | Almacenamiento externo (S3/MinIO) con **pre-signed URL** corta vida. No en DB |
| **Monto, moneda, estado** | Plano en DB (operación), auditoría completa |
| **Tokenización** | Si proveedor soporta: guardar solo token, no PAN/datos tarjeta |

### Cifrado en Tránsito y Reposo

| Capa | Estándar |
|------|----------|
| **TLS** | 1.3 preferido, 1.2 mínimo. Certificados válidos (Let's Encrypt / CA empresarial). mTLS para réplica PG |
| **DB en reposo** | Volumen encriptado (LUKS / AWS EBS encryption / Cloud SQL encryption). `pgcrypto` para columnas sensibles |
| **Backups** | `pgBackRest` con `--repo1-cipher-type=aes-256` + clave en Vault |
| **Secrets en tránsito** | Vault TLS, Docker secrets (tmpfs), no env vars |

---

## Resiliencia ante Fraude y Abuso

### Vectores de Fraude Específicos Spa Cielo

| Vector | Descripción | Mitigación |
|--------|-------------|------------|
| **Reserva falsa + no-show** | Cliente reserva, no aparece, bloquea cama/trabajadora | TTL reserva "Pendiente" corto (configurable, default 15 min). Job expiración automática. Penalización tras N no-shows (config) |
| **Pago QR falso / comprobante manipulado** | Cliente sube imagen editada / QR inválido | Validación **humana obligatoria** (secretaria) + verificación criptográfica HMAC del proveedor + timeout auto-rechazo |
| **Reintento pago double-charge** | Red inestable → doble click pagar | **Idempotency-Key** obligatorio en creación pago. Tabla deduplicación + constraint único |
| **Escalada privilegio** | Cliente intenta acceder API secretaria | JWT `role` claim + middleware + RLS en DB. Testing automatizado (OWASP ZAP / custom) |
| **Enumeración usuarios/recursos** | API expone IDs secuenciales | **UUID v7** (timestamp + random) para todos los IDs públicos. Rate limiting estricto en endpoints lista |
| **Scraping disponibilidad** | Bots consultan horarios libres | Rate limiting por IP + User-Agent. Cache público (CDN) para disponibilidad read-only |

### Detección de Anomalías (Observabilidad Seguridad)

| Métrica / Alerta | Umbral | Acción |
|------------------|--------|--------|
| **Login fallidos / IP** | >10 en 5 min | Bloqueo temporal IP (WAF / fail2ban) |
| **Login fallidos / usuario** | >5 en 15 min | Bloqueo cuenta 30 min + notificación email |
| **Webhook QR firma inválida** | >3 en 1 min | Alerta inmediata + bloqueo IP proveedor temporal |
| **Idempotency key colisiones** | >0 (inesperado) | Alerta: posible ataque replay |
| **Acceso denegado (403) spike** | >50 en 5 min | Alerta: reconocimiento / ataque |
| **Cambio config crítico** | Cualquier cambio | Alerta + notificación superusuario (auditoría) |

---

## Riesgos de Terceros (Supply Chain)

| Dependencia | Riesgo | Mitigación |
|-------------|--------|------------|
| **Proveedor QR / Pasarela Pago** | Credenciales expuestas, API cambiada, downtime | Capa anti-correlación (adapter pattern). Contract testing. Secrets rotables. Circuit breaker. Fallback manual (secretaria valida visual) |
| **WhatsApp / Email Provider** | API key leak, rate limit, spam complaints | Secrets en Vault. Queue async con retry. Opt-out tracking. Template approval |
| **Imagen Base Docker** | CVE en `postgres:16-alpine`, `node:20-alpine`, etc. | **Scaneo automático** (Trivy / Grype / Snyk) en CI. Rebuild semanal. `distroless` donde posible |
| **Dependencias App (npm/pypi/cargo/maven/go)** | Supply chain attack, CVE | **Dependabot / Renovate** + `npm audit` / `cargo audit` / `pip-audit` en CI. Lockfiles commitidos. `SBOM` (Syft) |
| **Reverse Proxy (nginx/Traefik/Caddy)** | CVE, config error | Imagen oficial, readonly rootfs, drop capabilities, security scanning |

---

## Matriz de Riesgos de Seguridad (Clasificación)

| # | Riesgo | Probabilidad | Impacto | Severidad | Mitigación Principal | Estado |
|---|--------|--------------|---------|-----------|---------------------|--------|
| SEC-01 | **Credenciales DB expuestas** (repo, logs, env) | Media | Crítico | **BLOQUEANTE** | Docker Secrets / Vault only. Zero env vars. Git pre-commit hooks (gitleaks) | Pendiente implementación |
| SEC-02 | **JWT secret débil / no rotado** | Media | Crítico | **BLOQUEANTE** | RS256 + Vault + rotación 90d + JWKS | Pendiente implementación |
| SEC-03 | **QR webhook spoofing** (pago falso) | Alta | Alto | **ALTO** | HMAC verification + timestamp + nonce + queue async | Diseñado (pendiente proveedor) |
| SEC-04 | **Doble asignación recurso** (race condition) | Alta | Crítico | **BLOQUEANTE** | PG `SELECT FOR UPDATE SKIP LOCKED` + TX ACID (04_database_analysis.md) | **Diseñado** |
| SEC-05 | **IDOR / BOLA** (acceso booking ajeno) | Media | Alto | **ALTO** | UUID v7 + AuthorizationService + RLS + testing | Diseñado |
| SEC-06 | **XSS via datos cliente** (nombre, notas) | Baja | Medio | **MEDIO** | Output encoding API (JSON), CSP, no HTML en frontend sin sanitize | Diseñado |
| SEC-07 | **Fuga PII en logs** (DNI, email, QR payload) | Media | Alto | **ALTO** | Structured logging con redaction (mask DNI, email, QR). No log request body completo | Pendiente implementación |
| SEC-08 | **Backup no cifrado / expuesto** | Baja | Crítico | **ALTO** | pgBackRest aes-256 + repo en storage privado + IAM restrictivo | Diseñado |
| SEC-09 | **Dependencia vulnerable (CVE)** | Media | Alto | **MEDIO** | Trivy en CI + Dependabot + rebuild semanal + distroless | Proceso definido |
| SEC-10 | **Falta MFA superusuario** | Baja | Crítico | **ALTO** | TOTP obligatorio para role=superusuario | Diseñado |
| SEC-11 | **Configuración maliciosa** (app_config timeout=0) | Baja | Alto | **MEDIO** | Validación esquema config (min/max), auditoría cambio, solo superusuario | Diseñado |
| SEC-12 | **Disponibilidad 99.8% no cumplida** (RNF-043) | Media | Alto | **ALTO** | HA PG síncrona + healthchecks + alertas + runbook incidente | **Pendiente RPO/RTO** |

---

## Preguntas Pendientes (Bloqueantes para Implementación Segura)

| # | Pregunta | Impacto Seguridad | Dueño |
|---|----------|-------------------|-------|
| SEC-Q1 | **RPO/RTO numéricos** (ver P-05) | Define HA, backup, cifrado backups, runbook | Producto/Técnico |
| SEC-Q2 | **Proveedor QR y método validación** (ver P-04) | Define HMAC scheme, webhook vs polling, secrets gestión | Producto |
| SEC-Q3 | **Regulación datos personales aplicable** (GDPR, LOPD, LGPD, etc.) | Define cifrado DNI, retención, derecho olvido, DPIA | Legal/Producto |
| SEC-Q4 | **MFA para secretaria/trabajadora** (opcional vs obligatorio) | Define flujo login, recuperación cuenta | Producto/Seguridad |
| SEC-Q5 | **Política retención auditoría / logs** (años) | Define particionado `audit_log`, compliance | Legal/Técnico |
| SEC-Q6 | **Penetration testing / Code review externo** requerido | Define timeline pre-prod | Seguridad/Gestión |

---

## Controles de Seguridad por Capa (Resumen Ejecutivo)

| Capa | Controles Clave | Verificación |
|------|-----------------|--------------|
| **Red** | TLS 1.3 everywhere, mTLS PG réplica, WAF (rate limit, geo-block opcional), VPC/red privada DB | Escaneo puertos, certificado válido, handshake TLS |
| **Contenedor** | Non-root user, read-only rootfs, drop ALL capabilities, no new privileges, secrets tmpfs, healthcheck | `docker inspect`, `trivy image`, runtime falco/sysdig |
| **Aplicación** | JWT RS256 + rotación, cookies HttpOnly Secure, rate limit, input validation, idempotency, authZ middleware + RLS, audit middleware | OWASP ZAP scan, unit/integration tests authZ, chaos testing |
| **Base de Datos** | RLS multi-sucursal, `pg_audit`, cifrado columnas sensibles, PITR + réplica síncrona, backups cifrados, least privilege users (app user ≠ admin) | `pg_audit` log review, backup restore test mensual, permission audit |
| **Infra/Orquestación** | Secrets management (Vault/Cloud), CI/CD pipeline con security gates (SAST, SCA, container scan), SBOM, deploy approvals | Pipeline logs, audit trail deploy, secret rotation evidence |
| **Operación** | Runbooks incidentes, alertas seguridad (SEC-01 a SEC-12), rotación credenciales programada, access review trimestral | Simulacro incidente, evidence rotación, access review minutes |

---

## Próximos Pasos

1. **`devops-architect`** → Evaluar despliegue, contenedores, escalado, observabilidad, backup, CI/CD, **K8s sí/no** (06_infrastructure.md)
2. **`architecture-reviewer`** → Revisión adversarial completa contra RF/RNF (07_architecture_review.md)
3. **`solution-leader`** → Consolidación + ADR + Plan de pruebas (08_recomendacion_final.md)

---

**Skill utilizado**: `security-review`  
**Agente responsable**: `security-reviewer`  
**Estado**: `HUMAN_STATUS: PENDING` — Requiere respuestas a SEC-Q1 a SEC-Q6 (especialmente RPO/RTO, proveedor QR, regulación PII) para completar controles detallados.
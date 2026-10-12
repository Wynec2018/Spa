# Análisis de Seguridad — D-07

Fecha: 2026-09-17
Especialista: security-reviewer
Driver: RNF-030 a RNF-035

---

## Referencia de Requisitos de Seguridad

| RNF | Requisito |
|-----|-----------|
| RNF-030 | Autenticación para operaciones administrativas |
| RNF-031 | Autorización por mínimo privilegio |
| RNF-032 | Cifrado en tránsito |
| RNF-033 | Contraseñas nunca en texto plano |
| RNF-034 | Secretos fuera del repositorio |
| RNF-035 | Controles de seguridad para APIs |

---

## Opción 1: JWT + RBAC interno

**Mecanismo**: Backend emite JWT firmados (RS256 o HS256 con clave rotativa). RBAC se implementa como middleware que valida claims en cada request.

### Cobertura de RNFs

| RNF | Cobertura | Nota |
|-----|-----------|------|
| RNF-030 | ✅ | JWT se emite tras login válido |
| RNF-031 | ✅ | Claims del JWT incluyen roles; middlewareRBAC filtra por permiso mínimo |
| RNF-032 | ✅ | Independiente — requiere TLS en HTTP layer |
| RNF-033 | ✅ | Validación de contraseña en login; hash con bcrypt/argon2 en DB |
| RNF-034 | ⚠️ | La firma JWT necesita una clave secreta; debe gestionarse por env vars o vault |
| RNF-035 | ✅ | Cada request API携带 JWT; rate limiting y validación en middleware |

### Trade-offs

- **Complejidad**: Baja-moderada. El sistema se construye desde cero pero es directo.
- **Mantenimiento**: Bajo. Sin dependencia externa para auth.
- **Nivel de seguridad**: Medio-alto. El token viaja en el cliente; si se compromete, el daño es proporcional al TTL. Revocación inmediata requiere blacklist en BD (O(n) en requests) o aceptar TTL corto.
- **POS físico**: Funciona bien — POS puede autenticar localmente con credenciales propias o un JWT emitido por la plataforma.

### Riesgos

1. **Revocación**: JWT no se invalida por defecto. Se necesita un endpoint de blacklist o almacenamiento de tokens activos → añade complejidad que muchos subestiman.
2. **Robo de token**: Sin refresh tokens bien diseñados, un token robado tiene acceso hasta expirar.
3. **Crecimiento de claims**: A medida que crecen los permisos, el JWT puede volverse innecesariamente grande o tener datos sensibles en el payload.

---

## Opción 2: OAuth2/OIDC con Provider Externo

**Mecanismo**: Se delega autenticación a un proveedor (Keycloak auto-hospedado, Auth0, AWS Cognito). El backend valida tokens OIDC emitidos por el IdP.

### Cobertura de RNFs

| RNF | Cobertura | Nota |
|-----|-----------|------|
| RNF-030 | ✅ | El IdP maneja login, MFA, etc. |
| RNF-031 | ✅ | RBAC se configura en el IdP o como claims. El backend valida scopes/roles. |
| RNF-032 | ✅ | OIDC especifica TLS. El IdP lo impone. |
| RNF-033 | ✅ | El IdP maneja hashing de contraseñas. |
| RNF-034 | ✅ | El IdP tiene su propio vault. El backend solo necesita el client_id y JWKS endpoint. |
| RNF-035 | ✅ | Validación de tokens OIDC + rate limiting en backend. |

### Trade-offs

- **Complejidad**: Alta. Requiere infraestructura adicional (Keycloak o servicio externo).
- **Mantenimiento**: Alto si self-hosted (Keycloak). Bajo si managed (Auth0) pero con costo mensual y lock-in.
- **Nivel de seguridad**: Alto. MFA, session management, token rotation, y audit logging ya incluidos.
- **POS físico**: Problemático. Un IdP externo requiere conectividad. POS offline no puede autenticar. Se necesitaría un mecanismo de fallback (token offline local) que rompe el modelo.

### Riesgos

1. **Disponibilidad del IdP**: Si Keycloak/Auth0 cae, no hay login. Dependencia de un punto único.
2. **Latencia**: Cada autenticación implica un round-trip al IdP. En POS con latencia de red, esto es visible.
3. **Vendor lock-in**: Auth0/Cognito crean dependencia. Keycloak self-hosted mitiga esto pero agrega carga operativa.
4. **Costo**: Auth0 tiene pricing por MAU que escala rápido con miles de usuarios de POS + web.

---

## Opción 3: Session-based + CSRF

**Mecanismo**: Login crea una sesión en servidor (cookie `HttpOnly`, `Secure`, `SameSite=Strict`). El servidor almacena el estado de sesión. CSRF tokens se envían como parte de cada request POST/PUT/DELETE.

### Cobertura de RNFs

| RNF | Cobertura | Nota |
|-----|-----------|------|
| RNF-030 | ✅ | Sesión se crea tras login válido |
| RNF-031 | ✅ | Roles se almacenan en la sesión del servidor |
| RNF-032 | ✅ | Independiente — TLS siempre requerido |
| RNF-033 | ✅ | Hash en BD, sesión no expone contraseña |
| RNF-034 | ⚠️ | Secret de cookie signing y DB credentials deben ser externos |
| RNF-035 | ✅ | CSRF token validado en cada mutating request. Rate limiting separado. |

### Trade-offs

- **Complejidad**: Baja. Patrón bien establecido, amplio soporte en frameworks.
- **Mantenimiento**: Bajo. Sin JWT para manejar. Las sesiones se invalidan con un DELETE en BD.
- **Nivel de seguridad**: Alto. El token de sesión nunca viaja al cliente en texto legible (HttpOnly). Revocación es instantánea (borrar sesión en BD). CSRF mitigado nativamente.
- **POS físico**: Funciona si POS es una app web o SPA que consume la misma API. Menos natural para clientes nativos.

### Riesgos

1. **Escalabilidad de sesión**: Cada sesión activa consume memoria/almacenamiento. Con 25K transacciones/hora y múltiples POS, la tabla de sesiones puede crecer. Solución: TTL agresivo + Redis/Memcached.
2. **Escenarios offline**: POS no puede autenticar sin conexión al servidor de sesiones. Similar limitación que OAuth2.
3. **Stateful server**: Requiere que todas las instancias del servidor accedan al mismo store de sesiones. Limita sharding simple.

---

## Opción 4: Híbrido (JWT para API, sesiones para POS)

**Mecanismo**: API web (frontend SPA) usa sesiones con CSRF. POS (aplicación nativa) usa JWT con refresh tokens. Ambos mecanismos validan contra el mismo backend de autorización (RBAC interno).

### Cobertura de RNFs

| RNF | Cobertura | Nota |
|-----|-----------|------|
| RNF-030 | ✅ | Ambos canales autentican |
| RNF-031 | ✅ | RBAC unificado detrás de ambos mecanismos |
| RNF-032 | ✅ | TLS en ambos canales |
| RNF-033 | ✅ | Hash en BD |
| RNF-034 | ⚠️ | Clave JWT + secrets de sesión = dos superficies de secretos |
| RNF-035 | ✅ | Validación diferenciada por canal |

### Trade-offs

- **Complejidad**: Media-alta. Dos mecanismos de autenticación = dos superficies de ataque, dos flujos de debugging, dos pipelines de testing.
- **Mantenimiento**: Medio. Más código que Opción 1 o 3 por separado.
- **Nivel de seguridad**: Bueno pero depende de la implementación. El doble mecanismo requiere disciplina para no crear inconsistencias.
- **POS físico**: Ideal — JWT funciona offline con refresh tokens. Web usa sesiones más seguras.

### Riesgos

1. **Complejidad de mantenimiento**: Mantener dos flujos de auth sincronizados es propenso a errores. Un fix de seguridad en uno puede olvidarse en el otro.
2. **Inconsistencia de permisos**: Si el RBAC no es estrictamente unificado, un rol puede tener permisos diferentes según el canal.
3. **Superficie de error**: Más código = más bugs. Más configuración = más puntos de fallo.

---

## Análisis Transversal: Secretos (RNF-034)

**Requisito**: Secretos fuera del repositorio.

### Estrategia Recomendada

| Capa | Mecanismo |
|------|-----------|
| **Desarrollo** | Variables de entorno + `.env` excluido de Git (`.gitignore`) |
| **Contenedores** | Secrets inyectados vía Docker secrets o variables de entorno en docker-compose |
| **Producción** | Vault (HashiCorp) o equivalente cloud. Secrets montados como archivos o inyectados como env vars |
| **Rotación** | Al menos: JWT signing key, DB password, API keys de pago. Rotación programada semanal/mensual |
| **Auditoría** | Log de acceso a secrets (quién leyó qué, cuándo) |

### Alternativas Evaluadas

- **HashiCorp Vault**: Potente pero requiere infraestructura adicional (más complejidad = más superficie de ataque si no se mantiene).
- **Docker secrets**: Simple, seguro para Docker Compose. Limitado para orquestación más compleja.
- **Cloud secrets manager**: AWS Secrets Manager / GCP Secret Manager. Simple pero vendor lock-in.
- **Variables de entorno simples**: Suficiente para entornos de desarrollo. En producción, mínimo docker secrets.

**Nota**: RNF-062 exige separación de configuración entre entornos. Esto se alinea naturalmente con vault o env vars por entorno.

---

## Análisis Transversal: Cifrado (RNF-032, RNF-033)

### En Tránsito (RNF-032)

| Componente | Estrategia |
|------------|------------|
| **Frontend ↔ Backend** | TLS 1.2+ obligatorio. Certificado válido (Let's Encrypt o certificado interno). HSTS habilitado. |
| **Backend ↔ Base de datos** | Conexión cifrada (PostgreSQL: `sslmode=verify-full`). |
| **Backend ↔ Proveedores de pago** | TLS implícito en comunicaciones con gateways. |
| **POS ↔ Backend** | TLS. POS debe validar certificado del servidor (no usar `verify=False`). |
| **Posible problema**: Certificados auto-firmados en POS requieren root CA confiable distribuida manualmente o por MDM. |

### En Reposo (RNF-033 — contraseñas)

| Dato | Estrategia |
|------|------------|
| **Contraseñas de usuario** | bcrypt (cost 12+) o argon2id. Nunca MD5/SHA sin salt. |
| **Datos de pago** | No almacenar. Usar tokenización vía gateway de pago. |
| **Datos personales** | Encriptación a nivel de columna si sensibles (PII). O encrypt-at-rest del volumen de DB si infra lo soporta. |
| **Backups** | Cifrar backups con clave separada. Almacenar clave de backup fuera del mismo vault que los secrets de producción. |

### Diagnóstico Actual

RNF-033 dice "contraseñas nunca en texto plano" pero no especifica un algoritmo. La recomendación es argon2id (resistente a GPU) o bcrypt (más ampliamente soportado). No hay excusa para usar SHA sin salt.

---

## Análisis Transversal: Auditoría de Seguridad (RNF-035)

**Requisito**: Controles de seguridad para APIs.

### Eventos a Registrar

| Evento | Severidad | Retención mínima |
|--------|-----------|------------------|
| Login exitoso | INFO | 90 días |
| Login fallido (3+ intentos) | WARN | 90 días |
| Logout | INFO | 30 días |
| Cambio de contraseña | WARN | 90 días |
| Cambio de rol/permisos | CRITICAL | 1 año |
| Acceso no autorizado (403) | WARN | 90 días |
| Rate limit alcanzado | WARN | 30 días |
| Error de autenticación (token inválido/expirado) | INFO | 30 días |
| Operación de pago | INFO | 1 año |
| Cambio de configuración del sistema | CRITICAL | 1 año |

### Formato

```
timestamp | event | user_id | ip | user_agent | resource | result | detail
```

JSON estructurado. Alineado con RNF-050 (logs estructurados) y RNF-052 (correlación distribuida).

### Implementación

- Middleware en cada endpoint de auth que escribe a archivo o stdout (para captura por docker logs + pipeline).
- No loggear: contraseñas, tokens, datos de tarjeta. Solo hashes o tokens redactados.
- Integración con stack de observabilidad de D-08 (OpenTelemetry o ELK).

---

## Comparación Resumen

| Criterio | JWT+RBAC | OAuth2/OIDC | Session+CSRF | Híbrido |
|----------|----------|-------------|--------------|---------|
| Complejidad | Baja-Media | Alta | Baja | Media-Alta |
| Mantenimiento | Bajo | Alto(self)/Bajo(managed) | Bajo | Medio |
| Seguridad | Medio-Alto | Alto | Alto | Bueno |
| Revocación | Difícil(sin blacklist) | Fácil(vía IdP) | Instantánea | Parcial |
| Offline POS | ✅ Nativo | ❌ Requiere fallback | ❌ Requiere fallback | ✅ Parcial |
| Vendor lock-in | Ninguno | Alto(managed) | Ninguno | Ninguno |
| Escalabilidad | Buena | Buena | Media(stateful) | Buena |
| Alcance RNF-030..035 | ✅ Cubre | ✅ Cubre | ✅ Cubre | ✅ Cubre |

---

## Hallazgos Clave

1. **Cualquier opción cubre los RNFs**. La diferencia está en complejidad, mantenimiento y trade-offs operativos.

2. **POS offline es el constraint diferenciador**. OAuth2 y sesiones server-side requieren conectividad. JWT nativo o híbrido son los más pragmáticos para un POS físico que puede perder conexión.

3. **RNF-034 (secretos) es transversal**. Todas las opciones necesitan una estrategia de vault/env vars. No hay atajo.

4. **RNF-032 (TLS) es independiente**. Todas las opciones requieren TLS; no es factor de decisión entre ellas.

5. **La complejidad del híbrido (Opción 4) es el riesgo principal**. Dos mecanismos de auth = dos superficies de error. Si el POS puede tolerar JWT puro, es más simple.

6. **RNF-035 (auditoría) requiere middleware dedicado**. No depende de la opción de auth seleccionada.

---

## Recomendación de Evaluación para Solution Leader

Las opciones viables son **JWT+RBAC (Opción 1)** y **Híbrido (Opción 4)**. La decisión depende de:

- ¿El POS necesita funcionar offline? → Opción 1 o 4.
- ¿Se puede tolerar complejidad de dos mecanismos? → Si no, Opción 1.
- ¿La revocación instantánea de sesión es crítica? → Si sí, considerar Opción 3 (pero requiere POS online).

**No evaluar Opción 2 (OAuth2) sin resolver el problema de POS offline primero.** El IdP externo es un punto de fallo que no se alinea con la naturaleza de POS físico.

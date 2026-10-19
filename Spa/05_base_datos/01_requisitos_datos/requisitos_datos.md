# Paso 01 — Requisitos de datos

Sistema: **Spa Cielo / Sistema Integral de Gestión y Atención Virtual para Spa**
Skill: `database-schema-designer`
Fecha: 2026-10-19
Fuentes: `Spa/00_contexto/`, `Spa/01_requisitos/RF.md`, `RNF.md`, `criterios_aceptacion.md`,
`Spa/02_configuracion/perfil_carga.yaml`, `Spa/03_resultados/08_recomendacion_final.md`,
`Spa/04_decisiones/decisiones_spa.md`

> Objetivo del paso: traducir RF/RNF/reglas de negocio a **requisitos de datos**
> (entidades, atributos, relaciones, patrones de acceso y volúmenes) **sin crear SQL**.

---

## 1. Entidades de datos

| # | Entidad | Descripción | Trazabilidad |
|---|---|---|---|
| E-01 | `branch` (sucursal) | Local del spa. Prepara multi-sucursal (D-05). | RNF-010/012, D-05 |
| E-02 | `user` (usuario de staff) | Superusuario, secretaría y trabajadora. Autenticación y rol. | RF-001, RNF-030/031, D-07 |
| E-03 | `client` (cliente) | Identidad del cliente (web o presencial), documento protegido. | RF-002, RN-004 |
| E-04 | `service` (servicio) | Catálogo: nombre, duración, precio, estado. | RF-003 |
| E-05 | `resource` (recurso) | Cama (1-6) o trabajadora (1-10) con estado dinámico. | RF-004/005, RN-001/003/005 |
| E-06 | `booking` (reserva/cita) | Reserva web o presencial con franja horaria y estado. | RF-004/006/013, RN-002/004 |
| E-07 | `payment` (pago) | Pago por QR con estado y verificación humana. | RF-007/012, RN-002 |
| E-08 | `promotion` (promoción) | Reglas de descuento con vigencia sobre servicios. | RF-009 |
| E-09 | `refund` (devolución/cancelación) | Devolución total o parcial asociada a un pago. | RF-010 |
| E-10 | `notification` (notificación) | Confirmaciones/recordatorios por WhatsApp o correo. | RF-011 |
| E-11 | `idempotency_key` | Deduplicación de peticiones ante reintentos. | RF-008, RNF-022 |
| E-12 | `outbox` | Eventos de dominio pendientes de publicar (saga/webhooks). | RNF-042, D-02 |
| E-13 | `audit_log` | Auditoría append-only de operaciones críticas. | RF-015, RNF-031 |
| E-14 | `app_config` | Parámetros operativos editables sin código. | RF-016, RNF-002 |

Relaciones M:N que requieren tabla asociativa:
- **E-08 ↔ E-04** `promotion_service` (una promoción aplica a N servicios).
- **E-06 ↔ E-05** `booking_resource` (una reserva ocupa 1 cama y 1 trabajadora; se modela
  con dos referencias tipadas o una tabla de asignación).

---

## 2. Atributos y clasificación de datos

| Entidad | Atributos clave | Sensibilidad |
|---|---|---|
| `branch` | id, nombre, dirección, estado, timestamps | Interna |
| `user` | id, branch_id, rol, nombre, hash_password, email/teléfono, estado, timestamps | Credencial + PII |
| `client` | id, branch_id, nombre, **documento_cifrado**, contacto, timestamps | **PII (cifrado, RN + RNF-032)** |
| `service` | id, branch_id, nombre, duracion_min, precio (`DECIMAL`), estado | Interna |
| `resource` | id, branch_id, tipo[cama\|trabajadora], nombre, estado[libre\|ocupada\|no_disponible], user_id (si trabajadora), version, timestamps | Interna |
| `booking` | id, branch_id, client_id, service_id, cama_id, trabajadora_id, inicio, fin, estado, payment_id, timestamps | Interna |
| `payment` | id, booking_id, proveedor, qr_payload, monto, moneda, estado, verificado_por, verificado_en, timestamps | Financiera |
| `promotion` | id, branch_id, nombre, reglas (JSON), valid_from, valid_to, activo | Interna |
| `refund` | id, payment_id, monto, motivo, estado, autorizado_por, timestamps | Financiera |
| `notification` | id, client_id, canal, plantilla, estado, enviado_en | PII ligera |
| `idempotency_key` | clave (PK `scope:key`), request_hash, response (JSON), expires_at | Técnica |
| `outbox` | id, tipo_evento, payload (JSON), publicado, intentos, timestamps | Técnica |
| `audit_log` | id, actor_user_id, accion, entidad, entidad_id, datos_previos (JSON), datos_nuevos (JSON), correlation_id, prev_hash, creado_en | **Auditoría (append-only)** |
| `app_config` | clave (PK), valor (JSON), updated_by, updated_at | Configuración |

---

## 3. Patrones de acceso (drivers de índices)

| # | Patrón / consulta | Frecuencia | Trazabilidad |
|---|---|---|---|
| A-01 | Buscar recursos `libre` de un tipo y franja horaria | Muy alta | RF-004/005/006 |
| A-02 | Detectar solapamiento de reserva activa para una cama/trabajadora | Muy alta (crítico) | RNF-020/005, RN-001 |
| A-03 | Listar reservas del día por sucursal y estado | Alta | RF-004, secretaría |
| A-04 | Verificar/actualizar comprobante de pago por `booking_id` | Alta | RF-007, RN-002 |
| A-05 | Estado en tiempo real de recursos (SSE) | Continua | RF-005, CA-001 |
| A-06 | Reportes de ocupación, ingresos y rendimiento por trabajadora | Media | RF-014, RF-017 |
| A-07 | Job de expiración de reservas `pendiente` (TTL) | Periódica | RF-016, P-03 |
| A-08 | Verificar idempotencia por clave | Alta | RF-008 |
| A-09 | Publicar eventos de `outbox` no publicados | Continua | RNF-042 |

---

## 4. Volúmenes y crecimiento

| Métrica | Valor | Fuente |
|---|---|---|
| Transacciones de negocio/hora (base) | 50 | RNF-001 |
| Pico | 100 (×2.0) | RNF-004, perfil_carga |
| Escenario evaluado | 500 (×10) | RNF-011 |
| Usuarios concurrentes (tiempo real) | ~10 | perfil_carga |
| Recursos por sucursal | 1-6 camas, 1-10 trabajadoras (configurable) | RN-001, RF-016 |
| Crecimiento | Camas/trabajadoras/sucursales sin rediseño | RNF-010/012 |
| Retención | Reservas, pagos y auditoría se conservan (RN de trazabilidad) | RF-015, RNF-044 |

---

## 5. Reglas de integridad derivadas (a constraints)

| ID | Regla de datos | Origen |
|---|---|---|
| RI-01 | No puede existir más de una reserva **activa** (`pendiente`/`confirmada`) para la misma cama en franjas solapadas. | RN-001, RNF-020 |
| RI-02 | No puede existir más de una reserva **activa** para la misma trabajadora en franjas solapadas. | RN-003, RNF-020 |
| RI-03 | Una reserva solo pasa a `confirmada` si su pago asociado está `verificado`. | RN-002 |
| RI-04 | Una trabajadora `ocupada` no recibe nuevas reservas hasta liberar. | RN-003 |
| RI-05 | `fin > inicio`; la franja de la reserva debe contener la duración del servicio. | RF-004 |
| RI-06 | `monto >= 0`; no usar punto flotante para dinero. | RNF-021 |
| RI-07 | Idempotencia: `clave` única por `scope`; un mismo `request_hash` no genera dos efectos. | RF-008, RNF-022 |
| RI-08 | `documento` del cliente se almacena cifrado; nunca en claro ni en logs. | RNF-032/033/034 |
| RI-09 | Toda operación crítica deja rastro en `audit_log` inmutable. | RF-015 |
| RI-10 | Parámetros operativos (nº camas/trabajadoras, TTL) viven en `app_config`. | RF-016, RNF-002 |

---

## 6. Criterios de aceptación de datos (a validar en Pruebas)

- CA-001: Notificación de cambio de estado de recurso en < 3 s.
- CA-002: 0 doble asignación en pruebas de concurrencia (10.000 iteraciones).
- CA-003: 1 sola entidad creada por clave de idempotencia (500 reintentos).
- CA-004: Flujo pago `pendiente → verificado → confirmada` sin inconsistencias.

---

## 7. Fuera de alcance (datos)

- Contabilidad completa, manufactura, marketplace, rutas, app móvil nativa
  (`00_contexto/alcance.md`, nota: contiene texto heredado de tienda; no se modifica
  sin autorización, RF/RNF prevalecen).
- Credenciales: nunca en el repositorio; solo vía `.env` (RNF-034).

**Estado del paso:** completado. No se generó SQL.

# Análisis de Requisitos — Spa Cielo / Sistema Integral de Gestión y Atención Virtual

Fecha: 2026-10-09

---

## Resumen Ejecutivo

Sistema de gestión para spa con 6 camas y hasta 10 trabajadoras. Automatiza reservas (web/presencial), asignación de recursos (camas + trabajadoras), pagos QR con validación humana, y monitoreo en tiempo real. Dominio centrado en **coordinación de recursos finitos** y **validación de pagos con intervención humana**. Requiere integridad bajo concurrencia entre canal web y recepción física, y trazabilidad de identidad/roles.

---

## Requisitos Funcionales Críticos

| ID | Requisito | Impacto | Notas |
|----|-----------|---------|-------|
| RF-001 | Administrar usuarios, roles y permisos | **ALTO** | Base de seguridad (Superusuario, Secretaria, Trabajadora, Cliente) |
| RF-002 | Administrar clientes | **ALTO** | Identidad digital, trazabilidad, registro presencial/web |
| RF-003 | Registrar Servicio, tiempo, usuario, cama y promociones | **ALTO** | Core operativo: asignación de recursos finitos (camas/trabajadoras) |
| RF-004 | Registrar recepción de pago del cliente al usuario y servicio | **ALTO** | Pago QR + validación humana obligatoria (RN-002) |
| RF-005 | Registrar entradas, salidas, reservas, ajustes y transferencias | **MEDIO** | Inventario/movimientos (menor alcance que tienda) |
| RF-006 | Registrar promociones | **MEDIO** | Aplicables a servicios |
| RF-007 | Registrar devoluciones parciales o totales | **MEDIO** | Reversión de pagos/asignaciones |
| RF-008 | Evitar doble descuento ante reintentos | **ALTO** | Idempotencia en pagos/reservas |
| RF-012 | Integrar pagos mediante interfaz desacoplada del proveedor | **ALTO** | QR estático/dinámico, validación semi-automática |
| RF-013 | Reflejar ventas web e inventario sin sobreventa frente al canal físico | **ALTO** | Coordinación tiempo real web vs recepción (camas/trabajadoras) |
| RF-014 | Generar reportes operativos de ventas, compras e inventario | **MEDIO** | Métricas de ocupación, ingresos, rendimiento |
| RF-015 | Auditar operaciones críticas | **ALTO** | Trazabilidad completa: pagos, asignaciones, cambios de estado |
| RF-016 | Administrar parámetros operativos sin modificar código | **MEDIO** | Configuración externa (ej. tiempo expiración reserva) |
| RF-017 | Superusuario visualiza reportes | **ALTO** | Dashboard ejecutivo, auditoría de identidad |

---

## Requisitos No Funcionales Críticos

| ID | Requisito | Impacto | Notas |
|----|-----------|---------|-------|
| RNF-001 | 50 transacciones de negocio/hora | **ALTO** | Capacidad inicial baja, pero define baseline |
| RNF-002 | Capacidad objetivo configurable externamente | **MEDIO** | Parámetro sin redeploy |
| RNF-003 | Transacción de negocio ≠ sentencia SQL | **ALTO** | Clarifica dimensionamiento real |
| RNF-004 | Evaluar picos con multiplicador (2.0x) | **MEDIO** | 100 transacciones/hora en pico |
| RNF-005 | Integridad bajo concurrencia físico/web | **CRÍTICO** | Evitar doble asignación de cama/trabajadora |
| RNF-006 | Código estructurado y documentado | **MEDIO** | Mantenibilidad |
| RNF-010 | Crecimiento de sucursales/cajas sin rediseñar | **MEDIO** | Preparar multi-sucursal futuro |
| RNF-011 | Evaluar escenario 10x (500 transacciones/hora) | **MEDIO** | Arquitectura escalable |
| RNF-012 | Arquitectura modular para >6 camas / >10 trabajadoras | **MEDIO** | Recursos parametrizables |
| RNF-020 | Evitar sobreventa por condiciones de carrera | **CRÍTICO** | Locking a nivel de recurso (cama/trabajadora) |
| RNF-021 | Fronteras transaccionales para dinero e inventario | **ALTO** | ACID en pagos y asignaciones |
| RNF-022 | Evitar duplicados ante reintentos | **ALTO** | Idempotencia (tokens, deduplicación) |
| RNF-030 a RNF-035 | Seguridad completa | **CRÍTICO** | Auth, AuthZ, TLS, secretos, APIs |
| RNF-040 a RNF-043 | Disponibilidad 99.8%, backup diario, RPO/RTO | **ALTO** | 24/7 reservas online, definir RPO/RTO |
| RNF-050 a RNF-052 | Observabilidad | **MEDIO** | Logs, métricas, correlación |
| RNF-060 a RNF-062 | Linux, contenedorizable, separación ambientes | **ALTO** | Restricción de despliegue |

---

## Inconsistencias Detectadas

| # | Descripción | Severidad |
|---|-------------|-----------|
| 1 | **RNF-001 vs RNF-003**: 50 transacciones/hora pero "no equivale a SQL". Falta definición operativa exacta: ¿una reserva? ¿un pago? ¿una asignación cama+trabajadora? | **ALTA** |
| 2 | **RN-002 (validación humana QR) vs RNF-005 (integridad concurrencia)**: La secretaría valida pagos manualmente — ventana de inconsistencia entre "Pendiente" y "Confirmada". ¿Puede web reservar mientras secretaría valida? | **ALTA** |
| 3 | **RF-013 (sin sobreventa web vs físico) vs RN-004 (llegada sin reserva)**: Cliente presencial entra si hay cama+trabajadora libre. ¿Cómo sincroniza con reservas web activas en tiempo real? | **ALTA** |
| 4 | **RNF-043 (99.8% disponibilidad) vs RNF-041 (RPO/RTO null)**: Disponibilidad objetivo definida pero RPO/RTO pendientes. Sin RPO/RTO no se puede validar la estrategia de backup/recuperación. | **MEDIA** |
| 5 | **RN-001 (camas 1-6, trabajadoras 1-10 "puede aumentar/disminuir") vs RNF-012**: Recursos dinámicos pero no se define quién/qué los modifica ni cómo afecta reservas existentes. | **MEDIA** |
| 6 | **RF-004 + RF-012**: "Pasarela QR" y "interfaz desacoplada" — ¿validación automática bancaria + humana? ¿O solo visualizador QR para secretaría? | **MEDIA** |

---

## Riesgos Identificados

| # | Riesgo | Probabilidad | Impacto | Mitigación |
|---|--------|--------------|---------|------------|
| 1 | **Doble asignación de recursos (cama/trabajadora)**: Web reserva + secretaría asigna presencial simultáneamente. Condición de carrera en recursos finitos (6 camas, 10 trabajadoras). | Alta | Crítico | Locks optimistas/pesimistas a nivel de recurso + transacciones ACID + versión/etiqueta de recurso |
| 2 | **Ventana de inconsistencia en pagos QR**: Estado "Pendiente" → validación humana → "Confirmada". Cliente puede aparecer como reservado sin pago confirmado. | Media | Alto | Timeout automático de reservas "Pendiente" + liberación de recursos + notificación |
| 3 | **Escalabilidad futura multi-sucursal**: Arquitectura inicial single-spa pero RNF-010/011 exigen crecimiento sin rediseño. | Media | Medio | Modelo de datos con sucursal desde día 1, aislamiento de recursos por sede |
| 4 | **Definición de RPO/RTO pendiente**: Sin métricas, no se puede elegir estrategia de backup/replicación adecuada. | Media | Alto | Definir en fase de diseño (solution-leader) antes de infraestructura |
| 5 | **Integración QR sin proveedor definido**: Restricción organizacional pendiente. Comportamiento de timeouts/reintentos desconocido. | Media | Medio | Capa anti-correlación + cola de reconciliación + polling/ webhook configurable |

---

## Preguntas Abiertas

| # | Pregunta | Requiere respuesta de | Impacto en diseño |
|---|----------|----------------------|-------------------|
| 1 | ¿Qué constituye exactamente una "transacción de negocio" para el cálculo de 50/hora? ¿Reserva completa? ¿Pago? ¿Asignación cama+trabajadora? | Producto/Dominio | Dimensionamiento real de capacidad, conexión pooling, sizing DB |
| 2 | **Política de conflicto web vs presencial**: ¿Reserva web bloquea cama/trabajadora físicamente (lock) o es reserva lógica sobreescribible por secretaría? | Producto/Dominio | Define mecanismo de concurrencia (optimista vs pesimista), UX de conflictos |
| 3 | **Expiración de reservas "Pendiente"**: ¿Cuánto tiempo antes de liberar cama/trabajadora? ¿Notifica al cliente? ¿Automático o manual? | Producto/Dominio | Job de limpieza, timeouts, UX de recuperación |
| 4 | **Proveedor QR**: ¿Validación automática (webhook bancario) + humana, o solo visualizador para secretaría? | Producto/Dominio | Complejidad integración, latencia, manejo de errores |
| 5 | **RPO/RTO objetivo**: ¿Cuánta pérdida de datos tolerable? ¿Cuánto tiempo de recuperación? | Producto/Técnico | Define backup strategy, replicación, HA |
| 6 | **Multi-sucursal desde día 1**: ¿Modelar sucursal en datos ahora o migración futura? | Producto/Dominio | Esquema DB, aislamiento recursos, reportes consolidados |
| 7 | **Gestión de recursos dinámicos**: ¿Quién cambia nº camas/trabajadoras? ¿Afecta reservas futuras? ¿Histórico? | Producto/Dominio | Modelo de datos, auditoría, UI admin |
| 8 | **Notificaciones WhatsApp/Email**: ¿Proveedor? ¿Plantillas? ¿Sincrónico o asíncrono? | Producto/Técnico | Cola de mensajes, retry, idempotencia |
| 9 | **Reportes superusuario**: ¿Tiempo real o batch? ¿Latencia tolerable? | Producto/Dominio | Réplica de lectura, materialized views, caching |

---

## Drivers Arquitectónicos

| # | Driver | Fuente | Justificación |
|---|--------|--------|---------------|
| D1 | **Integridad de recursos finitos bajo concurrencia** | RN-001, RN-003, RN-004, RNF-005, RNF-020 | 6 camas × 10 trabajadoras = recursos escasos. Web + recepción física concurrentes. Requiere control de concurrencia a nivel de fila/recurso con ACID |
| D2 | **Transaccionalidad de pagos con validación humana** | RN-002, RF-004, RF-012, RNF-021 | Flujo: Reserva → QR → Pendiente → Validación secretaría → Confirmada. Dinero + asignación recursos atómicos. Idempotencia obligatoria |
| D3 | **Idempotencia en operaciones críticas** | RF-008, RNF-022 | Reintentos de red, doble clic, reconexión. Tokens de idempotencia en reservas/pagos |
| D4 | **Tiempo real en estado de recursos** | CA-001 (<3s), RN-003, RN-005 | Cambio "Libre"→"Ocupada" visible inmediatamente. WebSockets / Server-Sent Events / polling corto |
| D5 | **Seguridad por roles y auditoría** | RF-001, RF-015, RF-017, RNF-030 a RNF-035 | 4 roles con permisos distintos. Superusuario ve todo. Auditoría inmutable de pagos/asignaciones |
| D6 | **Configurabilidad sin redeploy** | RF-016, RN-003 (parámetro configurable) | Timeouts, límites, reglas de negocio en config externa (DB/env) |
| D7 | **Portabilidad y contenerización** | RNF-060, RNF-061, RNF-062 | Linux, Docker, 3 ambientes (dev/test/prod) con config separada |
| D8 | **Escalabilidad modular multi-sucursal** | RNF-010, RNF-011, RNF-012, RN-001 | Preparar modelo para N sucursales, N camas, N trabajadoras desde inicio |
| D9 | **Observabilidad operativa** | RNF-050 a RNF-052 | Logs estructurados, métricas latencia/errores/volumen, trazabilidad pagos |

---

## Requisitos que Requieren Validación Humana

| # | Requisito | Pregunta pendiente |
|---|-----------|-------------------|
| 1 | RNF-001 / RNF-003 | Definir "transacción de negocio" operativamente para dimensionar |
| 2 | RN-002 / RF-013 / CA-002 | Política de conflicto: web bloquea recurso físicamente o reserva lógica? |
| 3 | RN-002 / RF-004 | Comportamiento expiración reserva "Pendiente" (timeout, liberación, notificación) |
| 4 | RF-012 / Restricciones | Proveedor QR inicial: validación automática + humana o solo visualizador? |
| 5 | RNF-041 / RNF-043 | RPO/RTO numéricos para definir estrategia backup/recuperación |
| 6 | RN-001 / RNF-012 | Autoridad y proceso para cambiar nº camas/trabajadoras; impacto en reservas futuras |

---

## Clasificación de Requisitos por Naturaleza

### Transacciones de Negocio (dominio)
- RF-003 (registro servicio + asignación cama/trabajadora)
- RF-004 (pago QR + validación)
- RF-005 (movimientos/inventario básico)
- RF-007 (devoluciones)
- RF-013 (coordinación web vs físico)

### Operaciones Técnicas Internas (infraestructura)
- RNF-001, RNF-002, RNF-004, RNF-010, RNF-011, RNF-012
- RNF-040, RNF-041, RNF-042, RNF-043, RNF-044
- RNF-060, RNF-061, RNF-062

### Operaciones de Soporte (cross-cutting)
- RF-001, RF-002 (usuarios/clientes — base transversal)
- RF-006, RF-008, RF-012, RF-014, RF-015, RF-016, RF-017
- RNF-005, RNF-006, RNF-020, RNF-021, RNF-022
- RNF-030 a RNF-035, RNF-050 a RNF-052

---

## Siguiente Fase

Este análisis será consumido por **`solution-leader`** para definir el **alcance de decisiones arquitectónicas** (02_decision_scope.md) antes de pasar a **`architect`**.

**Skill utilizado**: `requirements-analysis` (agents/skills/requirements-analysis/SKILL.md)
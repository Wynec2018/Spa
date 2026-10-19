# Paso 05 — Normalización

Sistema: **Spa Cielo**
Skill: `database-schema-designer`
Entrada: `03_modelo_logico/modelo_logico.md`
Objetivo: llegar a **3FN** (objetivo OLTP) justificando cualquier desnormalización con RF/RNF o patrón de acceso.

---

## 1. Primera Forma Normal (1FN) — valores atómicos

| Verificación | Resultado |
|---|---|
| Sin grupos repetidos en `booking` (cama y trabajadora son columnas separadas, no listas) | ✔ |
| `notification` guarda un único canal por fila (no "whatsapp,email" en un campo) | ✔ |
| `promotion.reglas` es `json` con estructura controlada (no listas en columnas escalares) | ✔ (documentado) |
| Sin columnas multivaluadas ni arrays de entidades | ✔ |

**1FN: cumplida.**

## 2. Segunda Forma Normal (2FN) — sin dependencias parciales

Todas las tablas de dominio usan **PK simple** (uuid/bigserial), por lo que no puede
existir dependencia parcial. La única PK compuesta es `promotion_service(promotion_id, service_id)`:
sus atributos no clave (si se añadieran, p. ej. `descuento`) dependerían de **toda** la clave, no de una parte.

**2FN: cumplida.**

## 3. Tercera Forma Normal (3FN) — sin dependencias transitivas

| Revisión | Resultado |
|---|---|
| `booking` no guarda `precio` ni `duracion_min` del servicio (residen en `service`) | ✔ |
| `client` no guarda `nombre_sucursal` (se obtiene por `branch_id`) | ✔ |
| `resource` no guarda datos de la trabajadora (se enlaza por `user_id`) | ✔ |
| `payment` no guarda datos del cliente (se llega por `booking → client`) | ✔ |
| `app_user` no duplica `branch.nombre` | ✔ |

**3FN: cumplida.**

## 4. BCNF

Se revisa cada determinante:

| Tabla | Determinantes candidatos | ¿Superclave? |
|---|---|---|
| `branch` | id, (nombre) | ✔ |
| `app_user` | id, (branch_id, contacto) | ✔ |
| `client` | id, (branch_id, documento_hash) | ✔ |
| `service` | id, (branch_id, nombre) | ✔ |
| `resource` | id, (branch_id, tipo, nombre) | ✔ |
| `booking` | id | ✔ |
| `payment` | id, booking_id (UK) | ✔ |
| `promotion_service` | (promotion_id, service_id) | ✔ |
| `app_config` | clave | ✔ |

**BCNF: cumplida** en todas las tablas.

---

## 5. Desnormalizaciones (controladas y justificadas)

| # | Desnormalización | Justificación (RF/RNF / patrón) | Controles |
|---|---|---|---|
| DZ-01 | `booking.payment_id` (además de `payment.booking_id` UK) | Lectura directa del estado de pago/confirmación para la secretaría (RF-007, RN-002, patrón A-04) | Aplicación mantiene coherencia; FK `SET NULL`; índice de apoyo |
| DZ-02 | Vistas materializadas de reportes (Paso 11) | Reportes de ocupación/ingresos en <2 s (RF-014/017, RNF-051), patrón lectura intensiva OLAP sobre OLTP | Refresh periódico (5 min); no son fuente de verdad |
| DZ-03 | `resource.estado` como estado *actual* | Consulta en tiempo real (RF-005, CA-001) sin recomputar historial | Historial temporal en `audit_log`/tabla histórica (Paso 10) |

No se aplican otras desnormalizaciones: el modelo es pequeño y OLTP
(50-500 tx/h), por lo que la normalización no degrada el rendimiento.

---

## 6. Resultado

- Esquema en **3FN/BCNF**.
- Desnormalizaciones explícitas: DZ-01, DZ-02, DZ-03, todas trazables.
- Base lista para el diseño físico (Paso 07) tras seleccionar DBMS (Paso 06).

**Estado del paso:** completado.

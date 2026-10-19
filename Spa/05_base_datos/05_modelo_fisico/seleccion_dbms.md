# Paso 06 — Selección del DBMS

Sistema: **Spa Cielo**
Skill: `databases`
Fecha: 2026-10-19
Regla: **no elegir por preferencia**; justificar con RF, RNF, restricciones y arquitectura.
Referencia de arquitectura: `Spa/03_resultados/08_recomendacion_final.md` (D-08), `04_decisiones/decisiones_spa.md`.

---

## 1. Criterios derivados de los requisitos

| # | Criterio | Origen |
|---|---|---|
| C-01 | Transacciones ACID multi-tabla (reserva + recurso + pago + auditoría) | RNF-021, RF-004/007 |
| C-02 | Bloqueo pesimista con *skip locked* para recursos escasos | RNF-005/020, D-01 |
| C-03 | Índices **únicos parciales** (evitar solape de reservas activas) | RNF-020, RI-01/RI-02 |
| C-04 | Seguridad a nivel de fila (RLS) para multi-sucursal | RNF-010/031, D-05 |
| C-05 | JSON nativo (parámetros, reglas de promoción, outbox) | RF-016, RF-009, RNF-042 |
| C-06 | Backup continuo y PITR (WAL), RPO/RTO | RNF-040/041/044, D-10 |
| C-07 | Job programado para TTL de reservas | RF-016, P-03 |
| C-08 | Linux + contenedores; sin costo de licencia | RNF-060/061, C-08 |
| C-09 | Auditoría/triggers y patrón temporal | RF-015, RNF-015 |
| C-10 | Integración con stack backend | D-09 (`PHP 8.3 + Laravel 11`) |

---

## 2. Comparación

Escala 1-5 (5 = mejor). Ponderación según impacto en requisitos.

| Criterio (peso) | PostgreSQL 16+ | MySQL 8 / MariaDB | SQL Server |
|---|---|---|---|
| C-01 ACID (3) | 5 | 5 | 5 |
| C-02 SKIP LOCKED (2) | 5 | 4 | 4 (`READPAST`) |
| C-03 Índices parciales (3) | 5 | 1 (no soporta) | 4 (filtrados) |
| C-04 RLS (2) | 5 (nativo) | 1 (no nativo) | 4 |
| C-05 JSON (2) | 5 (`jsonb`) | 4 (`json`) | 3 (JSON en `nvarchar`) |
| C-06 PITR/WAL (3) | 5 | 4 (binlog) | 4 |
| C-07 Jobs (1) | 4 (`pg_cron`) | 3 (event scheduler) | 4 (SQL Agent) |
| C-08 Linux/Contenedor/Costo (3) | 5 (libre) | 5 (libre) | 2 (licencia) |
| C-09 Auditoría/triggers (1) | 5 | 4 | 4 |
| C-10 Stack (2) | 5 | 5 | 4 |
| **Ponderado** | **4.86** | **3.71** | **3.76** |

---

## 3. Decisión

**DBMS seleccionado: PostgreSQL 16+.**

Justificación (trazable, sin preferencia):

1. **C-03/C-04 son discriminantes**: PostgreSQL es el único de los tres con
   **índices únicos parciales** *y* **RLS nativo**; ambos son requisitos directos
   (no solape de reservas activas, RNF-020; aislamiento multi-sucursal, RNF-010/D-05).
2. **C-05 `jsonb`** cubre `app_config`, reglas de promoción y `outbox` (RF-016/009, RNF-042)
   con indexación GIN.
3. **C-06 PITR por WAL** soporta el backup continuo y RPO/RTO (D-10/§6 de la recomendación).
4. **C-08** cumple Linux/contenedor sin costo de licencia (RNF-060/061, restricción C-08).
5. **C-10**: Laravel 11 soporta PostgreSQL de forma nativa (D-09).

Alternativas descartadas: MySQL/MariaDB (sin índices parciales ni RLS → obligaría a
lógica en aplicación para C-03/C-04); SQL Server (licencia y menor encaje en contenedor,
C-08).

> Consistente con **D-08** ya ratificado; este paso lo re-valida con evidencia de datos.

---

## 4. Consecuencias para el diseño físico

- Se habilitan (Paso 07 y siguientes): `jsonb` + GIN, índices únicos parciales,
  RLS por `branch_id`, `pg_cron`, `pgAudit`, `pgBackRest`.
- Skills a usar: `postgresql-table-design` + `databases`.

**Estado del paso:** completado. DBMS = **PostgreSQL 16+**.

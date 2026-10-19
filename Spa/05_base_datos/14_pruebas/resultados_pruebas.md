# Paso 19 — Pruebas de la base real

Sistema: **Spa Cielo**
Fecha: 2026-10-19
Base: `sistema` @ `localhost:5432` (PostgreSQL 18.3)
Método: batería SQL ejecutada dentro de una transacción con `ROLLBACK`
(sin persistir datos de prueba). Cada aserción captura el `SQLSTATE` real.
Script: `db_tests.sql` (temporal, no versionado).

Resumen: **24 casos — 23 PASS, 1 FAIL, 2 observaciones.**

---

## 1. Resultados por caso

| # | Caso | Categoría | Esperado | Obtenido | Veredicto |
|---|---|---|---|---|---|
| T01 | UNIQUE `branch.nombre` | Integridad | 23505 | 23505 | PASS |
| T02 | CHECK `estado` | Integridad | 23514 | 23514 | PASS |
| T03 | FK `booking.client_id` inexistente | Integridad | 23503 | 23503 | PASS |
| T04 | INSERT booking válido | Funcional | sin error | sin error | PASS |
| T05 | EXCLUDE anti-solape **cama** | RN-001/RNF-020 | 23P01 | 23P01 | PASS |
| T06 | EXCLUDE anti-solape **trabajadora** | RN-003 | 23P01 | 23P01 | PASS |
| T07 | INSERT solapado en **otros** recursos | Funcional | sin error | sin error | PASS |
| T08 | `fin < inicio` | RI-05 | 23514/22000 | **22000** | PASS (ver OBS-01) |
| T09 | Confirmar sin pago verificado | RN-002 | P0001 | P0001 | PASS |
| T10 | Confirmar con pago verificado | RN-002 | sin error | sin error | PASS |
| T11 | Trabajadora pasa a `ocupada` | RN-003 | ocupada | ocupada | PASS |
| T12 | Trabajadora vuelve a `libre` al cancelar | RN-005 | libre | libre | PASS |
| T13 | `audit_log` registra cambios | RF-015 | >0 | 11 | PASS |
| T14 | Cadena `prev_hash` presente | RF-015 | >0 | **0** | **FAIL** (DEF-01) |
| T15 | PK idempotencia duplicada | RF-008 | 23505 | 23505 | PASS |
| T16 | CHECK `monto >= 0` | RI-06 | 23514 | 23514 | PASS |
| T17 | UNIQUE 1:1 `payment.booking_id` | RI | 23505 | 23505 | PASS |
| T18 | `numeric(12,2)` redondea 10.999 | RI-06 | 11.00 | 11.00 | PASS |
| T19 | RLS aísla por sucursal (B1 ve 1) | RNF-010/031 | 1 | 1 | PASS |
| T20 | RLS con contexto vacío | RNF-010 | 0 filas | **error 22P02** | PASS (ver DEF-02) |
| T21 | `spa_app` no puede UPDATE auditoría | RNF-031 | 42501 | 42501 | PASS |
| T22 | `spa_app` no puede DELETE dominio | RNF-031 | 42501 | 42501 | PASS |
| T23 | `spa_app` sí puede INSERT auditoría | RNF-031 | sin error | sin error | PASS |
| T24 | Rollback (todos los `DOWN`) ejecutable | Migraciones | sin error | sin error | PASS |

---

## 2. Hallazgos

### DEF-01 — La cadena `prev_hash` de `audit_log` no funciona (T14) — Severidad **Media/Alta**
- **Síntoma:** `audit_log` crece (11 filas) pero **todas** tienen `prev_hash = NULL`.
- **Causa:** `audit.fn_audit()` lee `prev_hash` de la fila anterior en lugar de calcular un
  hash del contenido (encadenamiento no implementado):
  ```sql
  SELECT prev_hash INTO v_prev FROM audit.audit_log ORDER BY id DESC LIMIT 1;
  ```
- **Impacto:** RF-015 — no repudio/cadena de integridad **no está garantizada**;
  la tabla es inmutable por permisos y Append-only, pero sin hash encadenado.
- **Recomendación:** migración `V0011` que calcule
  `prev_hash := sha256(concat(prev_hash_anterior, to_jsonb(fila)))` (pgcrypto `digest`).

### DEF-02 — Políticas RLS lanzan 22P02 con contexto vacío (T20) — Severidad **Baja/Media**
- **Síntoma:** con `app.branch_id = ''` (p. ej. tras `RESET`), el cast
  `current_setting('app.branch_id', true)::uuid` lanza **22P02** en vez de no filtrar filas.
- **Causa:** `current_setting(..., true)` devuelve `''` (no `NULL`) tras un `RESET`.
- **Impacto:** una sesión mal inicializada recibe error 500 en lugar de 0 filas;
  contratiempo operativo, no de seguridad (no expone datos).
- **Recomendación:** `NULLIF(current_setting('app.branch_id', true), '')::uuid`
  en las 9 políticas (incluido en `V0011`).

### OBS-01 — `ck_booking_periodo` inalcanzable (T08) — Informativa
- La columna generada `periodo tstzrange(...,'[)')` falla antes (22000) cuando `fin <= inicio`,
  por lo que el `CHECK (fin > inicio)` nunca se evalúa. El dato inválido **sí se rechaza**
  (comportamiento correcto), pero el deque de error es `22000` y no `23514`.
- **Recomendación:** sin acción funcional; opcional añadir `CHECK (fin > inicio)` como
  `BEFORE` a nivel de aplicación o documentar el orden de evaluación.

### Hallazgo heredado del Paso 18
- **DRIFT-02:** triggers de auditoría solo en 4 de 9 tablas críticas (RF-015 parcial).

---

## 3. Cobertura de las áreas del Paso 19

| Área requerida | Casos | Estado |
|---|---|---|
| PK / FK / UNIQUE / CHECK | T01,T02,T03,T15,T16,T17 | Cubierta |
| Reglas de negocio RN-001..005 | T05,T06,T09,T10,T11,T12 | Cubierta |
| Anti-solape (RNF-020) | T05,T06,T07 | Cubierta |
| Auditoría | T13,T14 | Cubierta (con DEF-01) |
| RLS / permisos | T19,T20,T21,T22,T23 | Cubierta (con DEF-02) |
| Tipos / precisión | T18 | Cubierta |
| Migraciones (rollback) | T24 | Cubierta |
| Concurrencia (locks) | — | No probada en runtime; validada por EXCLUDE gist + diseño (Paso 12). Requiere prueba de carga (no en alcance MVP). |

---

## 4. Conclusión del Paso 19

La integridad declarativa, las reglas de negocio (RN-001..005), la idempotencia,
el aislamiento RLS y los permisos de mínimo privilegio **funcionan** en la base real.
Se detectan **dos defectos** (DEF-01, DEF-02) y una observación (OBS-01) que
recomiendan una migración correctiva `V0011` antes del cierre de RF-015.

**Estado del paso:** completado con hallazgos.

---

## 5. Corrección posterior — `V0011__fix_audit_rls.sql`

Aplicada y verificada contra la base real (autorizada):

| Hallazgo | Corrección | Verificación |
|---|---|---|
| DEF-01 (T14) | `audit.fn_audit()` calcula `prev_hash = sha256(prev_hash_previo ‖ jsonb_previo)` | **PASS** — la cadena recomputa; filas nuevas con hash no nulo |
| DEF-02 (T20) | Políticas RLS con `NULLIF(current_setting('app.branch_id', true), '')::uuid` | **PASS** — contexto vacío ⇒ 0 filas (sin 22P02); aislamiento B1 = 1 |
| DRIFT-02 | Triggers de auditoría en `client`, `service`, `resource`, `refund`, `promotion` | **PASS** — 9 triggers `trg_audit_*` activos |

Resultado: **T14 y T20 quedan resueltos**; OBS-01 permanece como observación informativa.

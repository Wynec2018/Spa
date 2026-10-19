# Paso 14 — Revisión DBA

Sistema: **Spa Cielo**
Skills: `database-schema-designer`, `databases`, `postgresql-table-design`
Revisor: database-specialist (rol DBA)
Fecha: 2026-10-19
Alcance: artefactos `01`–`13` de `Spa/05_base_datos/`.

> No se despliega nada en este paso. Este informe produce el gate de aprobación.

---

## 1. Checklist de revisión

| # | Verificación | Resultado |
|---|---|---|
| 1 | Cada tabla tiene PK | ✔ |
| 2 | Toda FK con `ON DELETE` e índice | ✔ |
| 3 | Tipos correctos (text/timestamptz/numeric; sin `varchar(n)`/`float`/`money`) | ✔ |
| 4 | `NOT NULL` en campos requeridos; DEFAULT en estados | ✔ |
| 5 | CHECK para todos los conjuntos de estados | ✔ |
| 6 | UNIQUE donde corresponde (negocio + surrogate) | ✔ |
| 7 | Anti-solape de recursos garantizado (EXCLUDE gist + SKIP LOCKED) | ✔ |
| 8 | Modelo en 3FN/BCNF; desnormalizaciones justificadas | ✔ |
| 9 | Índices ligados a patrones de acceso | ✔ |
| 10 | RLS por `branch_id` + roles de mínimo privilegio | ✔ |
| 11 | Auditoría append-only + triggers | ✔ |
| 12 | Migraciones reversibles planificadas (sin ejecutar) | ✔ |
| 13 | Trazabilidad RF/RNF → constraint/índice/trigger | ✔ |
| 14 | Sin credenciales en artefactos versionados | ✔ |

## 2. Trazabilidad requisitos → diseño (muestra)

| Requisito | Artefacto / mecanismo |
|---|---|
| RF-004 reserva con cama y trabajadora | `booking` + FK `cama_id`/`trabajadora_id` |
| RF-005 estado de recursos | `resource.estado` + índice compuesto + SSE |
| RF-007/RN-002 pago con validación humana | `payment` + trigger `trg_booking_confirm_requires_payment` + saga |
| RF-008 idempotencia | `idempotency_key` + UK |
| RF-015 auditoría | `audit_log` + triggers + `REVOKE` |
| RF-016 parámetros | `app_config` (jsonb) + `pg_cron` |
| RNF-005/020 integralidad concurrencia | EXCLUDE gist + SKIP LOCKED + advisory locks |
| RNF-010/012 multi-sucursal | `branch_id` + RLS |
| RNF-022 duplicados | idempotencia |
| RNF-032/033/034 seguridad | TLS, Argon2id, cifrado de PII, secretos fuera del repo |
| RNF-044 backups | plan de backup/PITR (recomendación D-10) |

## 3. Hallazgos

### Bloqueantes
- Ninguno.

### Advertencias (no bloqueantes)
| # | Advertencia | Recomendación |
|---|---|---|
| A-1 | `perfil_carga.yaml` mantiene `disponibilidad_objetivo`, `rpo_minutos`, `rto_minutos` en `null` | Los valores se adoptaron en `04_decisiones` (RPO=0, RTO<15 min, 99.8%). Actualizar el perfil cuando se autorice (no es dato de RF/RNF). |
| A-2 | `booking.payment_id` es desnormalización (DZ-01) | Mantener por trigger/aplicación; revisar si genera inconsistencia en pruebas |
| A-3 | `audit_log`/`outbox` pueden crecer | Vigilar umbral de partición (10M filas) |
| A-4 | `00_contexto/alcance.md` contiene texto heredado de tienda | Corregir el alcance del spa (requiere autorización; no afecta el modelo) |

### Observaciones de rendimiento
- Se espera buen desempeño a 10× (500 tx/h) sin particionado; verificar con carga (Paso 19).

---

## 4. Conclusión

El diseño lógico y físico cubre los requisitos de datos, integridad, seguridad y
concurrencia del spa, con trazabilidad verificada y sin bloqueantes.

**STATUS: APPROVED**

> Habilita el Paso 15 (generación de SQL). Nuevamente: no se conecta ni despliega
> hasta autorización explícita y con credenciales solo por `.env`.

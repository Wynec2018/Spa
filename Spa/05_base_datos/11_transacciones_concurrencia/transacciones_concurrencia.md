# Paso 12 — Transacciones y concurrencia

Sistema: **Spa Cielo**
Skills: `databases` + `postgresql-table-design`
Motor: PostgreSQL 16+
Trazabilidad: RNF-005/020/021/022, RN-001..005, D-01/D-02/D-03.

---

## 1. Fronteras transaccionales (RNF-021)

| Operación | Frontera (una transacción) |
|---|---|
| Crear reserva (web o presencial) | `booking` + `resource` (marca ocupado) + `audit_log` |
| Registrar y verificar pago | `payment` (estado) + `booking` (confirmación) + `audit_log` |
| Cancelar/devolver | `booking`/`payment`/`refund` (estado) + liberar `resource` + `audit_log` |
| Expirar reserva (job) | `booking` (expirada) + liberar `resource` + `notification` (outbox) |

**No** hay transacción atómica end-to-end con el proveedor QR: la validación es humana
(no atómica) → se resuelve con **saga** + **outbox** (D-02).

---

## 2. Bloqueo pesimista de recursos (D-01)

```sql
-- dentro de la transacción de reserva (orden determinista por id)
SELECT id FROM resource
WHERE branch_id = $1 AND tipo = $2 AND estado = 'libre'
ORDER BY id
FOR UPDATE SKIP LOCKED
LIMIT 1;
```

- `SKIP LOCKED` evita que dos recepciones tomen la misma cama/trabajadora.
- Orden determinista por `id` para evitar deadlocks.
- Sección crítica adicional con `pg_advisory_xact_lock(hashtext('booking:'||branch_id))`
  cuando se compite por el mismo recurso.
- Refuerzo declarativo: `EXCLUDE USING gist` (Paso 07) garantiza 0 solape aun si falla la app.

## 3. Niveles de aislamiento

| Caso | Nivel |
|---|---|
| Reserva/pago (crítico) | `REPEATABLE READ` |
| Lecturas generales | `READ COMMITTED` (default) |
| Reportes | `READ COMMITTED` sobre vistas materializadas / réplica de lectura |

## 4. Idempotencia (RF-008, RNF-022)

- Cabecera `Idempotency-Key` → `idempotency_key.clave = scope:key`.
- Si la clave existe y el `request_hash` coincide → se devuelve la `response` almacenada.
- Si el `request_hash` difiere con la misma clave → 409 (conflicto).
- Purga por `expires_at` (job).

## 5. Saga de pago (RN-002, D-02)

```
crear booking (pendiente) → generar QR (pendiente)
   → [humano] verifica comprobante
       ├── aprobado  → payment.verificado → booking.confirmada
       └── rechazado → payment.rechazado → booking.cancelada + liberar recurso
   → timeout TTL → booking.expirada + liberar recurso + notificar
```

- Estados persistidos; la saga es **orquestada** por la capa de aplicación.
- Eventos emitidos vía tabla `outbox` (transaccional) → publicados por un worker.
- Reintentos con backoff; *dead-letter* tras N intentos (RNF-042).

## 6. Job de expiración (RF-016, P-03)

```sql
-- pg_cron cada minuto
SELECT cron.schedule('expira_reservas', '* * * * *',
  $$ SELECT expirar_reservas_pendientes(); $$);
```

La función libera `resource` y marca `booking.expirada` para TTL vencido
(configurable en `app_config`).

## 7. Prevención de deadlocks y reintentos

- Acceso a recursos en orden determinista (`ORDER BY id`).
- Transacciones cortas; sin I/O de red dentro de la transacción.
- Reintento controlado (hasta 3) ante `serialization_failure`/`deadlock_detected`.

## 8. Requisitos → mecanismo

| Req. | Mecanismo |
|---|---|
| RNF-005/020 | SKIP LOCKED + EXCLUDE gist |
| RNF-021 | Fronteras transaccionales definidas |
| RNF-022 | Idempotency-Key |
| RN-002 | Saga orquestada + outbox |
| RNF-042 | Reintentos + dead-letter |

**Estado del paso:** completado.

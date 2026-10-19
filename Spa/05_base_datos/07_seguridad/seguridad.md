# Paso 09 — Seguridad de la base de datos

Sistema: **Spa Cielo**
Skills: `databases` + `postgresql-table-design` (RLS)
Motor: PostgreSQL 16+
Trazabilidad: RNF-030..035, RF-015, D-05/D-07.

---

## 1. Roles de base de datos (mínimo privilegio, RNF-031)

| Rol | Uso | Privilegios |
|---|---|---|
| `spa_owner` | Propietario del esquema (migraciones) | DDL completo; **no** se usa por la app |
| `spa_app` | Aplicación (Laravel) | `SELECT/INSERT/UPDATE` en tablas de dominio; `INSERT` en `audit_log`; **sin** `DELETE` en dominios |
| `spa_readonly` | Reportes/BI | Solo `SELECT` (vistas y vistas materializadas) |
| `spa_backup` | `pgBackRest` | Replicación/backup (rol de replicación) |
| `spa_cron` | Jobs (`pg_cron`) | `UPDATE` acotado (expiración de reservas) |

Sin uso de `superuser`/`postgres` por la aplicación. Contraseñas de rol provistas por
variables de entorno (nunca en SQL versionado).

---

## 2. Row-Level Security (aislamiento multi-sucursal, D-05)

```sql
ALTER TABLE booking ENABLE ROW LEVEL SECURITY;
ALTER TABLE app_user ENABLE ROW LEVEL SECURITY;
-- ... (todas las tablas con branch_id)
CREATE POLICY p_branch_booking ON booking
  USING (branch_id = current_setting('app.branch_id')::uuid);
```

- La aplicación fija `SET LOCAL app.branch_id = '<uuid>'` por transacción.
- El superusuario puede operar cross-sucursal mediante un rol/política de bypass
  controlado (`BYPASSRLS` acotado a `spa_readonly` de consolidación).
- Cubre RNF-010/031 (una sede no ve datos de otra).

---

## 3. Cifrado y datos sensibles (RNF-032/033/034)

| Aspecto | Medida |
|---|---|
| En tránsito | TLS 1.3 (mín. 1.2), `sslmode=verify-full` |
| Contraseñas de usuario | Argon2id en aplicación (`hash_password`); nunca reversible |
| Documento del cliente (PII) | Cifrado AES-256-GCM (aplicación) en `documento_cifrado`; `documento_hash` (SHA-256) para unicidad |
| Secretos | Fuera del repositorio (`.env`/Docker Secrets/Vault); rotación 90 días (RNF-034) |
| Logs | Enmascarado de PII; sin `qr_payload` ni documento en claro |

---

## 4. Auditoría e inmutabilidad (RF-015, RNF-031)

- Revocar `UPDATE`/`DELETE` de `audit_log` para `spa_app`:
  ```sql
  REVOKE UPDATE, DELETE ON audit_log FROM spa_app;
  ```
- `audit_log` con hash encadenado (`prev_hash`) → no repudio.
- `pgaudit` habilitado para operaciones DDL y accesos sensibles.

---

## 5. Superficie de seguridad de objetos

- Esquemas por módulo (`auth`, `booking`, `payment`, `core`) para acotar `GRANT`.
- `search_path` fijado por rol (evita secuestro de esquema).
- Sin `SECURITY DEFINER` salvo funciones de dominio auditadas y con `search_path` fijo.
- Vistas de reporte exponen solo columnas necesarias (sin PII).

---

## 6. Matriz requisito → control

| Requisito | Control de BD |
|---|---|
| RNF-030 Autenticación administrativa | Roles de app separados + Argon2id |
| RNF-031 Mínimo privilegio | `GRANT` por tabla/columna + RLS |
| RNF-032 Cifrado en tránsito | TLS / `sslmode` |
| RNF-033 Contraseñas | Argon2id (app) |
| RNF-034 Secretos fuera del repo | `.env`/Secrets; sin credenciales versionadas |
| RF-015 Auditoría | `audit_log` append-only + `pgaudit` |

**Estado del paso:** completado.

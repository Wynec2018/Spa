# Paso 10b — Versionamiento de esquema

Sistema: **Spa Cielo**
Skills: `database-schema-designer` + `databases`
Stack: `PHP 8.3 + Laravel 11` (D-09).

---

## 1. Estrategia

- **Herramienta**: migraciones de Laravel (`php artisan make:migration`), con opción de
  SQL versionado `V{version}__{descripcion}.sql` para cambios de alto riesgo.
- **Todo cambio de esquema** entra por migración versionada y revisada; nunca edición manual en producción.
- Cada migración es **forward + rollback** (reversible) y **idempotente** en lo posible.

## 2. Convención de nombres

```
V0001__init_core.sql
V0002__init_auth.sql
V0003__init_catalog.sql
V0004__init_booking.sql
V0005__init_payment.sql
V0006__integrity_triggers.sql
V0007__indexes.sql
V0008__rls_policies.sql
V0009__roles_grants.sql
V0010__seed_config.sql
```

- Prefijo `V{4 dígitos}` monotónico; nunca reutilizar número.
- Cambios destructivos separados y con aprobación explícita (ver §4).

## 3. Orden de aplicación (dependencias)

1. Extensiones y esquemas.
2. Tablas base (`branch`, `app_user`, `client`, `service`, `resource`).
3. Tablas transaccionales (`booking`, `payment`, `refund`).
4. Tablas de apoyo (`promotion(_service)`, `notification`, `outbox`, `idempotency_key`, `app_config`).
5. `audit_log` y triggers de integridad/auditoría.
6. Índices y constraints `EXCLUDE`.
7. Políticas RLS y `GRANT`s.
8. Seed de `app_config` (valores por defecto, sin datos sensibles).

## 4. Reglas

- **Nunca** `DROP DATABASE/TABLE`, `TRUNCATE` ni `DELETE` masivo sin **autorización explícita**
  (regla AGENTS y del workflow).
- Migraciones incompatibles se despliegan en fases (add → backfill → constrain).
- Verificación de estado de migraciones antes de cada despliegue.
- Rollback probado en entorno de pruebas antes de producción.

## 5. Ejemplo de secuencia sin downtime (cambio de columna)

```sql
-- V0042a: añadir nullable
ALTER TABLE client ADD COLUMN nuevo_contacto text;
-- (desplegar código que escribe en ambas columnas)
-- V0042b: backfill
UPDATE client SET nuevo_contacto = contacto WHERE nuevo_contacto IS NULL;
-- V0042c: NOT NULL
ALTER TABLE client ALTER COLUMN nuevo_contacto SET NOT NULL;
-- V0042d: retirar antigua (tras verificar)
ALTER TABLE client DROP COLUMN contacto;
```

## 6. Trazabilidad

Cada migración referencia el RF/RNF que la motiva (comentario de cabecera).

**Estado del paso:** completado.

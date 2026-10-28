# Patrón reutilizable de servicios y reservas

Reemplaza al patrón de categorías heredado de la tienda. El patrón vigente
`CP-BACK-01` es el de servicios; este documento lo extiende a
`catalog.service` y `booking.booking` de **Spa Cielo**.

Estructura obligatoria: `agents/guides/estructura_backend.md`.

## Flujo API

```text
Request
  → routes/api.php
  → Api/V1 Controller
  → JsonBody
  → Validator
  → Repository
  → DatabaseContext::run()
  → PostgreSQL / RLS
  → Resource
  → ApiResponse
```

## Flujo web

```text
Request
  → routes/web.php
  → Web Controller
  → SessionAuth / BranchContext
  → Validator
  → Repository
  → DatabaseContext::run()
  → PostgreSQL / RLS
  → Blade
```

## Flujos específicos

### Autenticación

```text
LoginPageController o AuthController
  → LoginValidator
  → AuthService
  → AuthRepository
  → sesión de servidor
```

### Reservas

```text
ReservationController o ReservationPageController
  → ReservationValidator
  → ReservationRepository
  → transacción repeatable read + locks
  → PostgreSQL (EXCLUDE, RLS)
```

## Capas y reglas

| Capa | Archivo | Regla |
|---|---|---|
| Route | `routes/api.php`, `routes/web.php` | solo declara rutas y middleware; sin SQL ni negocio |
| Controller API | `app/Http/Controllers/Api/V1/*.php` | recibe, valida, delega y responde; **sin SQL/PDO** |
| Controller Web | `app/Http/Controllers/Web/*.php` | sesión, datos y vista; **sin SQL/PDO** |
| Body | `app/Support/JsonBody.php` | exige `application/json`; error 415 |
| Validator | `app/Validation/*.php` | listas cerradas, tipos, UUID, fechas; no consulta BD |
| Contexto | `app/Support/{BranchContext,SessionAuth,RequestId,RouteId}.php` | sucursal, sesión, request id y UUID de ruta |
| Repository | `app/Repositories/*.php` | todo el SQL/Query Builder; usa `DatabaseContext` |
| Service | `app/Services/AuthService.php` | autenticación; sin SQL |
| Resource | `app/Http/Resources/*.php` | forma JSON; sin BD |
| Response | `app/Support/ApiResponse.php` | envelope y códigos HTTP |
| Exceptions | `app/Exceptions/*.php` | errores esperados; sin trazas al cliente |

No se usan `app/Core`, `Response::execute` ni `RuntimeException` con códigos
HTTP. Laravel y `bootstrap/app.php` gestionan middleware y errores.

## Respuesta estándar

Éxito:

```json
{
  "success": true,
  "data": {}
}
```

Listado:

```json
{
  "success": true,
  "data": [],
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 0,
    "last_page": 1
  }
}
```

Error:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Los datos enviados no son válidos.",
    "details": {}
  },
  "request_id": "..."
}
```

Códigos usados:

| Código | Uso |
|---|---|
| 200 | lectura, actualización, inactivación o cancelación idempotente |
| 201 | creación; incluye `Location` |
| 400 | query, body o UUID inválido |
| 401 | credenciales o sesión inválidas |
| 403 | usuario inactivo |
| 404 | servicio, reserva, cliente o recurso inexistente |
| 409 | duplicado, recurso no disponible, conflicto de franja o transición inválida |
| 415 | `Content-Type` distinto de `application/json` |
| 422 | validación de campos |
| 500 | error interno; sin SQL ni stack trace |
| 503 | base de datos no disponible |

Los errores 500 usan `INTERNAL_ERROR` y conservan el `request_id` para
correlación.

## Reglas de servicios

- Campos permitidos: `nombre`, `duracion_min`, `precio`.
- `nombre`: 1–120 caracteres, obligatorio, único por sucursal.
- `duracion_min`: entero 1–1440.
- `precio`: cadena decimal con hasta 2 decimales; nunca `float`.
- `estado` no se acepta en alta ni edición.
- `DELETE /api/v1/servicios/{id}` e inactivar desde web ejecutan
  `UPDATE estado='inactivo'`; nunca `DELETE FROM`.
- Inactivar un servicio ya inactivo devuelve 200.
- Duplicado de nombre: 409 `DUPLICATE_SERVICE`.

## Reglas de reservas

- Campos permitidos: `client_id`, `service_id`, `cama_id`, `trabajadora_id`,
  `inicio`.
- `inicio` debe ser ISO 8601 con zona horaria; se normaliza a UTC.
- `fin` se calcula en servidor con `duracion_min`; el cliente no lo envía.
- El servicio debe existir en la sucursal y estar `activo`.
- `cama_id` debe ser recurso tipo `cama` y no estar `no_disponible`.
- `trabajadora_id` debe ser recurso tipo `trabajadora` y estar `libre`.
- Se bloquean los recursos y se usa aislamiento `repeatable read`.
- El `EXCLUDE` de PostgreSQL impide solapamientos; el conflicto se traduce a
  409 `RESERVATION_CONFLICT`.
- La reserva nace `pendiente`.
- Solo se permite `pendiente → cancelada`.
- Cancelar una reserva ya cancelada devuelve 200; cancelar otro estado devuelve
  409 `INVALID_STATE_TRANSITION`.
- Confirmar o atender queda fuera de alcance: exige pago verificado (RN-002).
- No se implementa idempotencia general de RF-008 en esta fase.

## Contexto y transacción

Todo acceso a datos de negocio pasa por:

```php
DatabaseContext::run(
    callback: fn () => ...,
    branchId: $branchId,
    userId: $userId,
    correlationId: $requestId,
);
```

`DatabaseContext` abre la transacción y fija:

```text
app.branch_id
app.user_id
app.correlation_id
```

Reglas:

- `branch_id` sale de configuración o sesión; no del body, salvo login.
- RLS permanece activo; no se usa `SET row_security = off`.
- Una petición usa una transacción; no se reutiliza contexto entre peticiones.
- Los locks se usan solo donde la regla lo exige.
- No se reintenta automáticamente un conflicto de serialización; se devuelve
  409 en esta fase.

## Orden de validación

```text
UUID de ruta
  → body/query
  → existencia
  → relaciones y tipo de recurso
  → estado
  → reglas de negocio
```

## Checklist de validación arquitectónica

Antes de aprobar un checkpoint:

- Controller sin SQL/PDO;
- Validator sin consultas;
- Repository con todo el SQL;
- `DatabaseContext` fija sucursal, usuario y correlación;
- `branch_id` no viene del body salvo login;
- no hay `DELETE FROM`;
- duplicados y conflictos se traducen a 409;
- cancelación solo `pendiente → cancelada`;
- la reserva no confirma ni atiende;
- las suites de servicios, reservas y autenticación pasan;
- los fixtures se restauran.

Si se incumple:

```text
TECHNICAL_STATUS: FAILED
CAN_CONTINUE: false
```

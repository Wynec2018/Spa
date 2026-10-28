# Contrato API — Servicios (piloto)

**Checkpoint:** CP-API-01
**Tabla:** `catalog.service`
**Estado:** aprobado (`HUMAN_STATUS: APPROVED`, 2026-10-20)
**Base:** `/api/v1` · JSON UTF-8
**Alcance:** `Spa/04_decisiones/decisiones_alcance_fase3.md`
**Skills:** `api-design-principles`, `openapi-spec-generation`

---

## 1. Convenciones

- REST sobre JSON; `Content-Type: application/json; charset=utf-8`.
- Versión en la ruta: `/api/v1`.
- IDs UUID v4 en formato canónico; un UUID inválido es `400`.
- Sucursal (`branch_id`): **nunca** se recibe del cliente. La fija el servidor
  desde `APP_BRANCH_ID` y se aplica con `SET LOCAL app.branch_id` (RLS, T-01).
- `estado` es de solo lectura en la API: no se acepta en `POST` ni en `PUT`.
  Solo cambia a `inactivo` mediante `DELETE`.
- Prohibido `DELETE FROM`; la baja es lógica (T-07).
- Dinero: `numeric(12,2)` en BD y **string decimal** en JSON (`"250.00"`),
  para no perder precisión (RNF-021).
- Fechas: ISO 8601 en UTC (`2026-10-20T14:00:00Z`).
- El piloto no implementa autenticación; se ejecuta en entorno local. La sesión
  y los roles llegan en CP-BACK-03.
- Actualización de servicios: *last-write-wins* (no hay columna de versión).
- Sin CORS: el frontend es del mismo origen.

### Envoltura de éxito

```json
{
  "success": true,
  "data": {},
  "meta": {}
}
```

`meta` solo aparece en colecciones.

### Envoltura de error

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Los datos enviados no son válidos.",
    "details": { "precio": ["Debe ser un número mayor o igual a 0."] }
  },
  "request_id": "8f14e45f-ceea-4e8f-9c1a-2b3c4d5e6f70"
}
```

`request_id` proviene de `app.correlation_id` y se repite en los logs.
Nunca se devuelve stack trace ni detalle interno de PostgreSQL.

---

## 2. Recurso `service`

| Campo | Tipo JSON | Obligatorio | Regla |
|---|---|---|---|
| `id` | string (uuid) | Solo lectura | Generado por la BD |
| `nombre` | string | Sí | 1–120, sin espacios en los extremos |
| `duracion_min` | integer | Sí | ≥ 1 y ≤ 1440 |
| `precio` | string decimal | Sí | ≥ 0, máximo 2 decimales, ≤ 9999999999.99 |
| `estado` | string | Solo lectura | `activo` \| `inactivo` |
| `created_at` | string (date-time) | Solo lectura | UTC |
| `updated_at` | string (date-time) | Solo lectura | UTC |

Notas:
- `duracion_min ≤ 1440` es un límite de aplicación (una jornada), no del esquema.
- La unicidad `(branch_id, nombre)` es exacta y la impone la BD
  (`uq_service_nombre`). `"Masaje"` y `"masaje"` se consideran distintos.
  Una unicidad insensible a mayúsculas requeriría un índice funcional
  (cambio de esquema fuera de alcance).
- No se exponen `branch_id` ni campos internos.

---

## 3. Endpoints

### 3.1 `GET /api/v1/servicios`

Parámetros de consulta:

| Parámetro | Valores | Default | Notas |
|---|---|---|---|
| `estado` | `activo` \| `inactivo` | todos | Filtro exacto |
| `q` | string 1–120 | — | Búsqueda parcial por nombre, sin distinguir mayúsculas |
| `page` | entero ≥ 1 | 1 | |
| `per_page` | entero 1–100 | 20 | |
| `sort` | `nombre` \| `precio` \| `duracion_min` \| `created_at` | `nombre` | |
| `order` | `asc` \| `desc` | `asc` | |

`200`:

```json
{
  "success": true,
  "data": [
    {
      "id": "0f8fad5b-d9cb-469f-a165-70867728950e",
      "nombre": "Masaje relajante",
      "duracion_min": 60,
      "precio": "250.00",
      "estado": "activo",
      "created_at": "2026-10-20T14:00:00Z",
      "updated_at": "2026-10-20T14:00:00Z"
    }
  ],
  "meta": { "page": 1, "per_page": 20, "total": 1, "last_page": 1 }
}
```

- Colección vacía: `data: []`, `total: 0`, `last_page: 1`.
- Parámetro inválido: `400 INVALID_REQUEST`.
- Error de BD: `500 INTERNAL_ERROR`.

### 3.2 `GET /api/v1/servicios/{id}`

- `200` → objeto en `data`.
- `400 INVALID_ID` → UUID malformado.
- `404 SERVICE_NOT_FOUND` → no existe o pertenece a otra sucursal.

### 3.3 `POST /api/v1/servicios`

Body:

```json
{
  "nombre": "Masaje relajante",
  "duracion_min": 60,
  "precio": "250.00"
}
```

- `201` → objeto creado en `data`; cabecera `Location: /api/v1/servicios/{id}`.
- `422 VALIDATION_ERROR` → campos ausentes o inválidos.
- `400 INVALID_REQUEST` → campo no permitido (`estado`, `branch_id`, `id`, etc.)
  o JSON malformado.
- `409 DUPLICATE_SERVICE` → ya existe ese nombre en la sucursal.
- `415 UNSUPPORTED_MEDIA_TYPE` → `Content-Type` distinto de JSON.
- `500 INTERNAL_ERROR`.

### 3.4 `PUT /api/v1/servicios/{id}`

Reemplazo total de los campos editables (los tres son obligatorios):

```json
{
  "nombre": "Masaje relajante profundo",
  "duracion_min": 75,
  "precio": "300.00"
}
```

- `200` → objeto actualizado.
- `400 INVALID_ID` / `400 INVALID_REQUEST`.
- `404 SERVICE_NOT_FOUND`.
- `409 DUPLICATE_SERVICE`.
- `422 VALIDATION_ERROR`.
- `estado` no se modifica; un servicio inactivo sigue inactivo.

### 3.5 `DELETE /api/v1/servicios/{id}`

- `200` → objeto con `estado: "inactivo"`.
- Idempotente: si ya está inactivo, `200` con el mismo objeto.
- `400 INVALID_ID`.
- `404 SERVICE_NOT_FOUND`.
- No borra la fila y no hay reactivación en esta fase.

### 3.6 `GET /api/v1/health`

- `200` → `data: { "status": "ok", "db": "ok" }`.
- `503 SERVICE_UNAVAILABLE` → `data: { "status": "degraded", "db": "error" }`,
  sin detalle del error.
- No requiere autenticación y no expone configuración.

---

## 4. Códigos de error

| HTTP | `code` | Cuándo |
|---|---|---|
| 400 | `INVALID_REQUEST` | JSON malformado, campo no permitido, query inválida |
| 400 | `INVALID_ID` | UUID con formato inválido |
| 404 | `SERVICE_NOT_FOUND` | El servicio no existe en la sucursal |
| 405 | `METHOD_NOT_ALLOWED` | Método no soportado en la ruta |
| 409 | `DUPLICATE_SERVICE` | Violación de `uq_service_nombre` (SQLSTATE 23505) |
| 415 | `UNSUPPORTED_MEDIA_TYPE` | Cuerpo no JSON |
| 422 | `VALIDATION_ERROR` | Reglas de §5 incumplidas |
| 500 | `INTERNAL_ERROR` | Falla no controlada; mensaje genérico |
| 503 | `SERVICE_UNAVAILABLE` | `health` sin conexión a BD |

`details` es un objeto `campo → [mensajes]`; para errores sin campos se omite.

---

## 5. Reglas de validación

| Campo | Reglas |
|---|---|
| `nombre` | requerido; string; `trim`; longitud 1–120; no vacío tras `trim` |
| `duracion_min` | requerido; entero; ≥ 1; ≤ 1440; rechaza decimales y strings no numéricos |
| `precio` | requerido; número JSON o string numérico; ≥ 0; ≤ 9999999999.99; máximo 2 decimales; se normaliza a string con 2 decimales |
| Cuerpo | solo `nombre`, `duracion_min`, `precio`; cualquier otro campo → `400` |
| Query | `estado`, `q`, `page`, `per_page`, `sort`, `order`; cualquier otro → `400` |

No se aplica `htmlspecialchars` en la API: la salida es JSON y el escapado
corresponde a la vista (XSS se valida en CP-INT-04).

---

## 6. Comportamiento con la base

- Una transacción por petición; `SET LOCAL app.branch_id = :branch` antes de la consulta.
- `POST`/`PUT`: la violación de unicidad `23505` se traduce a `409`; no se
  reintenta ni se modifica el nombre.
- `DELETE`: `UPDATE catalog.service SET estado='inactivo'` sobre la fila de la sucursal.
- Búsqueda `q`: `ILIKE '%q%'` con `%`, `_` y `\` escapados. No hay índice
  dedicado; el volumen del piloto (RNF-001) no lo exige.
- RLS: si `app.branch_id` no está fijado, la consulta no debe ejecutarse;
  el adaptador responde `500` y registra el incidente (no filtra filas).

---

## 7. Pruebas esperadas

| CP | Casos |
|---|---|
| CP-API-02 | `health` con BD disponible · `.env` sin credenciales versionadas · contexto RLS |
| CP-API-03 | colección con datos · vacía · `estado`/`q` · paginación · query inválida · error BD |
| CP-API-04 | existente · inexistente · UUID inválido · otra sucursal (RLS) |
| CP-API-05 | alta válida (`201`) · campos ausentes (`422`) · nombre duplicado (`409`) · campo extra (`400`) · precio con 3 decimales (`422`) |
| CP-API-06 | edición válida · inexistente · duplicado · intento de enviar `estado` (`400`) |
| CP-API-07 | baja lógica · doble baja (`200`) · inexistente · fila conservada |
| CP-API-08 | OpenAPI 3.1 en `openapi/spa-api-v1.json` validado con Redocly + suite `scripts/api_crud_suite.php` (138 comprobaciones) |

---

## 8. Trazabilidad

| Requisito | Cobertura |
|---|---|
| RF-003 catálogo de servicios | CRUD con nombre, duración, precio y estado |
| RF-016 parámetros | Moneda leída de `app_config` (fase de implementación) |
| RNF-001/010 | Paginación y filtros |
| RNF-021 | `numeric(12,2)` y precio como string |
| RNF-030/031 | RLS por sucursal; autenticación en CP-BACK |
| RNF-034 | Credenciales solo por `.env`, nunca en el contrato |
| R-05 | `POST` no idempotente: documentado |

**HUMAN_STATUS: APPROVED**

# Contrato API — categorías (piloto)

**Checkpoint:** CP-API-01 · **Tabla:** `cat_categoria` · **Estado:** borrador para revisión humana
**Base:** `/api/v1/categorias` · JSON · UTF-8

## Reglas generales

- Respuesta envuelta: `{"success": bool, "data": ..., "error": "..."}`.
- IDs: entero (`BIGINT UNSIGNED`). ID no numérico → `400`.
- `estado`: `activo` | `inactivo`. Solo se expone en respuestas, nunca en bodies de escritura.
- Campos de auditoría (`user_create`, `user_update`, `user_created_at`, `user_update_at`): no se exponen ni se aceptan en el body; los rellena el servidor.
- `categoria_padre_id`: nullable. FK autocíclica `ON DELETE RESTRICT` → la API solo acepta padres existentes; nunca borra físicamente.
- Sin paginación por ahora (colección pequeña). Si crece, añadir `?page=&limit=` (techo conocido).
- Sin filtro de estado en CP-API-03; añadir `?estado=` solo si se solicita.

## Endpoints

### GET `/api/v1/categorias`
- `200` → `data`: array de `{id, nombre, categoria_padre_id, estado}` (incluye inactivas).
- `500` si falla la BD (mensaje genérico, sin stack trace).

### GET `/api/v1/categorias/{id}`
- `200` → objeto.
- `400` → id inválido.
- `404` → no existe.
- `500` → error BD.

### POST `/api/v1/categorias`
Body: `{ "nombre": string, "categoria_padre_id": int|null }`
- `201` → objeto creado.
- `400` → `nombre` ausente, no string o > 100 caracteres.
- `404` → `categoria_padre_id` no existe.
- `500` → error BD.
- No hay unicidad de `nombre` en el esquema → no se devuelve `409` por nombre duplicado.

### PUT `/api/v1/categorias/{id}`
Body: `{ "nombre": string, "categoria_padre_id": int|null }` (ambos obligatorios, reemplazo total)
- `200` → objeto actualizado.
- `400` → validación fallida o id inválido.
- `404` → categoría o padre no existe.
- `400` → si `categoria_padre_id == id` (auto-padre, bucle).
- `500` → error BD.
- `estado` no se modifica por PUT (solo DELETE lógico).

### DELETE `/api/v1/categorias/{id}`
- `200` → `UPDATE estado='inactivo'` (borrado lógico; nunca `DELETE FROM`).
- `404` → no existe.
- `400` → id inválido.
- Idempotente: si ya está inactiva, `200` igual.
- Hijos/productos no impiden inactivar (la FK RESTRICT solo aplica a borrado físico).

## Pruebas esperadas (se ejecutan desde CP-API-03)

| CP | Casos |
|----|-------|
| 03 | colección con datos · colección vacía (`data: []`) · error DB (`500`) |
| 04 | existente · inexistente (`404`) · id inválido (`400`) |
| 05 | POST válido (`201`) · nombre vacío/largo (`400`) · padre inexistente (`404`) |
| 06 | PUT válido · validación (`400`) · id/padre inexistente (`404`) · auto-padre (`400`) |
| 07 | DELETE → `estado='inactivo'` · inexistente (`404`) · doble DELETE (`200`) |

## Trazabilidad

- RF-020 (administrar categorías) → endpoints CRUD.
- `decisiones_api.md` → rutas, JSON, DELETE lógico.
- `modelo_fisico.md` → columnas, ENUM estado, FK autocíclica.

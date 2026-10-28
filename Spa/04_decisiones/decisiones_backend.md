# Decisiones de backend

Sistema: **Spa Cielo**
Fecha: 2026-10-20
Estado: CP-BACK-01/02/03/04/05/06/07/08/09 `APPROVED` (2026-10-21)
Precondición: workflow 03 (`api-pilot-workflow.json`) `COMPLETE` y `APPROVED`.
Alcance: `Spa/04_decisiones/decisiones_alcance_fase3.md`.

---

## 1. Patrón reutilizable (CP-BACK-01)

Se consolidó el CRUD de servicios en capas con responsabilidades separadas:

| Capa | Implementación | Responsabilidad |
|---|---|---|
| HTTP | `app/Http/Controllers/Api/V1/ServiceController.php` | Orquestar; no contiene SQL ni reglas de validación |
| Cuerpo JSON | `app/Support/JsonBody.php` | Exigir `application/json` y objeto JSON |
| Validación | `app/Validation/ServiceValidator.php` | Campos permitidos, reglas, `trim` y normalización de precio |
| Contexto | `app/Support/RequestId.php`, `BranchContext.php`, `RouteId.php` | `request_id`, sucursal configurada y UUID v4 de ruta |
| Persistencia | `app/Repositories/ServiceRepository.php` | SQL, transacción por petición y RLS |
| Presentación | `app/Http/Resources/ServiceResource.php` | Forma JSON del recurso |
| Respuestas | `app/Support/ApiResponse.php` | Envolturas `success/data/meta` y errores |
| Errores | `app/Exceptions/ApiException.php`, `DuplicateServiceException.php` | Errores esperados y conflicto de unicidad |
| Manejador global | `bootstrap/app.php` | Traduce excepciones a HTTP sin filtrar trazas |

Flujo: ruta → controlador → `JsonBody`/validador → repositorio → recurso → `ApiResponse`.
Las mismas piezas se usarán en autenticación y reservas; cada recurso aporta su
propio validador y repositorio.

## 2. Errores

- `ApiException` transporta `status`, `code`, `message`, `details` y, solo para
  `health`, `data`.
- `DuplicateServiceException` se produce en el repositorio al capturar SQLSTATE
  `23505`; el manejador global lo traduce a `409 DUPLICATE_SERVICE`.
- Los errores esperados (`400`, `404`, `409`, `415`, `422`) no se registran como
  fallos internos. Los `5xx` sí se registran con `report()`.
- Ninguna respuesta de `/api/*` expone `trace`, `exception`, rutas del servidor,
  SQL ni credenciales.
- `404` de ruta y `405` usan el mismo envelope y conservan las cabeceras del
  framework (por ejemplo `Allow` en `405`).

| HTTP | Código | Origen |
|---|---|---|
| 400 | `INVALID_REQUEST` | JSON inválido, campo o query no permitido |
| 400 | `INVALID_ID` | UUID de ruta no canónico o no v4 |
| 404 | `SERVICE_NOT_FOUND` | Fila inexistente o invisible por RLS |
| 409 | `DUPLICATE_SERVICE` | `uq_service_nombre` (SQLSTATE `23505`) |
| 415 | `UNSUPPORTED_MEDIA_TYPE` | `Content-Type` distinto de JSON |
| 422 | `VALIDATION_ERROR` | Reglas de campos |
| 500 | `INTERNAL_ERROR` | Falla no controlada |
| 503 | `SERVICE_UNAVAILABLE` | `health` sin base de datos |

## 3. Sucursal, usuario y transacciones

- En servicios el cliente nunca envía `branch_id`: se lee de `APP_BRANCH_ID`.
  Login es la excepción controlada (T-03): recibe `branch_id` porque `contacto`
  es único solo por sucursal.
- Toda consulta pasa por `DatabaseContext::run()`, que abre una transacción y fija
  `app.branch_id`, `app.user_id` y `app.correlation_id` con `set_config(..., true)`.
- `app.correlation_id` es el `request_id` (`X-Request-Id` o UUID generado).
- Una petición equivale a una transacción. No se comparten conexiones ni contexto
  entre peticiones.
- `spa_app` no ejecuta `DELETE FROM`; la baja de servicios es lógica.

## 4. Validación

- `JsonBody` rechaza `Content-Type` incorrecto (`415`), JSON malformado, cuerpos
  escalares o arreglos (`400`).
- Cada validador define la lista cerrada de campos; un campo desconocido en body
  o query produce `400 INVALID_REQUEST`.
- Las reglas de negocio usan `Validator` de Laravel y mensajes en español.
- `precio` se normaliza a cadena con dos decimales; nunca se convierte a `float`
  para persistir. El límite es `9999999999.99`.
- `estado` no se acepta en el body: es de solo lectura y cambia únicamente con
  `DELETE`.

## 5. Repositorios

- No conocen HTTP ni construyen respuestas.
- Reciben la sucursal y el `request_id` ya resueltos.
- Encapsulan filtros, paginación, orden, `ILIKE` escapado, escrituras y el mapeo
  de unicidad.
- Los controladores no acceden a `DB` directamente.

## 6. Autenticación y preparación para reservas

- Login usa `AuthRepository`, `AuthService`, `LoginValidator`, `AuthUserResource`
  y `JsonBody`; no hay SQL ni verificación de contraseña en el controlador.
- La sesión es de servidor; reservas usará `app.user_id` desde la sesión
  autenticada.
- Reservas usa un repositorio con transacción única y
  `SELECT ... FOR UPDATE SKIP LOCKED`; SQLSTATE `23P01` se traduce a `409`.
- `fin` se calculará con `catalog.service.duracion_min`; solo se habilitará
  `pendiente → cancelada` en esta fase (R-02).

## 7. Login (CP-BACK-03)

- `POST /api/v1/auth/login` recibe `contacto`, `password` y `branch_id`; cualquier
  otro campo produce `400 INVALID_REQUEST`.
- `contacto` se recorta y admite hasta 160 caracteres; `password` no se recorta y
  admite hasta 200; `branch_id` debe ser UUID. Los errores de forma son `422`.
- La consulta se ejecuta con `app.branch_id` de la sucursal elegida, une
  `core.branch` y exige `estado='activa'`. Un contacto de otra sucursal no es
  visible.
- La contraseña se verifica con `password_verify` contra el hash Argon2id. Si el
  usuario no existe se verifica contra un hash señuelo para reducir diferencias de
  tiempo. El fallo es siempre `401 INVALID_CREDENTIALS`.
- Un usuario `inactivo` con contraseña correcta recibe `403 USER_INACTIVE`; con
  contraseña incorrecta recibe el `401` genérico.
- Si el hash requiere actualización, se regenera con Argon2id y se actualiza.
- Al autenticar se regenera el ID de sesión (anti-fijación) y se guardan
  `auth_user_id`, `auth_branch_id` y `auth_rol`. La cookie de sesión se cifra y es
  `HttpOnly`, `SameSite=Lax`; no se emite JWT en esta fase.
- Los grupos `/api/v1/auth/*` y `/api/v1/reservas/*` montan `EncryptCookies`,
  `AddQueuedCookiesToResponse` y `StartSession`; reservas añade
  `AuthenticateSession`. El resto de la API sigue sin sesión.
- La respuesta no incluye `hash_password`, `estado` ni credenciales.
- CSRF, rate limiting y endurecimiento de cookies quedan para CP-INT-04.

## 8. Sesión: `me` y `logout` (CP-BACK-04)

- `GET /api/v1/auth/me` exige `auth_user_id` y `auth_branch_id` de la sesión.
  Un UUID ausente o inválido produce `401 UNAUTHENTICATED`.
- La identidad se relee de `auth.app_user` con la sucursal de la sesión, exigiendo
  usuario y sucursal activos. Si el usuario fue desactivado o la sucursal ya no
  está activa, se invalida la sesión y se responde `401 UNAUTHENTICATED`.
- `me` devuelve id, nombre, contacto, rol y `branch_id`; nunca el hash.
- `POST /api/v1/auth/logout` invalida la sesión completa, regenera el token de
  sesión y responde `200` con un mensaje. Es idempotente: sin sesión también
  responde `200`.
- `SessionAuth` valida los UUID de sesión antes de consultar. Las rutas `me` y
  `logout` comparten el grupo con `StartSession` y cookies cifradas.
- CSRF y rate limiting siguen pendientes para CP-INT-04.

## 9. Reservas: listado y detalle (CP-BACK-05)

- `GET /api/v1/reservas` y `GET /api/v1/reservas/{id}` exigen sesión autenticada.
  `AuthenticateSession` revalida usuario y sucursal activos y deja la identidad en
  atributos del request.
- La sucursal sale de `auth_branch_id`; no se acepta `branch_id` por query.
- Filtros de listado: `desde`, `hasta`, `estado`, `page`, `per_page`. El rango es
  `desde <= inicio < hasta`, en ISO 8601; una fecha sin hora se interpreta a las
  00:00 UTC. Un rango invertido o un filtro inválido produce
  `400 INVALID_REQUEST`.
- `estado` acepta `pendiente`, `confirmada`, `cancelada`, `expirada` o `atendida`.
  La paginación usa los mismos límites que servicios (20 por defecto, 100 máximo).
- El listado se ordena por `inicio` descendente y `id` descendente, y devuelve
  cliente, servicio, cama y trabajadora anidados. `precio` se serializa como
  cadena con dos decimales. No se exponen `branch_id` ni campos internos.
- El detalle valida UUID v4 (`400 INVALID_ID`); si la reserva no existe o
  pertenece a otra sucursal responde `404 RESERVATION_NOT_FOUND`.
- Las relaciones `catalog.client`, `catalog.service` y `catalog.resource` también
  están bajo RLS; una reserva de otra sucursal no aparece ni por listado ni por
  detalle.

## 10. Alta de reserva (CP-BACK-06)

- `POST /api/v1/reservas` exige sesión y recibe solo `client_id`, `service_id`,
  `cama_id`, `trabajadora_id` e `inicio`; `fin` no se acepta y un campo extra
  responde `400 INVALID_REQUEST`.
- `inicio` debe ser ISO 8601 con zona horaria; se normaliza a UTC. Los UUID
  inválidos y las fechas mal formadas responden `422 VALIDATION_ERROR`.
- La reserva nace `pendiente`; `fin` se calcula con `catalog.service.duracion_min`
  dentro de la misma transacción, no desde el cliente.
- Validaciones de dominio: cliente de la sucursal (`404 CLIENT_NOT_FOUND`),
  servicio de la sucursal y `activo` (`404 SERVICE_NOT_FOUND`, `409
  SERVICE_INACTIVE`), cama de tipo `cama` y trabajadora de tipo `trabajadora`
  (`422` si el tipo no corresponde), cama distinta de `no_disponible` y
  trabajadora en `libre` (`409 RESOURCE_UNAVAILABLE`).
- La cama en estado `ocupada` no se usa como bloqueo porque el esquema no
  sincroniza ese estado (T-05); su disponibilidad real la decide `EXCLUDE`.
- La trabajadora sí exige `estado='libre'`; el trigger
  `trg_resource_state_on_booking` la deja `ocupada` al crear una reserva activa
  (RN-003) y la libera al cancelar o expirar.
- Concurrencia: transacción `REPEATABLE READ`, bloqueo compartido del servicio y
  `SELECT ... FOR UPDATE SKIP LOCKED` sobre las dos filas de `catalog.resource`
  en orden de `id`. Otra transacción que ya las bloquea produce
  `409 RESOURCE_UNAVAILABLE`.
- El `EXCLUDE` de cama o trabajadora se traduce de `23P01` a
  `409 RESERVATION_CONFLICT`; una falla de serialización o deadlock (`40001`/
  `40P01`) también responde `409` sin reintento. El rollback deja cero filas de
  `booking` y libera cualquier bloqueo.
- Éxito: `201` con el recurso serializado y `Location: /api/v1/reservas/{id}`.
  La respuesta no expone `branch_id`, hashes ni credenciales.

## 11. Cancelación de reserva (CP-BACK-07)

- `PATCH /api/v1/reservas/{id}/estado` exige sesión y recibe solo
  `estado`; un campo extra responde `400 INVALID_REQUEST`. El único valor
  aceptado es `cancelada`; ausencia, tipo incorrecto u otro estado responde
  `422 VALIDATION_ERROR`.
- UUID v4 inválido responde `400 INVALID_ID`; una reserva inexistente o de otra
  sucursal responde `404 RESERVATION_NOT_FOUND`.
- Solo `pendiente` pasa a `cancelada`. Una reserva ya `cancelada` responde `200`
  de forma idempotente, sin nueva auditoría; `confirmada`, `expirada` o `atendida`
  responden `409 INVALID_STATE_TRANSITION` y no se modifican.
- La transacción usa `REPEATABLE READ` y bloquea la reserva con
  `SELECT ... FOR UPDATE`. Una falla de serialización o deadlock (`40001`/
  `40P01`) responde `409 RESERVATION_CONFLICT` sin reintento.
- El trigger `trg_resource_state_on_booking` libera la trabajadora al pasar a
  `cancelada` (RN-003/RN-005); el estado de la cama no se sincroniza (T-05).
  El trigger de auditoría registra el `UPDATE`.
- Éxito: `200` con la reserva serializada. La ruta no permite `confirmada`; la
  guarda de pago verificado (RN-002) se verifica en CP-BACK-08 en dos capas.

## 12. Guardas de confirmación (CP-BACK-08)

- La API no expone una transición a `confirmada` ni a `atendida`: el validador de
  estado solo admite `cancelada`, por lo que un intento directo responde
  `422 VALIDATION_ERROR` y no toca la reserva.
- La base de datos mantiene la defensa en profundidad: el trigger
  `trg_booking_confirm_requires_payment` exige un pago `verificado` (RN-002).
  Un `UPDATE` directo a `confirmada` sin pago falla con SQLSTATE `P0001` y la
  transacción revierte, dejando la reserva `pendiente` y la trabajadora `ocupada`.
- No se crea ni modifica `payment.payment` en esta fase; el flujo de pago sigue
  fuera de alcance (R-02).
- RLS: con `app.branch_id` de otra sucursal, las consultas de `booking`,
  `catalog.client`, `catalog.service` y `catalog.resource` devuelven `0` filas;
  la misma sucursal sigue viendo su reserva.
- Evidencia: `scripts/reservations_guards_suite.php`, 9/9.

## 13. Suite backend (CP-BACK-09)

- `Spa/06_codigo/scripts/backend_suite.php` es el runner final. Comprueba
  `GET /api/v1/health`, exige `DEMO_SUPER_PASSWORD` y
  `DEMO_TRABAJADORA_PASSWORD` y ejecuta las suites contra el servidor local.
- Bloques: CRUD de servicios, login, sesión, lectura, alta y cancelación de
  reservas, guardas de confirmación, PHPUnit y Pint. Cualquier fallo devuelve
  código distinto de cero.
- Resultado: 9/9 bloques OK; 221 comprobaciones de API (138 + 9 + 8 + 17 + 24 +
  16 + 9), PHPUnit 2/2 y Pint OK.
- No añade endpoints ni funcionalidad; cierra la verificación backend. El
  frontend no se inicia en esta fase.

## 14. Seed demo (CP-BACK-02)

- `Spa/06_codigo/tools/seed_demo.php` es idempotente y usa el rol `spa_app`,
  por lo que también respeta RLS.
- Sucursal: usa `APP_BRANCH_ID`; si está vacío, `1111...`. Si la fila no existe,
  la crea como `Sede Central`; si existe, no modifica sus datos.
- Usuarios: `super@spa.local`, `secretaria@spa.local` y `trabajadora@spa.local`,
  con roles `superusuario`, `secretaria` y `trabajadora`.
- Los usuarios se buscan por `(branch_id, contacto)`. Si existen, se actualizan
  rol, nombre y estado; el hash solo cambia si se aporta una contraseña.
- Contraseñas: `DEMO_SUPER_PASSWORD`, `DEMO_SECRETARIA_PASSWORD` y
  `DEMO_TRABAJADORA_PASSWORD` (mínimo 8 caracteres). Si el usuario es nuevo y no
  hay variable, se genera una contraseña aleatoria y se imprime una sola vez;
  nunca se escribe en el repositorio, en `.env` ni en la base de datos.
- Hash: `PASSWORD_ARGON2ID`. La auditoría guarda el hash, no la contraseña.
- En `production` el tool se bloquea salvo `--force` explícito.
- No crea servicios ni reservas; esos datos siguen siendo responsabilidad de sus
  flujos.

## 15. Evidencia

- Suite de contrato: `Spa/06_codigo/scripts/api_crud_suite.php`, 138/138.
- RLS: con otra sucursal, `GET` de un servicio responde `404` y el listado
  devuelve `0` filas.
- `php artisan test`: 2/2.
- Seed: dos ejecuciones (creación y reejecución idempotente); 3 usuarios
  activos; hashes `$argon2id$`; 0 coincidencias de contraseña en texto plano en
  `audit.audit_log`; otra sucursal devuelve 0 usuarios.
- Login: `scripts/auth_login_suite.php`, 9/9; cubre credenciales válidas, cookie
  de sesión, credenciales inválidas, sucursal incorrecta, UUID inválido, campo
  extra y usuario inactivo (restaurado al finalizar). El cuerpo no expone hash ni
  contraseña.
- Sesión: `scripts/auth_session_suite.php`, 8/8; cubre cookie `HttpOnly` y
  `SameSite=Lax`, `me` autenticado, `me` anónimo, logout, `me` posterior a logout
  y logout idempotente.
- Reservas: `scripts/reservations_read_suite.php`, 17/17; cubre listado por rango,
  filtro de estado, paginación, detalle, relaciones, UUID inválido, rango invertido,
  filtros inválidos, acceso anónimo y aislamiento por sucursal con fixtures
  deterministas.
- Alta de reservas: `scripts/reservations_write_suite.php`, 24/24; cubre sesión,
  validación de cuerpo y fecha, cliente/servicio/recurso inexistente, servicio
  inactivo, tipos incorrectos, cama no disponible, trabajadora ocupada, conflictos
  de cama y trabajadora con `409` y cero filas parciales, cálculo de `fin`,
  auditoría, ocupación de la trabajadora, estado de cama y RLS.
- Cancelación: `scripts/reservations_cancel_suite.php`, 16/16; cubre sesión,
  UUID inválido, reserva inexistente, cuerpo/estado inválido, intento de
  confirmación, transición desde `expirada`, cancelación idempotente, liberación
  de la trabajadora, auditoría y RLS desde otra sucursal.
- Guardas: `scripts/reservations_guards_suite.php`, 9/9; confirma que la API
  rechaza `confirmada` y `atendida` sin modificar la reserva, que el trigger de
  BD rechaza un `UPDATE` directo a `confirmada` sin pago (`P0001`, RN-002) y que
  RLS devuelve 0 filas para booking, cliente, servicio y recurso con otra
  sucursal.
- Regresión: lectura 17/17, alta 24/24, cancelación 16/16, guardas 9/9, login 9/9,
  sesión 8/8, CRUD de servicios 138/138 y `php artisan test` 2/2.
- Suite backend: `scripts/backend_suite.php`, 9/9 bloques y 221 comprobaciones de
  API; PHPUnit 2/2; Pint OK.
- Pint: sin cambios requeridos, incluidos seed y suites.

## 16. Trazabilidad

| Requisito | Cobertura |
|---|---|
| RF-001 | Login, consulta de identidad y logout por rol y estado |
| RF-003 | CRUD de servicios sin cambios funcionales |
| RF-004/006/013 | Listado, detalle, alta y cancelación de reservas con cama y trabajadora |
| RNF-005/020 | Consulta paginada, transacciones, bloqueos, EXCLUDE y 409 |
| RNF-006 | Separación controlador/repositorio/recurso |
| RNF-010/031 | Contexto RLS por transacción; aislamiento por sucursal |
| RNF-021 | Precio decimal como cadena |
| RNF-030/033 | Sesión de servidor, Argon2id, regeneración e invalidación |
| RNF-034 | Credenciales solo en `.env`; seed no persiste contraseñas |
| RN-002 | Confirmación rechazada sin pago verificado: enum cerrado en API y trigger en BD |
| RN-003/005 | Trabajadora `ocupada` al reservar y liberada al cancelar; cancelación idempotente |
| D-05/D-07/D-09 | Sucursal única, sesión en lugar de JWT en fase 3, Laravel 13 |

**HUMAN_STATUS: APPROVED**

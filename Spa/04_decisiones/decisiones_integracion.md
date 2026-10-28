# Decisiones de integración

Sistema: **Spa Cielo**
Fecha: 2026-10-24 (actualizado 2026-10-25)
Estado: CP-INT-01, CP-INT-02, CP-INT-03, CP-INT-04 y CP-INT-05 `APPROVED` (CP-INT-04 y CP-INT-05 aprobados 2026-10-25)
Alcance: `Spa/04_decisiones/decisiones_alcance_fase3.md`
Precondición: workflow 05 `COMPLETE` y `APPROVED` (2026-10-24).
Workflow: `agents/workflows/06_integration_workflow.md`.
Aprobación: CP-INT-01, CP-INT-02 y CP-INT-03 aprobados por el humano el 2026-10-24; CP-INT-04 y CP-INT-05 aprobados el 2026-10-25.

---

## 1. Regla de integración

- Un checkpoint por interacción; este documento registra CP-INT-01, CP-INT-02, CP-INT-03, CP-INT-04 y CP-INT-05 aprobados.
- No se agregan módulos, pantallas ni tablas.
- No se modifica el esquema ni las decisiones de API, backend o frontend.
- CP-INT-01 valida el flujo real login → dashboard → logout y el contexto de sucursal.
- CP-INT-02 valida el flujo real de servicios y la baja lógica.
- CP-INT-03 valida el flujo real de reservas, el conflicto 409 y la cancelación.
- CP-INT-04 valida sesión, SQLi, XSS, CSRF, secretos, logs, RLS y RN-002.
- CP-INT-05 valida el cierre y la regresión final; aprobado.

## 2. Flujo verificado (CP-INT-01)

| Paso | Resultado esperado | Resultado |
|---|---|---|
| `GET /login` | 200, selector de sucursal, CSRF y formulario | OK |
| `POST /login` válido | 302 a `/dashboard`, sesión autenticada | OK |
| `GET /dashboard` | 200 con sede, usuario, contacto y rol | OK |
| `GET /api/v1/auth/me` | 200 con la misma cookie de sesión | OK |
| `GET /login` con sesión | 302 a `/dashboard` | OK |
| `POST /logout` con CSRF | 302 a `/login`, sesión invalidada | OK |
| `GET /dashboard` con cookie anterior | 302 a `/login` | OK |
| `GET /api/v1/auth/me` con cookie anterior | 401 | OK |

La sesión web se reutiliza para `/api/v1/auth/me`; no se emite JWT ni se
duplica la autenticación en el frontend.

## 3. Sesión y sucursal

- Cookie: `spa-cielo-session`.
- Atributos verificados: `HttpOnly`, `SameSite=Lax`, `Path=/`.
- El ID de sesión se regenera al autenticar.
- Claves de sesión: `auth_user_id`, `auth_branch_id`, `auth_rol`.
- `POST /logout` invalida la sesión; la cookie anterior no puede volver a
  acceder al dashboard ni a `me`.
- La sucursal sale de `APP_BRANCH_ID` mediante `BranchContext`; el nombre se
  lee de `core.branch` bajo RLS.
- `POST /login` exige que `branch_id` coincida con `APP_BRANCH_ID`; un
  formulario manipulado no autentica contra otra sede.
- `me` devuelve `id`, `nombre`, `contacto`, `rol` y `branch_id`; no expone
  `hash_password` ni la contraseña.

## 4. Casos negativos

- Sucursal distinta: error de campo, sin sesión y sin acceso al dashboard.
- Contraseña incorrecta: error de campo, sin sesión y sin acceso al dashboard.
- `POST /login` sin CSRF: 419.
- `GET /dashboard` anónimo: 302 a `/login`.
- `GET /api/v1/auth/me` anónimo: 401.

## 5. Evidencia

- Suite de integración web: **43 comprobaciones, 0 fallos**.
- Login válido, dashboard, `me`, logout, regeneración de sesión, cookie
  `HttpOnly`/`SameSite=Lax`/`Path=/`, casos negativos y bloqueo posterior al
  logout verificados.
- `Pint`: OK.
- `PHPUnit`: 2/2 pruebas, 3 aserciones.
- `node --check` sobre `login.js`, `reservations.js` y `services.js`: OK.
- `view:cache` y `view:clear`: OK.
- Sin cambios de código de aplicación en este checkpoint.

La suite usó un hash temporal para `secretaria@spa.local` y lo restauró al
hash original dentro del mismo proceso. No se persistió ninguna contraseña ni
se alteraron fixtures o datos de negocio.

## 6. Decisiones

- El login de integración es el formulario web con CSRF; no se agrega un login
  separado para el frontend.
- El contexto de sucursal permanece fijado por configuración y RLS; el cliente
  no puede cambiarlo.
- La sesión de servidor es la única fuente de identidad para las pantallas y
  para `me`.
- La integración no modifica el código aprobado; solo lo verifica de extremo a
  extremo.
- Los checkpoints siguientes repiten la regla de un checkpoint por interacción.

## 7. Servicios (CP-INT-02)

| Paso | Resultado esperado | Resultado |
|---|---|---|
| `GET /servicios` | 200 con el catálogo de la sede | OK |
| `GET /servicios/nuevo` | 200 con formulario y CSRF | OK |
| `POST /servicios` válido | 302 al catálogo; servicio activo | OK |
| `POST /servicios` duplicado | error de nombre, sin fila nueva | OK |
| `POST /api/v1/servicios` duplicado | 409 `DUPLICATE_SERVICE` | OK |
| `GET /servicios/{id}/editar` | 200 con los datos actuales | OK |
| `PUT /servicios/{id}` | 302; nombre, duración y precio actualizados | OK |
| `POST /servicios/{id}/inactivar` | 302; estado `inactivo` | OK |
| `GET /servicios?estado=activo` | excluye el servicio inactivado | OK |
| `GET /servicios?estado=inactivo` | incluye el servicio inactivado | OK |
| `DELETE /api/v1/servicios/{id}` | 200; mismo id inactivo, fila conservada | OK |

- El duplicado web se traduce a un error del campo `nombre`; el API mantiene
  `409 DUPLICATE_SERVICE`.
- La inactivación es lógica: no se ejecuta `DELETE FROM` y la fila permanece.
- El API `DELETE` también es baja lógica; devuelve el mismo registro inactivo.
- La vista de un servicio inactivo no ofrece reactivación en esta fase.
- El servicio creado conserva el `branch_id` de la sede configurada.

## 8. Evidencia CP-INT-02

- Suite de integración web/API: **47 comprobaciones, 0 fallos**.
- `Pint`: OK.
- `PHPUnit`: 2/2 pruebas, 3 aserciones.
- `node --check` sobre `login.js`, `reservations.js` y `services.js`: OK.
- `view:cache` y `view:clear`: OK.
- Sin cambios de código de aplicación en este checkpoint.

Fixtures antes: 24 servicios (3 activos, 21 inactivos). Después: 26 servicios
(3 activos, 23 inactivos). Las dos filas de prueba quedaron inactivas:

- `153425f3-4f81-40d1-a6c6-09ab20e20994` — `Servicio integracion CP-INT-02 593206 editado`.
- `cc99f475-8027-404d-b758-ec44695a8998` — `Servicio integracion CP-INT-02 5E1E58 editado`.

No se borraron porque `spa_app` no tiene privilegio de borrado físico. La suite
usó un hash temporal para `secretaria@spa.local` y lo restauró al hash original.

## 9. Reservas (CP-INT-03)

| Paso | Resultado esperado | Resultado |
|---|---|---|
| `GET /reservas` | 200 con la agenda de la sede | OK |
| `GET /reservas/nueva` | 200 con cliente, servicio, cama, trabajadora y CSRF | OK |
| `POST /reservas` válido | 302 a la agenda; reserva `pendiente` | OK |
| `POST /reservas` con solapamiento | error de franja; sin fila nueva | OK |
| `POST /api/v1/reservas` con solapamiento | 409 `RESERVATION_CONFLICT`; sin fila nueva | OK |
| `POST /reservas/{id}/cancelar` | 302; estado `cancelada`; trabajadora libre | OK |
| `GET /reservas?fecha=...&estado=pendiente` | sin resultados tras cancelar | OK |
| `GET /reservas?fecha=...&estado=cancelada` | incluye la reserva cancelada | OK |

- La reserva creada usa `Cliente CP-BACK-05`, `Corte de cabello premium`
  (50 min), `Cama CP-BACK-05` y `Trabajadora CP-BACK-06 alta`, de
  10:00 a 10:50 UTC del 2032-06-15.
- El alta deja la reserva en `pendiente` y la trabajadora A en `ocupada`.
- El conflicto web se traduce a un error de franja; el API mantiene
  `409 RESERVATION_CONFLICT`. Ninguno crea una fila nueva.
- La segunda trabajadora libre permanece libre: el conflicto es por la cama,
  no por `RESOURCE_UNAVAILABLE`.
- La cancelación es lógica: la fila se conserva y el trigger
  `booking.fn_sync_trabajadora_estado` devuelve la trabajadora A a `libre`.
- No se ejecuta `DELETE FROM`; `spa_app` no tiene privilegio de borrado físico.

## 10. Evidencia CP-INT-03

- Suite de integración web/API: **47 comprobaciones, 0 fallos**.
- `Pint`: OK.
- `PHPUnit`: 2/2 pruebas, 3 aserciones.
- `node --check` sobre `login.js`, `reservations.js` y `services.js`: OK.
- `view:cache` y `view:clear`: OK.
- Sin cambios de código de aplicación en este checkpoint.

Fixtures antes: 18 reservas (14 canceladas, 3 pendientes, 1 expirada). Después:
19 reservas (15 canceladas, 3 pendientes, 1 expirada). La fila de prueba queda
cancelada:

- `bfd31a18-f336-467f-a757-c57445974f5c` — `Cliente CP-BACK-05` /
  `Corte de cabello premium` (50 min) / `Cama CP-BACK-05` /
  `Trabajadora CP-BACK-06 alta` / 2032-06-15 10:00–10:50 UTC / `cancelada`.

La suite usó un hash temporal para `secretaria@spa.local` y lo restauró al hash
original dentro del mismo proceso. No se persistió ninguna contraseña.

## 11. Seguridad (CP-INT-04)

| Caso | Resultado esperado | Resultado |
|---|---|---|
| `GET /dashboard` anónimo | 302 a `/login` | OK |
| `GET /api/v1/auth/me` anónimo | 401 | OK |
| Cookie de sesión alterada | `/me` 401 | OK |
| `POST /login` válido | 302; ID de sesión regenerado | OK |
| Atributos de cookie | `HttpOnly`, `SameSite=Lax`, `Path=/` | OK |
| `POST /logout` | 302; dashboard y `me` posteriores sin acceso | OK |
| SQLi en `q`, `sort` y `estado` | sin bypass; `%` y `_` escapados; orden/estado inválidos rechazados | OK |
| SQLi en id/estado de reserva | sin ejecución SQL, sin bypass y sin 500 | OK |
| SQLi en login web/API | sin sesión; error de credenciales en web | OK |
| XSS reflejado en `q` | escapado en `/servicios` | OK |
| XSS en `old()` del login | escapado al reintentar | OK |
| XSS almacenado en servicio | escapado en el listado; servicio restaurado | OK |
| CSRF en login, servicios, reservas y logout | 419; sin cambios de estado | OK |
| Secretos en `.env` | ignorado por git y no rastreado | OK |
| `.env.example` | `APP_KEY` y `DB_PASSWORD` vacíos | OK |
| Secretos hardcodeados | sin coincidencias en archivos versionados | OK |
| Logs y respuestas | sin hash, password, `APP_KEY`, `DB_PASSWORD`, SQLSTATE, traza ni cookie | OK |
| RLS sede actual | servicios y reservas de Sede Central visibles | OK |
| RLS sede desconocida | sin servicios, reservas ni sede | OK |
| RLS dos sedes | sede B aislada; A no ve B y B no ve A | OK |
| RLS rollback | sin sede ni servicio temporales | OK |
| RN-002 API anónimo | 401 | OK |
| RN-002 API autenticado | 422 sin pago verificado | OK |
| RN-002 en base de datos | `UPDATE` directo rechazado por trigger `P0001`; reserva sigue `pendiente` | OK |

- La API de servicios no exige autenticación por decisión vigente
  (`decisiones_api.md`); no se registra como hallazgo.
- Rate limiting no forma parte de CP-INT-04; sigue pendiente en
  `decisiones_backend.md`.
- No se modificó código de aplicación en este checkpoint.

## 12. Evidencia CP-INT-04

- Suite de integración web/API: **228 comprobaciones, 0 fallos**.
- `Pint`: OK.
- `PHPUnit`: 2/2 pruebas, 3 aserciones.
- `node --check` sobre `login.js`, `reservations.js` y `services.js`: OK.
- `view:cache` y `view:clear`: OK.
- Fixtures sin cambios permanentes: 26 servicios (3 activos, 23 inactivos),
  19 reservas (15 canceladas, 3 pendientes, 1 expirada), 0 pagos y 1 sede.
- El servicio usado para el XSS almacenado se restauró a su nombre y datos
  originales.
- La sede y el servicio temporales de la prueba RLS se revirtieron; no quedaron
  filas.
- `.env` no está rastreado; `.env.example` mantiene `APP_KEY` y `DB_PASSWORD`
  vacíos; no se encontraron secretos hardcodeados ni en logs/respuestas.
- La suite usó un hash Argon2id temporal para `secretaria@spa.local` y lo
  restauró al hash original dentro del mismo proceso. No se persistió ninguna
  contraseña.

## 13. Cierre (CP-INT-05)

| Caso | Resultado esperado | Resultado |
|---|---|---|
| Anónimo en dashboard/servicios/reservas | 302 a `/login` | OK |
| API `me` y reservas anónimas | 401 | OK |
| `POST /login` válido | 302 a `/dashboard`; sesión regenerada | OK |
| Cookie de sesión | `HttpOnly`, `SameSite=Lax`, `Path=/` | OK |
| Dashboard | 200 con sede, usuario y contacto | OK |
| Servicios | listado, filtros activo/inactivo, edición y alta | OK |
| Reservas | listado filtrado por fecha/estado pendiente y alta | OK |
| API `me` | 200 con usuario y sucursal | OK |
| API servicios | 200 con 3 activos | OK |
| API reservas | 200 e incluye la reserva pendiente | OK |
| CSRF negativo | 419 en logout y alta de servicio; sin cambios | OK |
| Logout | 302; dashboard 302; `me` 401; login 200 | OK |
| Rutas heredadas | `/categorias` y `/productos` ausentes (404) | OK |
| State, documento y artefactos | presentes y coherentes | OK |
| Fixtures | 1 sede, 26 servicios, 19 reservas, 0 pagos; sin cambios | OK |

- El cierre es de solo lectura: no crea servicios ni reservas.
- `db:show` confirma 15 tablas en `audit`, `auth`, `booking`, `catalog`,
  `core`, `payment` y `support`.
- El esquema se gestiona con los scripts SQL `V0001`–`V0011`; no se usa la tabla
  de migraciones de Laravel, por lo que `migrate:status` no aplica.
- `route:list` mantiene solo las rutas del alcance aprobado; no hay rutas de la
  tienda heredada.
- No se modificó código de aplicación en este checkpoint.

## 14. Evidencia CP-INT-05

- Suite de cierre web/API: **112 comprobaciones, 0 fallos**.
- Regresión de seguridad CP-INT-04: **228 comprobaciones, 0 fallos**.
- `Pint`: OK.
- `PHPUnit`: 2/2 pruebas, 3 aserciones.
- `node --check` sobre `login.js`, `reservations.js` y `services.js`: OK.
- `view:cache` y `view:clear`: OK.
- `db:show`: 15 tablas presentes; `route:list`: rutas en alcance.
- Fixtures sin cambios: 1 sede, 26 servicios (3 activos, 23 inactivos),
  19 reservas (15 canceladas, 3 pendientes, 1 expirada) y 0 pagos.
- El hash Argon2id temporal de `secretaria@spa.local` se restauró al original;
  no se persistió ninguna contraseña.
- `.env` no está rastreado; `.env.example` mantiene `APP_KEY` y `DB_PASSWORD`
  vacíos; no se encontraron secretos hardcodeados ni en logs/respuestas.

## 15. Cierre del workflow

CP-INT-05 aprobado con 112/112 comprobaciones. El workflow 06 queda cerrado.

`HUMAN_STATUS: APPROVED`

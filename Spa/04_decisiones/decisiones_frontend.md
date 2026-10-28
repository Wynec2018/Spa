# Decisiones de frontend

Sistema: **Spa Cielo**
Fecha: 2026-10-21
Estado: CP-FRONT-06 implementado y probado, pendiente de aprobación humana (2026-10-22)
Alcance: `Spa/04_decisiones/decisiones_alcance_fase3.md`
Precondición: workflow 04 `COMPLETE` y `APPROVED`.
Aprobación: CP-FRONT-01 a CP-FRONT-05 aprobados por el humano el 2026-10-21. CP-FRONT-06 pendiente de aprobación.

## Stack

- Laravel Blade + HTML + CSS + JavaScript puro.
- Sin React/Vue/Angular ni framework de UI.
- Sin build obligatorio: CSS y JS estáticos en `public/`; no se usa Vite para esta fase.
- Sesión de servidor emitida por el backend Laravel.
- Pantallas permitidas: `/login`, `/dashboard`, `/servicios`, `/reservas`.

## Dirección visual

- Modo principal: producto/servicio con apoyo editorial.
- Tesis: calma cálida y orden operativo; fondo marfil, verde bosque y arcilla.
- Tipografía: serif local para títulos, sans del sistema para interfaz; sin fuentes externas.
- Profundidad y color con moderación; sin animaciones decorativas ni imágenes de stock.
- El login prioriza claridad, confianza y recuperación de errores.

## Login (CP-FRONT-01)

- Rutas web: `GET /login`, `POST /login` y `POST /logout`.
- La sucursal se muestra como selector; el valor sale de `APP_BRANCH_ID` y el nombre de `core.branch` (lectura con RLS).
- Campos: sucursal, contacto y contraseña. `branch_id` no se elige libremente en esta operación de una sede.
- El formulario se envía al servidor con CSRF y usa `AuthService`; no expone credenciales a JavaScript.
- Éxitos redirigen a `/dashboard`; el placeholder de dashboard verifica la sesión y CP-FRONT-02 lo reemplaza.
- Errores: credenciales inválidas, usuario inactivo, validación y fallo de red/servidor. El resumen usa `role="alert"` y los campos se asocian a sus mensajes.
- Carga: el botón se deshabilita y anuncia `Ingresando…`; el alta de sesión no muestra éxito antes de la redirección.
- JavaScript es mejora progresiva: mostrar/ocultar contraseña, foco del resumen y estado de carga.

## Implementación de CP-FRONT-01

- `BranchRepository` lee `id` y `nombre` de `core.branch` bajo `DatabaseContext` y RLS.
- `LoginRequest` valida `branch_id` UUID, `contacto` de 1 a 160 caracteres y `password` de hasta 200; recorta espacios y usa mensajes en español.
- `LoginPageController` muestra la sucursal configurada, autentica con `AuthService`, regenera la sesión y guarda `auth_user_id`, `auth_branch_id` y `auth_rol`.
- El `POST /login` exige que `branch_id` coincida con `APP_BRANCH_ID`; un formulario manipulado no puede autenticar contra otra sede.
- Credenciales inválidas e inactividad se traducen a errores de campo; el resumen usa `role="alert"` y recibe foco.
- `public/css/app.css` implementa la dirección visual, estados de foco/error, carga, responsive y reduced motion.
- `public/js/login.js` es mejora progresiva: alterna la contraseña, enfoca el resumen con errores y bloquea el envío mientras muestra `Ingresando…`.
- `/dashboard` es un placeholder autenticado con usuario, rol y cierre de sesión; CP-FRONT-02 lo reemplaza.

## Verificación CP-FRONT-01

- `php -l` y Pint sobre rutas, request, repositorio y controladores: OK. `node --check` sobre `login.js`: OK.
- `GET /login` y assets estáticos: 200. `POST` con credenciales inválidas: error visible, foco en `#login-status`, `aria-invalid="true"` y contacto conservado.
- Validación de servidor con campos vacíos: mensajes de contacto y contraseña, sin depender de JavaScript.
- `POST` con una sucursal distinta de `APP_BRANCH_ID`: rechazado antes de `AuthService` con error de campo.
- Login válido: 302 a `/dashboard`; dashboard autenticado 200; `/dashboard` anónimo 302 a `/login`; logout 302 a `/login`; `/login` autenticado redirige a `/dashboard`.
- Verificación CDP en 360, 768 y 1280 px: sin desbordamiento horizontal, sin IDs duplicados, sin controles interactivos sin nombre y sin errores de consola o red.
- Capturas revisadas: login en 360/768/1280, login con error y dashboard en 360/1280.
- Contraste de pares críticos de texto, placeholder y borde de campo verificado contra WCAG AA; foco visible y objetivos táctiles de 48 px en formulario.

## Layout y dashboard (CP-FRONT-02)

- `layouts/app.blade.php` es el layout autenticado común: marca, navegación, usuario, cierre de sesión y footer. Espera `$user` desde el controlador.
- Navegación: `Resumen` y `Servicios` son enlaces; el estado activo se calcula por ruta. `Reservas` sigue deshabilitada con badge `Pronto` hasta CP-FRONT-06. En 560 px o menos el badge se oculta, `aria-label` anuncia “próximamente” y el enlace deshabilitado baja su opacidad.
- `DashboardRepository` agrupa por `estado` los conteos de `catalog.service` y `booking.booking` bajo `DatabaseContext`/RLS; devuelve activos, inactivos, total, pendientes, canceladas y total.
- `DashboardPageController` valida la sesión, obtiene el nombre de la sede y el resumen, y renderiza en servidor; los conteos no se consultan desde JavaScript.
- `dashboard.blade.php` muestra hero, sede y fecha, tarjeta de servicios activos, tarjeta oscura de reservas pendientes y resumen de sesión.
- El layout y el dashboard usan `.app-*`, `.dashboard-*` y `.stat-*`; se retiraron los estilos `.panel-*` del placeholder.

## Verificación CP-FRONT-02

- `php -l` sobre `DashboardRepository` y `DashboardPageController`: OK. Pint OK. `view:cache` y `view:clear`: OK. `/api/v1/health`: 200.
- Login válido: 302 a `/dashboard`; dashboard autenticado: 200. El HTML contiene los conteos y detalles esperados.
- Fixtures actuales: 3 servicios activos de 22 (19 inactivos) y 3 reservas pendientes de 14 (10 canceladas). Los conteos son dinámicos y dependen de los datos vigentes.
- CDP en 360, 768 y 1280 px: sin desbordamiento horizontal, IDs duplicados, controles sin nombre ni errores de consola/red. En esa verificación, `Resumen` estaba activo y ambos catálogos deshabilitados; CP-FRONT-03 habilita `Servicios`.
- Capturas full-page revisadas en 360, 768 y 1280 px. La navegación móvil permanece en una sola fila.

## Listado de servicios (CP-FRONT-03)

- Ruta web autenticada `GET /servicios` (`services.index`); sin sesión redirige a `/login`.
- `ServicePageController` reutiliza la sesión, la sede y `ServiceRepository` bajo `DatabaseContext`/RLS. No duplica SQL ni usa fetch desde JavaScript.
- Filtros de servidor: `q` por nombre (recortado y limitado a 120 caracteres) y `estado` cerrado a `activo`/`inactivo`. Valores inválidos se ignoran.
- Paginación de 10 filas, orden por nombre ascendente. Una página fuera de rango se ajusta a la última disponible.
- `AppConfigRepository` lee `moneda` de `core.app_config`; la vista muestra `Bs` para `BOB` y usa el código como respaldo.
- Vista: encabezado editorial, contador total/filtrado, formulario de filtros, tabla de servicio/duración/precio/estado, estado vacío y paginación que conserva filtros.
- En 700 px o menos la tabla se transforma en tarjetas con etiquetas visibles (CP-FRONT-09 elevó el breakpoint a 940 px); la paginación móvil conserva anterior/siguiente y el indicador de página.
- `public/css/app.css` añade estilos de catálogo, filtros, estados, tabla, tarjetas, paginación y `.sr-only`.

## Verificación CP-FRONT-03

- `php -l`, Pint y `view:cache`/`view:clear`: OK. `php artisan test`: 2/2; se actualizó el test base para esperar la redirección de `/` a `/login`.
- Listado por defecto: 22 servicios, 10 filas, 3 páginas, precios en `Bs` y estados correctos.
- Filtro por UI `q=masaje&estado=activo`: 1 resultado, `Masaje relajante`; el formulario conserva ambos valores y la nota de filtros.
- `estado=inactivo`: 19 resultados en 2 páginas; la segunda página muestra 11–19 y sus enlaces conservan el estado.
- `page=3`: 21–22 y página actual 3; `page=99` se ajusta a la página 3. `q=noexiste`: estado vacío, sin tabla y enlace para limpiar.
- CDP en 360, 768 y 1280 px: sin desbordamiento horizontal, IDs duplicados, controles sin nombre ni errores de consola/red. Tabla en escritorio/tablet y tarjetas en móvil.
- Capturas revisadas: listado en 360, 768 y 1280 px. Regresión de `/dashboard`: 200, `Resumen` activo y conteos 3/3.

## Alta de servicios (CP-FRONT-04)

- Rutas web autenticadas `GET /servicios/nuevo` (`services.create`) y `POST /servicios` (`services.store`); sin sesión redirigen a `/login`.
- `ServicePageController` reutiliza la sesión, la sede y la moneda de `core.app_config`; no duplica el contexto de autenticación.
- El alta usa `ServiceValidator::payload()` y `ServiceRepository::create()` bajo `DatabaseContext`/RLS. La web acepta coma o punto decimal y normaliza el precio; un `DuplicateServiceException` se traduce a un error del campo `nombre`.
- `services/create.blade.php` contiene el formulario accesible: etiquetas asociadas, ayudas, resumen `role="alert"`, errores por campo, `aria-invalid` y `aria-describedby`. La validación funciona sin JavaScript.
- `public/js/services.js` es mejora progresiva: enfoca el resumen de errores y bloquea el botón con el texto `Creando…` mientras se envía.
- El catálogo incorpora el CTA `Nuevo servicio`; tras crear, redirige a `/servicios` con un flash `role="status"`.
- El alta crea servicios en estado `activo`; no se añaden edición, inactivación ni borrado físico en este checkpoint.

## Verificación CP-FRONT-04

- `php -l`, Pint y `view:cache`/`view:clear`: OK. `node --check services.js`: OK. `php artisan test`: 2/2.
- `GET /servicios/nuevo` autenticado: 200; anónimo: 302 a `/login`. `/servicios` muestra el CTA y el formulario conserva la moneda de la sede.
- CDP en 360, 768 y 1280 px: sin desbordamiento horizontal, IDs duplicados, controles sin nombre ni errores de consola/red. El foco inicial está en `nombre`; en móvil el formulario y las acciones se apilan.
- Envío vacío: resumen visible y enfocado (`#service-form-status`), tres errores de campo, `aria-invalid` en nombre, duración y precio; sin depender de JavaScript para validar.
- Nombre duplicado (`Masaje relajante`): error asociado a `nombre`, campo marcado y valores de duración/precio conservados.
- Alta válida con precio `199,99`: se crea como activo, se muestra `Bs 199,99` y el catálogo vuelve con flash de éxito. La fila de prueba `Aseo web CP-FRONT-04 …` se inactivó después; no se borró físicamente.
- Fixtures tras la limpieza: 23 servicios en total, 3 activos y 20 inactivos. Capturas revisadas: formulario en 360/768/1280, errores vacíos y alta exitosa.

## Edición e inactivación de servicios (CP-FRONT-05)

- Rutas web autenticadas `GET /servicios/{id}/editar` (`services.edit`), `PUT /servicios/{id}` (`services.update`) y `POST /servicios/{id}/inactivar` (`services.deactivate`); sin sesión redirigen a `/login`.
- `ServicePageController` reutiliza la sesión, la sede, la moneda y `ServiceRepository` bajo `DatabaseContext`/RLS. El estado del servicio se lee del registro y no se acepta desde el formulario.
- La edición usa `ServiceValidator::payload()` y `ServiceRepository::update()`; la web normaliza coma o punto decimal y conserva los valores ante errores de validación o duplicado.
- La inactivación es una baja lógica mediante `ServiceRepository::deactivate()`; no existe borrado físico ni reactivación en esta fase.
- `services/edit.blade.php` muestra el formulario, el estado actual, la confirmación de inactivación en `details`/`summary` y un aviso específico cuando el servicio ya está inactivo.
- El catálogo incorpora la acción `Editar` por fila; las confirmaciones de actualización e inactivación vuelven con un flash `role="status"`.
- `public/js/services.js` mantiene la mejora progresiva: enfoca el resumen de errores, marca la carga como `Guardando…` o `Inactivando…` y deshabilita el botón correspondiente.
- La vista y las acciones del catálogo se adaptan a tabla en escritorio/tablet y tarjetas en móvil; los estados y el foco visible se conservan.

## Verificación CP-FRONT-05

- `php -l` sobre controlador y rutas, Pint, `node --check services.js` y `view:cache`/`view:clear`: OK. `php artisan test`: 2/2, 3 aserciones.
- Acceso autenticado a `/servicios` y a la edición: 200. Acceso anónimo: 302 a `/login`. UUID inválido o inexistente: 404.
- CDP en 1280, 768 y 360 px: sin desbordamiento horizontal, IDs duplicados, controles sin nombre ni errores de consola/red; la acción `Editar` permanece visible.
- Envío vacío: resumen enfocado, tres errores asociados y `aria-invalid`; el nombre duplicado muestra el error de nombre sin perder los demás valores.
- Edición válida con precio `159,90`: se actualiza a `Bs 159,90`, se conserva activo y vuelve al catálogo con flash de éxito.
- La confirmación de inactivación abre el `details`, recibe foco el `summary` y la baja deja el servicio `Inactivo` con flash de éxito; la vista posterior informa que no hay reactivación.
- La fila de prueba `Aseo web CP-FRONT-05 … editado v2` quedó inactiva; no se borró físicamente. Fixtures finales: 24 servicios, 3 activos y 21 inactivos.
- Capturas revisadas: catálogo 1280/768/360, edición 360/768/1280, errores vacíos, confirmación de inactivación, catálogo actualizado/inactivo y edición de un servicio inactivo.

## Listado de reservas (CP-FRONT-06)

- Ruta web autenticada `GET /reservas` (`reservations.index`); sin sesión redirige a `/login`. El enlace `Reservas` del layout queda habilitado y se marca con `aria-current="page"`.
- `ReservationPageController` reutiliza la sesión, la sede y `ReservationRepository::paginate()` bajo `DatabaseContext`/RLS; no duplica SQL ni usa fetch desde JavaScript.
- Filtros de servidor: `fecha` (`YYYY-MM-DD`, por defecto hoy UTC) y `estado` cerrado a `pendiente`, `confirmada`, `cancelada`, `expirada` y `atendida`. Fecha o estado inválidos se ignoran y vuelven a hoy/todos.
- El rango se calcula como `[00:00, +1 día)` en UTC; la tabla rotula las horas como UTC. La paginación es de 10 filas y una página fuera de rango se ajusta a la última disponible.
- Vista: encabezado editorial, contador total/filtrado, formulario de filtros, tabla de hora, cliente, servicio, recursos y estado, estado vacío y paginación que conserva filtros.
- El estado vacío distingue entre fecha sin reservas y combinación de fecha/estado sin resultados; ofrece `Limpiar`, `Ver todos los estados` o `Ir a hoy` según corresponda.
- En 700 px o menos la tabla se transforma en tarjetas con etiquetas visibles (CP-FRONT-09 elevó el breakpoint a 940 px); en 360 px las acciones y los filtros se apilan sin desbordamiento horizontal.
- `public/css/app.css` añade estilos para la tabla, recursos, estados de reserva, filtros y estado vacío, reutilizando la dirección visual y los componentes existentes.

## Verificación CP-FRONT-06

- `php -l` sobre controlador y rutas, Pint, `view:cache` y `php artisan test`: OK. 2/2 pruebas, 3 aserciones.
- Acceso anónimo a `/reservas`: 302 a `/login`; sesión autenticada: 200. `Reservas` es el enlace activo con `aria-current="page"` en las variantes probadas.
- Fecha por defecto (2026-10-22 UTC): 0 reservas y estado vacío correcto. `fecha=2030-03-01`: 7 reservas, todas `Cancelada`. `fecha=2030-01-15&estado=pendiente`: 1 reserva `Pendiente`.
- `fecha=2030-03-01&estado=pendiente`: estado vacío específico con `Ver todos los estados` e `Ir a hoy`. `fecha=2030-03-01&estado=cancelada` enviado desde el formulario: 7 resultados y filtros conservados.
- `fecha=2030-10-03&estado=expirada`: 1 resultado. Fecha inválida, estado inválido y `page=999`: normalizados a hoy/todos/página 1 sin error; `page=2` con una sola página se ajusta a la página 1.
- CDP en 1280, 768 y 360 px: sin desbordamiento horizontal, IDs duplicados, controles sin nombre ni errores de consola/red. Tabla en escritorio/tablet y tarjetas en móvil.
- Capturas revisadas: reservas por defecto 360/768/1280, fecha con 7 resultados 360/768/1280, filtro pendiente, estado vacío filtrado, parámetros inválidos y envío del formulario.
- La paginación no se pudo ejercitar visualmente con los fixtures actuales porque ninguna fecha supera 10 reservas; se verificó el ajuste de página fuera de rango. Los enlaces conservan `fecha` y `estado`.
- CP-FRONT-06 aprobado por el humano el 2026-10-22.

## Alta de reservas (CP-FRONT-07)

- Rutas web autenticadas `GET /reservas/nueva` (`reservations.create`) y `POST /reservas` (`reservations.store`); sin sesión redirigen a `/login`.
- `ReservationPageController` reutiliza la sesión, la sede y `ReservationRepository::formOptions()`/`create()` bajo `DatabaseContext`/RLS; no duplica SQL ni usa fetch desde JavaScript.
- Opciones del formulario: clientes existentes, servicios activos, camas y trabajadoras de la sede. Las camas `no_disponible` y las trabajadoras que no están `libre` se muestran deshabilitadas.
- El formulario captura `inicio` como `datetime-local` en UTC; la web lo normaliza a ISO 8601 `Z` y `ReservationValidator`/`create()` recalculan el `fin` con la duración del servicio.
- `reservations/create.blade.php` incluye etiquetas, ayudas, resumen `role="alert"`, errores por campo, `aria-invalid`, `aria-describedby` y un `<output aria-live="polite">` para el fin calculado.
- `public/js/reservations.js` es mejora progresiva: previsualiza el fin, enfoca el resumen de errores y bloquea el botón con `Creando reserva…`; la validación y el cálculo definitivo son del servidor.
- El alta crea la reserva en estado `pendiente`; no confirma, atiende ni cancela. Los conflictos y recursos no disponibles se traducen a errores de formulario conservando los valores enviados.
- El listado incorpora el CTA `Nueva reserva` y muestra un flash `role="status"` con cliente, fecha y rango UTC después del alta.

## Verificación CP-FRONT-07

- `php -l` sobre controlador, repositorio y rutas; Pint; `node --check reservations.js`; `view:cache`; `php artisan test`: OK. 2/2 pruebas, 3 aserciones.
- `GET /reservas/nueva` autenticado: 200; anónimo: 302 a `/login`. Opciones al cierre: 1 cliente existente, 3 servicios activos, 1 cama no disponible y 2 trabajadoras no libres.
- `POST /reservas` válido con `2030-06-15T10:00`, cliente existente, `Masaje relajante`, cama y trabajadora libres: 302 a `/reservas?fecha=2030-06-15`; flash con `10:00 a 11:00 UTC`; la reserva aparece `Pendiente` con `10:00–11:00`.
- Envío vacío: resumen enfocado, 5 errores de campo y `aria-invalid` en cliente, servicio, cama, trabajadora e inicio. Fecha inválida: error ISO 8601. Conflicto de cama: error de reserva y valores conservados.
- El fin calculado por JS con `Masaje relajante` (60 min) e inicio `15/06/2030 10:00` es `15/06/2030 11:00 UTC`; el servidor calculó el mismo rango al guardar.
- CDP en 1280, 768 y 360 px: sin desbordamiento horizontal, IDs duplicados, controles sin nombre ni errores de consola/red; capturas revisadas.
- La reserva de prueba `c691a54f-986a-435e-ae94-f5b3d25cbab9` quedó cancelada porque el usuario `spa_app` no tiene privilegio `DELETE`; no se borró físicamente. Fixtures al cierre: 15 reservas, 11 canceladas y 3 pendientes; la trabajadora usada volvió a `libre`.
- CP-FRONT-07 aprobado por el humano el 2026-10-22.

## Cancelación de reservas (CP-FRONT-08)

- Ruta web autenticada `POST /reservas/{id}/cancelar` (`reservations.cancel`); sin sesión redirige a `/login` y sin CSRF responde 419.
- `ReservationPageController::cancel` valida UUID v4, reutiliza `ReservationRepository::cancel` bajo `DatabaseContext`/RLS y preserva `fecha`, `estado` y `page` al volver al listado.
- Solo `pendiente` pasa a `cancelada`; la operación es idempotente para una reserva ya cancelada. Un estado no cancelable devuelve flash `role="alert"` sin modificar la reserva; UUID inválido o inexistente responde 404.
- La cancelación ejecuta el trigger de BD que libera la trabajadora; no se borra la reserva ni se agregan acciones de confirmar o atender.
- El listado muestra `Cancelar reserva` solo para pendientes, con `details`/`summary` como confirmación nativa, formulario POST con CSRF y campos ocultos de filtros y página. Las demás filas muestran `Sin acciones`.
- El resultado vuelve con flash `role="status"`; los errores de estado usan flash `role="alert"`.
- `public/js/reservations.js` marca el botón como `Cancelando…`, lo deshabilita y muestra el spinner; la cancelación funciona sin JavaScript.
- `public/css/app.css` agrega el panel de confirmación, el estado de error y la adaptación a tarjetas en 940 px o menos.

## Verificación CP-FRONT-08

- `php -l` sobre controlador y rutas; Pint; `node --check reservations.js`; `view:cache`; `php artisan test`: OK. 2/2 pruebas, 3 aserciones.
- Reserva de prueba `2031-01-15 10:00` cancelada con `POST /reservas/{id}/cancelar`: 302 a `/reservas?fecha=2031-01-15&estado=pendiente`, flash con cliente y rango UTC, estado `cancelada` y trabajadora nuevamente `libre`.
- Cancelación repetida: 302 sin cambio de estado. Reserva `expirada`: 302 con flash `Solo se pueden cancelar reservas pendientes.` y estado sin cambios. UUID inválido e inexistente: 404. POST anónimo sin CSRF: 419.
- CDP en 1280, 768 y 360 px: confirmación abierta, sin desbordamiento horizontal, IDs duplicados, controles sin nombre ni errores de consola/red; capturas revisadas.
- La carga muestra `Cancelando…`, el botón deshabilitado, `aria-busy="true"` y el spinner. La cancelación desde la UI devuelve el flash y deja la fila `Cancelada` sin acción disponible.
- La reserva de prueba `c691a54f-986a-435e-ae94-f5b3d25cbab9` y las reservas de CP-FRONT-08 (`2031-01-15`, `2031-02-15`, `2031-03-15`) quedaron canceladas porque `spa_app` no tiene privilegio `DELETE`; no se borraron físicamente. Fixtures al cierre: 18 reservas, 14 canceladas, 3 pendientes y 1 expirada.
- CP-FRONT-08 aprobado por el humano el 2026-10-24.

## Responsive y accesibilidad final (CP-FRONT-09)

- Se elevó el breakpoint de tarjetas de tabla de 700 a 940 px; servicios y reservas usan tarjetas en tablet/móvil y conservan `table`, `row`, `rowheader`, `cell` y `columnheader` en el árbol de accesibilidad.
- Los filtros se apilan en 900 px o menos; se corrigió el desbordamiento de la tabla de reservas a 768 px y el de formularios con texto al 200%.
- `.service-table-wrap` es una región enfocable (`tabindex="0"`, `role="region"`, `aria-label`) con scroll horizontal cuando el texto crece; no se recorta contenido.
- Se añadió `tabindex="-1"` a `main#main-content` y `form#login-form`; el skip link enfoca el destino y muestra outline de 3 px.
- `.field-action` y `.back-link` tienen altura mínima de 28 px; los bordes de `.button-quiet`, `.pagination-step` y `.button-danger-quiet` suben a ≥3:1.
- Cancelación e inactivación tienen nombres accesibles contextuales y abren con Enter/Espacio; `aria-busy` se marca también en el botón.
- `prefers-reduced-motion` desactiva spinner y transiciones; el foco visible se mantiene en todos los controles.

## Verificación CP-FRONT-09

- `Pint`, `node --check`, `view:cache` y `php artisan test`: OK. 2/2 pruebas, 3 aserciones.
- CDP 32 vistas (8 pantallas × 360/768/1280/640): sin overflow horizontal, IDs duplicados, controles sin etiqueta/nombre, imágenes sin alt, saltos de encabezado ni fallos de contraste de texto; `skip.targetTabIndex=-1` en todas.
- 360/768/1280: servicios y reservas en tarjetas; tabla semántica preservada; capturas revisadas. La tabla de reservas ya no se recorta a 768 px.
- Teclado: skip link enfoca `main`/`login-form`; `details` de cancelación e inactivación abren con Enter/Espacio; tab order alcanza tabla y acciones sin trampa; foco visible con outline 3 px.
- Zoom 200% (640 CSS px, DSF 2): sin overflow, targets pequeños ni IDs duplicados; tabla 585/585.
- Texto 200% en 1280/768/360: sin overflow horizontal; en 1280 la tabla de reservas usa scroll horizontal enfocable, sin recorte.
- `prefers-reduced-motion`: spinner `none`, skip `0s`.
- CP-FRONT-09 aprobado por el humano el 2026-10-24. Workflow de frontend cerrado.

## Accesibilidad y respuesta

- Base WCAG 2.2 AA: HTML semántico, etiquetas, skip links, foco visible, teclado, contraste y mensajes anunciados.
- Objetivos táctiles de 44 px cuando el espacio lo permite; mínimo 24 px.
- Tablas como regiones enfocables con scroll horizontal cuando el texto crece; sin recorte de contenido.
- Diseño verificado en 360, 768 y 1280 px, con zoom 200% y texto 200%.
- `prefers-reduced-motion` desactiva animaciones no esenciales.
- Sin dependencia de hover para información o acciones.

## Fuera de alcance de CP-FRONT-01..09

- Confirmación o atención de reservas: fuera de alcance de la fase.
- Reactivación de reservas canceladas: no incluida en esta fase.
- CSRF/rate limiting de la API: CP-INT-04.

**HUMAN_STATUS: APPROVED**

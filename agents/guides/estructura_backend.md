# Estructura obligatoria del backend — Spa Cielo

## Alcance

Esta guía describe el backend real de **Spa Cielo** en `Spa/06_codigo/`.

- Stack: PHP 8.3 + **Laravel 13**, PostgreSQL 18, PDO a través de Laravel.
- API REST JSON en `/api/v1`.
- Frontend server-rendered con Blade.
- No aplica a la landing independiente (`Spa/07_landing`) ni al bloque heredado
  `auth_usuario` / `cat_categoria` / `cat_producto`.

## Arquitectura

No se usa PHP puro con MVC propio. Se usa Laravel como framework base:

```text
Route → Controller → Validator/JsonBody → Repository → PostgreSQL/RLS
                                      ↘ Resource → ApiResponse
```

La lógica de negocio no vive en rutas, vistas ni JavaScript. La persistencia
solo vive en repositorios.

## Estructura

```text
Spa/06_codigo/
├── app/
│   ├── Exceptions/
│   │   ├── ApiException.php
│   │   └── DuplicateServiceException.php
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/V1/
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── HealthController.php
│   │   │   │   ├── ReservationController.php
│   │   │   │   └── ServiceController.php
│   │   │   └── Web/
│   │   │       ├── LoginPageController.php
│   │   │       ├── DashboardPageController.php
│   │   │       ├── ServicePageController.php
│   │   │       └── ReservationPageController.php
│   │   ├── Middleware/AuthenticateSession.php
│   │   ├── Requests/Web/LoginRequest.php
│   │   └── Resources/
│   │       ├── AuthUserResource.php
│   │       ├── ReservationResource.php
│   │       └── ServiceResource.php
│   │
│   ├── Models/User.php
│   ├── Providers/AppServiceProvider.php
│   │
│   ├── Repositories/
│   │   ├── AppConfigRepository.php
│   │   ├── AuthRepository.php
│   │   ├── BranchRepository.php
│   │   ├── DashboardRepository.php
│   │   ├── ReservationRepository.php
│   │   └── ServiceRepository.php
│   │
│   ├── Services/AuthService.php
│   │
│   ├── Support/
│   │   ├── ApiResponse.php
│   │   ├── BranchContext.php
│   │   ├── DatabaseContext.php
│   │   ├── JsonBody.php
│   │   ├── RequestId.php
│   │   ├── RouteId.php
│   │   └── SessionAuth.php
│   │
│   └── Validation/
│       ├── LoginValidator.php
│       ├── ReservationQueryValidator.php
│       ├── ReservationStateValidator.php
│       ├── ReservationValidator.php
│       └── ServiceValidator.php
│
├── bootstrap/app.php
├── config/
│   ├── app.php
│   ├── database.php
│   ├── session.php
│   └── spa.php
├── public/
│   ├── index.php
│   ├── css/app.css
│   └── js/{login,services,reservations}.js
├── resources/views/
│   ├── layouts/app.blade.php
│   ├── auth/login.blade.php
│   ├── dashboard.blade.php
│   ├── services/{index,create,edit}.blade.php
│   └── reservations/{index,create}.blade.php
├── routes/
│   ├── web.php
│   └── api.php
├── scripts/
│   ├── backend_suite.php
│   ├── api_crud_suite.php
│   ├── auth_login_suite.php
│   ├── auth_session_suite.php
│   ├── reservations_read_suite.php
│   ├── reservations_write_suite.php
│   ├── reservations_cancel_suite.php
│   └── reservations_guards_suite.php
├── tools/seed_demo.php
└── tests/
```

## Responsabilidades

### `routes/web.php`

- Define rutas web: `/login`, `/logout`, `/dashboard`, `/servicios`, `/reservas`.
- Usa middleware web, sesión y CSRF de Laravel.
- No contiene SQL, validaciones de negocio ni lógica de reservas.

### `routes/api.php`

- Define `/api/v1/health`, `/api/v1/auth/*`, `/api/v1/servicios` y
  `/api/v1/reservas`.
- Aplica sesión a autenticación y reservas.
- `servicios` es público por decisión de alcance del piloto.
- No contiene SQL ni reglas de negocio.

### Controllers

- Reciben la petición, invocan validadores/repositorios y devuelven respuesta.
- Los controllers API usan `JsonBody`, `RequestId`, `RouteId`, `BranchContext`,
  `ApiResponse` y `Resource`.
- Los controllers web verifican sesión, preparan datos y resuelven la vista.
- No contienen SQL, PDO ni reglas de disponibilidad.

### Validators

- Definen listas cerradas de campos permitidos.
- Usan `Validator` de Laravel y mensajes en español.
- No consultan la base de datos.
- `precio` se normaliza a cadena decimal; nunca a `float`.

### Repositories

- Único lugar autorizado para SQL/Query Builder/PDO de negocio.
- Reciben `branchId`, `userId` y `requestId` ya resueltos.
- Ejecutan todo dentro de `DatabaseContext::run()`.
- Encapsulan filtros, paginación, orden, escrituras, locks y traducción de
  conflictos de PostgreSQL.
- No conocen HTTP ni construyen respuestas.

### Services

- `AuthService` concentra autenticación, verificación Argon2id y regeneración de
  sesión.
- Las reglas de reservas que requieren transacción, locks o `EXCLUDE` viven en
  `ReservationRepository`; no se crea un Service vacío solo por capa.
- Ningún Service ejecuta SQL directo.

### Support

- `DatabaseContext`: abre la transacción y fija `app.branch_id`, `app.user_id` y
  `app.correlation_id` con `set_config(..., true)`.
- `BranchContext`: obtiene la sucursal desde `APP_BRANCH_ID`.
- `SessionAuth`: lee y valida `auth_user_id` / `auth_branch_id` de la sesión.
- `ApiResponse`: envelope `success/data/meta` y errores.
- `RequestId`, `RouteId`, `JsonBody`: request id, UUID v4 de ruta y cuerpo JSON.

### Resources y excepciones

- `Resources` definen la forma JSON; no consultan base de datos.
- `ApiException` y `DuplicateServiceException` representan errores esperados.
- `bootstrap/app.php` traduce excepciones a HTTP sin exponer trazas, SQL ni
  credenciales.

### Modelos

- `Models/User.php` es el stub de Laravel; no es la fuente de identidad del Spa.
- No crear modelos Eloquent para servicios o reservas. El dominio usa
  repositorios.

## Reglas obligatorias

```text
Controller != SQL/PDO
Service != SQL/PDO
View != SQL/PDO
Repository = SQL/PDO
```

- Toda consulta de negocio pasa por `DatabaseContext::run()`.
- Una petición equivale a una transacción; no se comparte contexto entre
  peticiones.
- `branch_id` no se acepta desde el cliente, salvo el login controlado que lo
  necesita para resolver el contacto único por sucursal.
- RLS permanece activo: no se usa `SET row_security = off` ni se filtra por
  sucursal solo en PHP.
- `spa_app` no ejecuta `DELETE FROM`; la baja de servicios es lógica
  (`estado='inactivo'`).
- Dinero con `numeric(12,2)` y cadena decimal de dos posiciones.
- La API mantiene el envelope `success/data/meta`; los errores usan
  `success=false` y `error.code`.
- La sesión es de servidor; no se emite JWT en esta fase.
- Las rutas web usan CSRF. La API de servicios no exige autenticación por
  decisión; autenticación y reservas sí usan sesión.
- No se guardan credenciales, hashes ni secretos en código, logs o respuestas.
- No se agregan módulos, pantallas ni tablas fuera del alcance ratificado.

## Validación arquitectónica

Antes de aprobar un checkpoint backend, comprobar:

- `public/index.php` sigue siendo el front controller de Laravel;
- no hay SQL de negocio en controllers, vistas ni rutas;
- los repositorios concentran la persistencia;
- los validadores no consultan la base de datos;
- `DatabaseContext` fija sucursal, usuario y correlación;
- `branch_id` no viene del body salvo login;
- no hay borrado físico;
- las rutas web y API están separadas;
- las pruebas y suites pasan;
- los fixtures se restauran.

Si se incumple:

```text
TECHNICAL_STATUS: FAILED
CAN_CONTINUE: false
```

# Estructura obligatoria del frontend — Spa Cielo

## Alcance

Esta guía describe el frontend real de **Spa Cielo** en `Spa/06_codigo/`.

- Blade + HTML5 + CSS3 + JavaScript ES6+.
- Sin React, Vue, Angular ni framework de UI.
- Sin proceso de compilación obligatorio; CSS y JS se sirven desde `public/`.
- Las pantallas son server-rendered por controladores web; no consumen la API
  desde JavaScript en esta fase.
- No aplica a la landing independiente del workflow 07 (`Spa/07_landing`).

## Estructura

```text
Spa/06_codigo/
├── app/Http/Controllers/Web/
│   ├── LoginPageController.php
│   ├── DashboardPageController.php
│   ├── ServicePageController.php
│   └── ReservationPageController.php
│
├── resources/views/
│   ├── layouts/
│   │   └── app.blade.php
│   ├── auth/
│   │   └── login.blade.php
│   ├── dashboard.blade.php
│   ├── services/
│   │   ├── index.blade.php
│   │   ├── create.blade.php
│   │   └── edit.blade.php
│   └── reservations/
│       ├── index.blade.php
│       └── create.blade.php
│
├── routes/web.php
│
└── public/
    ├── css/
    │   └── app.css
    └── js/
        ├── login.js
        ├── services.js
        └── reservations.js
```

No se usan `app/Views`, `app/Core` ni `public/assets/css` del bloque heredado.
`resources/views/welcome.blade.php` es un stub de Laravel y no forma parte del
flujo. Tampoco se crean módulos `js/api/` en esta fase: las pantallas reciben
datos ya renderizados por el servidor.

## Responsabilidades

### Web Controllers

- Verifican la sesión y el contexto de sucursal.
- Obtienen datos mediante repositorios.
- Preparan variables mínimas para la vista.
- Devuelven `View` o `RedirectResponse`.
- No contienen SQL, PDO ni reglas de negocio complejas.

### Views Blade

- Solo presentación.
- Pueden usar estructuras simples de Blade, condicionales y bucles.
- Deben escapar datos con `{{ }}`.
- No usan `{!! !!}` para datos de usuario.
- No contienen SQL, PDO, autenticación propia ni lógica CRUD.

### Layout

`layouts/app.blade.php` concentra:

- estructura HTML;
- navegación;
- usuario y cierre de sesión;
- CSRF del logout;
- carga de CSS/JS;
- mensajes flash y regiones accesibles.

### `public/css/app.css`

- Único lugar para estilos de la aplicación.
- No se concentra CSS crítico dentro de las vistas.
- No se usa framework CSS ni build obligatorio.

### `public/js/`

- Mejora progresiva, no requisito para completar la operación.
- `login.js`: mostrar/ocultar contraseña, foco de errores y estado de carga.
- `services.js`: foco de estado y bloqueo de botones al guardar/inactivar.
- `reservations.js`: cálculo visual del fin, foco de errores y estado de carga.
- No contienen credenciales, SQL, autorización ni reglas definitivas de negocio.
- La validación y el cálculo definitivo son del servidor.

## Pantallas permitidas

Solo:

### `/login`

- Contacto, contraseña, sucursal y CSRF.
- Sin registro, recuperación de contraseña ni administración de usuarios.
- Error visible y accesible; no revela si el contacto existe.

### `/dashboard`

- Usuario actual, sede y conteos básicos.
- Enlaces a servicios y reservas.
- No es un módulo de reportes ni analítica.

### `/servicios`

- Listado con filtros `q` y `estado`.
- Alta, edición e inactivación lógica.
- Sin borrado físico ni reactivación en esta fase.

### `/reservas`

- Listado por fecha y estado.
- Alta de reserva existente con cliente, servicio, cama y trabajadora.
- Cancelación solo de reservas `pendiente`.
- Sin confirmar ni atender: requieren pago verificado (RN-002), fuera de alcance.

## Lógica de interfaz

- El servidor decide autorización, filtros, paginación y estado.
- JavaScript no decide si un usuario puede cancelar, editar o inactivar.
- Los formularios web usan CSRF y validación de servidor.
- Los errores se muestran con `role="alert"`; los éxitos con `role="status"`.
- Los campos con error usan `aria-invalid` y `aria-describedby`.
- El foco se mueve al resumen de errores cuando corresponde.
- Los botones de envío se deshabilitan y anuncian carga; la operación funciona
  sin JavaScript.

## Estados obligatorios

Toda pantalla que dependa de datos debe contemplar:

```text
loading
empty
success
error
unauthorized
```

En server-rendered, `loading` se expresa con el estado del botón y `aria-busy`;
`empty` con un estado vacío específico; `error` con resumen y errores de campo;
`unauthorized` con redirección a `/login`.

## Seguridad

- Escapar salida con `{{ }}`; no usar HTML crudo para datos de usuario.
- No almacenar contraseñas, secretos ni tokens sensibles en JavaScript.
- No duplicar reglas de negocio ni autorización en el cliente.
- La sesión es de servidor; el frontend no emite ni valida JWT.
- Las rutas web de escritura usan CSRF.

## Accesibilidad y respuesta

- HTML semántico, etiquetas asociadas y skip link.
- Foco visible y navegación por teclado.
- Objetivos táctiles de al menos 24 px; 44 px cuando el espacio lo permite.
- Tablas como regiones enfocables con scroll horizontal cuando el texto crece.
- En 940 px o menos, tablas de servicios y reservas se presentan como tarjetas.
- Verificación en 360, 768 y 1280 px, zoom 200% y texto 200%.
- `prefers-reduced-motion` desactiva animaciones no esenciales.
- No depender solo del color para comunicar estado.

## Validación arquitectónica

Antes de aprobar un checkpoint frontend, comprobar:

- la vista Blade correspondiente existe;
- el controlador web existe y no contiene SQL;
- CSS está en `public/css/` y no incrustado en la vista;
- JavaScript está en `public/js/` y no incrustado en la vista;
- no hay SQL/PDO en vistas;
- no hay credenciales en JavaScript;
- los formularios usan CSRF;
- la validación real está en el servidor;
- la pantalla funciona sin JavaScript;
- las pruebas y `view:cache` pasan.

Si se incumple:

```text
TECHNICAL_STATUS: FAILED
CAN_CONTINUE: false
```

# Decisiones de API

**Estado:** APROBADO

- PHP 8.x puro.
- Sin framework.
- MySQL 8.x / InnoDB.
- PDO.
- REST + JSON.
- `/api/v1/`.

## Recursos permitidos
- `/api/v1/categorias`
- `/api/v1/productos`
- `/api/v1/auth/login`
- `/api/v1/auth/logout`
- `/api/v1/auth/me`

## API piloto
Tabla: `cat_categoria`.

Endpoints:
- GET `/api/v1/categorias`
- GET `/api/v1/categorias/{id}`
- POST `/api/v1/categorias`
- PUT `/api/v1/categorias/{id}`
- DELETE `/api/v1/categorias/{id}`

DELETE significa inactivar (`estado='inactivo'`), nunca eliminación física.

## Segundo recurso
Tabla: `cat_producto`.

Validar:
- SKU único;
- nombre;
- categoría existente;
- unidad;
- estado.

## Seguridad
Prepared statements, validación server-side, sin secretos en Git, sin stack traces en producción.

## Validación humana
`continúa` aprueba solo el checkpoint anterior y habilita un único checkpoint nuevo.

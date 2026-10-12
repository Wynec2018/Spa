# Decisiones de Alcance

**Estado:** APROBADO

## Alcance
Implementar únicamente:
1. autenticación mínima con `auth_usuario`;
2. API piloto con `cat_categoria`;
3. segundo módulo con `cat_producto`.

No implementar inventario, ventas, pagos, compras, promociones, carrito, devoluciones, transferencias, reportes, configuración completa ni RBAC completo.

## Tabla piloto fija
`cat_categoria`

## Segundo módulo fijo
`cat_producto`

## Login ficticio
Usar `auth_usuario` solo para:
- login;
- logout;
- sesión actual (`me`).

El usuario demo se crea con `proyecto/06_codigo/tools/create_demo_user.php`, leyendo `DEMO_*` desde `.env` y generando `PASSWORD_ARGON2ID`.

## Eliminación
Categorías y productos se inactivan:
`estado = 'inactivo'`

No ejecutar `DELETE FROM`.

## Frontend mínimo
Solo:
- `/login`
- `/dashboard`
- `/categorias`
- `/productos`

## Regla
Un checkpoint por interacción. Cada checkpoint termina en `HUMAN_STATUS: PENDING` y el agente se detiene.

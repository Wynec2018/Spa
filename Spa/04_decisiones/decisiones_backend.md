# Decisiones de Backend

**Estado:** APROBADO

## Stack
PHP 8.x puro, MVC, PDO, MySQL 8.x, `.env`, sin framework.

## Flujo
Request → Router → Controller → Validator → Service → Repository → PDO → MySQL

## Alcance
Solo:
- categorías;
- login/logout/me;
- productos.

## Login
Usar sesión PHP.
1. buscar email;
2. validar usuario activo;
3. `password_verify()`;
4. regenerar ID de sesión;
5. guardar datos mínimos.

No usar JWT ni RBAC completo en esta práctica.

## Usuario demo
Crear con `tools/create_demo_user.php`.
Contraseña desde `.env`.
Hash con `PASSWORD_ARGON2ID`.

## Restricciones
No introducir Laravel, Symfony, Slim, CodeIgniter, ORM, microservicios ni otros módulos.

## Regla
Un checkpoint por interacción.

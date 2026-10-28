# 07_landing - Landing publica Spa Cielo

Landing publica de conversion para trafico de anuncios. No es el sistema
administrativo.

## Alcance

- Mercado: Bolivia.
- Moneda visible: `Bs`.
- Servicios activos y cantidad de camas disponibles/no disponibles.
- Datos de solo lectura desde PostgreSQL.
- Sin login, registro, dashboard, CRUD, roles, carrito, checkout ni pagos.
- Sin tablas `landing_*` y sin reutilizar `cat_*`.

## Stack

PHP 8.x puro + PostgreSQL 18 + PDO + HTML5 + CSS3 + JavaScript ES6+.

Sin Laravel, MySQL, React/Vue/Angular, jQuery, Bootstrap/Tailwind ni Node.

## Estructura

- `public/index.php`: front controller; sin SQL/PDO.
- `app/Core/`: infraestructura de la landing.
- `app/Controllers/`: coordinacion de la pagina.
- `app/Repositories/`: unico lugar con SQL/PDO; solo `SELECT`.
- `app/Services/`: armado de datos; sin SQL.
- `app/Views/`: presentacion; sin SQL/PDO.
- `config/`: configuracion publica y conexion; sin credenciales versionadas.
- `docs/`: brief, arquitectura, fuentes de imagenes y validacion.
- `tools/`: validadores de estructura y checkpoint.
- `tests/`: fixtures y evidencia.

## Validacion

```text
php Spa/07_landing/tools/validate_structure.php
php Spa/07_landing/tools/validate_checkpoint.php CP-LAND-16
```

## Estado

CP-LAND-17 cierra la validación final. Los 17 checkpoints están completos y la
validación de CP-LAND-16 pasó con 14/14 grupos. La evidencia vive en
`tests/results/CP-LAND-16.txt`, el cierre en `docs/validacion_final.md` y el
estado en `agents/state/landing-workflow.json`. La aprobación humana final fue
otorgada el 2026-10-27.

# 07_landing — Landing comercial pública

Landing pública para tráfico de anuncios. Mercado: Bolivia. Precios demostrativos en `Bs`.

**No** es el sistema administrativo: sin login, registro, dashboard, CRUD, roles, carrito, checkout ni pagos.

## Stack aprobado (D-LAND-08)

PHP 8.x puro · MySQL 8.x/InnoDB · PDO · HTML5 · CSS3 · JavaScript ES6+ · jQuery · Bootstrap/Tailwind.
Sin Laravel/Symfony, sin React/Vue/Angular, sin Node/Vite/Webpack, sin librerías de motion.

## Catálogo

7 categorías × 10 productos = 70 productos demostrativos (`tests/fixtures/catalogo_bolivia.json`).

## Estructura

- `public/index.php` — Front Controller/composición (sin SQL/PDO, ≤140 líneas).
- `app/Repositories/` — único lugar con SQL de runtime (solo `SELECT` parametrizado).
- `app/Services/` — orquestación, sin SQL/PDO.
- `app/Views/` — presentación, sin SQL/PDO.
- `config/` — `app.php`, `database.php`, `landing.php` (CTA configurable).
- `database/` — `01_landing_schema.sql`, `02_landing_seed.sql` (solo aquí hay INSERT).
- `docs/` — brief, fuentes de imágenes, decisiones visuales, validación final.
- `tools/` — validadores de estructura y checkpoints.
- `tests/fixtures/`, `tests/results/` — catálogo demo y evidencia.

## Imágenes

Solo fuentes gratuitas verificables (Pexels, Unsplash, Pixabay, Wikimedia Commons).
Sin IA, sin Google Images/Pinterest. Evidencia obligatoria en `docs/fuentes_imagenes.md`.

## Validación

```
php proyecto/07_landing/tools/validate_structure.php
php proyecto/07_landing/tools/validate_checkpoint.php CP-LAND-01
```

## Workflow

`.agents/workflows/07_landing_workflow.md` — UN checkpoint por interacción,
siempre termina en `HUMAN_STATUS: PENDING`. Estado en `.agents/state/landing-workflow.json`.

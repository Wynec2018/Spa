# Estructura obligatoria — Landing Spa Cielo (07_landing)

## Alcance

- Pertenece al workflow 07, separado de `Spa/06_codigo`.
- Landing pública de conversión; no es el sistema administrativo.
- Sin login, registro, dashboard, CRUD, roles, carrito, checkout ni pagos.
- No aplica al backend/frontend de la fase 3.
- No crear `Spa/07_landing` hasta ejecutar CP-LAND-01.

## Estructura

```text
Spa/07_landing/
├── app/
│   ├── Core/
│   │   ├── App.php
│   │   ├── Database.php
│   │   ├── Router.php
│   │   ├── Request.php
│   │   ├── Response.php
│   │   └── View.php
│   │
│   ├── Controllers/
│   │   └── LandingController.php
│   │
│   ├── Repositories/
│   │   └── LandingRepository.php
│   │
│   ├── Services/
│   │   └── LandingService.php
│   │
│   ├── Middleware/
│   │   └── SecurityHeadersMiddleware.php
│   │
│   └── Views/
│       ├── layouts/
│       │   ├── app.php
│       │   ├── header.php
│       │   └── footer.php
│       ├── pages/
│       │   └── landing.php
│       ├── sections/
│       │   ├── hero.php
│       │   ├── servicios.php
│       │   ├── camas.php
│       │   ├── beneficios.php
│       │   ├── prueba_social.php
│       │   └── llamada_accion.php
│       └── components/
│           ├── servicio_card.php
│           └── cama_resumen.php
│
├── config/
│   ├── app.php
│   ├── database.php
│   └── landing.php
│
├── routes/
│   └── web.php
│
├── public/
│   ├── index.php
│   ├── .htaccess
│   └── assets/
│       ├── css/
│       │   ├── variables.css
│       │   ├── reset.css
│       │   ├── base.css
│       │   ├── layout.css
│       │   ├── components.css
│       │   ├── landing.css
│       │   └── responsive.css
│       ├── js/
│       │   └── landing.js
│       ├── img/
│       │   ├── hero/
│       │   ├── servicios/
│       │   ├── instalaciones/
│       │   └── iconos/
│       └── favicon.svg
│
├── storage/
│   ├── logs/
│   └── cache/
│
├── docs/
│   ├── brief_landing.md
│   ├── arquitectura.md
│   ├── decisiones_visuales.md
│   ├── fuentes_imagenes.md
│   └── validacion_final.md
│
├── tests/
│   ├── Unit/
│   ├── Integration/
│   ├── Functional/
│   ├── fixtures/
│   └── results/
│
├── tools/
│   ├── validate_structure.php
│   └── validate_checkpoint.php
│
├── .env.example
├── .gitignore
├── composer.json
└── README.md
```

No hay `database/migrations/` ni `seeds/` de landing: no se crean tablas. Los
datos se leen de las tablas existentes de Spa Cielo.

## Responsabilidades

| Capa | Archivo | Regla |
|---|---|---|
| Front controller | `public/index.php` | composición y arranque; sin SQL ni negocio |
| Router | `app/Core/Router.php` | rutas; sin SQL |
| Controller | `app/Controllers/LandingController.php` | coordina servicio y vista; sin SQL |
| Repository | `app/Repositories/LandingRepository.php` | único lugar con SQL/PDO; solo `SELECT` |
| Service | `app/Services/LandingService.php` | arma datos de servicios/camas; sin SQL |
| Views | `app/Views/**` | presentación; sin SQL/PDO |
| CSS | `public/assets/css/` | estilos; sin reglas de negocio |
| JS | `public/assets/js/landing.js` | mejora progresiva; sin secretos ni SQL |
| Config | `config/landing.php` | CTA, textos e imágenes públicas; sin credenciales |

## Lógica de datos

- `LandingRepository` consulta `catalog.service` con `estado='activo'`.
- Consulta `catalog.resource` con `tipo='cama'` para conteos agregados por
  `estado`.
- Los conteos de camas se rotulan como referenciales; no se promete tiempo real
  (T-05).
- No expone clientes, reservas, usuarios, pagos ni datos personales.
- Fija `app.branch_id` con `SET LOCAL` dentro de una transacción de lectura.
- Usa un rol de base de datos de solo lectura.

## Regla anti-monolito

FALLA técnica si:

- `public/index.php` contiene SQL/PDO;
- una View contiene SQL/PDO;
- el Repository hace `INSERT`, `UPDATE` o `DELETE`;
- se crean tablas `landing_*`;
- se reutiliza `cat_categoria` o `cat_producto`;
- se codifican 70 productos o un catálogo 7×10;
- se expone un listado de reservas, clientes o usuarios;
- CSS/JS importante está incrustado en `index.php`;
- se agrega login, dashboard, CRUD, carrito, checkout o pago;
- se agrega jQuery, Bootstrap, Tailwind, React, Vue o Angular.

Ante falla:

```text
TECHNICAL_STATUS: FAILED
CAN_CONTINUE: false
```

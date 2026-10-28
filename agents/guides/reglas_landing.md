# Reglas obligatorias — Landing Spa Cielo

## Alcance

Landing pública de conversión para tráfico de anuncios de **Spa Cielo**.

- Pertenece al workflow 07 (`Spa/07_landing`), separado de `Spa/06_codigo`.
- No es el sistema administrativo.
- No incluye login, registro, dashboard, CRUD, roles, permisos, carrito,
  checkout ni pagos reales.
- No forma parte de la fase 3.

## Tecnología permitida

- PHP 8.x puro
- PostgreSQL 18
- PDO
- HTML5
- CSS3
- JavaScript ES6+

## Prohibido sin decisión explícita

- Laravel/Symfony
- React/Vue/Angular
- jQuery
- Bootstrap/Tailwind
- Node/Vite/Webpack
- GSAP u otra librería de motion
- MySQL para la landing de Spa

## Fuente de datos

La landing es de solo lectura:

- `catalog.service`: servicios con `estado='activo'`.
- `catalog.resource`: camas (`tipo='cama'`) y sus estados.
- `core.branch`: nombre y datos públicos de la sede.
- `core.app_config`: moneda y configuración pública permitida.

Reglas:

- Solo `SELECT` parametrizado mediante `LandingRepository`.
- Sin `INSERT`, `UPDATE` ni `DELETE`.
- No crear tablas `landing_*`, `landing_categoria` ni `landing_producto`.
- No reutilizar `cat_categoria`, `cat_producto` ni el bloque heredado de tienda.
- Toda consulta fija `app.branch_id` con `SET LOCAL` para respetar RLS.
- Nunca exponer clientes, reservas, usuarios, pagos ni datos personales.
- Los conteos de camas son agregados; no se listan reservas.

## Contenido

- Servicios activos: nombre, duración y precio de `catalog.service`, mostrado
  como referencial en `Bs`.
- Cantidad de camas disponibles y no disponibles.
- La disponibilidad es referencial: no se promete tiempo real ni reserva web.
- No hay catálogo 7×10 ni 70 productos.
- No se inventan descripciones, precios fuera de `catalog.service`, teléfonos ni
  testimonios reales.
- Los testimonios, si existen, se identifican como demostrativos.

## Conversión

- CTA principal above-the-fold.
- CTA secundario.
- CTA final.
- Valores configurables en `config/landing.php`.
- No inventar teléfono/WhatsApp real.
- No prometer disponibilidad inmediata ni pago en línea.

## Imágenes

- No IA.
- No Google Images/Pinterest.
- Origen/licencia verificable.
- Fallback si la imagen no carga.
- `loading="lazy"` fuera del hero.
- Si un servicio no tiene imagen verificada, se muestra sin imagen; no se
  inventa una.

## Accesibilidad

- Semántica HTML.
- Navegación por teclado.
- Foco visible.
- `alt` descriptivo.
- Objetivos táctiles.
- `prefers-reduced-motion`.
- No depender solo del color.

## Performance

- No cargar imágenes pesadas de golpe.
- Reservar espacio/aspect-ratio.
- JS mínimo.
- Imágenes optimizadas.
- Sin dependencias de frontend no aprobadas.

## Post-create obligatorio

No se aprueba al terminar de codificar. Debe pasar auditoría:

1. visual;
2. motion;
3. accesibilidad/UI;
4. responsive;
5. performance/hardening;
6. estructura automática;
7. aprobación humana.

Si se incumple:

```text
TECHNICAL_STATUS: FAILED
CAN_CONTINUE: false
```

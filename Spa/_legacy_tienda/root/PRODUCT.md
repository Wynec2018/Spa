# Product

## Register

brand

## Users

Consumidores en Bolivia que consultan anuncios de productos (7 categorías, 70 anuncios) en una página pública, sin cuenta ni login. Contexto: navegación casual, móvil primero, decisión de contacto/consulta (WhatsApp o CTA), no compra en línea.

## Product Purpose

Landing pública y didáctica para "Altiplano Marketplace": mostrar el catálogo de anuncios, generar confianza y conducir a un contacto/consulta. Éxito = el visitante entiende la oferta en el primer scroll y llega a un CTA de contacto. Datos y precios son ficticios y demostrativos; no hay checkout, registro ni CRUD.

## Brand Personality

Claro, cercano, comercial y profesional (brief_landing). Comercio editorial sobrio: fotografía de producto real como protagonista, un solo acento verde (`#047857`), tipografía Outfit con peso, layout asimétrico. Confianza sin gritar.

## Anti-references

- Slop de landing AI: gradiente morado/azul, hero centrado sobre imagen oscura, 3 tarjetas idénticas, emojis en markup/alt, Inter, eyebrow en mayúsculas repetido por sección, hero-metric.
- E-commerce genérico tipo checkout/marketplace real (Amazon/MercadoLibre): aquí no se compra.
- Look de dashboard/admin: es un surface de marca, no de producto.
- Saturación estética: lane editorial-typographic (serif display + mono labels) — fuera de registro aquí.

## Design Principles

1. La foto vende: la imagen real del producto manda; el texto la acompaña.
2. Un acento, una voz: verde `#047857` único para CTA/énfasis; jerarquía por peso y espacio, no por color extra.
3. Sin cuenta, sin fricción: cero indicios de login/registro en copy o UI.
4. Movimiento con propósito: solo feedback de estado, con reduced-motion.
5. Honestidad demostrativa: testimonios/datos marcados como ficticios; nada que parezca transacción real.

## Accessibility & Inclusion

WCAG 2.2 AA: contraste ≥4.5:1 (muted `#52525b` sobre `#f9fafb`), targets ≥44px, semántica + skip-link + `:focus-visible`, `prefers-reduced-motion` (global + override), lazy-loading con alt descriptivo, zoom 200% sin rotura (medido en 360/768/1280, overflow=0).

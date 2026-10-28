# Política de imágenes — Landing Spa Cielo

## Alcance

Aplica a `Spa/07_landing`. No aplica al sistema administrativo `Spa/06_codigo`.

## Fuentes preferidas

- Pexels
- Unsplash
- Pixabay
- Wikimedia Commons

## Reglas

1. No Google Images/Pinterest.
2. No usar material con marca visible que sugiera afiliación.
3. Preferir imágenes genéricas de servicios, instalaciones y ambiente de spa;
   no productos de tienda ni alimentos.
4. Registrar URL directa, página de origen, fuente, autor, licencia/condición y
   fecha de verificación.
5. Si la procedencia/licencia no es verificable, NO usar.
6. No generar imágenes con IA.
7. Se permite `imagen_url` externa configurada en `config/landing.php`.
8. Debe existir fallback si la imagen no carga.
9. `docs/fuentes_imagenes.md` es evidencia obligatoria.
10. No usar imágenes de personas identificables sin licencia que permita uso
    comercial.
11. No usar imágenes de clientes reales ni de reservas reales.

## Servicios sin imagen

- Si un servicio no tiene imagen verificada, se muestra como tarjeta de texto
  con nombre, duración y precio.
- No se inventa una imagen ni se usa una genérica que induzca a error.
- CP-LAND-04 falla si una imagen usada no tiene fuente/licencia registrada.

## Performance

- `loading="lazy"` fuera del hero.
- Reservar dimensiones/aspect-ratio.
- Evitar resoluciones mayores a las necesarias.
- Optimizar formato y peso antes de publicar.

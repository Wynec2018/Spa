# Continuar — Landing Spa Cielo

## Objetivo
Construir `Spa/07_landing/` como landing pública para tráfico proveniente de anuncios.

NO es el sistema administrativo. NO puede incorporar login, registro, dashboard, panel,
CRUD administrativo, roles, permisos, carrito, checkout ni pagos reales.

## Alcance obligatorio
- Mercado objetivo: Bolivia.
- Moneda visible: `Bs`.
- Servicios Spa activos.
- Cantidad de camas disponibles y no disponibles.
- Datos de solo lectura desde PostgreSQL; sin `INSERT`, `UPDATE` ni `DELETE`.
- No crear tablas `landing_*` ni reutilizar `cat_*`.
- Imágenes: gratuitas, externas y con fuente/licencia verificable.
- CTA spa configurable.
- No inventar un número real de WhatsApp.
- No generar imágenes mediante IA.

## Lee primero, en este orden
1. `AGENTS.md` si existe.
2. `Spa/04_decisiones/decisiones_alcance_fase3.md`.
3. `Spa/04_decisiones/decisiones_landing.md`.
4. `agents/guides/estructura_landing.md`.
5. `agents/guides/reglas_landing.md`.
6. `agents/guides/politica_imagenes_landing.md`.
7. Skills aplicables en `agents/skills/`.
8. `agents/state/landing-workflow.json` si existe; se crea en CP-LAND-01.
9. `agents/workflows/07_landing_workflow.md`.

## Regla de ejecución
Ejecuta EXACTAMENTE UN checkpoint por interacción.

Al finalizar:
1. verifica físicamente cada archivo requerido;
2. ejecuta validadores y pruebas del checkpoint;
3. guarda evidencia en `Spa/07_landing/tests/results/`;
4. actualiza `agents/state/landing-workflow.json`;
5. establece `HUMAN_STATUS: PENDING`;
6. detente.

## Regla de aprobación
Solo se avanza con:
- `TECHNICAL_STATUS: PASSED`
- `HUMAN_STATUS: APPROVED`

Si falla una regla obligatoria:
- `TECHNICAL_STATUS: FAILED`
- `CAN_CONTINUE: false`

## Precedencia
`REQUISITOS > DECISIONES > WORKFLOW > STATE > GUIDES > SKILLS > IMPLEMENTACION`

Un skill jamás puede cambiar el stack aprobado ni introducir frameworks/librerías no autorizadas.
La creación visual y las auditorías POST-CREATE son fases diferentes.

# Continuar — Frontend mínimo

Alcance único:

- `/login`
- `/dashboard`
- `/categorias`
- `/productos`

No crear otras pantallas ni módulos.

Lee primero:

- `AGENTS.md`
- `SKILLS_SOURCES.md`
- `agents/state/frontend-workflow.json`
- `agents/workflows/05_frontend_workflow.md`
- `agents/guides/estructura_frontend.md`
- `Spa/_legacy_tienda/04_decisiones/decisiones_alcance.md`
- `Spa/_legacy_tienda/04_decisiones/decisiones_frontend.md`
- `Spa/_legacy_tienda/04_decisiones/decisiones_api.md`

Respeta obligatoriamente la estructura definida en:

`agents/guides/estructura_frontend.md`

No concentres HTML, CSS, JavaScript y llamadas API en un único archivo.

Ejecuta UN checkpoint.

Después:

1. verifica los archivos creados o modificados;
2. prueba la pantalla o función del checkpoint;
3. comprueba que no exista SQL ni acceso directo a base de datos desde Views;
4. actualiza `agents/state/frontend-workflow.json`;
5. marca `HUMAN_STATUS: PENDING`;
6. detente.

No avances automáticamente al siguiente checkpoint.

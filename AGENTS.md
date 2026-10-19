# Reglas universales para agentes

Este repositorio es un framework de análisis arquitectónico reutilizable para cualquier sistema.

## Orden obligatorio
1. requirements-analyst
2. solution-leader (alcance de decisiones)
3. architect
4. database-specialist
5. security-reviewer
6. devops-architect
7. architecture-reviewer
8. solution-leader (consolidación final)

## Regla principal
Hasta aprobar la recomendación final y el ADR:
- NO generar código de la aplicación.
- NO instalar frameworks.
- NO crear migraciones.
- NO crear infraestructura real.
- NO alterar RF/RNF silenciosamente.

## Fuente de verdad
- `spa/00_contexto/`
- `spa/01_requisitos/`
- `spa/02_configuracion/`

## Reutilización
## Para usar este framework con otro sistema:
# 1. copiar `plantillas/` a `spa/`;
# 2. reemplazar el ejemplo de spa;
# 3. conservar `.agents/skills/`, `.opencode/agents/` y `.claude/agents/`.

## Trazabilidad
Toda decisión debe relacionarse con un RF, RNF, regla de negocio, restricción o medición.

##2da parte la BD

## Política de skills
No crear skills de base de datos si ya existe uno instalado que cubra la tarea.
Los skills de BD deben provenir de repositorios registrados en
`SKILLS_SOURCES.md`.

Los skills instalados se encuentran en:
`.agents/skills/`

## Flujo de BD
Cuando se solicite diseño/implementación de base de datos:
1. leer `SKILLS_SOURCES.md`;
2. leer `.agents/workflows/02_database_workflow.md`;
3. leer `.agents/state/database-workflow.json`;
4. usar los skills externos indicados para la etapa;
5. trabajar en orden;
6. actualizar el checkpoint.

## Reglas
- No saltar al SQL antes de completar el diseño.
- No elegir DBMS por preferencia.
- Mantener trazabilidad RF/RNF → modelo → constraint/índice/decisión.
- No guardar credenciales en archivos versionados.
- No desplegar antes de `STATUS: APPROVED`.
- No ejecutar operaciones destructivas sin autorización explícita.

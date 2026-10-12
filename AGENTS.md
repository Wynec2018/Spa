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

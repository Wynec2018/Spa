# Framework de análisis arquitectónico — Spa Cielo

Framework reutilizable para **analizar y diseñar la arquitectura de un sistema antes de
implementarlo**. No genera código, migraciones ni infraestructura: produce decisiones,
artefactos y un ADR trazables a RF/RNF.

El ejemplo vigente es **Spa Cielo** (sistema integral de gestión y atención virtual de
un spa: reservas, recursos finitos, pagos por QR y monitoreo en tiempo real).

## Flujo obligatorio

1. `requirements-analyst`
2. `solution-leader` (alcance de decisiones)
3. `architect`
4. `database-specialist`
5. `security-reviewer`
6. `devops-architect`
7. `architecture-reviewer`
8. `solution-leader` (consolidación final)

## Estructura

| Ruta | Contenido |
|---|---|
| `Spa/00_contexto/` | Descripción, alcance, reglas de negocio y restricciones |
| `Spa/01_requisitos/` | `RF.md`, `RNF.md`, criterios de aceptación |
| `Spa/02_configuracion/` | Perfil de carga y parámetros |
| `Spa/03_resultados/` | Artefactos del pipeline (ver abajo) |
| `Spa/04_decisiones/` | Registro de decisiones (D-01..D-12) y preguntas resueltas (P-01..P-09) |
| `agents/skills/` | Skills compartidas del framework |
| `OpenCode/`, `Claude/` | Definiciones de agentes por plataforma |
| `Spa/_legacy_tienda/` | Ejemplo anterior (Altiplano Marketplace), archivado |

## Artefactos del pipeline (`Spa/03_resultados/`)

| # | Artefacto | Agente |
|---|---|---|
| 01 | `01_requirements_analysis.md` | requirements-analyst |
| 02 | `02_decision_scope.md` | solution-leader |
| 03 | `03_architecture_options.md` | architect |
| 04 | `04_database_analysis.md` | database-specialist |
| 05 | `05_security_review.md` | security-reviewer |
| 06 | `06_infrastructure.md` | devops-architect |
| 07 | `07_architecture_review.md` | architecture-reviewer |
| 08 | `08_recomendacion_final.md` | solution-leader |
| 09 | `09_validacion_arquitectura.md` | architecture-validation |

## Regla principal

Hasta aprobar la recomendación final y el ADR:

- NO generar código de la aplicación.
- NO instalar frameworks.
- NO crear migraciones.
- NO crear infraestructura real.
- NO alterar RF/RNF silenciosamente.

## Fuente de verdad

- `Spa/00_contexto/`
- `Spa/01_requisitos/`
- `Spa/02_configuracion/`

## Reutilización con otro sistema

1. Copiar la estructura de `Spa/` como plantilla.
2. Reemplazar el contenido de `Spa/00_contexto/`, `Spa/01_requisitos/` y `Spa/02_configuracion/`.
3. Conservar `agents/skills/`, `OpenCode/` y `Claude/`.
4. Ejecutar el flujo obligatorio y ratificar el ADR antes de implementar.

##segunda parte
Continúa este spa con la fase de ingeniería de base de datos.

Mantén exactamente la estructura existente.

Lee:
- AGENTS.md
- SKILLS_SOURCES.md
- spa/00_contexto/
- spa/01_requisitos/
- spa/02_configuracion/
- spa/03_resultados/
- spa/04_decisiones/

Luego sigue:
.agents/workflows/02_database_workflow.md

Usa únicamente los skills existentes instalados en:
.agents/skills/

Continúa desde:
.agents/state/database-workflow.json

Trabaja paso por paso.
No saltes etapas.
No empieces directamente creando tablas o SQL.
No elijas PostgreSQL, MySQL o SQL Server por preferencia:
justifica la selección mediante RF, RNF, restricciones y arquitectura.

Después de cada etapa:
1. indica el skill utilizado;
2. genera el artefacto correspondiente en proyecto/05_base_datos/;
3. resume las decisiones;
4. actualiza el checkpoint;
5. continúa con el siguiente paso si no existe bloqueo.

No conectes ni despliegues hasta que la revisión DBA genere:
STATUS: APPROVED

Nunca escribas credenciales en archivos versionados.
No ejecutes operaciones destructivas sin autorización explícita.

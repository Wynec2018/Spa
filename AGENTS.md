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
# 3. conservar `agents/skills/`, `OpenCode/` y `Claude/`.

## Trazabilidad
Toda decisión debe relacionarse con un RF, RNF, regla de negocio, restricción o medición.

##2da parte la BD

## Política de skills
No crear skills de base de datos si ya existe uno instalado que cubra la tarea.
Los skills de BD deben provenir de repositorios registrados en
`SKILLS_SOURCES.md`.

Los skills instalados se encuentran en:
`agents/skills/`

## Flujo de BD
Cuando se solicite diseño/implementación de base de datos:
1. leer `SKILLS_SOURCES.md`;
2. leer `agents/workflows/02_database_workflow.md`;
3. leer `agents/state/database-workflow.json`;
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

# Parte 3
## Desarrollo de aplicación — API, backend y frontend

Después del workflow de base de datos usar, en orden:

1. `agents/workflows/03_api_pilot_workflow.md`
2. `agents/workflows/04_backend_workflow.md`
3. `agents/workflows/05_frontend_workflow.md`
4. `agents/workflows/06_integration_workflow.md`

States:
- `agents/state/api-pilot-workflow.json`
- `agents/state/backend-workflow.json`
- `agents/state/frontend-workflow.json`
- `agents/state/integration-workflow.json`

### Regla obligatoria de checkpoint

- ejecutar UN checkpoint por interacción;
- probarlo;
- actualizar el state;
- marcar `HUMAN_STATUS: PENDING`;
- detenerse;
- esperar otro mensaje.

### Código

Todo código nuevo se crea en:

`Spa/06_codigo/`

### Decisiones vigentes

- `Spa/04_decisiones/decisiones_spa.md` (D-01..D-12 y ADR-001)
- `Spa/04_decisiones/decisiones_alcance_fase3.md` (alcance ratificado 2026-10-20)
- `Spa/04_decisiones/decisiones_stack_fase3.md` (Laravel 13 ratificado 2026-10-20)
- `Spa/04_decisiones/decisiones_integracion.md` (CP-INT-01, CP-INT-02 y CP-INT-03 aprobados 2026-10-24; CP-INT-04 y CP-INT-05 aprobados 2026-10-25)
- `Spa/04_decisiones/decisiones_landing.md` (workflow 07 independiente; aprobado 2026-10-25)

Las decisiones de API, backend, frontend e integración se registran en los documentos de cada workflow.

### Stack vigente

- PHP 8.3 + Laravel 13 (D-09 actualizado 2026-10-20).
- PostgreSQL 16+ (desplegado: PostgreSQL 18.3, D-08).
- PDO.
- API REST JSON.
- Frontend Laravel Blade + HTML + CSS + JavaScript puro.
- Sin React/Vue/Angular en esta fase.
- Landing 07: PHP 8.x puro + PostgreSQL 18 read-only; sin Laravel ni MySQL.

### Lectura progresiva

Al recibir `continúa`, leer primero state + workflow actuales.
No releer todo el repositorio salvo que exista contradicción, cambio de decisión o el checkpoint lo requiera.

## Alcance de desarrollo ratificado (2026-10-20)

Rige `Spa/04_decisiones/decisiones_alcance_fase3.md`. La fase 3 se limita a:

- `auth.app_user`: login/logout/me (sin CRUD de usuarios).
- `catalog.service`: CRUD con inactivación lógica (`estado='inactivo'`).
- `booking.booking`: alta `pendiente`, listado y cancelación.

Dependencias de solo lectura: `core.branch`, `catalog.client`,
`catalog.resource`, `core.app_config`, `payment.payment`.

Fuera de alcance: RF-002 (clientes), RF-005 (administración de recursos),
RF-007/RF-012 (pagos), RF-008 (idempotencia), RF-009, RF-010, RF-011,
RF-014/RF-017 (reportes) y registro de usuarios.

Pantallas: `/login`, `/dashboard`, `/servicios`, `/reservas`.

Las reservas solo transicionan `pendiente → cancelada`: confirmar exige pago
verificado (RN-002) y el módulo de pagos no está en alcance.

El bloque heredado de la tienda (`auth_usuario`, `cat_categoria`, `cat_producto`,
`/categorias`, `/productos`) no aplica: esas tablas no existen en el esquema.

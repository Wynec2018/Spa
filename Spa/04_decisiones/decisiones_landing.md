# Decisiones — Landing pública Spa Cielo

Fecha: 2026-10-25
Estado: `HUMAN_STATUS: APPROVED`
Reemplaza: `Spa/_legacy_tienda/04_decisiones/decisiones_landing.md`
Fuente: `CONTINUAR_LANDING.md`, `Spa/04_decisiones/decisiones_alcance_fase3.md` §6.

---

## 1. Contexto

La landing anterior describía un catálogo de tienda (7 categorías × 10 productos,
MySQL, jQuery/Bootstrap). Ese alcance no corresponde a Spa Cielo y quedó
archivado en `Spa/_legacy_tienda/`.

La landing de Spa Cielo es un canal público de conversión para tráfico de
anuncios. No es el sistema administrativo y no forma parte de la fase 3.

## 2. Decisiones

| ID | Decisión | Estado |
|---|---|---|
| D-LAND-01 | `Spa/07_landing` está separado de `Spa/06_codigo`. | APROBADO |
| D-LAND-02 | Landing pública de conversión para campañas; CTA comercial configurable. | APROBADO |
| D-LAND-03 | Sin login, registro, dashboard, CRUD administrativo, roles, permisos, carrito, checkout ni pagos reales. | APROBADO |
| D-LAND-04 | Fuente de datos: `catalog.service` (servicios activos) y `catalog.resource` (camas), en solo lectura. No se crean tablas `landing_*`. | APROBADO |
| D-LAND-05 | Contenido: servicios del spa y cantidad de camas disponibles/no disponibles. No hay catálogo 7×10 ni productos. | APROBADO |
| D-LAND-06 | Runtime de solo lectura: únicamente `SELECT` parametrizado. Sin `INSERT`, `UPDATE` ni `DELETE`. | APROBADO |
| D-LAND-07 | Imágenes gratuitas, externas y con fuente/licencia verificable; sin IA. | APROBADO |
| D-LAND-08 | Stack: PHP 8.x puro + PostgreSQL 18 + PDO + HTML5 + CSS3 + JavaScript ES6+. | APROBADO |
| D-LAND-09 | Skills de diseño y auditoría post-create según workflow 07. | APROBADO |
| D-LAND-10 | Human-in-the-loop obligatorio; ningún checkpoint avanza sin aprobación humana. | APROBADO |
| D-LAND-11 | Mercado Bolivia; moneda `Bs`; no inventar teléfono/WhatsApp real. | APROBADO |
| D-LAND-12 | Privacidad: la landing solo expone servicios activos y conteos agregados de camas; nunca clientes, reservas, usuarios ni datos personales. | APROBADO |
| D-LAND-13 | La disponibilidad de camas es referencial; no se promete disponibilidad en tiempo real ni reserva web. | APROBADO |

## 3. Consecuencias

- No se crean migraciones ni tablas para la landing.
- La landing usa un rol de base de datos de solo lectura sobre las tablas
  autorizadas; las credenciales viven en `.env`, nunca en el repositorio.
- Toda consulta fija `app.branch_id` con `SET LOCAL` dentro de una transacción de
  lectura para respetar RLS.
- No se reutilizan `cat_categoria`, `cat_producto` ni el bloque heredado.
- La landing no modifica `Spa/06_codigo` ni el esquema de la fase 3.
- El workflow 07 es independiente y no se ejecuta hasta que el humano lo inicie.

## 4. Trazabilidad

| Decisión | Fuente | Efecto |
|---|---|---|
| D-LAND-01/03 | `CONTINUAR_LANDING.md` | Separa landing del sistema administrativo |
| D-LAND-04/05/06 | `CONTINUAR_LANDING.md`, `catalog.service`, `catalog.resource` | Landing con servicios y camas, sin tienda |
| D-LAND-07 | `CONTINUAR_LANDING.md` | Imágenes verificables, sin IA |
| D-LAND-08 | `CONTINUAR_LANDING.md`, stack Spa | PostgreSQL read-only, sin MySQL/jQuery |
| D-LAND-11 | `CONTINUAR_LANDING.md` | Bolivia, Bs, CTA sin teléfono inventado |
| D-LAND-12/13 | RNF-030/031, T-05 | Sin PII y sin promesa de disponibilidad en tiempo real |

## 5. Ratificación

- **Fecha:** 2026-10-25.
- **Decisión:** reescribir la landing para Spa Cielo con servicios y camas.
- **Efecto:** habilita la corrección de las guías y del workflow 07.
- No autoriza por sí solo crear `Spa/07_landing`; el workflow 07 inicia en
  CP-LAND-01 con aprobación humana.

**HUMAN_STATUS: APPROVED**

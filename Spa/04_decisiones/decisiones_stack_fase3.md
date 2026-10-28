# Decisión de stack — Fase 3

Sistema: **Spa Cielo**
Fecha: 2026-10-20
Estado: `HUMAN_STATUS: APPROVED` (ratificado 2026-10-20)
Relación: actualiza el componente Laravel de **D-09**; no cambia RF, RNF ni arquitectura.

---

## 1. Contexto

`D-09` fijaba PHP 8.3 + **Laravel 11**. Al ejecutar CP-API-02, Composer 2.10
bloqueó la instalación:

- todas las versiones `laravel/framework` `11.31.0`–`11.57.0` están afectadas por
  los avisos `PKSA-d5tc-s1qs-h781`, `PKSA-m5cs-t1y6-qpcs`, `PKSA-3r5d-mb8f-1qw9`,
  `PKSA-mdq4-51ck-6kdq`, `PKSA-8qx-n5y5-vvnd`, `PKSA-q46n-4fdk-zjr4`,
  `PKSA-qzrn-rnz3-85w1`;
- Laravel 11 ya no recibe soporte de seguridad.

Desactivar el bloqueo de avisos habría introducido una dependencia vulnerable de
forma consciente, en contradicción con RNF-006 y RNF-030.

## 2. Decisión

| Componente | Versión |
|---|---|
| PHP | 8.3.35 NTS |
| Framework | **Laravel 13.x** (instalado 13.38.x) |
| Base de datos | PostgreSQL 18.3 (D-08) |
| Extensiones PHP | `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `zip`, `intl` |
| API | REST JSON en `/api/v1` |
| Frontend | Blade + HTML + CSS + JavaScript puro |

## 3. Justificación

- Laravel 13 es la versión estable actual y requiere PHP `^8.3`, que ya está
  desplegado.
- Mantiene el ecosistema y el modelo MVC decididos en D-09; no cambia la
  arquitectura ni el alcance de la fase 3.
- Evita instalar una versión con avisos de seguridad abiertos (RNF-006, RNF-030).

## 4. Consecuencias

- El esqueleto se genera con Laravel 13; las rutas API se registran en
  `bootstrap/app.php`.
- `D-09` se lee como "PHP 8.3 + Laravel 13" desde esta fecha.
- Los documentos históricos de la fase de BD conservan "Laravel 11" porque
  reflejan la decisión vigente en ese momento; este documento es la fuente
  actual para la fase 3.
- No se modifican RF/RNF ni el esquema de base de datos.

## 5. Trazabilidad

| Requisito | Efecto |
|---|---|
| RNF-006 mantenibilidad | Framework con soporte y parches |
| RNF-030/031 seguridad | Se evitan advisories conocidos |
| RNF-060/061 Linux/contenedor | Laravel 13 es compatible |
| D-09 | Actualización de versión, no de arquitectura |

**HUMAN_STATUS: APPROVED**

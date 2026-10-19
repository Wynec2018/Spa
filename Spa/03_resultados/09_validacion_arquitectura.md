# Validación de Arquitectura — Spa Cielo

Fecha: 2026-10-18
Skill: `architecture-validation`
Agente: `architecture-reviewer`
Alcance: `Spa/03_resultados/`, `Spa/04_decisiones/`, `Spa/00_contexto/`, `Spa/01_requisitos/`, `Spa/02_configuracion/`
Estado: `HUMAN_STATUS: PENDING`

Resultado: **NO VALIDADA (BLOQUEADA)**. El pipeline se ejecutó para dos dominios
distintos (Spa Cielo y Tienda Omnicanal) y los artefactos quedaron mezclados.

---

## 1. Mapa de dominio por artefacto

| Artefacto | Fecha | Dominio real |
|---|---|---|
| `01_requirements_analysis.md` | 2026-10-09 | Spa Cielo |
| `02_decision_scope.md` | 2026-10-09 | Spa Cielo |
| `03_architecture_options.md` | 2026-10-09 | Spa Cielo |
| `04_database_analysis.md` | 2026-10-09 | **Mezcla** Spa + tienda |
| `05_security_analysis.md` | 2026-09-17 | Tienda (retail) |
| `05_security_review.md` | 2026-10-09 | Spa Cielo |
| `06_devops_analysis.md` | 2026-09-17 | Tienda (retail) |
| `06_infrastructure.md` | 2026-10-09 | Spa Cielo |
| `07_architecture_review.md` | 2026-10-09 | Spa Cielo |
| `08_recomendacion_final.md` | 2026-09-17 | **Tienda Omnicanal** (retail) |
| `04_decisiones/*` | — | Tienda / marketplace |

---

## 2. Hallazgos

Cada hallazgo incluye ID, requisito afectado, evidencia, impacto, severidad,
cambio necesario y criterio de cierre.

### Bloqueantes

| ID | Req. afectado | Evidencia | Impacto | Cambio necesario | Criterio de cierre |
|---|---|---|---|---|---|
| VAL-B-01 | ADR / consolidación | `08_recomendacion_final.md:1` = "Sistema Tienda Omnicanal de Alimentos"; fecha 2026-09-17; RF-020/030/100, 25.000 tx/h, POS | No existe consolidación ni ADR para Spa Cielo | Re-ejecutar `solution-leader` y generar `08` de Spa Cielo | ADR de Spa Cielo con matriz y trazabilidad propias |
| VAL-B-02 | RF / RNF (fuente de verdad) | `RF.md` (RF-009/010/011/013 inventario), `RNF.md` (RNF-005 "web y físico", RNF-010 "sucursales/cajas") vs `descripcion.md:8-14` y `reglas_negocio.md` (camas/trabajadoras/QR) | Los requisitos no describen el sistema pedido; el análisis los fuerza (`01:21`) | Reescribir RF/RNF para el spa (requiere autorización humana) | RF/RNF trazables a RN-001..005 y CA-001..004 |
| VAL-B-03 | Proceso | Duplicados `05_security_analysis` vs `05_security_review`; `06_devops_analysis` vs `06_infrastructure` (dominios distintos) | Ambigüedad sobre qué consumió la revisión final | Archivar los de tienda; dejar un único 05/06 de Spa | Un solo artefacto por fase |

### Altos

| ID | Req. afectado | Evidencia | Impacto | Cambio necesario | Criterio de cierre |
|---|---|---|---|---|---|
| VAL-A-01 | Modelo de datos | `04_database_analysis.md`: título Spa pero modelo retail (`product`, `stock_movement`, `branch_id`); spa=30/tienda=25 | Modelo inconsistente para el dominio | Unificar el modelo por dominio | Modelo solo con entidades del spa |
| VAL-A-02 | Implementación | `04_decisiones/decisiones_alcance.md:5-11` (auth/categoría/producto), `decisiones_api.md:5-7` (PHP/MySQL), `decisiones_landing.md` — ninguna menciona camas/trabajadoras/reservas/QR | `06_codigo` + `07_landing` implementan el sistema equivocado para Spa Cielo | Decidir si se descartan o quedan fuera de alcance | Alcance e implementación alineados |
| VAL-A-03 | RNF-041, RNF-043, RNF-005 | Bloqueantes de `07` (ARV-RNF-01/02/03, ARV-ASSUME-01/04) sin resolver; `perfil_carga.yaml:5-7` RPO/RTO = null | Arquitectura sin base de decisión (HA, capacidad, stack, legal) | Resolver antes de ADR | Valores y decisiones registrados en ADR |

### Medios

| ID | Req. afectado | Evidencia | Impacto | Cambio necesario | Criterio de cierre |
|---|---|---|---|---|---|
| VAL-M-01 | Trazabilidad | `07` mide cobertura contra el RF retail (ARV-RF-01 cita RF-005/009/010/011/013 de inventario) | La matriz de cobertura no aplica al spa real | Rehacer matriz contra RF/RNF de spa | Cobertura spa verificable |
| VAL-M-02 | Contexto | `PRODUCT.md` / `DESIGN.md` describen Altiplano Marketplace | Documentación ajena al sistema | Separar producto real del ejemplo | Contexto coherente |

---

## 3. Lo que sí está correcto

`01`, `02`, `03`, `05_security_review`, `06_infrastructure` y `07` están centrados en
Spa Cielo, son de buena calidad y `07` aísla correctamente los bloqueantes reales
(RPO/RTO, política de conflicto web/presencial, stack backend, PII). La corrección
es quirúrgica, no un rediseño completo.

---

## 4. Plan de corrección

1. Confirmar el dominio (Spa Cielo) y autorizar la reescritura de `RF.md`/`RNF.md`.
2. Separar/archivar los artefactos de tienda (`05_security_analysis`,
   `06_devops_analysis`, `08_recomendacion_final`, `04_decisiones/*`).
3. Re-ejecutar `solution-leader` → `08_recomendacion_final` de Spa Cielo,
   resolviendo P-01..P-09.
4. Rehacer la matriz de trazabilidad contra los RF/RNF de spa.
5. Decidir el destino de `06_codigo`/`07_landing` (tienda/marketplace).

---

## 5. Requisitos que requieren validación humana

1. Reescritura de `RF.md` / `RNF.md` (VAL-B-02).
2. RPO/RTO numéricos (RNF-041/043).
3. Definición operativa de "transacción de negocio" (RNF-001/003).
4. Política de conflicto reserva web vs recepción física (RNF-005/020).
5. Proveedor de pago QR (RF-012, restricciones).
6. Multi-sucursal día 1 (RNF-010).

---

## 6. Actualización (2026-10-18)

Resueltos tras esta validación:

- **VAL-B-01**: generado el nuevo `08_recomendacion_final.md` de Spa Cielo.
- **VAL-B-02**: `RF.md`/`RNF.md` reescritos al dominio del spa.
- **VAL-B-03**: artefactos de tienda archivados en `Spa/_legacy_tienda/`.
- **VAL-A-01**: `04_database_analysis.md` depurado (sin entidades retail: `product`, `stock_movement`).
- **VAL-A-02**: decisiones de tienda archivadas; `06_codigo`/`07_landing` pendientes de decisión de alcance.
- **D-09**: resuelto — PHP 8.3 + Laravel 11.

Pendiente: ratificación humana de P-01..P-09 y destino de `06_codigo`/`07_landing`.

**Estado: `HUMAN_STATUS: PENDING`.**

# Desarrollo Incremental y Validación Humana

**Estado:** APROBADO

Un mensaje `continúa` permite:
1. aprobar el checkpoint anterior si está PASSED;
2. ejecutar UN checkpoint nuevo;
3. probar;
4. actualizar state;
5. detenerse.

Estados:
- TECHNICAL_STATUS: PENDING | PASSED | FAILED | BLOCKED
- HUMAN_STATUS: PENDING | APPROVED | CHANGES_REQUIRED

Alcance fijo:
- `cat_categoria`
- `auth_usuario`
- `cat_producto`

No ampliar el alcance automáticamente.

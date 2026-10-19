# Requisitos no funcionales
# Capacidad y rendimiento
- RNF-001. Soportar inicialmente 50 transacciones de negocio por hora.
- RNF-002. La capacidad objetivo debe ser configurable externamente.
- RNF-003. Una transacción de negocio no equivale a una sentencia SQL.
- RNF-004. Evaluar picos con el multiplicador configurado.
- RNF-005. Mantener integridad bajo concurrencia entre la reserva web y la recepción presencial.
- RNF-006. Código estructurado y documentado que permita realizar actualizaciones o correcciones de errores de manera rápida y sin afectar la operación del spa.
# Escalabilidad
- RNF-010. Permitir el crecimiento de recursos (camas y trabajadoras) y de sucursales sin rediseñar todo el dominio.
- RNF-011. Evaluar un escenario de 10x la carga inicial de transacciones de negocio.
- RNF-012. La arquitectura modular permitirá ampliar fácilmente la capacidad si el spa decide expandirse a más de 6 camas o incorporar más de 10 trabajadoras en el futuro.
# Integridad
- RNF-020. Evitar sobreasignación por condiciones de carrera.
- RNF-021. Definir fronteras transaccionales para dinero y asignación de recursos.
- RNF-022. Evitar duplicados ante reintentos.
# Seguridad
- RNF-030. Autenticación para operaciones administrativas.
- RNF-031. Autorización por mínimo privilegio.
- RNF-032. Cifrado en tránsito.
- RNF-033. Contraseñas nunca en texto plano.
- RNF-034. Secretos fuera del repositorio.
- RNF-035. Controles de seguridad para APIs.
# Disponibilidad y recuperación
- RNF-040. Definir estrategia de backup/restauración.
- RNF-041. RPO/RTO deben definirse antes de producción.
- RNF-042. Dependencias externas deben manejar timeout y reintentos controlados.
- RNF-043. El sistema deberá contar con una disponibilidad del 99.8%, asegurando que las reservas en línea puedan realizarse las 24 horas del día, los 7 días de la semana.
- RNF-044. Respaldos automáticos diarios de la base de datos para prevenir pérdida de información sobre citas, pagos y registros de clientes.
# Observabilidad
- RNF-050. Logs estructurados.
- RNF-051. Métricas de latencia, errores, disponibilidad y volumen.
- RNF-052. Correlación de operaciones distribuidas cuando corresponda.
# Portabilidad
- RNF-060. Ejecutarse en Linux.
- RNF-061. Poder contenerizarse.
- RNF-062. Separar configuraciones de desarrollo, pruebas y producción.

-- V010__seed
-- Seed mínimo: permisos canónicos + rol admin + core_configuracion (TTL reserva = 15 min, DB-P02).
-- NO incluye: usuarios MySQL (secreto), usuario admin de aplicación (hash Argon2id desde .env / bootstrap),
-- TTL de carrito (pendiente no definida — no inventar).
-- Rollback: DELETE solo en seed (data_loss aceptable solo en entorno desechable; en prod → forward-fix con UPDATE).
-- Ventana: aditiva, segura (requiere V001 auth_*/core_*, V009 roles).
USE `sistema`;
SET NAMES utf8mb4;

-- Permisos canónicos (RF/RBAC): ajustar lista final según matriz de permisos vigente.
INSERT INTO auth_permiso (codigo, descripcion, estado, user_create, user_update, user_created_at, user_update_at) VALUES
  ('catalogo.leer', 'Ver catálogo', 'activo', 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  ('catalogo.escribir', 'Modificar catálogo', 'activo', 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  ('inventario.leer', 'Ver inventario', 'activo', 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  ('inventario.ajustar', 'Ajustar stock', 'activo', 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  ('ventas.cobrar', 'Registrar venta POS', 'activo', 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  ('ventas.web.gestionar', 'Gestionar pedidos web', 'activo', 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  ('compras.gestionar', 'Gestionar compras', 'activo', 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  ('reportes.ver', 'Ver reportes', 'activo', 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  ('admin.config', 'Administrar configuración', 'activo', 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- Rol admin con todos los permisos sembrados.
INSERT INTO auth_rol (nombre, estado, user_create, user_update, user_created_at, user_update_at)
VALUES ('admin', 'activo', 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE estado = 'activo';

INSERT INTO auth_rol_permiso (rol_id, permiso_id, user_create, user_update, user_created_at, user_update_at)
SELECT r.id, p.id, 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
FROM auth_rol r
JOIN auth_permiso p
WHERE r.nombre = 'admin' AND p.estado = 'activo'
  AND NOT EXISTS (
    SELECT 1 FROM auth_rol_permiso rp WHERE rp.rol_id = r.id AND rp.permiso_id = p.id
  );

-- core_configuracion: TTL reserva de stock web (DB-P02: 15 min, job expiración idempotente).
-- seed único; clave única ya garantizada por uq_core_config_clave.
INSERT INTO core_configuracion (clave, valor, descripcion, user_create, user_update, user_created_at, user_update_at)
VALUES (
  'reserva_ttl_minutos',
  '15',
  'Minutos de vigencia de una reserva web de stock antes de expirar (DB-P02)',
  1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
)
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

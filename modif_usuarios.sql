USE flotilla_interna;

-- Añadir la columna 'estatus_cuenta' a la tabla 'usuarios'
ALTER TABLE usuarios
ADD COLUMN estatus_cuenta ENUM(
    'pendiente_aprobacion',
    'activa',
    'rechazada',
    'inactiva'
) NOT NULL DEFAULT 'pendiente_aprobacion' AFTER rol;

-- Opcional: Actualizar usuarios existentes a 'activa' si ya los usas
-- Si ya tienes usuarios creados, es buena idea marcarlos como 'activa' para que puedan seguir entrando
UPDATE usuarios
SET
    estatus_cuenta = 'activa'
WHERE
    estatus_cuenta = 'pendiente_aprobacion';
-- Solo afecta a los que tengan el default
-- O si prefieres:
-- UPDATE usuarios
-- SET estatus_cuenta = 'activa'
-- WHERE id IN (1, 2, ...); -- IDs de tus usuarios existentes
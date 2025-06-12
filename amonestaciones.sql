USE flotilla_interna;

-- PASO 1.1: Añadir la columna 'estatus_usuario' a la tabla 'usuarios'
-- Este campo controlará si el usuario está activo, amonestado o suspendido para solicitar vehículos.
ALTER TABLE usuarios
ADD COLUMN estatus_usuario ENUM(
    'activo',
    'amonestado',
    'suspendido'
) NOT NULL DEFAULT 'activo' AFTER estatus_cuenta;

-- Opcional: Actualizar usuarios existentes a 'activo' en este nuevo campo
-- Esto es para que tus usuarios actuales puedan seguir operando.
UPDATE usuarios
SET
    estatus_usuario = 'activo'
WHERE
    estatus_usuario = 'activo';
-- Asegura que no se cambie si ya está en otro estado si corres el script varias veces

-- PASO 1.2: Crear la tabla 'amonestaciones'
-- Registrará cada incidente o amonestación para un historial detallado.
CREATE TABLE amonestaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    fecha_amonestacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo_amonestacion ENUM('leve', 'grave', 'suspension') NOT NULL, -- Ej. "Leve", "Grave", "Suspension"
    descripcion TEXT NOT NULL,
    evidencia_url VARCHAR(512) NULL, -- Opcional: Ruta a foto/documento de evidencia
    amonestado_por INT NULL, -- ID del admin/manager que puso la amonestación
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE, -- Si se elimina el usuario, sus amonestaciones también
    FOREIGN KEY (amonestado_por) REFERENCES usuarios (id) ON DELETE SET NULL -- Si se elimina el admin, se pone NULL
);
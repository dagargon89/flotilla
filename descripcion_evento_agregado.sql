-- PASO 1: Renombrar la columna 'proposito' a 'descripcion'
ALTER TABLE solicitudes_vehiculos
CHANGE COLUMN proposito descripcion TEXT NOT NULL;

-- PASO 2: Añadir la nueva columna 'evento' antes de 'descripcion'
-- Se recomienda que 'evento' sea un VARCHAR con un límite de longitud
ALTER TABLE solicitudes_vehiculos
ADD COLUMN evento VARCHAR(255) NOT NULL AFTER destino;

-- Opcional: Si 'evento' puede ser nulo o si quieres un valor por defecto
-- ALTER TABLE solicitudes_vehiculos
-- ADD COLUMN evento VARCHAR(255) NULL AFTER destino;
-- ALTER TABLE solicitudes_vehiculos
-- ADD COLUMN evento VARCHAR(255) NOT NULL DEFAULT 'Viaje' AFTER destino;
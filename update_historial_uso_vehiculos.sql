ALTER TABLE historial_uso_vehiculos 
DROP COLUMN fotos_salida_url,
DROP COLUMN fotos_regreso_url,
ADD COLUMN fotos_salida_medidores_url LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(fotos_salida_medidores_url)),
ADD COLUMN fotos_salida_observaciones_url LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(fotos_salida_observaciones_url)),
ADD COLUMN fotos_regreso_medidores_url LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(fotos_regreso_medidores_url)),
ADD COLUMN fotos_regreso_observaciones_url LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(fotos_regreso_observaciones_url));
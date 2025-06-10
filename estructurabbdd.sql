-- *** Código SQL para la Base de Datos flotilla_interna ***

-- Paso 1: Crear la base de datos si no existe.
-- Usamos 'utf8mb4' para soportar una mayor variedad de caracteres, incluyendo emojis y caracteres especiales.
CREATE DATABASE IF NOT EXISTS flotilla_interna CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Paso 2: Seleccionar la base de datos que acabamos de crear para trabajar en ella.
USE flotilla_interna;

-- Paso 3: Creación de la tabla 'usuarios'
-- Guarda la información de los usuarios que usarán la plataforma.
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    correo_electronico VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NULL, -- Puede ser NULL si la autenticación es solo por Google
    rol ENUM('admin', 'empleado', 'flotilla_manager') NOT NULL DEFAULT 'empleado', -- Define los permisos de cada usuario
    google_id VARCHAR(255) UNIQUE NULL, -- ID único de Google para la autenticación
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP, -- Fecha y hora de creación del registro
    ultima_sesion DATETIME NULL -- Última vez que el usuario inició sesión
);

-- Paso 4: Creación de la tabla 'vehiculos'
-- Almacena todos los datos de los vehículos oficiales.
CREATE TABLE vehiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    marca VARCHAR(100) NOT NULL,
    modelo VARCHAR(100) NOT NULL,
    anio INT NOT NULL,
    placas VARCHAR(20) UNIQUE NOT NULL, -- Las placas deben ser únicas por vehículo
    vin VARCHAR(255) UNIQUE NULL, -- Número de Identificación Vehicular, también único
    tipo_combustible ENUM('Gasolina', 'Diésel', 'Eléctrico', 'Híbrido') NOT NULL,
    kilometraje_actual INT NOT NULL DEFAULT 0,
    estatus ENUM('disponible', 'en_uso', 'en_mantenimiento', 'inactivo') NOT NULL DEFAULT 'disponible', -- Estado actual del vehículo
    ubicacion_actual VARCHAR(255) NULL, -- Dónde se encuentra el vehículo (ej. "Oficina Principal", "Taller")
    observaciones TEXT NULL, -- Notas adicionales sobre el vehículo
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP -- Fecha en que el vehículo fue registrado en el sistema
);

-- Paso 5: Creación de la tabla 'solicitudes_vehiculos'
-- Registra cada solicitud que un usuario hace para usar un vehículo.
CREATE TABLE solicitudes_vehiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL, -- Quién hace la solicitud
    vehiculo_id INT NULL, -- ID del vehículo asignado (puede ser NULL si aún no se ha asignado)
    fecha_salida_solicitada DATETIME NOT NULL, -- Fecha y hora deseada de salida
    fecha_regreso_solicitada DATETIME NOT NULL, -- Fecha y hora deseada de regreso
    proposito TEXT NOT NULL, -- Razón del uso del vehículo
    destino VARCHAR(255) NOT NULL, -- Lugar o ruta a la que se dirige
    estatus_solicitud ENUM('pendiente', 'aprobada', 'rechazada', 'en_curso', 'completada', 'cancelada') NOT NULL DEFAULT 'pendiente', -- Estado de la solicitud
    fecha_aprobacion DATETIME NULL, -- Cuándo fue aprobada la solicitud
    aprobado_por INT NULL, -- Quién aprobó la solicitud
    observaciones_aprobacion TEXT NULL, -- Notas del aprobador
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP, -- Cuándo se creó la solicitud
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE, -- Si se borra el usuario, sus solicitudes se borran
    FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE SET NULL, -- Si se borra el vehículo, se pone NULL aquí
    FOREIGN KEY (aprobado_por) REFERENCES usuarios(id) ON DELETE SET NULL -- Si se borra el aprobador, se pone NULL aquí
);

-- Paso 6: Creación de la tabla 'historial_uso_vehiculos'
-- Detalle de cada vez que un vehículo es usado, incluyendo el estado al salir y regresar.
CREATE TABLE historial_uso_vehiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitud_id INT UNIQUE NOT NULL, -- Ligado a una solicitud específica, y debe ser único
    vehiculo_id INT NOT NULL, -- El vehículo que fue utilizado
    usuario_id INT NOT NULL, -- El usuario que lo utilizó
    kilometraje_salida INT NOT NULL, -- Kilometraje al momento de la salida
    nivel_combustible_salida DECIMAL(5,2) NOT NULL, -- Nivel de gasolina al salir (ej. 0.75 para 75%)
    fecha_salida_real DATETIME NOT NULL, -- Fecha y hora real de salida
    observaciones_salida TEXT NULL, -- Cualquier detalle o daño observado al salir
    fotos_salida_url JSON NULL, -- Rutas de las fotos del estado del vehículo al salir (como JSON array)
    kilometraje_regreso INT NULL, -- Kilometraje al momento del regreso
    nivel_combustible_regreso DECIMAL(5,2) NULL, -- Nivel de gasolina al regresar
    fecha_regreso_real DATETIME NULL, -- Fecha y hora real de regreso
    observaciones_regreso TEXT NULL, -- Cualquier detalle o daño observado al regresar
    fotos_regreso_url JSON NULL, -- Rutas de las fotos del estado del vehículo al regresar (como JSON array)
    FOREIGN KEY (solicitud_id) REFERENCES solicitudes_vehiculos(id) ON DELETE CASCADE,
    FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Paso 7: Creación de la tabla 'mantenimientos'
-- Registra todos los servicios y reparaciones realizados a los vehículos.
CREATE TABLE mantenimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehiculo_id INT NOT NULL, -- El vehículo al que se le hizo el mantenimiento
    tipo_mantenimiento VARCHAR(255) NOT NULL, -- Descripción del tipo de servicio (ej. "Cambio de aceite", "Revisión 10,000 km")
    fecha_mantenimiento DATETIME NOT NULL, -- Fecha en que se realizó el mantenimiento
    kilometraje_mantenimiento INT NULL, -- Kilometraje del vehículo en el momento del mantenimiento
    costo DECIMAL(10,2) NULL, -- Costo del servicio
    taller VARCHAR(255) NULL, -- Nombre del taller o proveedor
    observaciones TEXT NULL, -- Notas adicionales sobre el mantenimiento
    proximo_mantenimiento_km INT NULL, -- Kilometraje estimado para el siguiente mantenimiento
    proximo_mantenimiento_fecha DATETIME NULL, -- Fecha estimada para el siguiente mantenimiento
    FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE CASCADE -- Si se borra el vehículo, sus mantenimientos se borran
);

-- Paso 8: Creación de la tabla 'documentos_vehiculos'
-- Para almacenar las rutas a los documentos importantes de cada vehículo.
CREATE TABLE documentos_vehiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehiculo_id INT NOT NULL, -- Vehículo al que pertenece el documento
    nombre_documento VARCHAR(255) NOT NULL, -- Nombre del documento (ej. "Tarjeta de Circulación", "Póliza de Seguro")
    ruta_archivo VARCHAR(512) NOT NULL, -- Ruta o URL donde está guardado el archivo en el servidor
    fecha_vencimiento DATE NULL, -- Fecha de vencimiento del documento, si aplica
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP, -- Cuándo se subió el documento
    subido_por INT NULL, -- Quién subió el documento
    FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE CASCADE, -- Si se borra el vehículo, sus documentos se borran
    FOREIGN KEY (subido_por) REFERENCES usuarios(id) ON DELETE SET NULL -- Si se borra el usuario, se pone NULL aquí
);
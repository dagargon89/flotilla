-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 12-06-2025 a las 07:06:35
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `flotilla_interna`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `amonestaciones`
--

CREATE TABLE `amonestaciones` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha_amonestacion` datetime NOT NULL DEFAULT current_timestamp(),
  `tipo_amonestacion` enum('leve','grave','suspension') NOT NULL,
  `descripcion` text NOT NULL,
  `evidencia_url` varchar(512) DEFAULT NULL,
  `amonestado_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `amonestaciones`
--

INSERT INTO `amonestaciones` (`id`, `usuario_id`, `fecha_amonestacion`, `tipo_amonestacion`, `descripcion`, `evidencia_url`, `amonestado_por`) VALUES
(1, 2, '2025-06-11 21:46:15', 'leve', 'Ensució el auto por dentro', NULL, 1),
(2, 3, '2025-06-11 21:52:06', 'grave', 'Choco estando ebrio', NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documentos_vehiculos`
--

CREATE TABLE `documentos_vehiculos` (
  `id` int(11) NOT NULL,
  `vehiculo_id` int(11) NOT NULL,
  `nombre_documento` varchar(255) NOT NULL,
  `ruta_archivo` varchar(512) NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `fecha_subida` datetime DEFAULT current_timestamp(),
  `subido_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `documentos_vehiculos`
--

INSERT INTO `documentos_vehiculos` (`id`, `vehiculo_id`, `nombre_documento`, `ruta_archivo`, `fecha_vencimiento`, `fecha_subida`, `subido_por`) VALUES
(1, 2, 'Aseguranza', '/flotilla/storage/uploads/vehiculo_documentos/doc_684a3bf995a87.jpg', NULL, '2025-06-11 20:31:21', 1),
(2, 2, 'Engomado ecologico', '/flotilla/storage/uploads/vehiculo_documentos/doc_684a3c6399fbc.png', NULL, '2025-06-11 20:33:07', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_uso_vehiculos`
--

CREATE TABLE `historial_uso_vehiculos` (
  `id` int(11) NOT NULL,
  `solicitud_id` int(11) NOT NULL,
  `vehiculo_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `kilometraje_salida` int(11) NOT NULL,
  `nivel_combustible_salida` decimal(5,2) NOT NULL,
  `fecha_salida_real` datetime NOT NULL,
  `observaciones_salida` text DEFAULT NULL,
  `fotos_salida_url` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`fotos_salida_url`)),
  `kilometraje_regreso` int(11) DEFAULT NULL,
  `nivel_combustible_regreso` decimal(5,2) DEFAULT NULL,
  `fecha_regreso_real` datetime DEFAULT NULL,
  `observaciones_regreso` text DEFAULT NULL,
  `fotos_regreso_url` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`fotos_regreso_url`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mantenimientos`
--

CREATE TABLE `mantenimientos` (
  `id` int(11) NOT NULL,
  `vehiculo_id` int(11) NOT NULL,
  `tipo_mantenimiento` varchar(255) NOT NULL,
  `fecha_mantenimiento` datetime NOT NULL,
  `kilometraje_mantenimiento` int(11) DEFAULT NULL,
  `costo` decimal(10,2) DEFAULT NULL,
  `taller` varchar(255) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `proximo_mantenimiento_km` int(11) DEFAULT NULL,
  `proximo_mantenimiento_fecha` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes_vehiculos`
--

CREATE TABLE `solicitudes_vehiculos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `vehiculo_id` int(11) DEFAULT NULL,
  `fecha_salida_solicitada` datetime NOT NULL,
  `fecha_regreso_solicitada` datetime NOT NULL,
  `descripcion` text NOT NULL,
  `destino` varchar(255) NOT NULL,
  `evento` varchar(255) NOT NULL,
  `estatus_solicitud` enum('pendiente','aprobada','rechazada','en_curso','completada','cancelada') NOT NULL DEFAULT 'pendiente',
  `fecha_aprobacion` datetime DEFAULT NULL,
  `aprobado_por` int(11) DEFAULT NULL,
  `observaciones_aprobacion` text DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `solicitudes_vehiculos`
--

INSERT INTO `solicitudes_vehiculos` (`id`, `usuario_id`, `vehiculo_id`, `fecha_salida_solicitada`, `fecha_regreso_solicitada`, `descripcion`, `destino`, `evento`, `estatus_solicitud`, `fecha_aprobacion`, `aprobado_por`, `observaciones_aprobacion`, `fecha_creacion`) VALUES
(1, 1, 4, '2025-06-12 19:37:00', '2025-06-14 12:00:00', 'Se llevaran bocinas, casas de campaña, mesas y sillas', 'Samalayuca', 'Camping', 'aprobada', '2025-06-11 20:25:45', 1, 'Nada en particular', '2025-06-11 19:38:42'),
(2, 1, NULL, '2025-06-12 20:53:00', '2025-06-13 12:00:00', 'Solo se transportara gente', 'Suroriente', 'Arte en el parque', 'rechazada', '2025-06-11 20:54:41', 1, 'ME caes gordo', '2025-06-11 20:54:06'),
(3, 2, 3, '2025-06-14 10:17:00', '2025-06-14 23:17:00', 'sdfasdfads', 'sdfasdfsd', 'prueba', 'aprobada', '2025-06-11 22:18:53', 1, 'ninguna', '2025-06-11 22:18:22');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `correo_electronico` varchar(255) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `rol` enum('admin','empleado','flotilla_manager') NOT NULL DEFAULT 'empleado',
  `estatus_cuenta` enum('pendiente_aprobacion','activa','rechazada','inactiva') NOT NULL DEFAULT 'pendiente_aprobacion',
  `estatus_usuario` enum('activo','amonestado','suspendido') NOT NULL DEFAULT 'activo',
  `google_id` varchar(255) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `ultima_sesion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `correo_electronico`, `password`, `rol`, `estatus_cuenta`, `estatus_usuario`, `google_id`, `fecha_creacion`, `ultima_sesion`) VALUES
(1, 'David García', 'dgarcia@planjuarez.org', '$2y$10$6wZ4sx/bg37hJrUqs2Xcf.kgDi8uCM7Q5lbtfISsPXqDtQBBKRE7.', 'admin', 'activa', 'activo', NULL, '2025-06-11 19:12:11', '2025-06-11 22:52:56'),
(2, 'Empleado', 'empleado@test.com', '$2y$10$pWGBWEAonoRmlEyizkdiO.f2gw6mgksa3e/FKivyX8d1aglVCxe4q', 'empleado', 'activa', 'amonestado', NULL, '2025-06-11 21:17:40', '2025-06-11 22:19:24'),
(3, 'Lider de flotilla', 'liderflotilla@test.com', '$2y$10$nW5dTKf6aNTW3VamG1Zn0OVTf2aP5Mp1qjOvaafPvIrHheJb2XNZO', 'flotilla_manager', 'activa', 'suspendido', NULL, '2025-06-11 21:21:21', '2025-06-11 22:44:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vehiculos`
--

CREATE TABLE `vehiculos` (
  `id` int(11) NOT NULL,
  `marca` varchar(100) NOT NULL,
  `modelo` varchar(100) NOT NULL,
  `anio` int(11) NOT NULL,
  `placas` varchar(20) NOT NULL,
  `vin` varchar(255) DEFAULT NULL,
  `tipo_combustible` enum('Gasolina','Diésel','Eléctrico','Híbrido') NOT NULL,
  `kilometraje_actual` int(11) NOT NULL DEFAULT 0,
  `estatus` enum('disponible','en_uso','en_mantenimiento','inactivo') NOT NULL DEFAULT 'disponible',
  `ubicacion_actual` varchar(255) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `vehiculos`
--

INSERT INTO `vehiculos` (`id`, `marca`, `modelo`, `anio`, `placas`, `vin`, `tipo_combustible`, `kilometraje_actual`, `estatus`, `ubicacion_actual`, `observaciones`, `fecha_registro`) VALUES
(1, 'Nissan', 'Versa', 2020, 'ABC-123-D', '3N1BD7APXLL123456', 'Gasolina', 45200, 'disponible', 'Estacionamiento P1', 'Ideal para traslados urbanos y personal.', '2025-06-11 19:17:07'),
(2, 'Chevrolet', 'Aveo', 2021, 'XYZ-987-A', '1G1BD5AT7M123457', 'Gasolina', 30500, 'disponible', 'Estacionamiento P2', 'Compacto y económico, buen rendimiento.', '2025-06-11 19:17:07'),
(3, 'Honda', 'CR-V', 2019, 'DEF-456-E', '5J6RM4H74KLL123458', 'Gasolina', 78100, 'disponible', 'Estacionamiento P3', 'Camioneta familiar, cómoda para viajes.', '2025-06-11 19:17:07'),
(4, 'Ford', 'F-150', 2022, 'GHI-789-F', '1FTFX1A61NKB123459', 'Gasolina', 15800, 'disponible', 'Patio de Carga Sur', 'Pickup robusta, capacidad media de carga.', '2025-06-11 19:17:07'),
(6, 'Isuzu', 'NPR', 2018, 'MNO-345-C', 'JF2LBR1A9G5123461', 'Diésel', 125000, 'disponible', 'Centro de Distribución', 'Camión ligero de caja, alta capacidad para mercancía.', '2025-06-11 19:17:07');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `amonestaciones`
--
ALTER TABLE `amonestaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `amonestado_por` (`amonestado_por`);

--
-- Indices de la tabla `documentos_vehiculos`
--
ALTER TABLE `documentos_vehiculos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vehiculo_id` (`vehiculo_id`),
  ADD KEY `subido_por` (`subido_por`);

--
-- Indices de la tabla `historial_uso_vehiculos`
--
ALTER TABLE `historial_uso_vehiculos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `solicitud_id` (`solicitud_id`),
  ADD KEY `vehiculo_id` (`vehiculo_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `mantenimientos`
--
ALTER TABLE `mantenimientos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vehiculo_id` (`vehiculo_id`);

--
-- Indices de la tabla `solicitudes_vehiculos`
--
ALTER TABLE `solicitudes_vehiculos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `vehiculo_id` (`vehiculo_id`),
  ADD KEY `aprobado_por` (`aprobado_por`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `correo_electronico` (`correo_electronico`),
  ADD UNIQUE KEY `google_id` (`google_id`);

--
-- Indices de la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `placas` (`placas`),
  ADD UNIQUE KEY `vin` (`vin`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `amonestaciones`
--
ALTER TABLE `amonestaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `documentos_vehiculos`
--
ALTER TABLE `documentos_vehiculos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `historial_uso_vehiculos`
--
ALTER TABLE `historial_uso_vehiculos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `mantenimientos`
--
ALTER TABLE `mantenimientos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `solicitudes_vehiculos`
--
ALTER TABLE `solicitudes_vehiculos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `amonestaciones`
--
ALTER TABLE `amonestaciones`
  ADD CONSTRAINT `amonestaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `amonestaciones_ibfk_2` FOREIGN KEY (`amonestado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `documentos_vehiculos`
--
ALTER TABLE `documentos_vehiculos`
  ADD CONSTRAINT `documentos_vehiculos_ibfk_1` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documentos_vehiculos_ibfk_2` FOREIGN KEY (`subido_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `historial_uso_vehiculos`
--
ALTER TABLE `historial_uso_vehiculos`
  ADD CONSTRAINT `historial_uso_vehiculos_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_vehiculos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `historial_uso_vehiculos_ibfk_2` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `historial_uso_vehiculos_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `mantenimientos`
--
ALTER TABLE `mantenimientos`
  ADD CONSTRAINT `mantenimientos_ibfk_1` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `solicitudes_vehiculos`
--
ALTER TABLE `solicitudes_vehiculos`
  ADD CONSTRAINT `solicitudes_vehiculos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `solicitudes_vehiculos_ibfk_2` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `solicitudes_vehiculos_ibfk_3` FOREIGN KEY (`aprobado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

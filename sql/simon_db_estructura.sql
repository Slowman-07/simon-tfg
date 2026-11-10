-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 09-11-2026 a las 18:07:15
-- Versión del servidor: 10.11.16-MariaDB
-- Versión de PHP: 8.5.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `simon_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `familia`
--

CREATE TABLE `familia` (
  `id_familia` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL COMMENT 'Se usa como usuario para iniciar sesión',
  `contrasena` varchar(255) DEFAULT NULL COMMENT 'Hash con password_hash() (bcrypt). Vacío hasta que la familia activa su cuenta (ver ficha Activar cuenta); nunca en texto plano'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ficha_nota`
--

CREATE TABLE `ficha_nota` (
  `id_nota` int(11) NOT NULL,
  `id_infante` int(11) NOT NULL COMMENT 'Niño o niña a quien corresponde la nota',
  `id_historia` int(11) DEFAULT NULL COMMENT 'Historia de la biblioteca que se trabajó en la sesión (opcional)',
  `fecha_sesion` datetime NOT NULL COMMENT 'Cuándo fue la sesión; la elige la profesional',
  `nota` text NOT NULL COMMENT 'Anotación libre de la profesional',
  `fecha_carga` datetime NOT NULL COMMENT 'Cuándo se escribió la nota',
  `fecha_edicion` datetime DEFAULT NULL COMMENT 'Última vez que se editó; vacío si nunca se editó'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_progreso`
--

CREATE TABLE `historial_progreso` (
  `id_historial` int(11) NOT NULL,
  `id_infante` int(11) NOT NULL,
  `id_historia` int(11) NOT NULL,
  `fecha_ejecucion` datetime NOT NULL,
  `estado` varchar(20) NOT NULL,
  `apoyo_necesitado` varchar(20) DEFAULT NULL COMMENT 'independiente / visual / verbal / físico',
  `tiempo_minutos` int(11) DEFAULT NULL COMMENT 'Cuánto tardó el niño en completar la historia',
  `autorregulacion` text DEFAULT NULL COMMENT 'Observación del adulto sobre cómo se reguló el niño',
  `comentario` text DEFAULT NULL COMMENT 'Observación libre adicional'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historias`
--

CREATE TABLE `historias` (
  `id_historia` int(11) NOT NULL,
  `id_infante` int(11) DEFAULT NULL,
  `id_historia_origen` int(11) DEFAULT NULL COMMENT 'Historia de la que se duplicó esta, si corresponde',
  `id_personaje` int(11) DEFAULT NULL COMMENT 'Personaje que protagoniza esta historia',
  `tipo_actividad` varchar(10) NOT NULL DEFAULT 'rutina' COMMENT '''rutina'' (narracion/decision) u ''ordenar'' (ordenar palabras); fijo desde la creacion, elegido en el menu principal',
  `titulo` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `nivel_complejidad` int(11) DEFAULT NULL,
  `actividad_avd` varchar(100) DEFAULT NULL COMMENT 'Actividad de la vida diaria que representa (ej: lavarse las manos)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `infantes`
--

CREATE TABLE `infantes` (
  `id_infante` int(11) NOT NULL,
  `id_familia` int(11) DEFAULT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellido` varchar(50) NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `nivel_autonomia` text DEFAULT NULL COMMENT 'Qué tan independiente es el niño, según el profesional (no una edad). Texto libre.',
  `apoyo_necesario` varchar(20) DEFAULT NULL COMMENT 'independiente / visual / verbal / físico',
  `tipo_imagen` varchar(20) DEFAULT NULL COMMENT 'foto / pictograma / ambas',
  `cantidad_pasos` int(11) DEFAULT NULL COMMENT 'Pasos que se muestran juntos en pantalla',
  `audio_activo` tinyint(1) NOT NULL DEFAULT 0,
  `subtitulo_activo` tinyint(1) NOT NULL DEFAULT 0,
  `seleccion_activa` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `paso`
--

CREATE TABLE `paso` (
  `id_paso` int(11) NOT NULL,
  `id_historia` int(11) NOT NULL,
  `orden` int(11) NOT NULL COMMENT 'Posición del paso dentro de la historia',
  `tipo_paso` varchar(20) NOT NULL COMMENT '''narracion'' / ''decision'' / ''ordenar''',
  `texto_narracion` text DEFAULT NULL,
  `imagen_url` varchar(255) DEFAULT NULL COMMENT 'Narración: imagen principal. Decisión: opción A. Ordenar: imagen de confirmación (opcional)',
  `audio_url` varchar(255) DEFAULT NULL,
  `imagen_opcion_b_url` varchar(255) DEFAULT NULL COMMENT 'Solo para tipo_paso = decision, con dos opciones',
  `opcion_correcta` varchar(10) DEFAULT NULL COMMENT '''A'' o ''B'', solo para tipo_paso = decision',
  `texto_resultado` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `paso_orden_item`
--

CREATE TABLE `paso_orden_item` (
  `id_item` int(11) NOT NULL,
  `id_paso` int(11) NOT NULL,
  `texto` varchar(100) NOT NULL COMMENT 'La palabra que el niño tiene que tocar',
  `imagen_url` varchar(255) DEFAULT NULL COMMENT 'Pictograma que acompaña a la palabra (opcional)',
  `orden_correcto` int(11) NOT NULL COMMENT 'Posición de esta palabra en la oración bien armada'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personaje`
--

CREATE TABLE `personaje` (
  `id_personaje` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL COMMENT 'Ej: "el oso Mieloson"',
  `imagen_url` varchar(255) DEFAULT NULL COMMENT 'Cómo se ve el personaje (opcional)',
  `texto_presentacion` text DEFAULT NULL COMMENT 'Lo que SIMON lee para presentarlo, si está cargado y el audio está activo (opcional)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profesional`
--

CREATE TABLE `profesional` (
  `id_profesional` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellido` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL COMMENT 'Se usa como usuario para iniciar sesión',
  `contrasena` varchar(255) NOT NULL COMMENT 'Hash generado con password_hash() (bcrypt), nunca en texto plano',
  `recuperacion_token` char(64) DEFAULT NULL,
  `recuperacion_vence` datetime DEFAULT NULL,
  `recuperacion_aprobada` tinyint(1) NOT NULL DEFAULT 0,
  `especialidad` varchar(100) DEFAULT NULL COMMENT 'Ej: Terapia Ocupacional, Fonoaudiología'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `familia`
--
ALTER TABLE `familia`
  ADD PRIMARY KEY (`id_familia`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `ficha_nota`
--
ALTER TABLE `ficha_nota`
  ADD PRIMARY KEY (`id_nota`),
  ADD KEY `fk_nota_infante` (`id_infante`),
  ADD KEY `fk_nota_historia` (`id_historia`);

--
-- Indices de la tabla `historial_progreso`
--
ALTER TABLE `historial_progreso`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `fk_historial_infante` (`id_infante`),
  ADD KEY `fk_historial_historia` (`id_historia`);

--
-- Indices de la tabla `historias`
--
ALTER TABLE `historias`
  ADD PRIMARY KEY (`id_historia`),
  ADD KEY `fk_historias_infante` (`id_infante`),
  ADD KEY `fk_historias_origen` (`id_historia_origen`),
  ADD KEY `fk_historias_personaje` (`id_personaje`);

--
-- Indices de la tabla `infantes`
--
ALTER TABLE `infantes`
  ADD PRIMARY KEY (`id_infante`),
  ADD KEY `fk_infantes_familia` (`id_familia`);

--
-- Indices de la tabla `paso`
--
ALTER TABLE `paso`
  ADD PRIMARY KEY (`id_paso`),
  ADD KEY `fk_paso_historia` (`id_historia`);

--
-- Indices de la tabla `paso_orden_item`
--
ALTER TABLE `paso_orden_item`
  ADD PRIMARY KEY (`id_item`),
  ADD KEY `fk_item_paso` (`id_paso`);

--
-- Indices de la tabla `personaje`
--
ALTER TABLE `personaje`
  ADD PRIMARY KEY (`id_personaje`);

--
-- Indices de la tabla `profesional`
--
ALTER TABLE `profesional`
  ADD PRIMARY KEY (`id_profesional`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `familia`
--
ALTER TABLE `familia`
  MODIFY `id_familia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ficha_nota`
--
ALTER TABLE `ficha_nota`
  MODIFY `id_nota` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_progreso`
--
ALTER TABLE `historial_progreso`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historias`
--
ALTER TABLE `historias`
  MODIFY `id_historia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `infantes`
--
ALTER TABLE `infantes`
  MODIFY `id_infante` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `paso`
--
ALTER TABLE `paso`
  MODIFY `id_paso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `paso_orden_item`
--
ALTER TABLE `paso_orden_item`
  MODIFY `id_item` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `personaje`
--
ALTER TABLE `personaje`
  MODIFY `id_personaje` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `profesional`
--
ALTER TABLE `profesional`
  MODIFY `id_profesional` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `ficha_nota`
--
ALTER TABLE `ficha_nota`
  ADD CONSTRAINT `fk_nota_historia` FOREIGN KEY (`id_historia`) REFERENCES `historias` (`id_historia`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_nota_infante` FOREIGN KEY (`id_infante`) REFERENCES `infantes` (`id_infante`);

--
-- Filtros para la tabla `historial_progreso`
--
ALTER TABLE `historial_progreso`
  ADD CONSTRAINT `fk_historial_historia` FOREIGN KEY (`id_historia`) REFERENCES `historias` (`id_historia`),
  ADD CONSTRAINT `fk_historial_infante` FOREIGN KEY (`id_infante`) REFERENCES `infantes` (`id_infante`);

--
-- Filtros para la tabla `historias`
--
ALTER TABLE `historias`
  ADD CONSTRAINT `fk_historias_infante` FOREIGN KEY (`id_infante`) REFERENCES `infantes` (`id_infante`),
  ADD CONSTRAINT `fk_historias_origen` FOREIGN KEY (`id_historia_origen`) REFERENCES `historias` (`id_historia`),
  ADD CONSTRAINT `fk_historias_personaje` FOREIGN KEY (`id_personaje`) REFERENCES `personaje` (`id_personaje`);

--
-- Filtros para la tabla `infantes`
--
ALTER TABLE `infantes`
  ADD CONSTRAINT `fk_infantes_familia` FOREIGN KEY (`id_familia`) REFERENCES `familia` (`id_familia`);

--
-- Filtros para la tabla `paso`
--
ALTER TABLE `paso`
  ADD CONSTRAINT `fk_paso_historia` FOREIGN KEY (`id_historia`) REFERENCES `historias` (`id_historia`);

--
-- Filtros para la tabla `paso_orden_item`
--
ALTER TABLE `paso_orden_item`
  ADD CONSTRAINT `fk_item_paso` FOREIGN KEY (`id_paso`) REFERENCES `paso` (`id_paso`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

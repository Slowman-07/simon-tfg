-- =====================================================================
-- S.I.M.O.N. — Reinicio LOCAL con la biblioteca real (incluye a Panbu)
-- Generado a partir de la exportación de los datos del hosting (solo biblioteca).
--
-- SOLO PARA LA PC (XAMPP). NUNCA correr en el hosting: borra TODO
-- (profesional, familias, niños y niñas, asignaciones, seguimientos y notas).
--
-- Qué hace:
--   1) Borra los datos de las nueve tablas, en un orden que respeta las claves foráneas.
--   2) Carga la biblioteca igual que está en el hosting, con los mismos números de id:
--      personajes (Mieloson, Rosa, Panbu), cinco historias, sus pasos y las tres gemas de Panbu.
--   3) No crea ninguna cuenta: al abrir el sistema aparece "Crear cuenta de profesional".
-- No toca la estructura de las tablas.
-- =====================================================================

SET NAMES utf8mb4;

-- 1) Borrado (el orden importa por las claves foráneas)
DELETE FROM ficha_nota;
DELETE FROM historial_progreso;
DELETE FROM paso_orden_item;
DELETE FROM paso;
DELETE FROM historias WHERE id_historia_origen IS NOT NULL;
DELETE FROM historias;
DELETE FROM infantes;
DELETE FROM familia;
DELETE FROM profesional;
DELETE FROM personaje;

-- Los contadores de las tablas de usuarios vuelven a empezar desde 1
ALTER TABLE ficha_nota AUTO_INCREMENT = 1;
ALTER TABLE historial_progreso AUTO_INCREMENT = 1;
ALTER TABLE infantes AUTO_INCREMENT = 1;
ALTER TABLE familia AUTO_INCREMENT = 1;
ALTER TABLE profesional AUTO_INCREMENT = 1;

-- 2) Biblioteca (personajes, historias, pasos e ítems)
INSERT INTO `personaje` (`id_personaje`, `nombre`, `imagen_url`, `texto_presentacion`) VALUES
(1, 'Mieloson', 'img/personajes/mieloson.png', '¡Hola, amiguito! Te presento a uno de mis amigos: Mieloson. Es un oso grande pero muy dulce, y le encantan la miel y jugar. Te voy a contar muchas historias de él.'),
(2, 'Rosa', 'img/personajes/rosa.png', '¡Hola, amiguito! Te presento a una de mis amigas: Rosa. Te voy a contar muchas historias de ella.'),
(3, 'Panbu', NULL, '¡Hola, amiguito! Te presento a uno de mis amigos: Panbu. Es un panda curioso al que le encanta explorar. Te voy a contar muchas historias de él.');

INSERT INTO `historias` (`id_historia`, `id_infante`, `id_historia_origen`, `id_personaje`, `tipo_actividad`, `titulo`, `descripcion`, `nivel_complejidad`, `actividad_avd`) VALUES
(1, NULL, NULL, 2, 'rutina', 'Rosa va al baño', 'Rosa pide ir al baño, hace pis y se lava las manos.', NULL, 'Ir al baño'),
(2, NULL, NULL, 2, 'rutina', 'Rosa desayuna', 'Rosa desayuna, pide permiso y se cepilla los dientes.', NULL, 'Desayuno e higiene'),
(3, NULL, NULL, 1, 'rutina', 'Mieloson se baña', 'Mieloson se baña antes de dormir.', NULL, 'Baño'),
(4, NULL, NULL, 1, 'rutina', 'Mieloson ordena los juguetes', 'Mieloson guarda sus juguetes cuando termina de jugar.', NULL, 'Orden'),
(9, NULL, NULL, 3, 'ordenar', 'Panbu y el cofre', 'Panbu encuentra un mapa y ayuda a abrir un cofre ordenando gemas de colores.', NULL, 'Ordenar una secuencia de colores');

INSERT INTO `paso` (`id_paso`, `id_historia`, `orden`, `tipo_paso`, `texto_narracion`, `imagen_url`, `audio_url`, `imagen_opcion_b_url`, `opcion_correcta`, `texto_resultado`) VALUES
(1, 1, 1, 'narracion', 'El Sol se asoma por la ventana de mi amiga Rosa y la despierta.', 'img/historias/rosa_va_al_bano/estilo_1/01.jpg', NULL, NULL, NULL, NULL),
(2, 1, 2, 'narracion', 'Rosa se baja de la cama y se pone las pantuflas.', 'img/historias/rosa_va_al_bano/estilo_1/02.jpg', NULL, NULL, NULL, NULL),
(3, 1, 3, 'narracion', 'Rosa va al cuarto de mamá y papá. Le da la mano a mamá y señala la puerta: quiere ir al baño.', 'img/historias/rosa_va_al_bano/estilo_1/03.jpg', NULL, NULL, NULL, NULL),
(4, 1, 4, 'narracion', 'En el baño, Rosa se sienta en su pelela, como mamá. Hace pis.', 'img/historias/rosa_va_al_bano/estilo_1/04.jpg', NULL, NULL, NULL, NULL),
(5, 1, 5, 'decision', '¿Qué hace Rosa ahora?', 'img/historias/rosa_va_al_bano/estilo_1/05_a.jpg', NULL, 'img/historias/rosa_va_al_bano/estilo_1/05_b.jpg', 'A', 'Rosa se lava las manos.'),
(6, 1, 6, 'narracion', 'Rosa sale del baño con las manos limpias.', 'img/historias/rosa_va_al_bano/estilo_1/06.jpg', NULL, NULL, NULL, NULL),
(14, 3, 1, 'narracion', 'Mi amigo el Sol se fue y llegó su hermana, la Luna. Ya es de noche y es hora de bañarse antes de dormir.', 'img/historias/se_bana/estilo_1/01.jpg', NULL, NULL, NULL, NULL),
(15, 3, 2, 'narracion', 'Mieloson estuvo jugando todo el día y quedó sucio. Necesita limpiarse el cuerpo.', 'img/historias/se_bana/estilo_1/02.jpg', NULL, NULL, NULL, NULL),
(16, 3, 3, 'narracion', 'Con ayuda de papá, Mieloson se saca la ropa.', 'img/historias/se_bana/estilo_1/03.jpg', NULL, NULL, NULL, NULL),
(17, 3, 4, 'narracion', 'Mieloson entra a la bañera, con agua calentita y sus juguetes.', 'img/historias/se_bana/estilo_1/04.jpg', NULL, NULL, NULL, NULL),
(18, 3, 5, 'narracion', '¡Qué lindo es bañarse! Mamá o papá le moja la cabeza y el cuerpo mientras Mieloson juega.', 'img/historias/se_bana/estilo_1/05.jpg', NULL, NULL, NULL, NULL),
(19, 3, 6, 'decision', 'Mieloson quiere ayudar a lavarse: ¿con qué se lava Mieloson el cuerpo?', 'img/historias/se_bana/estilo_1/06_a.jpg', NULL, 'img/historias/se_bana/estilo_1/06_b.jpg', 'A', 'Mieloson se lava el cuerpo con jabón.'),
(20, 3, 7, 'narracion', 'Mieloson se enjuaga.', 'img/historias/se_bana/estilo_1/07.jpg', NULL, NULL, NULL, NULL),
(21, 3, 8, 'decision', 'Mieloson sale del agua: ¿qué debe hacer?', 'img/historias/se_bana/estilo_1/08_a.jpg', NULL, 'img/historias/se_bana/estilo_1/08_b.jpg', 'A', 'Mieloson se seca con la toalla.'),
(22, 3, 9, 'narracion', '¡Mieloson está limpio y listo para dormir!', 'img/historias/se_bana/estilo_1/09.jpg', NULL, NULL, NULL, NULL),
(23, 4, 1, 'narracion', 'Mi amigo Mieloson está en su habitación y mira su caja de juguetes.', 'img/historias/ordena_juguetes/estilo_1/01.jpg', NULL, NULL, NULL, NULL),
(24, 4, 2, 'narracion', 'Mieloson juega por todo el piso. ¡Se divierte mucho!', 'img/historias/ordena_juguetes/estilo_1/02.jpg', NULL, NULL, NULL, NULL),
(25, 4, 3, 'narracion', 'Mamá lo viene a buscar: es hora de comer.', 'img/historias/ordena_juguetes/estilo_1/03.jpg', NULL, NULL, NULL, NULL),
(26, 4, 4, 'decision', '¿Qué debe hacer Mieloson antes de comer?', 'img/historias/ordena_juguetes/estilo_1/04_a.jpg', NULL, 'img/historias/ordena_juguetes/estilo_1/04_b.jpg', 'B', 'Mieloson guarda los juguetes en la caja.'),
(27, 4, 5, 'narracion', 'El piso quedó libre. Más tarde, Mieloson podrá volver a jugar.', 'img/historias/ordena_juguetes/estilo_1/05.jpg', NULL, NULL, NULL, NULL),
(62, 2, 1, 'narracion', 'Mi amiga Rosa se sienta a la mesa para desayunar.', 'img/historias/rosa_desayuna/estilo_1/01.jpg', NULL, NULL, NULL, NULL),
(64, 2, 2, 'narracion', 'A Rosa le encantan la leche y la miel. Mamá le prepara su tazón de leche y una tostada con miel.', 'img/historias/rosa_desayuna/estilo_1/02.jpg', NULL, NULL, NULL, NULL),
(66, 2, 3, 'narracion', 'Rosa toma su tazón de leche.', 'img/historias/rosa_desayuna/estilo_1/03.jpg', NULL, NULL, NULL, NULL),
(68, 2, 4, 'narracion', 'Rosa come su tostada con miel.', 'img/historias/rosa_desayuna/estilo_1/04.jpg', NULL, NULL, NULL, NULL),
(70, 2, 5, 'narracion', 'Rosa vuelve a tomar el tazón y lo vacía.', 'img/historias/rosa_desayuna/estilo_1/05.jpg', NULL, NULL, NULL, NULL),
(72, 2, 6, 'narracion', 'Mamá le alcanza una servilleta.', 'img/historias/rosa_desayuna/estilo_1/06.jpg', NULL, NULL, NULL, NULL),
(74, 2, 7, 'narracion', 'Rosa se limpia la boca con la servilleta.', 'img/historias/rosa_desayuna/estilo_1/07.jpg', NULL, NULL, NULL, NULL),
(76, 2, 8, 'narracion', 'Rosa le pide permiso a mamá para ir a jugar y le muestra que terminó todo el desayuno. Mamá le dice que sí, pero que antes se cepille los dientes.', 'img/historias/rosa_desayuna/estilo_1/08.jpg', NULL, NULL, NULL, NULL),
(78, 2, 9, 'eleccion', 'Rosa va al baño.', 'img/historias/rosa_desayuna/estilo_1/09.jpg', NULL, NULL, NULL, NULL),
(80, 2, 11, 'narracion', 'Rosa se cepilla los dientes.', 'img/historias/rosa_desayuna/estilo_1/11.jpg', NULL, NULL, NULL, NULL),
(82, 2, 12, 'narracion', 'Rosa va a su cuarto.', 'img/historias/rosa_desayuna/estilo_1/12.jpg', NULL, NULL, NULL, NULL),
(84, 2, 13, 'narracion', '¡Ahora sí, con los dientes limpios, Rosa se puso a jugar!', 'img/historias/rosa_desayuna/estilo_1/13.jpg', NULL, NULL, NULL, NULL),
(93, 9, 1, 'narracion', 'Panbu salió a pasear por el bosque de bambúes fuera del pueblo, cuando de golpe se encuentra un mapa en el suelo.', 'img/historias/panbu_cofre/estilo_1/01.jpg', NULL, NULL, NULL, NULL),
(95, 9, 2, 'mapa', 'Panbu agarra el mapa y lo mira.', 'img/historias/panbu_cofre/estilo_1/02.jpg', NULL, NULL, NULL, NULL),
(96, 9, 3, 'narracion', 'Siguiendo el mapa, encuentra un cofre entre dos bambúes y una piedra. ¡Tiene una cerradura especial!', 'img/historias/panbu_cofre/estilo_1/03.jpg', NULL, NULL, NULL, NULL),
(97, 9, 4, 'ordenar', 'Panbu te muestra el mapa de nuevo. ¿Podés ayudarlo a abrir la cerradura usando los colores?', 'img/historias/panbu_cofre/estilo_1/04_mapa.jpg', NULL, 'img/historias/panbu_cofre/estilo_1/04_cofre.jpg', NULL, NULL),
(98, 9, 5, 'narracion', '¡El cofre se abrió! ¡Panbu encontró muchos juguetes!', 'img/historias/panbu_cofre/estilo_1/05.jpg', NULL, NULL, NULL, NULL),
(106, 2, 10, 'eleccion', '¿Qué necesita Rosa para cepillarse los dientes?', 'img/historias/rosa_desayuna/estilo_1/10_a.jpg', NULL, 'img/historias/rosa_desayuna/estilo_1/10_b.jpg', 'A', NULL);

INSERT INTO `paso_orden_item` (`id_item`, `id_paso`, `texto`, `imagen_url`, `orden_correcto`) VALUES
(1, 97, 'rojo', 'img/gemas/rojo.png', 1),
(2, 97, 'amarillo', 'img/gemas/amarillo.png', 2),
(3, 97, 'azul', 'img/gemas/azul.png', 3);

-- 3) Verificación: tiene que dar 3 personajes, 5 historias, 38 pasos y 3 ítems
SELECT (SELECT COUNT(*) FROM personaje) AS personajes, (SELECT COUNT(*) FROM historias) AS historias,
       (SELECT COUNT(*) FROM paso) AS pasos, (SELECT COUNT(*) FROM paso_orden_item) AS items,
       (SELECT COUNT(*) FROM profesional) AS profesionales;

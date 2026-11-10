<?php // Abre PHP
    session_start(); // Retoma la sesión para saber quién está logueado
    $esProfesional = isset($_SESSION['id_profesional']); // true si hay un profesional logueado: isset() pregunta si esa clave existe en la sesión
    $esFamilia = isset($_SESSION['id_familia']); // true si hay una familia logueada
    if (!$esProfesional && !$esFamilia) { // ¿No hay nadie logueado? && significa "y"; el ! invierte cada respuesta
        header("Location: login.php"); // Si no hay nadie, manda al login
        exit; // Corta acá: no se muestra nada más
    } // Fin de la verificación de sesión
    if ($esFamilia) { // ¿Es una familia? No tiene menú: su pantalla de inicio es la lista de sus niños con las historias asignadas
        header("Location: seguir_historia.php"); // La manda a esa lista
        exit; // Corta acá: no se muestra nada más
    } // Fin del desvío de la familia
?>
<!DOCTYPE html> <!-- Avisa al navegador que es HTML5 -->
<html lang="es"> <!-- Abre el documento en español -->
<head> <!-- Abre el encabezado -->
    <meta charset="UTF-8"> <!-- Para que tildes y ñ se vean bien -->
    <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- Hace que el celular o la tablet usen su ancho real: sin esta línea, dibuja la página como en una compu y la achica -->
    <title>Menú - S.I.M.O.N.</title> <!-- Texto de la pestaña del navegador -->
    <link rel="stylesheet" href="estilos.css?v=<?php echo filemtime(__DIR__ . "/estilos.css"); // Fecha de la última modificación: si el archivo cambia, el navegador baja la versión nueva ?>"> <!-- Carga la hoja de estilos compartida -->
</head> <!-- Cierra el encabezado -->
<body> <!-- Abre el cuerpo -->
    <h1>S.I.M.O.N.</h1> <!-- Título: por ahora solo el nombre; más adelante irá el dibujo de S.I.M.O.N. saludando -->
    <p>Hola, soy S.I.M.O.N. ¿Qué actividad querés realizar?</p> <!-- Pregunta de S.I.M.O.N. -->

    <a class="boton boton-grande" href="seguir_historia.php?t=rutina">Historias de rutinas</a> <!-- Botón grande de las historias de rutinas: lleva a la lista de ese tipo (t=rutina) -->
    <a class="boton boton-grande" href="seguir_historia.php?t=ordenar">Ordenar</a> <!-- Botón grande de ordenar: lleva a la lista de ese tipo (t=ordenar) -->

    <h2 class="grupo-titulo">Opciones de la profesional</h2> <!-- Título que separa las actividades de las opciones de gestión -->
    <ul> <!-- Lista de opciones de la profesional -->
        <li><a class="boton" href="ninos.php">Perfiles de niños y niñas</a></li> <!-- Link a la pantalla donde se agrega un niño o se edita su perfil -->
        <li><a class="boton" href="asignar_historia.php">Asignar historia</a></li> <!-- Link a la pantalla que asigna una historia ya armada a un niño: hay que asignarla antes de poder seguirla -->
        <li><a class="boton" href="consultar_historial.php">Consultar historial</a></li> <!-- Link a la pantalla donde el profesional ve los seguimientos -->
        <li><a class="boton" href="cambiar_contrasena.php">Cambiar contraseña</a></li> <!-- Link a la pantalla donde la profesional cambia su contraseña -->
    </ul> <!-- Cierra la lista -->

    <a class="boton-sec" href="logout.php">Cerrar sesión</a> <!-- Link a la pantalla que cierra la sesión -->
</body> <!-- Cierra el cuerpo -->
</html> <!-- Cierra el documento -->

<?php // Abre PHP. Pantalla a la que se llega solo desde el aviso que recibe el programador
    require __DIR__ . "/conexion.php"; // Trae la conexión a la base: deja lista la variable $conexion
    require __DIR__ . "/aviso.php"; // Trae las funciones de aviso (para armar la dirección del sitio)

    $token = $_GET['t'] ?? $_POST['t'] ?? ""; // La clave de aprobación que vino en el enlace
    $aprobado = false; // Pasa a true cuando se aprueba

    $q = $conexion->prepare("SELECT id_profesional, nombre, email FROM profesional WHERE recuperacion_token = ? AND recuperacion_vence > NOW() AND recuperacion_aprobada = 0"); // Busca una recuperación con esa clave que todavía no venció y no fue aprobada
    $q->execute([hash('sha256', $token)]); // Ejecuta con el hash de la clave (en la base solo se guarda el hash)
    $fila = ($token !== "") ? $q->fetch(PDO::FETCH_ASSOC) : false; // Trae la fila, o false si no hay clave o no coincide

    if ($fila && $_SERVER["REQUEST_METHOD"] == "POST") { // ¿La clave es válida y se apretó el botón de aprobar?
        $consulta = $conexion->prepare("UPDATE profesional SET recuperacion_aprobada = 1 WHERE id_profesional = ?"); // Marca la recuperación como aprobada
        $consulta->execute([$fila['id_profesional']]); // Ejecuta con el id
        $aprobado = true; // Marca que se aprobó
    } // Fin del if
?>
<!DOCTYPE html> <!-- Avisa al navegador que es HTML5 -->
<html lang="es"> <!-- Abre el documento en español -->
<head> <!-- Abre el encabezado -->
    <meta charset="UTF-8"> <!-- Para que tildes y ñ se vean bien -->
    <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- Hace que el celular use su ancho real: sin esta línea, dibuja la página como en una compu y la achica -->
    <title>Aprobar recuperación - S.I.M.O.N.</title> <!-- Texto de la pestaña del navegador -->
    <link rel="stylesheet" href="estilos.css?v=<?php echo filemtime(__DIR__ . "/estilos.css"); // Fecha de la última modificación: si el archivo cambia, el navegador baja la versión nueva ?>"> <!-- Carga la hoja de estilos compartida -->
</head> <!-- Cierra el encabezado -->
<body> <!-- Abre el cuerpo -->
    <h1>Aprobar recuperación de contraseña</h1> <!-- Título -->

    <?php if ($aprobado): // Si se acaba de aprobar ?>
        <p class="success">Aprobado.</p> <!-- Aviso -->
        <p>Avisale a <?php echo htmlspecialchars($fila['nombre']); // Nombre de la profesional, de forma segura ?> que ya puede elegir su contraseña nueva en <?php echo htmlspecialchars(urlBaseSitio() . "/recuperar_contrasena.php"); // Dirección de la pantalla ?> (paso 2). Tiene hasta que venza el pedido, unos 30 minutos desde que lo hizo.</p> <!-- Qué hacer ahora -->

    <?php elseif ($fila): // Si el enlace es válido y todavía no se aprobó ?>
        <p>La profesional <strong><?php echo htmlspecialchars($fila['nombre']); ?></strong> (<?php echo htmlspecialchars($fila['email']); ?>) pidió recuperar su contraseña.</p> <!-- Quién lo pidió -->
        <p>Aprobalo solo si ella te lo avisó por otro medio. Si no fue ella, ignorá este aviso: el pedido vence solo.</p> <!-- Advertencia -->
        <form method="POST" action="aprobar_recuperacion.php"> <!-- Formulario de aprobación: pide confirmación con un botón -->
            <input type="hidden" name="t" value="<?php echo htmlspecialchars($token); // Lleva la clave de aprobación, de forma segura ?>"> <!-- Campo oculto: la clave -->
            <button type="submit">Aprobar recuperación</button> <!-- Botón que aprueba -->
        </form> <!-- Cierra el formulario -->

    <?php else: // Si el enlace no sirve ?>
        <p class="error">Este enlace no es válido, ya se usó o venció.</p> <!-- Aviso -->
    <?php endif; // Fin del if de las pantallas ?>
</body> <!-- Cierra el cuerpo -->
</html> <!-- Cierra el documento -->

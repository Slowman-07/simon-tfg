<?php // Abre PHP
    session_start(); // Retoma la sesión para saber si hay alguien logueado
    if (!isset($_SESSION['id_profesional'])) { // ¿NO hay un profesional logueado? Esta pantalla es solo para el profesional
        header("Location: login.php"); // Si no hay profesional logueado, manda al login
        exit; // Corta acá: no se muestra ni se procesa nada más
    } // Fin del guardia de entrada

    require __DIR__ . "/conexion.php"; // Trae la conexión a la base: deja lista la variable $conexion

    $idProf = (int)$_SESSION['id_profesional']; // Id del profesional que está usando el sistema
    $mensajeError = ""; // Mensaje de error, vacío al principio
    $mensajeOk = isset($_GET['ok']) ? "Contraseña cambiada." : ""; // Aviso de éxito: se muestra cuando la dirección trae ok=1

    if ($_SERVER["REQUEST_METHOD"] == "POST") { // ¿Se envió el formulario?
        $actual = $_POST['actual'] ?? ""; // Lee la contraseña actual
        $nueva = $_POST['nueva'] ?? ""; // Lee la contraseña nueva
        $repetir = $_POST['repetir'] ?? ""; // Lee la repetición de la nueva

        $q = $conexion->prepare("SELECT contrasena FROM profesional WHERE id_profesional = ?"); // Busca el hash de la contraseña guardada
        $q->execute([$idProf]); // Ejecuta con el id
        $fila = $q->fetch(PDO::FETCH_ASSOC); // Trae la fila

        if (!$fila || !password_verify($actual, $fila['contrasena'])) { // ¿La contraseña actual no coincide con la guardada?
            $mensajeError = "La contraseña actual no es correcta."; // Avisa y no cambia nada
        } elseif (strlen($nueva) < 8) { // ¿La nueva tiene menos de 8 caracteres?
            $mensajeError = "La contraseña nueva tiene que tener al menos 8 caracteres."; // Avisa y no cambia nada
        } elseif ($nueva !== $repetir) { // ¿Las dos no son idénticas?
            $mensajeError = "Las contraseñas nuevas no coinciden."; // Avisa y no cambia nada
        } else { // Todo en orden
            $consulta = $conexion->prepare("UPDATE profesional SET contrasena = ? WHERE id_profesional = ?"); // Prepara el cambio
            $consulta->execute([password_hash($nueva, PASSWORD_BCRYPT), $idProf]); // Guarda el hash de la contraseña nueva, nunca la clave en texto
            header("Location: cambiar_contrasena.php?ok=1"); // Redirige con ok=1: así recargar la página no repite el cambio
            exit; // Corta acá, después de redirigir
        } // Fin del if de validaciones
    } // Fin del if de POST
?>
<!DOCTYPE html> <!-- Avisa al navegador que es HTML5 -->
<html lang="es"> <!-- Abre el documento en español -->
<head> <!-- Abre el encabezado -->
    <meta charset="UTF-8"> <!-- Para que tildes y ñ se vean bien -->
    <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- Hace que el celular use su ancho real: sin esta línea, dibuja la página como en una compu y la achica -->
    <title>Cambiar contraseña - S.I.M.O.N.</title> <!-- Texto de la pestaña del navegador -->
    <link rel="stylesheet" href="estilos.css?v=<?php echo filemtime(__DIR__ . "/estilos.css"); // Fecha de la última modificación: si el archivo cambia, el navegador baja la versión nueva ?>"> <!-- Carga la hoja de estilos compartida -->
</head> <!-- Cierra el encabezado -->
<body> <!-- Abre el cuerpo -->
    <h1>Cambiar contraseña</h1> <!-- Título principal -->
    <p><a href="menu.php">Volver al menú</a></p> <!-- Link para volver al menú -->

    <?php if ($mensajeOk): // Si se cambió bien, se muestra el aviso ?>
        <p class="success"><?php echo htmlspecialchars($mensajeOk); ?></p> <!-- class="success" le da el estilo verde -->
    <?php endif; // Fin del if ?>
    <?php if ($mensajeError): // Si hay un mensaje de error, se muestra hasta el endif ?>
        <p class="error"><?php echo htmlspecialchars($mensajeError); ?></p> <!-- class="error" le da el estilo rojo -->
    <?php endif; // Fin del if ?>

    <form method="POST" action="cambiar_contrasena.php"> <!-- Formulario: manda los datos por POST a esta misma página -->
        <label>Contraseña actual:</label> <!-- Etiqueta del campo -->
        <input type="password" name="actual" required> <!-- Contraseña actual -->
        <label>Contraseña nueva (mínimo 8 caracteres):</label> <!-- Etiqueta del campo -->
        <input type="password" name="nueva" required> <!-- Contraseña nueva -->
        <label>Repetir contraseña nueva:</label> <!-- Etiqueta del campo -->
        <input type="password" name="repetir" required> <!-- Repetición de la nueva -->
        <button type="submit">Cambiar contraseña</button> <!-- Botón que envía el formulario -->
    </form> <!-- Cierra el formulario -->

    <script src="ojo.js"></script> <!-- Agrega el ojo que muestra la contraseña -->
</body> <!-- Cierra el cuerpo -->
</html> <!-- Cierra el documento -->

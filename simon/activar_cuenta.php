<?php // Abre PHP
    session_start(); // Abre o retoma la sesión, igual que en el resto de las pantallas
    $email = $_GET['email'] ?? $_POST['email'] ?? ''; // El email llega por la URL (GET) desde login.php; ?? significa "si no existe, usá lo que sigue"; el '' final evita errores si no llega ninguno
    $mensajeError = ""; // Mensaje de error, vacío al principio

    require __DIR__ . "/conexion.php"; // Trae la conexión a la base, definida una sola vez en conexion.php: deja lista la variable $conexion

    if ($_SERVER["REQUEST_METHOD"] == "POST") { // ¿Se envió el formulario de elegir contraseña?
        $nuevaContrasena = $_POST['contrasena']; // Lee la contraseña elegida
        $confirmar = $_POST['confirmar_contrasena']; // Lee la repetición de la contraseña
        $nombreFamilia = trim($_POST['nombre_familia']); // Lee el nombre que la familia eligió para su cuenta; trim() saca los espacios de los costados

        if ($nombreFamilia === "") { // ¿Dejó el nombre vacío?
            $mensajeError = "Escribí el nombre de la familia."; // Avisa; no se guarda nada
        } elseif (strlen($nuevaContrasena) < 8) { // Si no, ¿la contraseña tiene menos de 8 caracteres? strlen cuenta los caracteres
            $mensajeError = "La contraseña tiene que tener al menos 8 caracteres."; // Avisa; no se guarda nada
        } elseif ($nuevaContrasena !== $confirmar) { // Si no, ¿las dos contraseñas NO son idénticas? !== compara valor y tipo
            $mensajeError = "Las contraseñas no coinciden."; // Avisa; no se guarda nada
        } else { // Todo bien: hay nombre y las contraseñas coinciden
            $hash = password_hash($nuevaContrasena, PASSWORD_BCRYPT); // Convierte la clave en un hash bcrypt: lo que se guarda nunca es la clave real
            $consulta = $conexion->prepare("UPDATE familia SET nombre = ?, contrasena = ? WHERE email = ? AND contrasena IS NULL"); // Guarda el nombre elegido y la contraseña de ESA familia, pero solo si todavía no tiene contraseña (IS NULL): impide pisar una cuenta ya activada
            $consulta->execute([$nombreFamilia, $hash, $email]); // Ejecuta: cada hueco recibe un valor, en el mismo orden en que aparecen (nombre, hash y email)

            if ($consulta->rowCount() > 0) { // rowCount() es cuántas filas modificó el UPDATE; más de 0 significa que se activó
                header("Location: login.php"); // Redirige al login para que entre con la clave nueva
                exit; // Corta la ejecución después de redirigir
            } else { // 0 filas: el email no existe o la cuenta ya estaba activa
                $mensajeError = "No se puede activar esta cuenta: el email no existe o ya fue activada."; // Avisa el motivo
            } // Fin del if de rowCount
        } // Fin del if de validaciones
    } // Fin del if de POST
?>
<!DOCTYPE html> <!-- Avisa al navegador que es HTML5 -->
<html lang="es"> <!-- Abre el documento en español -->
<head> <!-- Abre el encabezado -->
    <meta charset="UTF-8"> <!-- Para que tildes y ñ se vean bien -->
    <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- Hace que el celular use su ancho real: sin esta línea, dibuja la página como en una compu y la achica -->
    <title>Activar cuenta - S.I.M.O.N.</title> <!-- Texto de la pestaña del navegador -->
    <link rel="stylesheet" href="estilos.css?v=<?php echo filemtime(__DIR__ . "/estilos.css"); // Fecha de la última modificación: si el archivo cambia, el navegador baja la versión nueva ?>"> <!-- Carga la hoja de estilos compartida -->
</head> <!-- Cierra el encabezado -->
<body> <!-- Abre el cuerpo -->
    <h1>Activar tu cuenta</h1> <!-- Título principal -->
    <p>Elegí el nombre de tu familia y una contraseña para <?php echo htmlspecialchars($email); // Muestra el email de forma segura ?></p> <!-- Le recuerda a la familia qué cuenta está activando -->

    <?php if ($mensajeError): // Si hay un mensaje de error, se muestra lo que sigue hasta el endif ?>
        <p class="error"><?php echo htmlspecialchars($mensajeError); // Imprime el error de forma segura ?></p> <!-- class="error" le da el estilo rojo -->
    <?php endif; // Fin del if ?>

    <form method="POST" action="activar_cuenta.php?email=<?php echo urlencode($email); // Vuelve a poner el email en la URL de destino para no perderlo al reenviar ?>"> <!-- Formulario que se envía a esta misma página, conservando el email en la URL -->
        <label>Nombre de la familia:</label><br> <!-- Etiqueta del campo del nombre -->
        <input type="text" name="nombre_familia" maxlength="50" required><br><br> <!-- Nombre que elige la familia para su cuenta; PHP lo lee en $_POST['nombre_familia']; 50 es el tamaño de la columna nombre -->
        <label>Nueva contraseña (mínimo 8 caracteres):</label><br> <!-- Etiqueta del primer campo de clave -->
        <input type="password" name="contrasena" required><br><br> <!-- Campo de la contraseña nueva; PHP la lee en $_POST['contrasena'] -->
        <label>Confirmar contraseña:</label><br> <!-- Etiqueta del segundo campo -->
        <input type="password" name="confirmar_contrasena" required><br><br> <!-- Campo para repetirla; PHP la lee en $_POST['confirmar_contrasena'] -->
        <button type="submit">Activar cuenta</button> <!-- Botón que envía el formulario -->
    </form> <!-- Cierra el formulario -->
    <script src="ojo.js"></script> <!-- Agrega el ojo que muestra la contraseña mientras se mantiene apretado -->
</body> <!-- Cierra el cuerpo -->
</html> <!-- Cierra el documento -->

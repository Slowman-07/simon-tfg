<?php // Abre PHP. Todo el PHP va ARRIBA del HTML: session_start() tiene que correr antes de enviar cualquier texto al navegador
    session_start(); // Abre (o retoma) la sesión de este visitante; sin esto el servidor "olvida" quién es entre una pantalla y otra
    if (isset($_SESSION['id_profesional']) || isset($_SESSION['id_familia'])) { // ¿Ya hay alguien logueado? || significa "o"
        header("Location: menu.php"); // Si ya entró, no tiene sentido mostrar el login: lo manda al menú
        exit; // Corta acá: sin exit, PHP seguiría ejecutando el resto del archivo después de redirigir
    } // Fin de la verificación de sesión
    $mensajeError = ""; // Guarda el mensaje de error; empieza vacío para que la variable exista aunque nadie haya enviado el formulario
    $mensajeOk = isset($_GET['creada']) ? "Cuenta creada. Ya podés ingresar." : ""; // Si se llegó desde la pantalla de crear cuenta (la dirección trae creada=1), muestra este aviso

    if ($_SERVER["REQUEST_METHOD"] == "POST") { // ¿Se envió el formulario? POST = apretaron Ingresar; GET = solo abrieron la página
        $email = $_POST['email']; // Lee lo que se escribió en el campo cuyo name es "email"
        $contrasenaIngresada = $_POST['contrasena']; // Lee lo que se escribió en el campo cuyo name es "contrasena"

        require __DIR__ . "/conexion.php"; // Trae la conexión a la base, definida una sola vez en conexion.php: deja lista la variable $conexion

        $consulta = $conexion->prepare("SELECT * FROM profesional WHERE email = ?"); // Prepara la consulta; el signo ? es un hueco: el dato del usuario nunca se mezcla con el SQL (evita la inyección SQL)
        $consulta->execute([$email]); // Ejecuta la consulta poniendo $email en el lugar del hueco
        $profesional = $consulta->fetch(PDO::FETCH_ASSOC); // Trae UNA fila como array (columna => valor); si no hay ninguna, devuelve false

        if ($profesional) { // ¿Se encontró un profesional con ese email? (false significa que no)
            if (password_verify($contrasenaIngresada, $profesional['contrasena'])) { // Compara la clave escrita contra el hash guardado; devuelve true si coinciden
                session_regenerate_id(true); // Cambia el identificador de la sesión al entrar: buena práctica de seguridad
                $_SESSION = []; // Vacía lo que hubiera de una sesión anterior
                $_SESSION['id_profesional'] = $profesional['id_profesional']; // Guarda en la sesión el id: las pantallas protegidas preguntan por esta clave
                $_SESSION['nombre'] = $profesional['nombre']; // Guarda el nombre en la sesión
                header("Location: menu.php"); // Entró bien: lo manda al menú
                exit; // Corta acá, después de redirigir
            } else { // El email existe pero la clave no coincide
                $mensajeError = "Email o contraseña incorrectos."; // Mensaje genérico a propósito: no revela si el email existe
            } // Fin del if de password_verify
        } else { // No es profesional: probamos si es familia
            $consulta = $conexion->prepare("SELECT * FROM familia WHERE email = ?"); // Misma consulta preparada, ahora sobre la tabla familia
            $consulta->execute([$email]); // Ejecuta con el email escrito
            $familia = $consulta->fetch(PDO::FETCH_ASSOC); // Trae la fila de la familia, o false si no existe

            if ($familia && $familia['contrasena'] === null) { // ¿Existe la familia y todavía no eligió clave? El === compara valor Y tipo: es la forma correcta de chequear null
                header("Location: activar_cuenta.php?email=" . urlencode($email)); // Redirige a activar_cuenta.php pasando el email por la URL; urlencode protege caracteres como la arroba
                exit; // Corta acá: sin exit, PHP seguiría ejecutando el resto del archivo después de redirigir
            } elseif ($familia && password_verify($contrasenaIngresada, $familia['contrasena'])) { // Si existe y la clave coincide con su hash
                session_regenerate_id(true); // Cambia el identificador de la sesión al entrar
                $_SESSION = []; // Vacía lo que hubiera de una sesión anterior
                $_SESSION['id_familia'] = $familia['id_familia']; // Guarda en la sesión el id de la familia
                $_SESSION['nombre'] = $familia['nombre']; // Guarda el nombre en la sesión
                header("Location: menu.php"); // Entró bien: lo manda al menú
                exit; // Corta acá, después de redirigir
            } else { // No existe ni como profesional ni como familia, o la clave no coincide
                $mensajeError = "Email o contraseña incorrectos."; // El mismo mensaje genérico de antes
            } // Fin del if de familia
        } // Fin del if/else de profesional o familia
    } // Fin del if de POST
?>
<!DOCTYPE html> <!-- Avisa al navegador que es HTML5 -->
<html lang="es"> <!-- Abre el documento en español -->
<head> <!-- Abre el encabezado -->
    <meta charset="UTF-8"> <!-- Para que tildes y ñ se vean bien -->
    <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- Hace que el celular use su ancho real: sin esta línea, dibuja la página como en una compu y la achica -->
    <title>Iniciar sesión - S.I.M.O.N.</title> <!-- Texto de la pestaña del navegador -->
    <link rel="stylesheet" href="estilos.css?v=<?php echo filemtime(__DIR__ . "/estilos.css"); // Fecha de la última modificación: si el archivo cambia, el navegador baja la versión nueva ?>"> <!-- Carga la hoja de estilos estilos.css (si el archivo no existe, la página se ve sin estilo pero funciona) -->
</head> <!-- Cierra el encabezado -->
<body> <!-- Abre el cuerpo -->
    <h1>Iniciar sesión</h1> <!-- Título principal -->

    <?php if ($mensajeOk): // Si hay un aviso de éxito, se muestra hasta el endif ?>
        <p class="success"><?php echo htmlspecialchars($mensajeOk); // Imprime el aviso de forma segura ?></p> <!-- class="success" le da el estilo verde -->
    <?php endif; // Fin del if ?>

    <?php if ($mensajeError): // Si hay un mensaje de error, se muestra lo que sigue hasta el endif ?>
        <p class="error"><?php echo htmlspecialchars($mensajeError); // Imprime el error de forma segura ?></p> <!-- class="error" le da el estilo rojo -->
    <?php endif; // Fin del if ?>

    <form method="POST" action="login.php"> <!-- Formulario: al enviarlo manda los datos por POST a login.php (esta misma página) -->
        <label>Email:</label><br> <!-- Etiqueta del campo; br hace un salto de línea -->
        <input type="email" name="email" required><br><br> <!-- Campo de email; name="email" es la clave que PHP lee en $_POST['email']; required impide enviarlo vacío -->
        <label>Contraseña:</label><br> <!-- Etiqueta del campo de clave -->
        <input type="password" name="contrasena" required><br><br> <!-- Campo de clave: oculta lo que se escribe; name="contrasena" se lee en $_POST['contrasena'] -->
        <button type="submit">Ingresar</button> <!-- Botón que envía el formulario -->
    </form> <!-- Cierra el formulario -->
    <p><a href="recuperar_contrasena.php">Olvidé mi contraseña (profesional)</a></p> <!-- Link a la pantalla donde el profesional elige una contraseña nueva con su código -->
    <p>Si sos una familia y olvidaste tu contraseña, pedile a la profesional que restablezca tu acceso.</p> <!-- Aviso para las familias -->
    <script src="ojo.js"></script> <!-- Agrega el ojo que muestra la contraseña mientras se mantiene apretado -->
</body> <!-- Cierra el cuerpo -->
</html> <!-- Cierra el documento -->

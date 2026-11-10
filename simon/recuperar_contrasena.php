<?php // Abre PHP. Pantalla pública: la usa el profesional que olvidó su contraseña
    session_start(); // Abre la sesión, como en las demás pantallas
    require __DIR__ . "/conexion.php"; // Trae la conexión a la base: deja lista la variable $conexion
    require __DIR__ . "/aviso.php"; // Trae las funciones para avisarle al programador

    $mensajeError = ""; // Mensaje de error, vacío al principio
    $mensajePedido = ""; // Mensaje que se muestra después de pedir la recuperación
    $listo = false; // Pasa a true cuando la contraseña se cambió bien
    $emailEscrito = trim($_POST['email'] ?? ""); // Lo que se escribió en el email, para no hacerlo escribir de nuevo si hay un error

    if ($_SERVER["REQUEST_METHOD"] == "POST") { // ¿Se envió alguno de los dos formularios?
        $accion = $_POST['accion'] ?? ""; // Cuál de los dos: "pedir" (avisar al programador) o "cambiar" (elegir la contraseña nueva)

        if ($accion === "pedir") { // PASO 1: pidió la recuperación
            $q = $conexion->prepare("SELECT id_profesional, nombre, (recuperacion_vence IS NOT NULL AND recuperacion_vence > DATE_ADD(NOW(), INTERVAL 25 MINUTE)) AS reciente FROM profesional WHERE email = ?"); // Busca al profesional y comprueba si ya pidió una recuperación hace menos de 5 minutos
            $q->execute([$emailEscrito]); // Ejecuta con el email
            $fila = $q->fetch(PDO::FETCH_ASSOC); // Trae la fila, o false si no existe

            if ($fila && !$fila['reciente']) { // Si existe y no acaba de pedir otra (evita llenar de avisos al programador)
                $token = bin2hex(random_bytes(16)); // Crea una clave de aprobación al azar, de 32 caracteres
                $consulta = $conexion->prepare("UPDATE profesional SET recuperacion_token = ?, recuperacion_vence = DATE_ADD(NOW(), INTERVAL 30 MINUTE), recuperacion_aprobada = 0 WHERE id_profesional = ?"); // Guarda el hash de la clave, vence en 30 minutos y queda sin aprobar
                $consulta->execute([hash('sha256', $token), $fila['id_profesional']]); // Guarda solo el hash: la clave real viaja únicamente en el aviso
                $enlace = urlBaseSitio() . "/aprobar_recuperacion.php?t=" . $token; // Arma el enlace de aprobación
                avisarProgramador("SIMON - recuperar contrasena", $fila['nombre'] . " pidio recuperar su contrasena. Toca para revisar y aprobar. Vence en 30 minutos.", $enlace); // Le manda el aviso al celular del programador
            } // Fin del if
            $mensajePedido = "Listo. Si el email corresponde a una cuenta, ya le avisamos al programador. Cuando te confirme, completá el segundo paso de más abajo. Si pasan unos minutos y no te dicen nada, escribile."; // Mismo mensaje exista o no el email: no revela si la cuenta existe
        } elseif ($accion === "cambiar") { // PASO 2: ya le aprobaron y elige la contraseña nueva
            $nueva = $_POST['nueva'] ?? ""; // Lee la contraseña nueva
            $repetir = $_POST['repetir'] ?? ""; // Lee la repetición

            if ($emailEscrito === "" || $nueva === "") { // ¿Falta algún dato?
                $mensajeError = "Completá el email y la contraseña nueva."; // Avisa y no cambia nada
            } elseif (strlen($nueva) < 8) { // ¿La contraseña nueva tiene menos de 8 caracteres?
                $mensajeError = "La contraseña nueva tiene que tener al menos 8 caracteres."; // Avisa y no cambia nada
            } elseif ($nueva !== $repetir) { // ¿Las dos no son idénticas?
                $mensajeError = "Las contraseñas no coinciden."; // Avisa y no cambia nada
            } else { // Los datos están completos: se comprueba que esté aprobada
                $q = $conexion->prepare("SELECT id_profesional, (recuperacion_aprobada = 1 AND recuperacion_vence > NOW()) AS aprobada FROM profesional WHERE email = ?"); // Busca al profesional y comprueba si su recuperación está aprobada y vigente
                $q->execute([$emailEscrito]); // Ejecuta con el email
                $fila = $q->fetch(PDO::FETCH_ASSOC); // Trae la fila, o false si no existe

                if (!$fila || !$fila['aprobada']) { // ¿No existe, o no está aprobada, o ya venció?
                    $mensajeError = "Esa recuperación todavía no fue aprobada o ya venció. Pedila de nuevo en el paso 1."; // Mismo mensaje en todos los casos
                } else { // Está aprobada
                    $consulta = $conexion->prepare("UPDATE profesional SET contrasena = ?, recuperacion_token = NULL, recuperacion_vence = NULL, recuperacion_aprobada = 0 WHERE id_profesional = ?"); // Cambia la contraseña y borra la recuperación para que no se pueda reutilizar
                    $consulta->execute([password_hash($nueva, PASSWORD_BCRYPT), $fila['id_profesional']]); // Guarda el hash de la contraseña nueva
                    $listo = true; // Marca que todo salió bien
                } // Fin del if
            } // Fin del if de validaciones
        } // Fin del if de acciones
    } // Fin del if de POST
?>
<!DOCTYPE html> <!-- Avisa al navegador que es HTML5 -->
<html lang="es"> <!-- Abre el documento en español -->
<head> <!-- Abre el encabezado -->
    <meta charset="UTF-8"> <!-- Para que tildes y ñ se vean bien -->
    <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- Hace que el celular use su ancho real: sin esta línea, dibuja la página como en una compu y la achica -->
    <title>Recuperar contraseña - S.I.M.O.N.</title> <!-- Texto de la pestaña del navegador -->
    <link rel="stylesheet" href="estilos.css?v=<?php echo filemtime(__DIR__ . "/estilos.css"); // Fecha de la última modificación: si el archivo cambia, el navegador baja la versión nueva ?>"> <!-- Carga la hoja de estilos compartida -->
</head> <!-- Cierra el encabezado -->
<body> <!-- Abre el cuerpo -->

<?php if ($listo): // PANTALLA FINAL: la contraseña se cambió ?>
    <h1>Contraseña cambiada</h1> <!-- Título -->
    <p class="success">Ya podés ingresar con tu contraseña nueva.</p> <!-- Aviso -->
    <p><a href="login.php">Ir a iniciar sesión</a></p> <!-- Link al login -->

<?php else: // PANTALLA NORMAL: los dos pasos ?>
    <h1>Recuperar contraseña</h1> <!-- Título -->
    <p><a href="login.php">Volver a iniciar sesión</a></p> <!-- Link para volver -->

    <?php if ($mensajeError): // Si hay un mensaje de error, se muestra hasta el endif ?>
        <p class="error"><?php echo htmlspecialchars($mensajeError); ?></p> <!-- class="error" le da el estilo rojo -->
    <?php endif; // Fin del if ?>

    <h2>Paso 1: pedir la recuperación</h2> <!-- Primera sección -->
    <p>Escribí tu email. Se le avisa al programador, que te va a confirmar por el medio que usan habitualmente.</p> <!-- Explicación -->
    <?php if ($mensajePedido): // Si ya se pidió, se muestra la respuesta ?>
        <p class="success"><?php echo htmlspecialchars($mensajePedido); ?></p> <!-- class="success" le da el estilo verde -->
    <?php endif; // Fin del if ?>
    <form method="POST" action="recuperar_contrasena.php"> <!-- Formulario del paso 1 -->
        <input type="hidden" name="accion" value="pedir"> <!-- Campo oculto: dice que se quiere pedir la recuperación -->
        <label>Email:</label> <!-- Etiqueta del campo -->
        <input type="email" name="email" value="<?php echo htmlspecialchars($emailEscrito); // Repone lo escrito, de forma segura ?>" required> <!-- Email del profesional -->
        <button type="submit">Avisar al programador</button> <!-- Botón que envía el pedido -->
    </form> <!-- Cierra el formulario -->

    <h2>Paso 2: elegir la contraseña nueva</h2> <!-- Segunda sección -->
    <p>Hacelo cuando el programador te confirme que aprobó el pedido.</p> <!-- Explicación -->
    <form method="POST" action="recuperar_contrasena.php"> <!-- Formulario del paso 2 -->
        <input type="hidden" name="accion" value="cambiar"> <!-- Campo oculto: dice que se quiere elegir la contraseña nueva -->
        <label>Email:</label> <!-- Etiqueta del campo -->
        <input type="email" name="email" value="<?php echo htmlspecialchars($emailEscrito); ?>" required> <!-- Email del profesional -->
        <label>Contraseña nueva (mínimo 8 caracteres):</label> <!-- Etiqueta del campo -->
        <input type="password" name="nueva" required> <!-- Contraseña nueva -->
        <label>Repetir contraseña nueva:</label> <!-- Etiqueta del campo -->
        <input type="password" name="repetir" required> <!-- Repetición -->
        <button type="submit">Cambiar contraseña</button> <!-- Botón que envía el formulario -->
    </form> <!-- Cierra el formulario -->
<?php endif; // Fin del if de las pantallas ?>

    <script src="ojo.js"></script> <!-- Agrega el ojo que muestra la contraseña -->
</body> <!-- Cierra el cuerpo -->
</html> <!-- Cierra el documento -->

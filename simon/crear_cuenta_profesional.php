<?php // Abre PHP. Pantalla de configuración inicial: el profesional crea su cuenta la primera vez que se usa el sistema
    require __DIR__ . "/conexion.php"; // Trae la conexión a la base, definida una sola vez en conexion.php: deja lista la variable $conexion

    $yaExiste = ((int)$conexion->query("SELECT COUNT(*) FROM profesional")->fetchColumn()) > 0; // true si ya hay un profesional cargado: en ese caso esta pantalla queda bloqueada
    $mensajeError = ""; // Mensaje de error, vacío al principio
    $valores = ["nombre" => "", "apellido" => "", "email" => "", "especialidad" => ""]; // Lo que se escribió, para no hacer volver a tipear todo si hay un error

    if (!$yaExiste && $_SERVER["REQUEST_METHOD"] == "POST") { // ¿Se envió el formulario y todavía no hay profesional?
        $valores["nombre"] = trim($_POST['nombre'] ?? ""); // Lee el nombre; trim() saca los espacios del principio y del final
        $valores["apellido"] = trim($_POST['apellido'] ?? ""); // Lee el apellido
        $valores["email"] = trim($_POST['email'] ?? ""); // Lee el email
        $valores["especialidad"] = trim($_POST['especialidad'] ?? ""); // Lee la especialidad (opcional)
        $contrasena = $_POST['contrasena'] ?? ""; // Lee la contraseña elegida
        $confirmar = $_POST['confirmar_contrasena'] ?? ""; // Lee la repetición de la contraseña

        if ($valores["nombre"] === "" || $valores["apellido"] === "" || $valores["email"] === "" || $contrasena === "") { // ¿Falta algún dato obligatorio? || significa "o"
            $mensajeError = "Completá nombre, apellido, email y contraseña."; // Avisa y no guarda nada
        } elseif (!filter_var($valores["email"], FILTER_VALIDATE_EMAIL)) { // ¿El email no tiene un formato válido? filter_var lo comprueba
            $mensajeError = "El email no es válido."; // Avisa y no guarda nada
        } elseif (strlen($contrasena) < 8) { // ¿La contraseña tiene menos de 8 caracteres? strlen cuenta los caracteres
            $mensajeError = "La contraseña tiene que tener al menos 8 caracteres."; // Avisa y no guarda nada
        } elseif ($contrasena !== $confirmar) { // ¿Las dos contraseñas NO son idénticas?
            $mensajeError = "Las contraseñas no coinciden."; // Avisa y no guarda nada
        } else { // Todo en orden: se crea la cuenta
            $hash = password_hash($contrasena, PASSWORD_BCRYPT); // Convierte la clave en un hash bcrypt: lo que se guarda nunca es la clave real
            $especialidadGuardar = ($valores["especialidad"] === "") ? null : $valores["especialidad"]; // Si no escribió especialidad, guarda vacío
            $consulta = $conexion->prepare("INSERT INTO profesional (nombre, apellido, email, contrasena, especialidad) VALUES (?, ?, ?, ?, ?)"); // Prepara el alta con cinco huecos
            $consulta->execute([$valores["nombre"], $valores["apellido"], $valores["email"], $hash, $especialidadGuardar]); // Ejecuta poniendo cada valor en su hueco, en ese orden
            header("Location: login.php?creada=1"); // Manda al login avisando que la cuenta se creó
            exit; // Corta acá, después de redirigir
        } // Fin del if de validaciones
    } // Fin del if de POST
?>
<!DOCTYPE html> <!-- Avisa al navegador que es HTML5 -->
<html lang="es"> <!-- Abre el documento en español -->
<head> <!-- Abre el encabezado -->
    <meta charset="UTF-8"> <!-- Para que tildes y ñ se vean bien -->
    <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- Hace que el celular use su ancho real: sin esta línea, dibuja la página como en una compu y la achica -->
    <title>Crear cuenta de profesional - S.I.M.O.N.</title> <!-- Texto de la pestaña del navegador -->
    <link rel="stylesheet" href="estilos.css?v=<?php echo filemtime(__DIR__ . "/estilos.css"); // Fecha de la última modificación: si el archivo cambia, el navegador baja la versión nueva ?>"> <!-- Carga la hoja de estilos compartida -->
</head> <!-- Cierra el encabezado -->
<body> <!-- Abre el cuerpo -->

<?php if ($yaExiste): // Si ya hay un profesional, la pantalla queda bloqueada ?>
    <h1>Cuenta ya creada</h1> <!-- Título -->
    <p>Este sistema ya tiene una cuenta de profesional.</p> <!-- Aviso: no se puede crear otra -->
    <p><a href="login.php">Ir a iniciar sesión</a></p> <!-- Link al login -->

<?php else: // Si todavía no hay profesional, se muestra el formulario ?>
    <h1>Crear cuenta de profesional</h1> <!-- Título -->
    <p>Es la primera vez que se usa el sistema. Creá tu cuenta para empezar. Esta pantalla se bloquea apenas la cuenta queda creada.</p> <!-- Explica por qué aparece esta pantalla -->

    <?php if ($mensajeError): // Si hay un mensaje de error, se muestra hasta el endif ?>
        <p class="error"><?php echo htmlspecialchars($mensajeError); // Imprime el error de forma segura ?></p> <!-- class="error" le da el estilo rojo -->
    <?php endif; // Fin del if ?>

    <form method="POST" action="crear_cuenta_profesional.php"> <!-- Formulario: al enviarlo manda los datos por POST a esta misma página -->
        <label>Nombre:</label> <!-- Etiqueta del campo -->
        <input type="text" name="nombre" maxlength="50" value="<?php echo htmlspecialchars($valores['nombre']); // Repone lo escrito, de forma segura ?>" required> <!-- Nombre, hasta 50 caracteres, obligatorio -->
        <label>Apellido:</label> <!-- Etiqueta del campo -->
        <input type="text" name="apellido" maxlength="50" value="<?php echo htmlspecialchars($valores['apellido']); ?>" required> <!-- Apellido, hasta 50 caracteres, obligatorio -->
        <label>Email (es tu usuario para ingresar):</label> <!-- Etiqueta del campo -->
        <input type="email" name="email" maxlength="100" value="<?php echo htmlspecialchars($valores['email']); ?>" required> <!-- Email, hasta 100 caracteres, obligatorio -->
        <label>Especialidad (opcional):</label> <!-- Etiqueta del campo -->
        <input type="text" name="especialidad" maxlength="100" value="<?php echo htmlspecialchars($valores['especialidad']); ?>"> <!-- Texto libre, por ejemplo Fonoaudiología -->
        <label>Contraseña (mínimo 8 caracteres):</label> <!-- Etiqueta del campo -->
        <input type="password" name="contrasena" required> <!-- Campo de clave: oculta lo que se escribe -->
        <label>Repetir contraseña:</label> <!-- Etiqueta del campo -->
        <input type="password" name="confirmar_contrasena" required> <!-- Campo para repetir la clave -->
        <button type="submit">Crear cuenta</button> <!-- Botón que envía el formulario -->
    </form> <!-- Cierra el formulario -->
<?php endif; // Fin del if de las pantallas ?>

    <script src="ojo.js"></script> <!-- Agrega el ojo que muestra la contraseña mientras se mantiene apretado -->
</body> <!-- Cierra el cuerpo -->
</html> <!-- Cierra el documento -->

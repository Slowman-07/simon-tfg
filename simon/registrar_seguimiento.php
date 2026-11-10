<?php // Abre PHP
    session_start(); // Retoma la sesión para saber quién está logueado
    $esProfesional = isset($_SESSION['id_profesional']); // true si hay un profesional logueado
    $esFamilia = isset($_SESSION['id_familia']); // true si hay una familia logueada
    if (!$esProfesional && !$esFamilia) { // ¿No hay nadie logueado?
        header("Location: login.php"); // Si no hay nadie, manda al login
        exit; // Corta acá: no se muestra nada más
    } // Fin de la verificación de sesión
    if (!$esFamilia) { // ¿Quien entra no es una familia? El seguimiento lo registra solo la familia: la profesional ve lo que cargan en "Consultar historial"
        header("Location: menu.php"); // Se la manda al menú
        exit; // Corta acá: no se muestra nada más
    } // Fin del control de familia

    require __DIR__ . "/conexion.php"; // Trae la conexión a la base, definida una sola vez en conexion.php: deja lista la variable $conexion

    $idHistoria = (int)($_GET['h'] ?? $_POST['h'] ?? 0); // Historia sobre la que se registra (llega en la dirección o en el formulario); (int) la convierte en número
    $mensajeError = ""; // Mensaje de error, vacío al principio
    $guardadoOk = false; // Bandera: pasa a true cuando el seguimiento se guardó bien

    $q = $conexion->prepare("SELECT h.id_historia, h.id_infante, h.titulo, i.nombre, i.apellido, i.id_familia FROM historias h JOIN infantes i ON i.id_infante = h.id_infante WHERE h.id_historia = ?"); // Busca la historia asignada junto con los datos del niño
    $q->execute([$idHistoria]); // Ejecuta con el id de la historia
    $historia = $q->fetch(PDO::FETCH_ASSOC); // Trae la fila, o false si no existe o no está asignada a ningún niño
    $permitido = $historia && $historia['id_familia'] == $_SESSION['id_familia']; // true si la historia existe y es de un niño o niña de la familia que entra

    $guardadoOk = ($permitido && isset($_GET['ok'])); // true si se llegó acá después de guardar (la dirección trae ok=1)

    if ($permitido && $_SERVER["REQUEST_METHOD"] == "POST") { // ¿Se envió el formulario y se tiene permiso?
        $estado = $_POST['estado'] ?? ""; // Lee si la completó (si, parcial o no)
        $comentario = trim($_POST['comentario'] ?? ""); // Lee el comentario libre; trim() saca los espacios de los costados

        $estado = in_array($estado, ["si", "parcial", "no"], true) ? $estado : "sin_indicar"; // Si no marcó ninguna opción, se guarda como "sin indicar": no es obligatorio
        $comentarioGuardar = ($comentario === "") ? null : $comentario; // Texto o vacío

        $consulta = $conexion->prepare("INSERT INTO historial_progreso (id_infante, id_historia, fecha_ejecucion, estado, comentario) VALUES (?, ?, NOW(), ?, ?)"); // Prepara el alta del seguimiento: cuatro huecos (apoyo, tiempo y regulación ya no se piden: esas columnas quedan vacías)
        $consulta->execute([$historia['id_infante'], $historia['id_historia'], $estado, $comentarioGuardar]); // Ejecuta poniendo cada valor en su hueco, en el mismo orden que las columnas
        header("Location: registrar_seguimiento.php?h=" . $idHistoria . "&ok=1"); // Redirige a la misma pantalla con ok=1: así recargar la página no vuelve a guardar el registro
        exit; // Corta acá, después de redirigir
    } // Fin del if de POST
?>
<!DOCTYPE html> <!-- Avisa al navegador que es HTML5 -->
<html lang="es"> <!-- Abre el documento en español -->
<head> <!-- Abre el encabezado -->
    <meta charset="UTF-8"> <!-- Para que tildes y ñ se vean bien -->
    <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- Hace que el celular use su ancho real: sin esta línea, dibuja la página como en una compu y la achica -->
    <title>Registrar seguimiento - S.I.M.O.N.</title> <!-- Texto de la pestaña del navegador -->
    <link rel="stylesheet" href="estilos.css?v=<?php echo filemtime(__DIR__ . "/estilos.css"); // Fecha de la última modificación: si el archivo cambia, el navegador baja la versión nueva ?>"> <!-- Carga la hoja de estilos compartida -->
</head> <!-- Cierra el encabezado -->
<body> <!-- Abre el cuerpo -->

<?php if (!$permitido): // Si no existe la historia o no se tiene permiso ?>
    <h1>Seguimiento no disponible</h1> <!-- Título -->
    <p>No se encontró esa historia.</p> <!-- Aviso -->
    <p><a href="seguir_historia.php">Volver a la lista</a></p> <!-- Link para volver -->

<?php elseif ($guardadoOk): // Si se guardó bien ?>
    <h1>Seguimiento registrado</h1> <!-- Título -->
    <p class="success">Quedó guardado el seguimiento de "<?php echo htmlspecialchars($historia['titulo']); // Muestra el título de forma segura ?>".</p> <!-- Confirmación en verde -->
    <p><a href="seguir_historia.php">Volver a las historias</a></p> <!-- Link a la lista -->

<?php else: // Pantalla normal: el formulario ?>
    <h1>Registrar seguimiento</h1> <!-- Título -->
    <p><?php echo htmlspecialchars($historia['apellido'] . ", " . $historia['nombre']); // Muestra "Apellido, Nombre" de forma segura ?>: <strong><?php echo htmlspecialchars($historia['titulo']); // Título de la historia ?></strong></p> <!-- Aclara de quién y de qué historia es el registro -->

    <?php if ($mensajeError): // Si hay un mensaje de error, se muestra hasta el endif ?>
        <p class="error"><?php echo htmlspecialchars($mensajeError); // Imprime el error de forma segura ?></p> <!-- class="error" le da el estilo rojo -->
    <?php endif; // Fin del if ?>

    <form method="POST" action="registrar_seguimiento.php"> <!-- Formulario: al enviarlo manda los datos por POST a esta misma página -->
        <input type="hidden" name="h" value="<?php echo (int)$idHistoria; ?>"> <!-- Campo oculto: lleva el id de la historia para que no se pierda al enviar -->

        <p>Todo es opcional: podés completar solo lo que quieras, o guardar sin escribir nada.</p> <!-- Aviso: no hay campos obligatorios -->

        <label>¿Completó la rutina?</label> <!-- Etiqueta del grupo -->
        <label><input type="radio" name="estado" value="si"> Sí</label> <!-- Opción: la completó -->
        <label><input type="radio" name="estado" value="parcial"> Parcialmente</label> <!-- Opción: la completó en parte -->
        <label><input type="radio" name="estado" value="no"> No</label> <!-- Opción: no la completó -->

        <label>¿Querés contarle algo a la profesional? (opcional)</label> <!-- Etiqueta del campo -->
        <textarea name="comentario" rows="3" style="width: 100%;"></textarea> <!-- Texto libre de varias líneas -->

        <button type="submit">Guardar</button> <!-- Botón que envía el formulario -->
    </form> <!-- Cierra el formulario -->
    <p><a href="seguir_historia.php">Cancelar y volver a las historias</a></p> <!-- Link para salir sin guardar -->
<?php endif; // Fin del if de las pantallas ?>

</body> <!-- Cierra el cuerpo -->
</html> <!-- Cierra el documento -->

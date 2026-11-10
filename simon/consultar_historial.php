<?php // Abre PHP
    session_start(); // Retoma la sesión para saber si hay alguien logueado
    if (!isset($_SESSION['id_profesional'])) { // ¿NO hay un profesional logueado? Esta pantalla es solo para el profesional
        header("Location: login.php"); // Si no hay profesional logueado, manda al login
        exit; // Corta acá: no se muestra ni se procesa nada más
    } // Fin del guardia de entrada

    require __DIR__ . "/conexion.php"; // Trae la conexión a la base, definida una sola vez en conexion.php: deja lista la variable $conexion

    if ($_SERVER["REQUEST_METHOD"] == "POST") { // ¿Se apretó el botón Borrar de algún registro?
        $idHistorial = (int)($_POST['id_historial'] ?? 0); // Lee el id del registro a borrar, como número entero
        if ($idHistorial > 0) { // ¿Es un id válido?
            $consulta = $conexion->prepare("DELETE FROM historial_progreso WHERE id_historial = ?"); // Prepara el borrado de ese registro
            $consulta->execute([$idHistorial]); // Ejecuta con el id
        } // Fin del if
        $volver = "consultar_historial.php?b=1"; // Dirección a la que se vuelve; b=1 avisa que se borró algo
        if (!empty($_POST['n'])) { // Si se estaba filtrando por un niño, se conserva el filtro
            $volver .= "&n=" . (int)$_POST['n']; // Agrega el niño a la dirección
        } // Fin del if
        header("Location: " . $volver); // Redirige: así recargar la página no repite el borrado
        exit; // Corta acá, después de redirigir
    } // Fin del if de POST

    $mensajeOk = isset($_GET['b']) ? "Registro borrado." : ""; // Mensaje que se muestra después de borrar
    $idNino = (int)($_GET['n'] ?? 0); // Niño por el que se filtra (n= en la dirección); 0 = todos

    $etiquetaEstado = ["si" => "Sí", "parcial" => "Parcialmente", "no" => "No", "sin_indicar" => "Sin indicar"]; // Cómo se muestra cada estado guardado

    $ninos = $conexion->query("SELECT id_infante, nombre, apellido FROM infantes ORDER BY apellido, nombre")->fetchAll(PDO::FETCH_ASSOC); // Lista de niños para el filtro

    $sql = "SELECT r.id_historial, r.fecha_ejecucion, r.estado, r.apoyo_necesitado, r.tiempo_minutos, r.autorregulacion, r.comentario, i.nombre, i.apellido, h.titulo FROM historial_progreso r JOIN infantes i ON i.id_infante = r.id_infante JOIN historias h ON h.id_historia = r.id_historia"; // Registros con el nombre del niño y el título de la historia
    $parametros = []; // Valores para los huecos de la consulta; empieza vacío
    if ($idNino > 0) { // Si se eligió un niño en el filtro
        $sql .= " WHERE r.id_infante = ?"; // Agrega el filtro por niño
        $parametros[] = $idNino; // Agrega el id del niño a los valores
    } // Fin del if
    $sql .= " ORDER BY r.fecha_ejecucion DESC, r.id_historial DESC"; // Ordena del más nuevo al más viejo
    $consulta = $conexion->prepare($sql); // Prepara la consulta completa
    $consulta->execute($parametros); // Ejecuta con los valores
    $registros = $consulta->fetchAll(PDO::FETCH_ASSOC); // Trae todas las filas
?>
<!DOCTYPE html> <!-- Avisa al navegador que es HTML5 -->
<html lang="es"> <!-- Abre el documento en español -->
<head> <!-- Abre el encabezado -->
    <meta charset="UTF-8"> <!-- Para que tildes y ñ se vean bien -->
    <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- Hace que el celular use su ancho real: sin esta línea, dibuja la página como en una compu y la achica -->
    <title>Historial - S.I.M.O.N.</title> <!-- Texto de la pestaña del navegador -->
    <link rel="stylesheet" href="estilos.css?v=<?php echo filemtime(__DIR__ . "/estilos.css"); // Fecha de la última modificación: si el archivo cambia, el navegador baja la versión nueva ?>"> <!-- Carga la hoja de estilos compartida -->
</head> <!-- Cierra el encabezado -->
<body> <!-- Abre el cuerpo -->
    <h1>Historial de seguimiento</h1> <!-- Título principal -->
    <p><a href="menu.php">Volver al menú</a></p> <!-- Link para volver al menú -->

    <?php if ($mensajeOk): // Si hay mensaje de éxito, se muestra hasta el endif ?>
        <p class="success"><?php echo htmlspecialchars($mensajeOk); // Imprime el mensaje de forma segura ?></p> <!-- class="success" le da el estilo verde -->
    <?php endif; // Fin del if ?>

    <form method="GET" action="consultar_historial.php"> <!-- Formulario del filtro: se envía por GET, así el niño queda en la dirección -->
        <label>Ver el historial de:</label> <!-- Etiqueta de la lista -->
        <select name="n" onchange="this.form.submit()"> <!-- Lista desplegable; al cambiarla se envía sola (JavaScript) -->
            <option value="0">Todos los niños y niñas</option> <!-- Sin filtro -->
            <?php foreach ($ninos as $n): // Repite el bloque siguiente por cada niño ?>
                <option value="<?php echo (int)$n['id_infante']; ?>"<?php echo ($n['id_infante'] == $idNino) ? " selected" : ""; // Deja marcado el niño que se está filtrando ?>><?php echo htmlspecialchars($n['apellido'] . ", " . $n['nombre']); // Muestra "Apellido, Nombre" de forma segura ?></option> <!-- Una opción por niño -->
            <?php endforeach; // Fin del foreach de niños ?>
        </select> <!-- Cierra la lista -->
    </form> <!-- Cierra el formulario -->

    <?php if (count($registros) === 0): // Si no hay ningún registro para mostrar ?>
        <p>Todavía no hay seguimientos registrados.</p> <!-- Aviso -->
    <?php endif; // Fin del if ?>

    <?php foreach ($registros as $r): // Repite el bloque siguiente por cada registro ?>
        <div style="background: white; padding: 12px; margin: 12px 0; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.1);"> <!-- Tarjeta de un registro -->
            <strong><?php echo htmlspecialchars($r['apellido'] . ", " . $r['nombre']); // Niño ?></strong> — <?php echo htmlspecialchars($r['titulo']); // Historia ?><br> <!-- Primera línea: niño y historia -->
            <?php echo date("d/m/Y H:i", strtotime($r['fecha_ejecucion'])); // Fecha y hora con formato día/mes/año ?><br> <!-- Fecha y hora -->
            Completó: <?php echo htmlspecialchars($etiquetaEstado[$r['estado']] ?? $r['estado']); // Estado con su etiqueta; si no la conoce, muestra el valor tal cual ?><br> <!-- Estado -->
            <?php if (!empty($r['apoyo_necesitado'])): // Solo si se cargó el apoyo ?>
                Apoyo: <?php echo htmlspecialchars($r['apoyo_necesitado']); ?><br> <!-- Apoyo necesitado -->
            <?php endif; // Fin del if ?>
            <?php if ($r['tiempo_minutos'] !== null): // Solo si se cargó el tiempo ?>
                Tiempo: <?php echo (int)$r['tiempo_minutos']; ?> min<br> <!-- Minutos -->
            <?php endif; // Fin del if ?>
            <?php if (!empty($r['autorregulacion'])): // Solo si se cargó la observación ?>
                Regulación: <?php echo nl2br(htmlspecialchars($r['autorregulacion'])); // nl2br respeta los saltos de línea ?><br> <!-- Observación de regulación -->
            <?php endif; // Fin del if ?>
            <?php if (!empty($r['comentario'])): // Solo si se cargó un comentario ?>
                Comentario: <?php echo nl2br(htmlspecialchars($r['comentario'])); ?><br> <!-- Comentario libre -->
            <?php endif; // Fin del if ?>
            <form method="POST" action="consultar_historial.php" onsubmit="return confirm('¿Borrar este registro? No se puede deshacer.');"> <!-- Formulario de borrado; confirm() pregunta antes de enviar y, si se cancela, no se envía -->
                <input type="hidden" name="id_historial" value="<?php echo (int)$r['id_historial']; ?>"> <!-- Campo oculto: id del registro a borrar -->
                <input type="hidden" name="n" value="<?php echo (int)$idNino; ?>"> <!-- Campo oculto: conserva el filtro -->
                <button type="submit">Borrar</button> <!-- Botón de borrar -->
            </form> <!-- Cierra el formulario -->
        </div> <!-- Cierra la tarjeta -->
    <?php endforeach; // Fin del foreach de registros ?>
</body> <!-- Cierra el cuerpo -->
</html> <!-- Cierra el documento -->

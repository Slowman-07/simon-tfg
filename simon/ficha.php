<?php // Abre PHP
    session_start(); // Retoma la sesión para saber si hay alguien logueado
    if (!isset($_SESSION['id_profesional'])) { // ¿NO hay un profesional logueado? La ficha es solo para la profesional: la familia no puede entrar ni saber que existe
        http_response_code(404); // Responde como si la página no existiera: así nadie puede comprobar, ni siquiera escribiendo la dirección, que la ficha existe
        echo "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL was not found on this server.</p></body></html>"; // Mensaje neutro, igual al de cualquier página que no existe
        exit; // Corta acá: no se muestra ni se procesa nada más
    } // Fin del guardia de entrada

    require __DIR__ . "/conexion.php"; // Trae la conexión a la base, definida una sola vez en conexion.php: deja lista la variable $conexion
    date_default_timezone_set("America/Argentina/Buenos_Aires"); // Hora de Argentina para "ahora" y para las fechas de carga y edición: el hosting trabaja con otra hora

    $idNino = (int)($_GET['n'] ?? $_POST['n'] ?? 0); // Niño o niña de la ficha (llega en la dirección o en el formulario); (int) lo convierte en número
    $mensajeError = ""; // Mensaje de error, vacío al principio
    $mensajeOk = ["1" => "Nota guardada.", "2" => "Cambios guardados.", "3" => "Nota borrada."][(string)($_GET['ok'] ?? "")] ?? ""; // Aviso de éxito según el número que trae la dirección (ok=1, 2 o 3)

    $q = $conexion->prepare("SELECT id_infante, nombre, apellido FROM infantes WHERE id_infante = ?"); // Busca al niño o niña
    $q->execute([$idNino]); // Ejecuta con el id
    $nino = $q->fetch(PDO::FETCH_ASSOC); // Trae la fila, o false si no existe

    $historias = []; // Historias que tiene asignadas este niño o niña: id de la biblioteca => título
    if ($nino) { // Si el niño existe
        $q = $conexion->prepare("SELECT DISTINCT id_historia_origen, titulo FROM historias WHERE id_infante = ? AND id_historia_origen IS NOT NULL ORDER BY id_historia_origen"); // Busca sus historias asignadas, con el id de la historia de la biblioteca de la que salió cada una
        $q->execute([$idNino]); // Ejecuta con el id
        $historias = $q->fetchAll(PDO::FETCH_KEY_PAIR); // Arma la lista id => título
    } // Fin del if

    if ($nino && $_SERVER["REQUEST_METHOD"] == "POST") { // ¿Se envió un formulario de una ficha que existe?
        $accion = $_POST['accion'] ?? ""; // Qué se quiere hacer: "guardar" o "borrar"

        if ($accion === "borrar") { // Si se apretó "Borrar"
            $consulta = $conexion->prepare("DELETE FROM ficha_nota WHERE id_nota = ? AND id_infante = ?"); // Borra la nota, y solo si es de este niño
            $consulta->execute([(int)($_POST['id_nota'] ?? 0), $idNino]); // Ejecuta con el id de la nota y el del niño
            header("Location: ver_ficha.php?n=" . $idNino . "&ok=3"); // Va a la hoja de la ficha con el aviso de nota borrada
            exit; // Corta acá, después de redirigir
        } elseif ($accion === "guardar") { // Si se apretó "Guardar"
            $idNota = (int)($_POST['id_nota'] ?? 0); // Id de la nota; 0 si es una nota nueva
            $fechaTexto = trim($_POST['fecha_sesion'] ?? ""); // Fecha y hora de la sesión, como las escribió
            $texto = trim($_POST['nota'] ?? ""); // Anotación; trim() saca los espacios de los costados
            $idHist = (int)($_POST['id_historia'] ?? 0); // Historia trabajada; 0 si no eligió ninguna
            $fecha = DateTime::createFromFormat('Y-m-d\TH:i', $fechaTexto); // Intenta leer la fecha con el formato del campo de fecha y hora

            if (!$fecha || $fecha->format('Y-m-d\TH:i') !== $fechaTexto) { // ¿La fecha no se pudo leer o no es una fecha real?
                $mensajeError = "La fecha y la hora no son válidas."; // Avisa y no guarda nada
            } elseif ($texto === "") { // ¿La anotación está vacía?
                $mensajeError = "Escribí la anotación."; // Avisa y no guarda nada
            } else { // Todo en orden: se guarda
                $fechaSesion = $fecha->format('Y-m-d H:i:00'); // Fecha de la sesión en el formato de la base
                $historiaGuardar = isset($historias[$idHist]) ? $idHist : null; // Solo acepta una historia que este niño tenga asignada; si no, guarda vacío
                $ahora = date('Y-m-d H:i:s'); // Momento actual, hora de Argentina

                if ($idNota > 0) { // Si es una nota que ya existía, se edita
                    $consulta = $conexion->prepare("UPDATE ficha_nota SET fecha_sesion = ?, id_historia = ?, nota = ?, fecha_edicion = ? WHERE id_nota = ? AND id_infante = ?"); // Prepara el cambio, solo si la nota es de este niño
                    $consulta->execute([$fechaSesion, $historiaGuardar, $texto, $ahora, $idNota, $idNino]); // Guarda los cambios y anota cuándo se editó
                    header("Location: ver_ficha.php?n=" . $idNino . "&ok=2"); // Va a la hoja de la ficha con el aviso de cambios guardados
                } else { // Si es una nota nueva
                    $consulta = $conexion->prepare("INSERT INTO ficha_nota (id_infante, id_historia, fecha_sesion, nota, fecha_carga) VALUES (?, ?, ?, ?, ?)"); // Prepara el alta con cinco huecos
                    $consulta->execute([$idNino, $historiaGuardar, $fechaSesion, $texto, $ahora]); // Ejecuta poniendo cada valor en su hueco, en ese orden
                    header("Location: ficha.php?n=" . $idNino . "&ok=1"); // Vuelve a la ficha con el aviso de nota guardada
                } // Fin del if de nueva o editada
                exit; // Corta acá, después de redirigir
            } // Fin del if de validaciones
        } // Fin del if de acciones
    } // Fin del if de POST

    // Valores del formulario de arriba: por defecto, una nota nueva con la fecha y la hora de ahora
    $nota = ["id_nota" => 0, "fecha_sesion" => date('Y-m-d\TH:i'), "nota" => "", "id_historia" => 0];

    if ($nino) { // Si el niño existe, se decide qué lleva el formulario
        if ($mensajeError !== "") { // ¿Hubo un error al guardar? El formulario conserva lo que se había escrito
            $nota = ["id_nota" => $idNota, "fecha_sesion" => $fechaTexto, "nota" => $texto, "id_historia" => $idHist]; // Conserva lo escrito
        } elseif (isset($_GET['e'])) { // ¿Se apretó "Editar" en una nota? Sus datos suben al formulario
            $q = $conexion->prepare("SELECT id_nota, fecha_sesion, nota, id_historia FROM ficha_nota WHERE id_nota = ? AND id_infante = ?"); // Busca la nota, solo si es de este niño
            $q->execute([(int)$_GET['e'], $idNino]); // Ejecuta con el id de la nota y el del niño
            $fila = $q->fetch(PDO::FETCH_ASSOC); // Trae la fila, o false si no existe
            if ($fila) { // Si la nota existe
                $nota = ["id_nota" => (int)$fila['id_nota'], "fecha_sesion" => date('Y-m-d\TH:i', strtotime($fila['fecha_sesion'])), "nota" => $fila['nota'], "id_historia" => (int)$fila['id_historia']]; // Carga los datos de la nota en el formulario
            } // Fin del if
        } // Fin del if de qué lleva el formulario
    } // Fin del if

    $editando = ($nota['id_nota'] > 0); // true si el formulario está editando una nota que ya existía

?>
<!DOCTYPE html> <!-- Avisa al navegador que es HTML5 -->
<html lang="es"> <!-- Abre el documento en español -->
<head> <!-- Abre el encabezado -->
    <meta charset="UTF-8"> <!-- Para que tildes y ñ se vean bien -->
    <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- Hace que el celular use su ancho real: sin esta línea, dibuja la página como en una compu y la achica -->
    <title>Ficha - S.I.M.O.N.</title> <!-- Texto de la pestaña del navegador -->
    <link rel="stylesheet" href="estilos.css?v=<?php echo filemtime(__DIR__ . "/estilos.css"); // Fecha de la última modificación: si el archivo cambia, el navegador baja la versión nueva ?>"> <!-- Carga la hoja de estilos compartida -->
</head> <!-- Cierra el encabezado -->
<body> <!-- Abre el cuerpo -->

<?php if (!$nino): // Si no existe ese niño o niña ?>
    <h1>Ficha no disponible</h1> <!-- Título -->
    <p>No se encontró ese perfil.</p> <!-- Aviso -->
    <p><a href="ninos.php">Volver a Perfiles</a></p> <!-- Link para volver -->

<?php else: // PANTALLA ÚNICA: solo el formulario para cargar una nota; la ficha se ve en ver_ficha.php ?>
    <h1>Ficha</h1> <!-- Título -->
    <h2><?php echo htmlspecialchars($nino['apellido'] . ", " . $nino['nombre']); // Muestra "Apellido, Nombre" de forma segura ?></h2> <!-- Nombre del niño o la niña -->
    <div class="controles"> <!-- Botones para ver la ficha o volver -->
        <a class="boton-sec" href="ver_ficha.php?n=<?php echo (int)$idNino; ?>">Ver ficha</a> <!-- Abre la hoja de la ficha, de solo lectura -->
        <a class="boton-sec" href="ninos.php">Volver a Perfiles</a> <!-- Vuelve a la lista de perfiles -->
        <a class="boton-sec" href="menu.php">Volver al menú</a> <!-- Vuelve al menú -->
    </div> <!-- Cierra los botones -->

    <?php if ($mensajeOk): // Si se hizo algo, se muestra el aviso ?>
        <p class="success"><?php echo htmlspecialchars($mensajeOk); ?></p> <!-- class="success" le da el estilo verde -->
    <?php endif; // Fin del if ?>
    <?php if ($mensajeError): // Si hay un mensaje de error, se muestra hasta el endif ?>
        <p class="error"><?php echo htmlspecialchars($mensajeError); ?></p> <!-- class="error" le da el estilo rojo -->
    <?php endif; // Fin del if ?>

    <form method="POST" action="ficha.php"> <!-- Formulario: al enviarlo manda los datos por POST a esta misma página; siempre está a la vista, para anotar en cualquier momento -->
        <input type="hidden" name="n" value="<?php echo (int)$idNino; ?>"> <!-- Campo oculto: el niño o la niña de la ficha -->
        <input type="hidden" name="accion" value="guardar"> <!-- Campo oculto: dice que se quiere guardar -->
        <input type="hidden" name="id_nota" value="<?php echo (int)$nota['id_nota']; ?>"> <!-- Campo oculto: id de la nota; 0 si es nueva -->

        <label><?php echo $editando ? "Editando una nota:" : "Fecha y hora de la sesión:"; // Si se está editando, se aclara ?></label> <!-- Etiqueta del campo -->
        <input type="datetime-local" name="fecha_sesion" value="<?php echo htmlspecialchars($nota['fecha_sesion']); ?>" required> <!-- Selector de fecha y hora; arranca con el momento actual y se puede cambiar -->

        <label>Historia trabajada (opcional):</label> <!-- Etiqueta de la lista -->
        <select name="id_historia"> <!-- Lista desplegable con las historias que tiene asignadas -->
            <option value="0">Ninguna</option> <!-- Permite dejarlo vacío -->
            <?php foreach ($historias as $idH => $tituloH): // Una opción por cada historia asignada ?>
                <option value="<?php echo (int)$idH; ?>"<?php echo ((int)$nota['id_historia'] === (int)$idH) ? " selected" : ""; // Marca la que ya estaba elegida ?>><?php echo htmlspecialchars($tituloH); ?></option> <!-- Una historia -->
            <?php endforeach; // Fin del foreach ?>
        </select> <!-- Cierra la lista -->

        <label>Anotación:</label> <!-- Etiqueta del campo -->
        <textarea name="nota" rows="5" style="width: 100%;" required><?php echo htmlspecialchars($nota['nota']); // Repone el texto, de forma segura ?></textarea> <!-- Texto libre de varias líneas -->

        <button type="submit"><?php echo $editando ? "Guardar cambios" : "Guardar nota"; // El botón cambia según el caso ?></button> <!-- Botón que envía el formulario -->
        <?php if ($editando): // Si se está editando, se ofrece salir sin guardar ?>
            <a class="boton-sec" href="ver_ficha.php?n=<?php echo (int)$idNino; ?>">Cancelar</a> <!-- Botón para dejar la edición y volver a la hoja de la ficha -->
        <?php endif; // Fin del if ?>
    </form> <!-- Cierra el formulario -->
<?php endif; // Fin del if de las pantallas ?>

</body> <!-- Cierra el cuerpo -->
</html> <!-- Cierra el documento -->

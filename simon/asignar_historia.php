<?php // Abre PHP
    session_start(); // Retoma la sesión para saber si hay alguien logueado
    if (!isset($_SESSION['id_profesional'])) { // ¿NO hay un profesional logueado? isset() pregunta si la clave existe; el ! invierte la respuesta
        header("Location: login.php"); // Si no hay profesional logueado, manda al login
        exit; // Corta acá: no se muestra ni se procesa nada más
    } // Fin del guardia de entrada

    $mensajeError = ""; // Mensaje de error, vacío al principio
    $mensajeOk = ""; // Mensaje de éxito, vacío al principio

    require __DIR__ . "/conexion.php"; // Trae la conexión a la base, definida una sola vez en conexion.php: deja lista la variable $conexion

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['quitar'])) { // ¿Se apretó el botón Quitar de una historia asignada?
        $idQuitar = (int)$_POST['quitar']; // Lee el id de la historia a quitar, como número entero

        $q = $conexion->prepare("SELECT COUNT(*) FROM historias WHERE id_historia = ? AND id_infante IS NOT NULL"); // Prepara: ¿es una copia asignada a un niño? (las de la biblioteca nunca se quitan)
        $q->execute([$idQuitar]); // Ejecuta con el id
        $esCopia = $q->fetchColumn() > 0; // true si es una copia asignada

        $q = $conexion->prepare("SELECT COUNT(*) FROM historial_progreso WHERE id_historia = ?"); // Prepara: ¿esa historia tiene seguimientos registrados?
        $q->execute([$idQuitar]); // Ejecuta con el id
        $tieneSeguimientos = $q->fetchColumn() > 0; // true si hay al menos un seguimiento

        if (!$esCopia) { // Si no es una copia asignada
            $mensajeError = "No se encontró esa asignación."; // Avisa y no borra nada
        } elseif ($tieneSeguimientos) { // Si tiene seguimientos, no se deja quitar para no perder lo que anotó la familia
            $mensajeError = "No se puede quitar: esta historia tiene seguimientos registrados. Borralos primero desde \"Consultar historial\"."; // Avisa cómo seguir
        } else { // Se puede quitar
            try { // Intenta hacer todo el borrado; si algo falla, salta al catch
                $conexion->beginTransaction(); // Abre una transacción: o se borra todo o nada
                $consulta = $conexion->prepare("DELETE i FROM paso_orden_item i JOIN paso p ON p.id_paso = i.id_paso WHERE p.id_historia = ?"); // Borra las palabras de los pasos de ordenar, si las hubiera
                $consulta->execute([$idQuitar]); // Ejecuta con el id de la historia
                $consulta = $conexion->prepare("DELETE FROM paso WHERE id_historia = ?"); // Borra los pasos de la copia
                $consulta->execute([$idQuitar]); // Ejecuta
                $consulta = $conexion->prepare("DELETE FROM historias WHERE id_historia = ? AND id_infante IS NOT NULL"); // Borra la copia; la condición asegura que nunca se borre una historia de la biblioteca
                $consulta->execute([$idQuitar]); // Ejecuta
                $conexion->commit(); // Confirma: recién acá quedan guardados los borrados
                $mensajeOk = "Asignación quitada. La historia vuelve a estar disponible para asignar."; // Mensaje de éxito
            } catch (PDOException $error) { // Si algo falló
                $conexion->rollBack(); // Deshace todo lo hecho dentro de la transacción
                $mensajeError = "No se pudo quitar la asignación: " . $error->getMessage(); // Avisa el motivo
            } // Fin del try/catch
        } // Fin del if de validaciones
    } elseif ($_SERVER["REQUEST_METHOD"] == "POST") { // Si no fue Quitar, ¿se envió el formulario de asignar?
        $idInfante = (int)($_POST['id_infante'] ?? 0); // Lee el niño elegido; (int) lo convierte en número entero y ?? 0 deja 0 si no llegó nada
        $idOrigen = (int)($_POST['id_historia_origen'] ?? 0); // Lee la historia ya armada elegida, también como número entero

        $q = $conexion->prepare("SELECT COUNT(*) FROM infantes WHERE id_infante = ?"); // Prepara: ¿existe ese niño?
        $q->execute([$idInfante]); // Ejecuta con el id del niño
        $existeNino = $q->fetchColumn() > 0; // fetchColumn() devuelve el número contado; true si es mayor que 0

        $q = $conexion->prepare("SELECT COUNT(*) FROM historias WHERE id_historia = ? AND id_infante IS NULL AND tipo_actividad IN ('rutina', 'ordenar')"); // Prepara: ¿existe esa historia y es de la biblioteca (sin niño asignado)? Valen las de rutina y las de ordenar (Panbu)
        $q->execute([$idOrigen]); // Ejecuta con el id de la historia
        $existeOrigen = $q->fetchColumn() > 0; // true si la historia existe y es de la biblioteca

        $q = $conexion->prepare("SELECT COUNT(*) FROM historias WHERE id_infante = ? AND id_historia_origen = ?"); // Prepara: ¿ese niño ya tiene una copia de esa historia?
        $q->execute([$idInfante, $idOrigen]); // Ejecuta con el niño y la historia de origen
        $yaAsignada = $q->fetchColumn() > 0; // true si ya estaba asignada

        if (!$existeNino || !$existeOrigen) { // ¿Falta elegir bien el niño o la historia?
            $mensajeError = "Elegí un niño/a y una historia de la lista."; // Avisa y no guarda nada
        } elseif ($yaAsignada) { // Si no, ¿ya estaba asignada?
            $mensajeError = "Esa historia ya está disponible para ese niño/a. Podés seguirla todas las veces que quieras desde \"Seguir una historia\"."; // Evita duplicados y explica cómo repetirla
        } else { // Todo bien: se puede asignar
            try { // Intenta hacer todo el trabajo; si algo falla, salta al catch
                $conexion->beginTransaction(); // Abre una transacción: o se guardan TODOS los cambios o ninguno

                $consulta = $conexion->prepare("INSERT INTO historias (id_infante, id_historia_origen, id_personaje, tipo_actividad, titulo, descripcion, nivel_complejidad, actividad_avd) SELECT ?, id_historia, id_personaje, tipo_actividad, titulo, descripcion, nivel_complejidad, actividad_avd FROM historias WHERE id_historia = ?"); // Copia la historia de la biblioteca como una nueva fila: la de origen queda guardada en id_historia_origen y el primer hueco es el niño
                $consulta->execute([$idInfante, $idOrigen]); // Ejecuta: primer hueco = niño, segundo = historia de origen
                $idNueva = $conexion->lastInsertId(); // Id que la base le asignó a la copia recién creada

                $consulta = $conexion->prepare("INSERT INTO paso (id_historia, orden, tipo_paso, texto_narracion, texto_resultado, imagen_url, audio_url, imagen_opcion_b_url, opcion_correcta) SELECT ?, orden, tipo_paso, texto_narracion, texto_resultado, imagen_url, audio_url, imagen_opcion_b_url, opcion_correcta FROM paso WHERE id_historia = ?"); // Copia todos los pasos de la historia de origen, pero apuntando a la copia nueva
                $consulta->execute([$idNueva, $idOrigen]); // Ejecuta: primer hueco = copia nueva, segundo = historia de origen

                $consulta = $conexion->prepare("INSERT INTO paso_orden_item (id_paso, texto, imagen_url, orden_correcto) SELECT pn.id_paso, i.texto, i.imagen_url, i.orden_correcto FROM paso_orden_item i JOIN paso po ON po.id_paso = i.id_paso JOIN paso pn ON pn.id_historia = ? AND pn.orden = po.orden WHERE po.id_historia = ?"); // Copia las palabras o gemas de los pasos de ordenar: cada una se engancha al paso de la copia que tiene la misma posición que el paso de origen
                $consulta->execute([$idNueva, $idOrigen]); // Ejecuta: primer hueco = copia nueva, segundo = historia de origen (si la historia no tiene pasos de ordenar, no copia nada)
                $conexion->commit(); // Confirma: recién acá quedan guardados todos los cambios
                $mensajeOk = "Historia asignada. Ya podés seguirla desde \"Seguir una historia\"."; // Mensaje de éxito
            } catch (PDOException $error) { // Si algo falló en cualquiera de los dos pasos
                $conexion->rollBack(); // Deshace todo lo hecho dentro de la transacción: no queda nada a medias
                $mensajeError = "No se pudo asignar la historia: " . $error->getMessage(); // Avisa el motivo
            } // Fin del try/catch
        } // Fin del if de validaciones
    } // Fin del if de POST

    $ninos = $conexion->query("SELECT id_infante, nombre, apellido FROM infantes ORDER BY apellido, nombre")->fetchAll(PDO::FETCH_ASSOC); // Lista de niños para elegir; no hay datos del usuario en la consulta, así que query() es seguro
    $biblioteca = $conexion->query("SELECT h.id_historia, h.titulo, h.tipo_actividad, h.descripcion, h.nivel_complejidad, p.nombre AS personaje FROM historias h LEFT JOIN personaje p ON p.id_personaje = h.id_personaje WHERE h.id_infante IS NULL AND h.tipo_actividad IN ('rutina', 'ordenar') ORDER BY h.tipo_actividad = 'ordenar', h.id_historia")->fetchAll(PDO::FETCH_ASSOC); // Historias ya armadas: las que no pertenecen a ningún niño, con el nombre de su personaje
    $asignadas = $conexion->query("SELECT h.id_historia, h.nivel_complejidad, i.nombre, i.apellido, h.titulo FROM historias h JOIN infantes i ON i.id_infante = h.id_infante ORDER BY i.apellido, i.nombre, h.id_historia_origen")->fetchAll(PDO::FETCH_ASSOC); // Historias ya asignadas a algún niño, para ver el resultado
?>
<!DOCTYPE html> <!-- Avisa al navegador que es HTML5 -->
<html lang="es"> <!-- Abre el documento en español -->
<head> <!-- Abre el encabezado -->
    <meta charset="UTF-8"> <!-- Para que tildes y ñ se vean bien -->
    <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- Hace que el celular use su ancho real: sin esta línea, dibuja la página como en una compu y la achica -->
    <title>Asignar historia - S.I.M.O.N.</title> <!-- Texto de la pestaña del navegador -->
    <link rel="stylesheet" href="estilos.css?v=<?php echo filemtime(__DIR__ . "/estilos.css"); // Fecha de la última modificación: si el archivo cambia, el navegador baja la versión nueva ?>"> <!-- Carga la hoja de estilos compartida -->
</head> <!-- Cierra el encabezado -->
<body> <!-- Abre el cuerpo -->
    <h1>Asignar historia</h1> <!-- Título principal -->
    <p><a href="menu.php">Volver al menú</a></p> <!-- Link para volver al menú inicial -->
    <p>Asignar es dejar una historia disponible para el niño/a; se hace una sola vez por historia. Después se puede seguir todas las veces que se quiera, desde "Seguir una historia".</p> <!-- Aclaración para que no se confunda asignar con mostrar -->

    <?php if ($mensajeOk): // Si hay mensaje de éxito, se muestra hasta el endif ?>
        <p class="success"><?php echo htmlspecialchars($mensajeOk); // Imprime el mensaje de forma segura ?></p> <!-- class="success" le da el estilo verde -->
        <p><a href="menu.php">Ir a las actividades</a></p> <!-- Link directo a la pantalla que muestra la historia -->
    <?php endif; // Fin del if ?>

    <?php if ($mensajeError): // Si hay mensaje de error, se muestra hasta el endif ?>
        <p class="error"><?php echo htmlspecialchars($mensajeError); // Imprime el error de forma segura ?></p> <!-- class="error" le da el estilo rojo -->
    <?php endif; // Fin del if ?>

    <?php if (count($ninos) === 0): // Si todavía no hay ningún niño cargado ?>
        <p>Primero cargá un niño/a en "Perfiles de niños y niñas".</p> <!-- Sin niños no hay a quién asignar -->
    <?php elseif (count($biblioteca) === 0): // Si no hay historias ya armadas en la base ?>
        <p>Todavía no hay historias ya armadas cargadas.</p> <!-- Sin historias no hay qué asignar -->
    <?php else: // Hay niños y hay historias: se muestra el formulario ?>
        <form method="POST" action="asignar_historia.php"> <!-- Formulario: al enviarlo manda los datos por POST a esta misma página -->
            <label>Niño/a:</label> <!-- Etiqueta de la lista de niños -->
            <select name="id_infante" required> <!-- Lista desplegable; lo elegido llega en $_POST['id_infante'] -->
                <option value="">Elegir...</option> <!-- Opción vacía inicial: required obliga a elegir otra -->
                <?php foreach ($ninos as $n): // Repite el bloque siguiente por cada niño de la lista ?>
                    <option value="<?php echo (int)$n['id_infante']; // Pone el id del niño como valor de la opción ?>"><?php echo htmlspecialchars($n['apellido'] . ", " . $n['nombre']); // Muestra "Apellido, Nombre" de forma segura ?></option> <!-- Una opción por niño -->
                <?php endforeach; // Fin del foreach de niños ?>
            </select> <!-- Cierra la lista -->

            <label>Historia:</label> <!-- Etiqueta del grupo de historias -->
            <?php $grupoActual = null; // Recuerda cuál fue el último título de grupo que se mostró
            foreach ($biblioteca as $h): // Repite el bloque siguiente por cada historia ya armada ?>
                <?php if ($grupoActual !== $h['tipo_actividad']): // ¿Cambió el tipo de actividad respecto de la historia anterior?
                    $grupoActual = $h['tipo_actividad']; // Guarda el tipo actual ?>
                    <h3 class="grupo-titulo"><?php echo $grupoActual === 'ordenar' ? 'Ordenar' : 'Historias de rutinas'; // Título del grupo ?></h3> <!-- Título que separa las rutinas de las actividades de ordenar -->
                <?php endif; // Fin del if ?>
                <p> <!-- Un párrafo por historia -->
                    <input type="radio" name="id_historia_origen" value="<?php echo (int)$h['id_historia']; // Pone el id de la historia como valor del botón ?>" required> <!-- Botón de opción: solo se puede elegir una historia; required obliga a elegir alguna -->
                    <strong><?php echo htmlspecialchars($h['titulo']); // Muestra el título de forma segura ?></strong> <!-- Título en negrita -->
                    (<?php echo htmlspecialchars($h['personaje'] ?? "sin personaje"); // Muestra el personaje; ?? da un texto de respaldo si no tiene ?>)<br> <!-- Personaje entre paréntesis y salto de línea -->
                    <?php echo htmlspecialchars($h['descripcion'] ?? ""); // Muestra la descripción corta de forma segura ?> <!-- Descripción de la historia -->
                </p> <!-- Cierra el párrafo -->
            <?php endforeach; // Fin del foreach de historias ?>
            <button type="submit">Asignar</button> <!-- Botón que envía el formulario -->
        </form> <!-- Cierra el formulario -->
    <?php endif; // Fin del if/elseif/else ?>

    <h2>Historias ya asignadas</h2> <!-- Subtítulo de la lista de resultado -->
    <?php if (count($asignadas) === 0): // Si todavía no se asignó ninguna ?>
        <p>Todavía no hay historias asignadas.</p> <!-- Aviso -->
    <?php else: // Si hay alguna asignada ?>
        <ul> <!-- Lista con viñetas -->
            <?php foreach ($asignadas as $a): // Repite el bloque siguiente por cada historia asignada ?>
                <li><?php echo htmlspecialchars($a['apellido'] . ", " . $a['nombre'] . ": " . $a['titulo']); // Muestra "Apellido, Nombre: Título" de forma segura ?>
                    <form method="POST" action="asignar_historia.php" style="display: inline; padding: 0; background: none; box-shadow: none;" onsubmit="return confirm('¿Quitar esta historia? Se borra su copia.');"> <!-- Formulario de quitar, sin el recuadro de los otros; confirm() pregunta antes de enviar -->
                        <input type="hidden" name="quitar" value="<?php echo (int)$a['id_historia']; ?>"> <!-- Campo oculto: id de la historia a quitar -->
                        <button type="submit" style="margin-top: 0; padding: 4px 10px;">Quitar</button> <!-- Botón de quitar -->
                    </form> <!-- Cierra el formulario -->
                </li> <!-- Un renglón por asignación -->
            <?php endforeach; // Fin del foreach de asignadas ?>
        </ul> <!-- Cierra la lista -->
    <?php endif; // Fin del if ?>
</body> <!-- Cierra el cuerpo -->
</html> <!-- Cierra el documento -->

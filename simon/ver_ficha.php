<?php // Abre PHP
    session_start(); // Retoma la sesión para saber si hay alguien logueado
    if (!isset($_SESSION['id_profesional'])) { // ¿NO hay un profesional logueado? La ficha es solo para la profesional: la familia no puede entrar ni saber que existe
        http_response_code(404); // Responde como si la página no existiera: así nadie puede comprobar, ni siquiera escribiendo la dirección, que la ficha existe
        echo "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL was not found on this server.</p></body></html>"; // Mensaje neutro, igual al de cualquier página que no existe
        exit; // Corta acá: no se muestra ni se procesa nada más
    } // Fin del guardia de entrada

    require __DIR__ . "/conexion.php"; // Trae la conexión a la base, definida una sola vez en conexion.php: deja lista la variable $conexion

    $idNino = (int)($_GET['n'] ?? 0); // Niño o niña de la ficha (llega en la dirección); (int) lo convierte en número
    $mensajeOk = ["2" => "Cambios guardados.", "3" => "Nota borrada."][(string)($_GET['ok'] ?? "")] ?? ""; // Aviso de éxito según el número que trae la dirección (ok=2 o 3), cuando se vuelve de editar o de borrar una nota

    $q = $conexion->prepare("SELECT id_infante, nombre, apellido, fecha_nacimiento FROM infantes WHERE id_infante = ?"); // Busca al niño o niña
    $q->execute([$idNino]); // Ejecuta con el id
    $nino = $q->fetch(PDO::FETCH_ASSOC); // Trae la fila, o false si no existe

    $notas = []; // Notas de la ficha
    $historias = []; // Historias que tiene asignadas este niño o niña
    if ($nino) { // Si el niño existe, se buscan sus notas y sus historias
        $q = $conexion->prepare("SELECT n.id_nota, n.fecha_sesion, n.nota, n.fecha_carga, n.fecha_edicion, h.titulo FROM ficha_nota n LEFT JOIN historias h ON h.id_historia = n.id_historia WHERE n.id_infante = ? ORDER BY n.fecha_sesion, n.id_nota"); // Busca sus notas con el título de la historia trabajada, de la más vieja a la más nueva, como en una ficha de papel
        $q->execute([$idNino]); // Ejecuta con el id
        $notas = $q->fetchAll(PDO::FETCH_ASSOC); // Trae todas las notas

        $q = $conexion->prepare("SELECT DISTINCT id_historia_origen, titulo FROM historias WHERE id_infante = ? AND id_historia_origen IS NOT NULL ORDER BY id_historia_origen"); // Busca las historias que tiene asignadas, una sola vez cada una
        $q->execute([$idNino]); // Ejecuta con el id
        $historias = $q->fetchAll(PDO::FETCH_KEY_PAIR); // Arma la lista id => título
    } // Fin del if
?>
<!DOCTYPE html> <!-- Avisa al navegador que es HTML5 -->
<html lang="es"> <!-- Abre el documento en español -->
<head> <!-- Abre el encabezado -->
    <meta charset="UTF-8"> <!-- Para que tildes y ñ se vean bien -->
    <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- Hace que el celular use su ancho real: sin esta línea, dibuja la página como en una compu y la achica -->
    <title>Ver ficha - S.I.M.O.N.</title> <!-- Texto de la pestaña del navegador -->
    <link rel="stylesheet" href="estilos.css?v=<?php echo filemtime(__DIR__ . "/estilos.css"); // Fecha de la última modificación: si el archivo cambia, el navegador baja la versión nueva ?>"> <!-- Carga la hoja de estilos compartida -->
</head> <!-- Cierra el encabezado -->
<body class="con-hoja"> <!-- Abre el cuerpo; "con-hoja" le da más ancho, como una hoja de papel -->

<?php if (!$nino): // Si no existe ese niño o niña ?>
    <h1>Ficha no disponible</h1> <!-- Título -->
    <p>No se encontró ese perfil.</p> <!-- Aviso -->
    <p><a href="ninos.php">Volver a Perfiles</a></p> <!-- Link para volver -->

<?php else: // La hoja de la ficha, de solo lectura ?>
    <div class="controles no-imprimir"> <!-- Botones de arriba: no salen al imprimir -->
        <a class="boton-sec" href="ficha.php?n=<?php echo (int)$idNino; ?>">Cargar una nota</a> <!-- Va al formulario para anotar -->
        <a class="boton-sec" href="ninos.php">Volver a Perfiles</a> <!-- Vuelve a la lista de perfiles -->
        <a class="boton-sec" href="menu.php">Volver al menú</a> <!-- Vuelve al menú -->
        <button type="button" class="boton-sec" onclick="window.print();">Imprimir</button> <!-- Abre el cuadro de impresión del navegador -->
    </div> <!-- Cierra los botones -->

    <?php if ($mensajeOk): // Si se hizo algo (editar o borrar), se muestra el aviso ?>
        <p class="success no-imprimir"><?php echo htmlspecialchars($mensajeOk); ?></p> <!-- class="success" le da el estilo verde -->
    <?php endif; // Fin del if ?>

    <div class="hoja"> <!-- La hoja: fondo blanco, borde fino y letra de libro, como una ficha en papel -->
        <div class="hoja-cabecera"> <!-- Encabezado de la hoja -->
            <p class="hoja-titulo">Ficha de seguimiento</p> <!-- Título de la hoja -->
            <p class="hoja-nombre"><?php echo htmlspecialchars($nino['apellido'] . ", " . $nino['nombre']); // Muestra "Apellido, Nombre" de forma segura ?></p> <!-- Nombre del niño o la niña -->
            <?php if (!empty($nino['fecha_nacimiento'])): // Solo si tiene fecha de nacimiento cargada ?>
                <p class="hoja-dato">Fecha de nacimiento: <?php echo date("d/m/Y", strtotime($nino['fecha_nacimiento'])); // En formato día/mes/año ?></p> <!-- Fecha de nacimiento -->
            <?php endif; // Fin del if ?>
        </div> <!-- Cierra el encabezado -->

        <h3 class="hoja-seccion">Notas</h3> <!-- Sección de notas -->
        <?php if (!$notas): // Si todavía no hay notas ?>
            <p class="hoja-vacia">Todavía no hay notas en esta ficha.</p> <!-- Aviso -->
        <?php endif; // Fin del if ?>
        <?php foreach ($notas as $n): // Un bloque por cada nota, de la más vieja a la más nueva ?>
            <div class="hoja-nota"> <!-- Bloque de una nota -->
                <p class="hoja-fecha"><strong><?php echo date("d/m/Y H:i", strtotime($n['fecha_sesion'])); // Fecha y hora de la sesión ?></strong><?php echo $n['titulo'] ? " — " . htmlspecialchars($n['titulo']) : ""; // Si hay historia trabajada, la muestra ?></p> <!-- Fecha de la sesión e historia trabajada -->
                <p class="hoja-texto"><?php echo nl2br(htmlspecialchars($n['nota'])); // Texto de la nota, respetando los saltos de línea ?></p> <!-- Texto de la nota -->
                <p class="hoja-meta">Cargada el <?php echo date("d/m/Y H:i", strtotime($n['fecha_carga'])); ?><?php echo $n['fecha_edicion'] ? " · editada el " . date("d/m/Y H:i", strtotime($n['fecha_edicion'])) : ""; // Si se editó, lo aclara ?></p> <!-- Cuándo se cargó y se editó -->
                <div class="controles no-imprimir"> <!-- Botones de la nota: no salen al imprimir -->
                    <a class="boton-sec" href="ficha.php?n=<?php echo (int)$idNino; ?>&amp;e=<?php echo (int)$n['id_nota']; ?>">Editar</a> <!-- Abre el formulario con esta nota para corregirla -->
                    <form method="POST" action="ficha.php" style="flex: 1 1 180px; margin: 0; padding: 0; background: none; box-shadow: none;" onsubmit="return confirm('¿Borrar esta nota? No se puede deshacer.');"> <!-- Formulario de borrar, con confirmación; lo procesa ficha.php -->
                        <input type="hidden" name="n" value="<?php echo (int)$idNino; ?>"> <!-- Campo oculto: el niño o la niña -->
                        <input type="hidden" name="accion" value="borrar"> <!-- Campo oculto: dice qué se quiere hacer -->
                        <input type="hidden" name="id_nota" value="<?php echo (int)$n['id_nota']; ?>"> <!-- Campo oculto: la nota a borrar -->
                        <button type="submit" class="boton-sec" style="width: 100%; margin: 0;">Borrar</button> <!-- Botón de borrar -->
                    </form> <!-- Cierra el formulario -->
                </div> <!-- Cierra los botones de la nota -->
            </div> <!-- Cierra el bloque de la nota -->
        <?php endforeach; // Fin del foreach ?>

        <h3 class="hoja-seccion">Historias asignadas</h3> <!-- Sección de historias asignadas -->
        <?php if (!$historias): // Si todavía no tiene ninguna asignada ?>
            <p class="hoja-vacia">Todavía no tiene historias asignadas.</p> <!-- Aviso -->
        <?php else: // Si tiene, se listan ?>
            <ul class="hoja-lista"> <!-- Lista de historias -->
                <?php foreach ($historias as $tituloH): // Una línea por cada historia asignada ?>
                    <li><?php echo htmlspecialchars($tituloH); ?></li> <!-- Título de la historia -->
                <?php endforeach; // Fin del foreach ?>
            </ul> <!-- Cierra la lista -->
        <?php endif; // Fin del if ?>
    </div> <!-- Cierra la hoja -->
<?php endif; // Fin del if de las pantallas ?>

</body> <!-- Cierra el cuerpo -->
</html> <!-- Cierra el documento -->

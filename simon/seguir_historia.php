<?php // Abre PHP
    session_start(); // Retoma la sesión para saber quién está logueado
    $esProfesional = isset($_SESSION['id_profesional']); // true si hay un profesional logueado
    $esFamilia = isset($_SESSION['id_familia']); // true si hay una familia logueada
    if (!$esProfesional && !$esFamilia) { // ¿No hay nadie logueado? && significa "y"
        header("Location: login.php"); // Si no hay nadie, manda al login
        exit; // Corta acá: no se muestra nada más
    } // Fin de la verificación de sesión

    require __DIR__ . "/conexion.php"; // Trae la conexión a la base, definida una sola vez en conexion.php: deja lista la variable $conexion

    function urlImagen($ruta) { // Función que prepara la dirección de una imagen para mostrarla
        $archivo = __DIR__ . "/" . $ruta; // Ubicación real del archivo en el servidor
        $version = is_file($archivo) ? "?v=" . filemtime($archivo) : ""; // Si el archivo existe, agrega la fecha de su última modificación como "?v=número"
        return htmlspecialchars($ruta . $version); // Devuelve la dirección de forma segura; si el archivo cambia, el número cambia y el navegador baja la imagen nueva
    } // Fin de la función

    function proporcionImagen($ruta) { // Función que calcula la proporción de una imagen: su ancho dividido por su alto (1,5 si es horizontal 3:2; 0,75 si es vertical 3:4)
        $archivo = __DIR__ . "/" . $ruta; // Ubicación real del archivo en el servidor
        $medidas = is_file($archivo) ? @getimagesize($archivo) : false; // getimagesize() lee el ancho y el alto de la imagen; da false si el archivo no existe o no se puede leer (la @ evita que muestre un aviso)
        if (!$medidas || $medidas[1] == 0) { // ¿No se pudieron leer las medidas?
            return "1"; // Devuelve 1 (cuadrada): la imagen se ve igual, sin agrandarse de más
        } // Fin del if
        return number_format($medidas[0] / $medidas[1], 4, ".", ""); // Ancho dividido alto, con 4 decimales y punto decimal (así el CSS lo entiende en cualquier idioma del servidor)
    } // Fin de la función

    $idHistoria = (int)($_GET['h'] ?? 0); // Historia que se está siguiendo (viene en la dirección como ?h=); (int) la convierte en número y ?? 0 deja 0 si no llegó
    $orden = (int)($_GET['p'] ?? 1); // Paso en el que está (viene como p=); si no llegó, empieza en el 1
    $respuesta = $_GET['r'] ?? ""; // Opción marcada en una decisión (r=A o r=B); vacío si todavía no marcó ninguna
    $tipo = $_GET['t'] ?? ""; // Tipo de actividad de la lista que se pide (t=rutina o t=ordenar); lo elige la pantalla de actividades
    $vista = "lista"; // Qué pantalla mostrar: "lista" (elegir historia), "paso", "fin" o "error"
    $historia = null; // Datos de la historia que se sigue
    $paso = null; // Datos del paso actual
    $totalPasos = 0; // Cantidad de pasos de la historia que se ven (los de elección no cuentan si la selección está apagada)
    $ordenes = []; // Posiciones (orden) de los pasos que se ven, de menor a mayor
    $maxOrden = 0; // Posición más alta de la historia, se vea o no
    $ordenAnt = null; // Posición del paso anterior que se ve; null si no hay
    $ordenSig = 1; // Posición del paso siguiente que se ve; si no hay, la que muestra el final
    $numeroPaso = 1; // Número de este paso entre los que se ven (el que dice "Paso x de y")
    $ultimoOrden = 1; // Posición del último paso que se ve
    $esCorrecta = null; // null = todavía no marcó; true o false = resultado de lo que marcó
    $siguiente = null; // Datos del paso que viene después, para ir cargando sus imágenes de antemano
    $estado = ["sub" => 0, "audio" => 0, "ok" => []]; // Estado de la historia: subtítulo y audio (1 = activado) y las decisiones ya resueltas
    $interactiva = false; // true si este paso es una decisión que el niño tiene que resolver tocando
    $imagenPrincipal = ""; // Imagen que se muestra en los pasos de narración y en las decisiones que se cuentan como cuento
    $textoPaso = ""; // Texto del paso (subtítulo)
    $esMapa = false; // true si este paso es el mapa con las tres gemas dibujadas encima (historia de Panbu)
    $hayDecisiones = false; // true si la historia tiene puntos de decisión con dos imágenes: solo entonces aparece en la barra el botón para activar la selección
    $esOrdenar = false; // true si este paso es la actividad de ordenar las gemas en la cerradura (historia de Panbu)
    $gemas = []; // Las tres gemas de la actividad: nombre del color => ruta de su imagen
    $secuencia = []; // Orden de los colores que se ve en el mapa y que hay que copiar en la cerradura
    $bandeja = []; // Colores en el orden mezclado en que se ofrecen las gemas
    $posMapa = [["38.7%", "30.7%"], ["50.8%", "30.7%"], ["62.9%", "30.7%"]]; // Centro de cada círculo en el mapa entero: izquierda y arriba, en % de la imagen
    $posTira = [["29.4%", "49.6%"], ["50%", "49.6%"], ["70.7%", "49.6%"]]; // Centro de cada círculo en la tira del mapa (paso de ordenar), en % de la imagen
    $posAgujero = [["27.6%", "60.6%"], ["49.8%", "61.5%"], ["72.1%", "62.5%"]]; // Centro de cada orificio en la imagen vieja de la cerradura (completa y vertical), en % de la imagen
    $posAgujeroBanda = [["21.5%", "45.3%"], ["49.7%", "48.9%"], ["78.1%", "53%"]]; // Centro de cada orificio en la imagen de la cerradura que es solo la franja de los tres orificios (horizontal), en % de la imagen
    $posAgujeroRedonda = [["23.9%", "47.1%"], ["49.8%", "48.4%"], ["75.8%", "49.8%"]]; // Centro de cada orificio en la imagen de la cerradura que es solo la placa redonda (cuadrada), en % de la imagen

    if ($idHistoria > 0) { // ¿Se pidió una historia concreta?
        $q = $conexion->prepare("SELECT h.id_historia, h.titulo, h.tipo_actividad, i.id_familia, i.subtitulo_activo, i.audio_activo, i.seleccion_activa FROM historias h JOIN infantes i ON i.id_infante = h.id_infante WHERE h.id_historia = ?"); // Busca la historia junto con los datos de su niño: familia, preferencias y si la profesional activó la selección
        $q->execute([$idHistoria]); // Ejecuta con el id de la historia
        $historia = $q->fetch(PDO::FETCH_ASSOC); // Trae la fila, o false si no existe o no está asignada a ningún niño

        if (!$historia || ($esFamilia && $historia['id_familia'] != $_SESSION['id_familia'])) { // ¿No existe, o es una familia que quiere ver la historia de otra familia?
            $vista = "error"; // Muestra el aviso de error
        } else { // La historia existe y esta persona puede verla
            if (isset($_GET['n'])) { // ¿Se está empezando la historia desde la lista (n=1)?
                unset($_SESSION['ver'][$idHistoria]); // Borra lo que se había recordado de otras veces: arranca de nuevo
                unset($_SESSION['panbu'][$idHistoria]); // Borra también el orden de gemas sorteado: en cada vuelta sale uno nuevo
            } // Fin del if
            $estado = $_SESSION['ver'][$idHistoria] ?? ["sub" => (int)$historia['subtitulo_activo'], "audio" => (int)$historia['audio_activo'], "sel" => 0, "ok" => []]; // Toma lo que se venía usando en esta historia; si no hay nada, arranca con los subtítulos y el audio del perfil, y con la selección de opciones apagada (se activa con el botón de la barra)
            if (!isset($estado['ok'])) { // Si el estado guardado es de una versión anterior
                $estado['ok'] = []; // Agrega la lista de decisiones resueltas, vacía
            } // Fin del if
            if (isset($_GET['s'])) { // ¿Se tocó el control de subtítulos (s=1 o s=0)?
                $estado['sub'] = ($_GET['s'] === '1') ? 1 : 0; // Lo activa o lo apaga
            } // Fin del if
            if (isset($_GET['a'])) { // ¿Se tocó el control de audio (a=1 o a=0)?
                $estado['audio'] = ($_GET['a'] === '1') ? 1 : 0; // Lo activa o lo apaga
            } // Fin del if
            if (!isset($estado['sel'])) { // Si el estado guardado es de una versión anterior
                $estado['sel'] = 0; // Agrega la selección de opciones, apagada
            } // Fin del if
            if (isset($_GET['sel'])) { // ¿Se tocó el control de selección de opciones (sel=1 o sel=0)?
                $estado['sel'] = ($_GET['sel'] === '1') ? 1 : 0; // La activa o la apaga
            } // Fin del if

            $q = $conexion->prepare("SELECT orden, tipo_paso FROM paso WHERE id_historia = ? ORDER BY orden"); // Prepara: la posición y el tipo de cada paso de la historia
            $q->execute([$idHistoria]); // Ejecuta
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $fila) { // Repite por cada paso
                $maxOrden = max($maxOrden, (int)$fila['orden']); // Recuerda la posición más alta
                if ($fila['tipo_paso'] === 'eleccion' && !$estado['sel']) { // ¿Es un paso de elección y la selección está apagada? Esos pasos solo existen con la selección activada
                    continue; // Se saltea: no entra en la lista de pasos que se ven
                } // Fin del if
                $ordenes[] = (int)$fila['orden']; // Guarda la posición de un paso que se ve
            } // Fin del foreach
            $totalPasos = count($ordenes); // Cantidad de pasos que se ven
            $ultimoOrden = ($totalPasos > 0) ? end($ordenes) : 1; // Posición del último paso que se ve
            if ($orden >= 1 && $orden <= $maxOrden && !in_array($orden, $ordenes, true)) { // ¿Se pidió un paso que no se ve (uno de elección con la selección apagada)?
                $proximo = $maxOrden + 1; // Por defecto, el final de la historia
                foreach ($ordenes as $o) { // Busca el primer paso que se ve y viene después
                    if ($o > $orden) { $proximo = $o; break; } // Lo encontró
                } // Fin del foreach
                $_SESSION['ver'][$idHistoria] = $estado; // Guarda el estado antes de redirigir
                header("Location: seguir_historia.php?h=" . $idHistoria . "&p=" . $proximo); // Salta a ese paso
                exit; // Corta acá, después de redirigir
            } // Fin del if
            $pos = array_search($orden, $ordenes, true); // Lugar de este paso entre los que se ven (false si no está)
            $ordenAnt = ($pos !== false && $pos > 0) ? $ordenes[$pos - 1] : null; // Posición del paso anterior que se ve; null en el primero
            $ordenSig = ($pos !== false && $pos < count($ordenes) - 1) ? $ordenes[$pos + 1] : $maxOrden + 1; // Posición del paso siguiente que se ve; si no hay, la que muestra el final
            $numeroPaso = ($pos !== false) ? $pos + 1 : 1; // Número de este paso entre los que se ven

            $q = $conexion->prepare("SELECT COUNT(*) FROM paso WHERE id_historia = ? AND tipo_paso IN ('decision', 'eleccion') AND imagen_url IS NOT NULL AND imagen_url <> '' AND imagen_opcion_b_url IS NOT NULL AND imagen_opcion_b_url <> ''"); // Prepara: ¿cuántos puntos de decisión con dos imágenes tiene la historia?
            $q->execute([$idHistoria]); // Ejecuta
            $hayDecisiones = ((int)$q->fetchColumn() > 0); // true si tiene al menos uno: recién ahí tiene sentido mostrar el botón de selección

            $q = $conexion->prepare("SELECT * FROM paso WHERE id_historia = ? AND orden = ?"); // Busca el paso que corresponde a la posición pedida
            $q->execute([$idHistoria, $orden]); // Ejecuta con la historia y la posición
            $paso = $q->fetch(PDO::FETCH_ASSOC); // Trae el paso, o false si ya no hay más pasos

            if (!$paso) { // ¿No hay paso en esa posición? Entonces la historia terminó
                $vista = "fin"; // Muestra la pantalla de cierre
                unset($_SESSION['panbu'][$idHistoria]); // La historia terminó: se borra el orden de gemas sorteado
            } else { // Hay paso: se muestra
                $vista = "paso"; // Muestra el paso
                $q = $conexion->prepare("SELECT imagen_url, imagen_opcion_b_url FROM paso WHERE id_historia = ? AND orden = ?"); // Busca las imágenes del paso que sigue
                $q->execute([$idHistoria, $ordenSig]); // Ejecuta con la historia y la posición del paso siguiente que se ve
                $siguiente = $q->fetch(PDO::FETCH_ASSOC); // Trae sus imágenes, o false si no hay más pasos

                $esDecision = ($paso['tipo_paso'] === 'decision' || $paso['tipo_paso'] === 'eleccion'); // true si el paso es una decisión (la de elección es igual, pero solo aparece con la selección activada)
                $tieneA = !empty($paso['imagen_url']); // true si hay imagen de la opción A
                $tieneB = !empty($paso['imagen_opcion_b_url']); // true si hay imagen de la opción B
                $interactiva = ($esDecision && $estado['sel'] && $tieneA && $tieneB); // Es una decisión para tocar solo si se activó la selección con el botón de la barra y hay dos imágenes

                if ($esDecision && !$interactiva) { // ¿Es una decisión que se cuenta como cuento (selección apagada)?
                    $imagenPrincipal = ($paso['opcion_correcta'] === 'B' && $tieneB) ? $paso['imagen_opcion_b_url'] : $paso['imagen_url']; // Muestra la imagen de la opción correcta
                    $textoPaso = !empty($paso['texto_resultado']) ? $paso['texto_resultado'] : ($paso['texto_narracion'] ?? ""); // Cuenta lo que pasa con la opción correcta; si el paso no tiene esa frase, usa la pregunta
                } else { // Narración común, o decisión para resolver
                    $imagenPrincipal = $paso['imagen_url'] ?? ""; // Imagen del paso
                    $textoPaso = $paso['texto_narracion'] ?? ""; // Texto del paso
                } // Fin del if

                if ($paso['tipo_paso'] === 'mapa' || $paso['tipo_paso'] === 'ordenar') { // ¿Es un paso de la historia de Panbu: el mapa con las gemas o la actividad de ordenarlas?
                    $q = $conexion->prepare("SELECT i.texto, i.imagen_url FROM paso_orden_item i JOIN paso p ON p.id_paso = i.id_paso WHERE p.id_historia = ? AND p.tipo_paso = 'ordenar' ORDER BY i.orden_correcto"); // Busca las gemas de la actividad de esta historia: sirven tanto para el mapa como para la cerradura
                    $q->execute([$idHistoria]); // Ejecuta con el id de la historia
                    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $gema) { // Repite por cada gema encontrada
                        $gemas[$gema['texto']] = $gema['imagen_url']; // Guarda "color => ruta de la imagen"
                    } // Fin del foreach
                    if (count($gemas) === 3) { // ¿Están las tres gemas? Si falta alguna, el paso se muestra como una narración común y la historia no se traba
                        $colores = array_keys($gemas); // Los tres nombres de color
                        $guardada = $_SESSION['panbu'][$idHistoria] ?? null; // Orden sorteado que ya se venía usando en esta vuelta, si lo hay
                        if (!is_array($guardada) || count($guardada) !== 3 || count(array_diff($guardada, $colores)) > 0 || count(array_diff($colores, $guardada)) > 0) { // ¿No hay un orden guardado, o no sirve porque no tiene exactamente los tres colores?
                            shuffle($colores); // Mezcla los colores al azar: es el orden nuevo
                            $guardada = $colores; // Lo toma como el orden de esta vuelta
                            $_SESSION['panbu'][$idHistoria] = $guardada; // Lo recuerda en la sesión: el mapa y la cerradura tienen que mostrar el mismo orden
                        } // Fin del if
                        $secuencia = array_values($guardada); // Orden que se ve en el mapa y que hay que copiar
                        $esMapa = ($paso['tipo_paso'] === 'mapa'); // true si este paso es el mapa
                        $esOrdenar = ($paso['tipo_paso'] === 'ordenar'); // true si este paso es la actividad
                        if ($esOrdenar) { // En la actividad, las gemas se ofrecen en otro orden, mezclado
                            $bandeja = array_keys($gemas); // Los tres colores
                            shuffle($bandeja); // Los mezcla al azar
                        } // Fin del if
                    } // Fin del if de las tres gemas
                } elseif (isset($_SESSION['panbu'][$idHistoria])) { // Es otro paso, pero quedó guardado un orden de gemas sorteado
                    $q = $conexion->prepare("SELECT MIN(orden) FROM paso WHERE id_historia = ? AND tipo_paso = 'ordenar'"); // Busca en qué posición está la actividad de ordenar
                    $q->execute([$idHistoria]); // Ejecuta con el id de la historia
                    $ordenActividad = $q->fetchColumn(); // Posición de la actividad (null si la historia no tiene)
                    if ($ordenActividad !== null && $ordenActividad !== false && $orden > (int)$ordenActividad) { // ¿Ya se pasó la actividad? Es decir, el cofre ya se abrió
                        unset($_SESSION['panbu'][$idHistoria]); // Borra el orden sorteado: si se vuelve atrás, sale uno nuevo
                    } // Fin del if
                } // Fin del if de los pasos de Panbu

                if ($interactiva && ($respuesta === 'A' || $respuesta === 'B')) { // ¿Es una decisión para resolver y ya marcó una opción válida?
                    $esCorrecta = ($respuesta === $paso['opcion_correcta']); // Compara lo que marcó con la opción correcta guardada
                    if ($esCorrecta) { // Si acertó
                        $estado['ok'][$orden] = 1; // Recuerda que este paso ya está resuelto, para poder volver a él y avanzar sin repetirlo
                        $_SESSION['ver'][$idHistoria] = $estado; // Guarda el estado antes de redirigir
                        header("Location: seguir_historia.php?h=" . $idHistoria . "&p=" . $ordenSig); // Pasa directo al paso siguiente, sin mensaje ni botón: acertar hace avanzar
                        exit; // Corta acá, después de redirigir
                    } // Fin del if
                } // Fin del if de decisión
            } // Fin del if de paso
            $_SESSION['ver'][$idHistoria] = $estado; // Recuerda el estado en la sesión, para que valga en los pasos siguientes
        } // Fin del if de historia
    } // Fin del if de historia pedida

    if ($esFamilia) { // ¿Es una familia? Para ella la lista es siempre la misma: sus niños con todas sus historias asignadas
        $urlLista = "seguir_historia.php"; // A dónde se vuelve para elegir otra historia
    } elseif ($historia && $vista !== "error") { // Es la profesional y hay una historia válida
        $urlLista = "seguir_historia.php?t=" . urlencode($historia['tipo_actividad']); // Vuelve a la lista del mismo tipo que la historia que se sigue
    } else { // Es la profesional y no hay historia válida
        $urlLista = "menu.php"; // Vuelve al menú, que es su pantalla de inicio
    } // Fin del if
    if ($vista === "lista" && !$esFamilia && $tipo !== "rutina" && $tipo !== "ordenar") { // ¿La profesional pidió la lista sin un tipo válido (por ejemplo, con un enlace guardado de antes)?
        header("Location: menu.php"); // Se la manda al menú, donde elige la actividad
        exit; // Corta acá: no se muestra nada más
    } // Fin del desvío

    $base = "seguir_historia.php?h=" . $idHistoria . "&amp;p="; // Parte común de las direcciones de los pasos
    $urlAnterior = ($vista === "paso" && $ordenAnt !== null) ? $base . $ordenAnt : ""; // Dirección del paso anterior que se ve; vacía en el primero
    $bloqueaAvance = $interactiva || $esOrdenar; // true si este paso hay que resolverlo antes de seguir: una decisión para tocar o la actividad de ordenar
    $puedeAvanzar = ($vista === "paso") && (!$bloqueaAvance || ($interactiva && ($esCorrecta === true || !empty($estado['ok'][$orden])))); // Se puede avanzar salvo que sea un paso todavía sin resolver; la actividad de ordenar habilita su flecha con JavaScript cuando se resuelve
    $urlSiguiente = $puedeAvanzar ? $base . $ordenSig : ""; // Dirección del paso siguiente que se ve; vacía si falta resolver la decisión
    $urlTrasActividad = $base . $ordenSig; // Dirección del paso que sigue a la actividad de ordenar: a ella salta solo la pantalla al resolverla; el botón Seguir y la flecha quedan de respaldo
    $controles = "seguir_historia.php?h=" . $idHistoria . "&amp;p=" . $orden; // Parte común de las direcciones de los controles de subtítulo y audio
    $mostrarTexto = ($vista === "paso") && ($estado['sub'] || empty($imagenPrincipal)) && $textoPaso !== ""; // true si hay que mostrar el texto debajo de la imagen: con el subtítulo activo, o mientras el paso todavía no tenga imagen cargada

    if ($vista === "lista") { // Si hay que mostrar la lista de historias
        $sql = "SELECT i.id_infante, i.nombre, i.apellido, h.id_historia, h.titulo FROM historias h JOIN infantes i ON i.id_infante = h.id_infante"; // Historias asignadas, con el niño de cada una
        $parametros = []; // Valores para los huecos de la consulta; empieza vacío
        if ($esFamilia) { // Si es una familia, solo ve a sus propios niños
            $sql .= " WHERE i.id_familia = ?"; // Agrega el filtro por familia (.= suma texto al final)
            $parametros[] = $_SESSION['id_familia']; // Agrega el id de la familia a los valores
        } else { // Si es la profesional, ve las historias del tipo de actividad que eligió en el menú
            $sql .= " WHERE h.tipo_actividad = ?"; // Agrega el filtro por tipo de actividad
            $parametros[] = $tipo; // Agrega el tipo a los valores
        } // Fin del if de familia
        $sql .= " ORDER BY i.apellido, i.nombre, h.id_historia_origen"; // Ordena por niño y por orden de las historias
        $q = $conexion->prepare($sql); // Prepara la consulta completa
        $q->execute($parametros); // Ejecuta con los valores
        $lista = $q->fetchAll(PDO::FETCH_ASSOC); // Trae todas las filas
    } // Fin del if de lista
?>
<!DOCTYPE html> <!-- Avisa al navegador que es HTML5 -->
<html lang="es"> <!-- Abre el documento en español -->
<head> <!-- Abre el encabezado -->
    <meta charset="UTF-8"> <!-- Para que tildes y ñ se vean bien -->
    <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- Hace que el celular o la tablet usen su ancho real: sin esta línea, dibuja la página como en una compu y la achica -->
    <title>Seguir historia - S.I.M.O.N.</title> <!-- Texto de la pestaña del navegador -->
    <link rel="stylesheet" href="estilos.css?v=<?php echo filemtime(__DIR__ . "/estilos.css"); // Fecha de la última modificación: si el archivo cambia, el navegador baja la versión nueva ?>"> <!-- Carga la hoja de estilos compartida -->
    <?php if ($siguiente): // Si hay un paso siguiente, se piden sus imágenes de antemano ?>
        <?php foreach (["imagen_url", "imagen_opcion_b_url"] as $col): // Repite para la imagen principal y la de la opción B ?>
            <?php if (!empty($siguiente[$col])): // Si el paso siguiente tiene esa imagen ?>
                <link rel="prefetch" as="image" href="<?php echo urlImagen($siguiente[$col]); // Misma dirección que usará la pantalla: así el navegador la reutiliza ?>"> <!-- Le pide al navegador que la descargue mientras se mira el paso actual -->
            <?php endif; // Fin del if ?>
        <?php endforeach; // Fin del foreach ?>
    <?php endif; // Fin del if ?>
</head> <!-- Cierra el encabezado -->
<body class="<?php echo $vista === 'paso' ? 'ancha' . ($mostrarTexto ? ' con-sub' : '') . ($interactiva ? ' decision' : '') : ''; // En la pantalla de un paso, la página usa todo el ancho disponible para que la imagen sea lo más grande posible; "con-sub" avisa que hay subtítulo debajo y "decision" que hay opciones para tocar; en los dos casos la imagen cede un poco de alto para dejarles lugar ?>"> <!-- Abre el cuerpo -->

<?php if ($vista === "lista"): // PANTALLA 1: elegir qué historia seguir ?>
    <h1><?php echo $esFamilia ? "Seguir una historia" : ($tipo === "ordenar" ? "Ordenar" : "Historias de rutinas"); // La familia ve un título general; la profesional, el de la actividad elegida ?></h1> <!-- Título -->
    <?php if (!$esFamilia): // Solo la profesional tiene menú al que volver: para la familia, esta lista es su pantalla de inicio ?>
        <p><a href="menu.php">Volver al menú</a></p> <!-- Link para volver al menú; las reglas de estilos.css lo muestran como botón -->
    <?php endif; // Fin del if ?>
    <?php if (count($lista) === 0): // Si no hay ninguna historia asignada ?>
        <p>Todavía no hay historias asignadas<?php echo $esFamilia ? "" : " en esta actividad"; ?>.</p> <!-- Aviso -->
    <?php endif; // Fin del if ?>
    <?php $ninoActual = null; // Recuerda para qué niño se mostró el último título
    foreach ($lista as $fila): // Repite el bloque siguiente por cada historia asignada ?>
        <?php if ($ninoActual !== $fila['id_infante']): // ¿Cambió el niño respecto de la fila anterior?
            $ninoActual = $fila['id_infante']; // Guarda el niño actual ?>
            <h2><?php echo htmlspecialchars($fila['apellido'] . ", " . $fila['nombre']); // Muestra "Apellido, Nombre" de forma segura ?></h2> <!-- Título con el nombre del niño -->
        <?php endif; // Fin del if ?>
        <div class="tarjeta"> <!-- Tarjeta de una historia -->
            <a class="boton" href="seguir_historia.php?h=<?php echo (int)$fila['id_historia']; // Pone el id de la historia en la dirección ?>&amp;p=1&amp;n=1"><?php echo htmlspecialchars($fila['titulo']); // El título de la historia es el botón grande para empezarla ?></a> <!-- Botón grande para empezar la historia -->
            <?php if ($esFamilia): // El seguimiento lo registra solo la familia: la profesional no ve este botón y consulta lo cargado en "Consultar historial" ?>
                <a class="boton-sec" href="registrar_seguimiento.php?h=<?php echo (int)$fila['id_historia']; ?>">Registrar seguimiento</a> <!-- Botón secundario para anotar cómo le fue -->
            <?php endif; // Fin del if ?>
        </div> <!-- Cierra la tarjeta -->
    <?php endforeach; // Fin del foreach ?>

    <?php if ($esFamilia): // La familia no tiene menú: su cierre de sesión está al pie de la lista ?>
        <a class="boton-sec" href="logout.php">Cerrar sesión</a> <!-- Botón para cerrar la sesión -->
    <?php endif; // Fin del if ?>

<?php elseif ($vista === "error"): // PANTALLA DE ERROR: historia inexistente o de otra familia ?>
    <h1>Historia no disponible</h1> <!-- Título -->
    <p>No se encontró esa historia.</p> <!-- Aviso -->
    <p><a href="<?php echo $urlLista; // La familia vuelve a su lista; la profesional, al menú ?>"><?php echo $esFamilia ? "Volver a las historias" : "Volver al menú"; ?></a></p> <!-- Link para volver -->

<?php elseif ($vista === "fin"): // PANTALLA FINAL: la historia terminó ?>
    <h1><?php echo htmlspecialchars($historia['titulo']); // Muestra el título de forma segura ?></h1> <!-- Título de la historia -->
    <p class="mensaje bien">¡Terminamos la historia!</p> <!-- Mensaje de cierre, grande y en verde -->
    <a class="boton" href="seguir_historia.php?h=<?php echo (int)$idHistoria; ?>&amp;p=<?php echo $ultimoOrden; // Vuelve al último paso que se ve ?>">Volver al último paso</a> <!-- Botón para retroceder un paso desde el final -->
    <?php if ($esFamilia): // El seguimiento lo registra solo la familia ?>
        <a class="boton" href="registrar_seguimiento.php?h=<?php echo (int)$idHistoria; ?>">Registrar cómo le fue</a> <!-- Botón para anotar el seguimiento -->
    <?php endif; // Fin del if ?>
    <a class="boton-sec" href="<?php echo $urlLista; // Lista del mismo tipo de actividad ?>">Elegir otra historia</a> <!-- Botón a la lista -->
    <?php if (!$esFamilia): // La familia no tiene menú: "Elegir otra historia" ya la lleva a su lista ?>
        <a class="boton-sec" href="menu.php">Volver al menú</a> <!-- Botón al menú -->
    <?php endif; // Fin del if ?>

<?php else: // PANTALLA DE UN PASO ?>
    <a class="icono salir" href="<?php echo $urlLista; // Lista del mismo tipo de actividad ?>" aria-label="Salir de la historia" title="Salir de la historia"> <!-- Botón para salir, arriba a la derecha de la pantalla: una X -->
        <svg viewBox="0 0 48 48" width="28" height="28" fill="none" stroke="#000" stroke-width="5" stroke-linecap="round" aria-hidden="true"><line x1="10" y1="10" x2="38" y2="38"/><line x1="38" y1="10" x2="10" y2="38"/></svg> <!-- Dibujo de la X -->
    </a> <!-- Cierra el botón de salir -->

    <div class="paso-contenedor"> <!-- Contenedor del paso: la imagen con sus flechas, y debajo la fila de iconos y subtítulo -->
    <div class="escena" data-ant="<?php echo $urlAnterior; ?>" data-sig="<?php echo $urlSiguiente; ?>" data-deslizar="<?php echo $esOrdenar ? '0' : '1'; // En la actividad de ordenar no se desliza: arrastrar una gema se confundiría con pasar de paso ?>"> <!-- Zona del paso; los datos son las direcciones a las que llevan las flechas y el deslizar con el dedo (vacías = no se puede ir) -->
        <div class="marco<?php echo $esOrdenar ? ' marco-actividad' : ''; // En la actividad de ordenar las flechas van arriba, junto al mapa modelo, para no tapar las gemas ni la cerradura ?>"> <!-- Marco que contiene la imagen y, a los lados, las flechas -->
            <?php if ($urlAnterior !== ""): // Si hay un paso anterior ?>
                <a class="flecha izq" href="<?php echo $urlAnterior; ?>" aria-label="Paso anterior">&#10094;</a> <!-- Flecha grande a la izquierda: vuelve al paso anterior -->
            <?php else: // En el primer paso no hay a dónde volver ?>
                <span class="flecha izq oculta"></span> <!-- Lugar vacío, para que la imagen quede centrada en pantallas anchas -->
            <?php endif; // Fin del if ?>

            <?php if ($interactiva): // Decisión para resolver: dos opciones para tocar ?>
                <div class="opciones"> <!-- Las dos opciones, una al lado de la otra -->
                    <a class="opcion" href="<?php echo $controles; ?>&amp;r=A"><img src="<?php echo urlImagen($paso['imagen_url']); ?>" alt="Opción A"></a> <!-- Opción A -->
                    <a class="opcion" href="<?php echo $controles; ?>&amp;r=B"><img src="<?php echo urlImagen($paso['imagen_opcion_b_url']); ?>" alt="Opción B"></a> <!-- Opción B -->
                </div> <!-- Cierra las opciones -->
            <?php elseif ($esOrdenar): // Actividad de ordenar: la tira del mapa, la cerradura y la bandeja con las gemas ?>
                <?php
                    $ratioCerr = (float)proporcionImagen($paso['imagen_opcion_b_url']); // Proporción ancho/alto de la imagen de la cerradura: la franja nueva es muy ancha (más de 2), la imagen vieja es vertical (menos de 1)
                    if ($ratioCerr > 1.5) { // ¿La imagen es muy ancha? Es la franja de los tres orificios
                        $posCerr = $posAgujeroBanda; // Posiciones de los orificios en la franja
                        $estiloCerr = "--agujero: 27%; --gema-puesta: 81%;"; // Tamaño de la zona táctil de cada orificio y de la gema puesta, en % de la imagen
                    } elseif ($ratioCerr > 0.9) { // ¿Es casi cuadrada? Es la placa redonda de la cerradura
                        $posCerr = $posAgujeroRedonda; // Posiciones de los orificios en la placa redonda
                        $estiloCerr = "--agujero: 24.5%; --gema-puesta: 82%;"; // Tamaño de la zona táctil y de la gema puesta
                    } else { // Si es alta y angosta, es la imagen vieja de la cerradura (completa)
                        $posCerr = $posAgujero; // Posiciones de los orificios en la imagen vieja
                        $estiloCerr = "--agujero: 24%; --gema-puesta: 71.25%;"; // Tamaño de la zona táctil y de la gema puesta
                    } // Fin del if
                ?>
                <div class="ordenar" id="ordenar" style="--ratio-cerr: <?php echo number_format($ratioCerr, 4, ".", ""); // La proporción de la cerradura: el CSS la usa para calcular los tamaños ?>;" data-solucion="<?php echo htmlspecialchars(implode(',', $secuencia)); // Colores en el orden correcto, de izquierda a derecha: los usa el JavaScript para comprobar ?>"> <!-- Toda la actividad -->
                    <div class="ordenar-lado"> <!-- Lado del mapa: título, tira del mapa, bandeja de gemas y mensajes -->
                        <p class="ordenar-titulo">Ayudá a Panbu a abrir el cofre</p> <!-- Título de la actividad -->
                        <div class="tira"> <!-- Tira del mapa con las tres gemas dibujadas: es el modelo que hay que copiar -->
                            <img class="tira-img" src="<?php echo urlImagen($paso['imagen_url']); // Tira con los tres círculos vacíos ?>" alt="Mapa que muestra el orden de las gemas"> <!-- Imagen de la tira -->
                            <?php foreach ($secuencia as $i => $color): // Una gema encima de cada círculo, en el orden sorteado ?>
                                <img class="gema-sobre" src="<?php echo urlImagen($gemas[$color]); ?>" alt="Gema de color <?php echo htmlspecialchars($color); ?>" style="left: <?php echo $posTira[$i][0]; ?>; top: <?php echo $posTira[$i][1]; ?>; width: 18.3%;"> <!-- Gema sobre el círculo -->
                            <?php endforeach; // Fin del foreach de la tira ?>
                        </div> <!-- Cierra la tira -->
                        <div class="zona-bandeja"> <!-- Lugar de las gemas: al resolver, el botón Seguir aparece en este mismo lugar -->
                        <div class="bandeja" id="ordenar-bandeja"> <!-- Bandeja con las tres gemas mezcladas -->
                            <?php foreach ($bandeja as $i => $color): // Una casilla por gema, en el orden mezclado ?>
                                <div class="casilla" data-casilla="<?php echo $i; ?>"> <!-- Casilla fija: la gema vuelve acá si se la saca de un orificio -->
                                    <div class="gema" role="button" tabindex="0" data-color="<?php echo htmlspecialchars($color); ?>" data-casilla="<?php echo $i; ?>" aria-label="Gema de color <?php echo htmlspecialchars($color); ?>"> <!-- La gema: se toca o se arrastra -->
                                        <img src="<?php echo urlImagen($gemas[$color]); ?>" alt="" draggable="false"> <!-- Dibujo de la gema -->
                                    </div> <!-- Cierra la gema -->
                                </div> <!-- Cierra la casilla -->
                            <?php endforeach; // Fin del foreach de la bandeja ?>
                        </div> <!-- Cierra la bandeja -->
                        <a class="boton ordenar-seguir" id="ordenar-seguir" href="<?php echo $urlTrasActividad; ?>" hidden>Seguir</a> <!-- Botón para continuar: oculto hasta que se resuelve -->
                        </div> <!-- Cierra la zona de las gemas -->
                        <p class="ordenar-ayuda" id="ordenar-ayuda">Tocá una gema y después el orificio, o arrastrala.</p> <!-- Ayuda; el JavaScript la cambia por ¡Muy bien! o Probemos de nuevo -->
                    </div> <!-- Cierra el lado del mapa -->
                    <div class="cerradura" style="<?php echo $estiloCerr; ?>"> <!-- Cerradura del cofre, con tres orificios donde van las gemas -->
                        <img class="cerradura-img" src="<?php echo urlImagen($paso['imagen_opcion_b_url']); // Recorte de la cerradura ?>" alt="Cerradura del cofre, con tres orificios"> <!-- Imagen de la cerradura -->
                        <?php for ($i = 0; $i < 3; $i++): // Un orificio por cada gema ?>
                            <div class="agujero" role="button" tabindex="0" data-pos="<?php echo $i; ?>" aria-label="Orificio <?php echo $i + 1; ?>" style="left: <?php echo $posCerr[$i][0]; ?>; top: <?php echo $posCerr[$i][1]; ?>;"></div> <!-- Zona táctil del orificio, más grande que el agujero dibujado -->
                        <?php endfor; // Fin del for de los orificios ?>
                    </div> <!-- Cierra la cerradura -->
                </div> <!-- Cierra toda la actividad -->
            <?php elseif ($esMapa && !empty($imagenPrincipal)): // Mapa entero con las tres gemas dibujadas encima de los círculos ?>
                <div class="mapa-cont"> <!-- Contenedor del mapa: las gemas se ubican con porcentajes respecto de esta imagen -->
                    <img class="paso-img" src="<?php echo urlImagen($imagenPrincipal); ?>" alt="Mapa con tres gemas"> <!-- Imagen del mapa -->
                    <?php foreach ($secuencia as $i => $color): // Una gema encima de cada círculo, en el orden sorteado ?>
                        <img class="gema-sobre" src="<?php echo urlImagen($gemas[$color]); ?>" alt="Gema de color <?php echo htmlspecialchars($color); ?>" style="left: <?php echo $posMapa[$i][0]; ?>; top: <?php echo $posMapa[$i][1]; ?>; width: 10.7%;"> <!-- Gema sobre el círculo -->
                    <?php endforeach; // Fin del foreach del mapa ?>
                </div> <!-- Cierra el contenedor del mapa -->
            <?php elseif (!empty($imagenPrincipal)): // Paso de narración (o decisión contada como cuento) con imagen ?>
                <img class="paso-img" src="<?php echo urlImagen($imagenPrincipal); // Ruta de la imagen, de forma segura ?>" alt="Imagen del paso" style="--ratio: <?php echo proporcionImagen($imagenPrincipal); // Proporción ancho/alto de la imagen: el CSS la usa para agrandar las imágenes horizontales hasta el ancho disponible ?>"> <!-- Imagen del paso, lo más grande que entre en la pantalla -->
            <?php endif; // Fin del if ?>

            <?php if ($urlSiguiente !== ""): // Si se puede avanzar ?>
                <a class="flecha der" href="<?php echo $urlSiguiente; ?>" aria-label="Paso siguiente">&#10095;</a> <!-- Flecha grande a la derecha: pasa al paso siguiente -->
            <?php elseif ($esOrdenar): // Actividad de ordenar: la flecha existe pero está oculta hasta que se resuelve ?>
                <a class="flecha der oculta" id="flecha-sig" href="<?php echo $urlTrasActividad; ?>" aria-label="Paso siguiente" tabindex="-1">&#10095;</a> <!-- Flecha derecha: el JavaScript la muestra al resolver -->
            <?php else: // Decisión todavía sin resolver: no se puede avanzar ?>
                <span class="flecha der oculta"></span> <!-- Lugar vacío -->
            <?php endif; // Fin del if ?>
        </div> <!-- Cierra el marco -->
    </div> <!-- Cierra la zona del paso -->

    <?php if ($interactiva): // Mensajes de una decisión para resolver ?>
        <?php if ($esCorrecta === false): // Marcó la otra opción (si marca la correcta, el sistema ya pasó directo al paso siguiente) ?>
            <p class="mensaje">Probemos de nuevo.</p> <!-- Mensaje neutro: sin sonidos ni alertas de error; puede volver a marcar sin límite -->
        <?php endif; // Fin del if de resultado ?>
    <?php endif; // Fin del if ?>

    <div class="barra"> <!-- Fila debajo de la imagen: a la izquierda los iconos de subtítulo y audio, y a su lado el texto -->
        <div class="iconos"> <!-- Iconos, de izquierda a derecha: selección de imagen (si la historia tiene decisiones), subtítulos y audio; tocarlos los activa o los apaga; tachado significa apagado -->
            <?php if ($hayDecisiones): // El icono de selección aparece solo si la historia tiene puntos de decisión con dos imágenes ?>
                <a class="icono" href="<?php echo $controles; ?>&amp;sel=<?php echo $estado['sel'] ? 0 : 1; // Si está activada, el enlace la apaga; si no, la enciende ?>" aria-label="Elegir la imagen correcta: <?php echo $estado['sel'] ? 'activado, tocar para apagarlo' : 'apagado, tocar para activarlo'; ?>" title="Elegir la imagen correcta"> <!-- Icono de selección -->
                    <svg viewBox="0 0 48 48" width="36" height="36" fill="none" stroke="#000" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="12" width="17" height="24" rx="3"/><rect x="27" y="12" width="17" height="24" rx="3"/><path d="M8 24l4 4 6-8"/><?php if (!$estado['sel']): // Si está apagada, se dibuja la raya que tacha el icono ?><line x1="8" y1="42" x2="42" y2="8" stroke="#fff" stroke-width="9"/><line x1="8" y1="42" x2="42" y2="8" stroke="#000" stroke-width="4"/><?php endif; ?></svg> <!-- Dos imágenes con un tilde en una; tachado si está apagado -->
                </a> <!-- Cierra el icono de selección -->
            <?php endif; // Fin del if de selección ?>
            <a class="icono" href="<?php echo $controles; ?>&amp;s=<?php echo $estado['sub'] ? 0 : 1; // Si están activados, el enlace los apaga; si no, los enciende ?>" aria-label="Subtítulos: <?php echo $estado['sub'] ? 'activados, tocar para apagarlos' : 'apagados, tocar para activarlos'; ?>" title="Subtítulos"> <!-- Icono de subtítulos -->
                <svg viewBox="0 0 48 48" width="36" height="36" fill="none" stroke="#000" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8h36a2 2 0 0 1 2 2v22a2 2 0 0 1-2 2H22l-9 8v-8H6a2 2 0 0 1-2-2V10a2 2 0 0 1 2-2z"/><line x1="12" y1="18" x2="36" y2="18"/><line x1="12" y1="26" x2="28" y2="26"/><?php if (!$estado['sub']): // Si están apagados, se dibuja la raya que tacha el icono ?><line x1="8" y1="42" x2="42" y2="8" stroke="#fff" stroke-width="9"/><line x1="8" y1="42" x2="42" y2="8" stroke="#000" stroke-width="4"/><?php endif; ?></svg> <!-- Globo de texto con renglones; tachado si está apagado -->
            </a> <!-- Cierra el icono de subtítulos -->
            <?php if (!empty($paso['audio_url'])): // El icono de audio aparece solo si este paso tiene un archivo de audio cargado ?>
                <a class="icono" href="<?php echo $controles; ?>&amp;a=<?php echo $estado['audio'] ? 0 : 1; ?>" aria-label="Audio: <?php echo $estado['audio'] ? 'activado, tocar para apagarlo' : 'apagado, tocar para activarlo'; ?>" title="Audio"> <!-- Icono de audio -->
                    <svg viewBox="0 0 48 48" width="36" height="36" fill="none" stroke="#000" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 18h8l10-8v28l-10-8H5z"/><path d="M31 17a10 10 0 0 1 0 14"/><path d="M36 11a18 18 0 0 1 0 26"/><?php if (!$estado['audio']): // Si está apagado, se dibuja la raya que tacha el icono ?><line x1="8" y1="42" x2="42" y2="8" stroke="#fff" stroke-width="9"/><line x1="8" y1="42" x2="42" y2="8" stroke="#000" stroke-width="4"/><?php endif; ?></svg> <!-- Bocina con ondas; tachada si está apagado -->
                </a> <!-- Cierra el icono de audio -->
            <?php endif; // Fin del if de audio ?>
        </div> <!-- Cierra los iconos -->
        <div class="texto"> <!-- Texto del paso, a la derecha de los iconos -->
            <?php if ($mostrarTexto): // Subtítulo: el texto del paso, grande ?>
                <p class="subtitulo"><?php echo htmlspecialchars($textoPaso); // Muestra el texto del paso de forma segura ?></p> <!-- Texto del paso -->
                <p class="avance">Paso <?php echo $numeroPaso; ?> de <?php echo $totalPasos; ?></p> <!-- Avance, discreto -->
            <?php endif; // Fin del if ?>
        </div> <!-- Cierra el texto -->
    </div> <!-- Cierra la fila de iconos y texto -->

    <?php if (!empty($paso['audio_url']) && $estado['audio']): // Si el audio está activado y el paso tiene archivo, se reproduce ?>
        <audio src="<?php echo htmlspecialchars($paso['audio_url']); ?>" autoplay controls style="display: block; margin: 12px auto;"></audio> <!-- Reproductor con el audio del paso -->
    <?php endif; // Fin del if ?>
    </div> <!-- Cierra el contenedor del paso -->

    <script> /* Deslizar con el dedo y teclas de flecha: complementan a las flechas de la pantalla */
        (function () { /* Función que se ejecuta sola: así sus variables no se mezclan con las de otras partes */
            var zona = document.querySelector('.escena'); /* Zona del paso, con las direcciones guardadas */
            if (!zona || zona.dataset.deslizar !== '1') { return; } /* Si no hay zona, o en este paso no se puede deslizar, no hace nada */
            var anterior = zona.dataset.ant; /* Dirección del paso anterior (vacía si no hay) */
            var siguiente = zona.dataset.sig; /* Dirección del paso siguiente (vacía si no se puede avanzar) */
            var x0 = null, y0 = null; /* Dónde apoyó el dedo */
            document.addEventListener('touchstart', function (e) { /* Cuando el dedo toca la pantalla */
                x0 = e.changedTouches[0].clientX; /* Guarda la posición horizontal */
                y0 = e.changedTouches[0].clientY; /* Guarda la posición vertical */
            }, { passive: true }); /* passive: no frena el desplazamiento normal de la página */
            document.addEventListener('touchend', function (e) { /* Cuando el dedo se levanta */
                if (x0 === null) { return; } /* Si no había un toque previo, no hace nada */
                var dx = e.changedTouches[0].clientX - x0; /* Cuánto se movió hacia los lados */
                var dy = e.changedTouches[0].clientY - y0; /* Cuánto se movió hacia arriba o abajo */
                x0 = null; /* Olvida el toque */
                if (Math.abs(dx) < 70 || Math.abs(dy) > Math.abs(dx) * 0.6) { return; } /* Si el movimiento fue corto o más vertical que horizontal, lo toma como un toque o un desplazamiento y no hace nada */
                if (dx < 0 && siguiente) { window.location.href = siguiente; } /* Deslizó hacia la izquierda: paso siguiente */
                else if (dx > 0 && anterior) { window.location.href = anterior; } /* Deslizó hacia la derecha: paso anterior */
            }, { passive: true });
            document.addEventListener('keydown', function (e) { /* Teclas, para quien use una compu */
                if (e.key === 'ArrowRight' && siguiente) { window.location.href = siguiente; } /* Flecha derecha del teclado: siguiente */
                if (e.key === 'ArrowLeft' && anterior) { window.location.href = anterior; } /* Flecha izquierda: anterior */
            });
        })();
    </script>

    <?php if ($esOrdenar): // Solo en la actividad de ordenar: el código que mueve las gemas ?>
    <script> /* Actividad de ordenar las gemas: tocar una gema y después un orificio, o arrastrarla */
        (function () { /* Función que se ejecuta sola: así sus variables no se mezclan con las de otras partes */
            var zona = document.getElementById('ordenar'); /* Toda la actividad */
            if (!zona) { return; } /* Si por algún motivo no está, no hace nada */
            var solucion = zona.getAttribute('data-solucion').split(','); /* Colores en el orden correcto, de izquierda a derecha */
            var agujeros = Array.prototype.slice.call(zona.querySelectorAll('.agujero')); /* Los tres orificios, de izquierda a derecha */
            var casillas = Array.prototype.slice.call(zona.querySelectorAll('.casilla')); /* Las tres casillas de la bandeja */
            var cerradura = zona.querySelector('.cerradura'); /* La cerradura: la zona donde se sueltan las gemas */
            var bandeja = document.getElementById('ordenar-bandeja'); /* Bandeja con las gemas */
            var ayuda = document.getElementById('ordenar-ayuda'); /* Renglón de ayuda y mensajes */
            var botonSeguir = document.getElementById('ordenar-seguir'); /* Botón Seguir: aparece al resolver */
            var flechaSig = document.getElementById('flecha-sig'); /* Flecha derecha: aparece al resolver */
            var textoAyuda = ayuda.textContent; /* Texto de ayuda original, para volver a ponerlo */
            var elegida = null; /* Gema elegida con un toque, a la espera de que se toque un orificio */
            var arrastre = null; /* Datos del arrastre en curso (null si no se está arrastrando) */
            var ignorarClic = false; /* true justo después de soltar una gema arrastrada: el clic que viene a continuación no cuenta */
            var resuelta = false; /* true cuando la cerradura ya se abrió */

            function elegir(gema) { /* Marca una gema como elegida, o ninguna si se pasa null */
                if (elegida) { elegida.classList.remove('elegida'); } /* Le saca la marca a la anterior */
                elegida = gema; /* Recuerda la nueva */
                if (elegida) { elegida.classList.add('elegida'); } /* Marca la nueva */
                zona.classList.toggle('hay-elegida', !!elegida); /* Con una gema elegida, los orificios se resaltan para mostrar a dónde ir */
            } /* Fin de elegir */

            function volverABandeja(gema) { /* Devuelve una gema a su casilla de la bandeja */
                casillas[parseInt(gema.getAttribute('data-casilla'), 10)].appendChild(gema); /* Cada gema tiene su casilla: vuelve siempre al mismo lugar */
            } /* Fin de volverABandeja */

            function colocar(gema, agujero) { /* Pone una gema en un orificio */
                var ocupante = agujero.querySelector('.gema'); /* Gema que ya estaba en ese orificio, si había */
                var origen = gema.parentNode; /* De dónde viene la gema: una casilla de la bandeja u otro orificio */
                if (ocupante === gema) { return; } /* Ya estaba ahí: no hay nada que hacer */
                if (ocupante) { /* Si el orificio estaba ocupado */
                    if (origen.classList.contains('agujero')) { origen.appendChild(ocupante); } /* Si la gema venía de otro orificio, las dos se intercambian */
                    else { volverABandeja(ocupante); } /* Si venía de la bandeja, la que estaba vuelve a la bandeja */
                } /* Fin del if */
                agujero.appendChild(gema); /* Pone la gema en el orificio */
            } /* Fin de colocar */

            function mostrar(texto, clase) { /* Cambia el mensaje del renglón de ayuda */
                ayuda.textContent = texto; /* Pone el texto */
                ayuda.className = 'ordenar-ayuda' + (clase ? ' ' + clase : ''); /* Pone el estilo: ayuda común, mensaje neutro o mensaje positivo */
            } /* Fin de mostrar */

            function resolver() { /* Se llama cuando las tres gemas están en el orden correcto */
                resuelta = true; /* Recuerda que ya se resolvió */
                elegir(null); /* No queda ninguna gema elegida */
                zona.classList.add('resuelta'); /* Las gemas ya no se pueden mover */
                mostrar('', ''); /* Sin mensaje: no hace falta decir nada, se pasa directo al cofre abierto */
                setTimeout(function () { window.location.href = botonSeguir.getAttribute('href'); }, 800); /* Una pausa breve para ver la última gema en su lugar, y salta al paso siguiente: el cofre abierto */
                setTimeout(function () { /* Respaldo: si el salto tardara o fallara, a los 3,5 segundos aparecen el botón Seguir y la flecha */
                    botonSeguir.hidden = false; /* Aparece el botón Seguir */
                    if (flechaSig) { /* Si está la flecha derecha */
                        flechaSig.classList.remove('oculta'); /* La muestra */
                        flechaSig.removeAttribute('tabindex'); /* Y vuelve a poder usarse con el teclado */
                    } /* Fin del if */
                }, 3500); /* Fin del respaldo */
            } /* Fin de resolver */

            function revisar() { /* Mira cómo quedaron las gemas y avisa */
                var puestos = agujeros.map(function (a) { /* Para cada orificio... */
                    var g = a.querySelector('.gema'); /* ...busca la gema que tiene */
                    return g ? g.getAttribute('data-color') : null; /* y devuelve su color (null si está vacío) */
                }); /* Fin del map */
                var completo = puestos.every(function (c) { return c !== null; }); /* true si los tres orificios tienen gema */
                if (!completo) { mostrar(textoAyuda, ''); return; } /* Si falta alguna, vuelve el texto de ayuda */
                var acierto = puestos.every(function (c, i) { return c === solucion[i]; }); /* true si cada color está en su lugar */
                if (acierto) { resolver(); } /* Todo bien: se abre el cofre */
                else { mostrar('Probemos de nuevo.', 'mensaje'); } /* Si no, mensaje neutro: sin sonidos ni alertas de error; se puede intentar sin límite */
            } /* Fin de revisar */

            function agujeroCerca(x, y) { /* Busca el orificio al que se quiere llevar la gema: el más cercano al dedo, siempre que el dedo esté sobre la cerradura o apenas alrededor */
                var z = cerradura.getBoundingClientRect(); /* Posición y tamaño de la cerradura en la pantalla */
                var zonaOrificio = agujeros[0].getBoundingClientRect().width; /* Ancho de la zona táctil de un orificio: sirve de medida, porque la cerradura puede ser grande o chica según la imagen y la pantalla */
                var margenLado = zonaOrificio * 0.6; /* Tolerancia a los costados: poco más de medio orificio */
                var margenAbajo = zonaOrificio * 0.6; /* Tolerancia hacia abajo */
                var margenArriba = 12; /* Hacia arriba casi no hay: ahí están las gemas */
                if (x < z.left - margenLado || x > z.right + margenLado || y < z.top - margenArriba || y > z.bottom + margenAbajo) { return null; } /* Lejos de la cerradura: no se quiere poner en ningún orificio */
                var mejor = null; /* Orificio elegido */
                var mejorDist = Infinity; /* Distancia del elegido */
                agujeros.forEach(function (a) { /* Mira cada orificio */
                    var r = a.getBoundingClientRect(); /* Su posición y tamaño en la pantalla */
                    var dx = x - (r.left + r.width / 2); /* Distancia horizontal al centro del orificio */
                    var dy = y - (r.top + r.height / 2); /* Distancia vertical al centro del orificio */
                    var d = Math.sqrt(dx * dx + dy * dy); /* Distancia en línea recta */
                    if (d < mejorDist) { mejor = a; mejorDist = d; } /* Se queda con el más cercano, sin importar cuán lejos esté: lo que cuenta es haber soltado sobre la cerradura */
                }); /* Fin del forEach */
                return mejor; /* Devuelve el orificio más cercano, o null si se soltó lejos de la cerradura */
            } /* Fin de agujeroCerca */

            zona.addEventListener('click', function (e) { /* Toque o clic: sirve para el modo "tocar una gema y después el orificio" */
                if (ignorarClic || resuelta || arrastre) { return; } /* Justo después de arrastrar, con la cerradura ya abierta, o mientras otro dedo está arrastrando, no hace nada */
                var gema = e.target.closest('.gema'); /* ¿Se tocó una gema? */
                var agujero = e.target.closest('.agujero'); /* ¿Se tocó un orificio (o una gema puesta en él)? */
                if (gema && !agujero) { /* Gema que está en la bandeja */
                    elegir(elegida === gema ? null : gema); /* Si ya estaba elegida, se desmarca; si no, se elige */
                } else if (agujero) { /* Orificio, vacío u ocupado */
                    if (elegida) { /* Si hay una gema elegida */
                        colocar(elegida, agujero); /* La pone en ese orificio */
                        elegir(null); /* Ya no queda ninguna elegida */
                        revisar(); /* Mira si se resolvió */
                    } else if (gema) { /* Sin gema elegida, se tocó una que ya estaba puesta */
                        volverABandeja(gema); /* La saca: vuelve a la bandeja */
                        revisar(); /* Actualiza el mensaje */
                    } /* Fin del if interno */
                } else { /* Se tocó otra cosa de la actividad */
                    elegir(null); /* Se desmarca lo elegido */
                } /* Fin del if */
            }); /* Fin del click */

            zona.addEventListener('keydown', function (e) { /* Teclado: Enter o espacio equivalen a tocar */
                if (e.key !== 'Enter' && e.key !== ' ') { return; } /* Solo esas dos teclas */
                var objetivo = e.target.closest('.gema, .agujero'); /* Gema u orificio que tiene el foco */
                if (!objetivo) { return; } /* Si no hay, no hace nada */
                e.preventDefault(); /* Evita que la barra espaciadora baje la página */
                objetivo.click(); /* Lo trata como un toque */
            }); /* Fin del keydown */

            zona.addEventListener('contextmenu', function (e) { e.preventDefault(); }); /* Evita el menú que aparece al dejar el dedo apoyado mucho rato */

            document.addEventListener('pointerdown', function () { ignorarClic = false; }, true); /* Apenas empieza un toque nuevo, deja de ignorar clics: el clic "fantasma" de un arrastre llega siempre antes que el toque siguiente */

            zona.addEventListener('pointerdown', function (e) { /* Se apoyó el dedo o el mouse: puede ser el comienzo de un arrastre */
                if (resuelta) { return; } /* Con la cerradura abierta, nada se mueve */
                if (arrastre) { /* ¿Ya hay un dedo arrastrando una gema? */
                    if (Date.now() - arrastre.visto < 3000) { return; } /* Sí: manda ese primer dedo, y se ignora cualquier otro que se apoye (la mano de un adulto, la palma, otro dedo) */
                    limpiarArrastre(); /* Ese arrastre quedó colgado hace más de 3 segundos (por ejemplo, se perdió el aviso de que se levantó el dedo): se descarta para no trabar la actividad */
                } /* Fin del if */
                if (e.pointerType === 'mouse' && e.button !== 0) { return; } /* Con el mouse, solo el botón izquierdo */
                var gema = e.target.closest('.gema'); /* ¿Se apoyó sobre una gema? */
                if (!gema) { return; } /* Si no, no hay nada que arrastrar */
                arrastre = { gema: gema, id: e.pointerId, x0: e.clientX, y0: e.clientY, ultimoX: e.clientX, ultimoY: e.clientY, visto: Date.now(), activo: false, fantasma: null }; /* Guarda dónde empezó; todavía no es un arrastre: puede ser solo un toque */
            }); /* Fin del pointerdown */

            document.addEventListener('pointermove', function (e) { /* Se mueve el dedo o el mouse */
                if (!arrastre || e.pointerId !== arrastre.id) { return; } /* Si no hay arrastre posible, no hace nada */
                arrastre.ultimoX = e.clientX; /* Recuerda dónde está el dedo... */
                arrastre.ultimoY = e.clientY; /* ...por si el sistema corta el gesto antes de soltar */
                arrastre.visto = Date.now(); /* Y cuándo fue la última vez que se lo vio: sirve para descartar un arrastre colgado */
                if (!arrastre.activo) { /* Todavía no se consideraba arrastre */
                    var dx = e.clientX - arrastre.x0; /* Cuánto se movió hacia los lados */
                    var dy = e.clientY - arrastre.y0; /* Cuánto se movió hacia arriba o abajo */
                    if (Math.sqrt(dx * dx + dy * dy) < 10) { return; } /* Si se movió muy poco, sigue siendo un toque */
                    var r = arrastre.gema.getBoundingClientRect(); /* Tamaño de la gema en la pantalla */
                    var f = document.createElement('div'); /* Crea una copia que sigue al dedo */
                    f.className = 'gema-fantasma'; /* Estilo de la copia */
                    f.style.width = r.width + 'px'; /* Mismo ancho que la gema */
                    f.style.height = r.width + 'px'; /* Mismo alto: es cuadrada */
                    var img = document.createElement('img'); /* Dibujo de la copia */
                    img.src = arrastre.gema.querySelector('img').src; /* El mismo dibujo que la gema */
                    img.alt = ''; /* Sin texto alternativo: es decorativa */
                    f.appendChild(img); /* Pone el dibujo en la copia */
                    document.body.appendChild(f); /* Muestra la copia en la pantalla */
                    arrastre.fantasma = f; /* Recuerda la copia */
                    arrastre.activo = true; /* Ahora sí es un arrastre */
                    arrastre.gema.classList.add('levantada'); /* La gema original se ve apagada mientras se la lleva */
                    elegir(null); /* Si había una gema elegida, se desmarca */
                } /* Fin del if */
                var ancho = parseFloat(arrastre.fantasma.style.width); /* Ancho de la copia */
                arrastre.fantasma.style.left = (e.clientX - ancho / 2) + 'px'; /* Centra la copia bajo el dedo, horizontalmente */
                arrastre.fantasma.style.top = (e.clientY - ancho / 2) + 'px'; /* Y verticalmente */
                var cerca = agujeroCerca(e.clientX, e.clientY); /* Orificio que se alcanzaría si se soltara ahora */
                agujeros.forEach(function (a) { a.classList.toggle('sobre', a === cerca); }); /* Ese orificio se marca con un aro: así se ve a dónde va a caer la gema */
            }); /* Fin del pointermove */

            function limpiarArrastre() { /* Descarta un arrastre abandonado, sin acomodar la gema */
                if (!arrastre) { return; } /* Si no hay ninguno, no hace nada */
                if (arrastre.fantasma && arrastre.fantasma.parentNode) { arrastre.fantasma.parentNode.removeChild(arrastre.fantasma); } /* Saca la copia de la pantalla */
                arrastre.gema.classList.remove('levantada'); /* La gema vuelve a verse normal */
                agujeros.forEach(function (o) { o.classList.remove('sobre'); }); /* Se saca el aro de los orificios */
                arrastre = null; /* Ya no hay arrastre */
            } /* Fin de limpiarArrastre */

            function terminarArrastre(e, soltar) { /* Cierra un arrastre. soltar = true si se soltó la gema; false si el sistema cortó el gesto (por ejemplo, al apoyar otro dedo o la palma): en los dos casos la gema se acomoda donde estaba el dedo */
                if (!arrastre || e.pointerId !== arrastre.id) { return; } /* Si no hay arrastre, no hace nada */
                var a = arrastre; /* Copia los datos del arrastre */
                arrastre = null; /* Ya no hay arrastre */
                if (!a.activo) { return; } /* Si nunca pasó de un toque, el evento click se ocupa */
                var x = a.ultimoX; /* Por defecto, el último lugar donde se vio el dedo */
                var y = a.ultimoY; /* Idem, en vertical */
                if (soltar && !(e.clientX === 0 && e.clientY === 0)) { x = e.clientX; y = e.clientY; } /* Si el evento trae la posición del dedo, usa esa */
                a.fantasma.parentNode.removeChild(a.fantasma); /* Saca la copia de la pantalla */
                a.gema.classList.remove('levantada'); /* La gema vuelve a verse normal */
                agujeros.forEach(function (o) { o.classList.remove('sobre'); }); /* Se saca el aro de los orificios */
                var destino = agujeroCerca(x, y); /* Busca el orificio más cercano, si se soltó sobre la cerradura */
                if (destino) { colocar(a.gema, destino); } /* Si lo hay, pone la gema ahí */
                else if (a.gema.parentNode.classList.contains('agujero')) { volverABandeja(a.gema); } /* Si no, y venía de un orificio, queda sacada: vuelve a la bandeja */
                revisar(); /* Mira cómo quedó todo */
                ignorarClic = true; /* El clic que el navegador genera al soltar no debe contar como un toque */
                setTimeout(function () { ignorarClic = false; }, 300); /* Y vuelve a contar enseguida */
            } /* Fin de terminarArrastre */

            document.addEventListener('pointerup', function (e) { terminarArrastre(e, true); }); /* Se soltó el dedo o el mouse */
            document.addEventListener('pointercancel', function (e) { terminarArrastre(e, false); }); /* El sistema canceló el gesto */
        })();
    </script>
    <?php endif; // Fin del if de la actividad de ordenar ?>
<?php endif; // Fin del if de las pantallas ?>

</body> <!-- Cierra el cuerpo -->
</html> <!-- Cierra el documento -->

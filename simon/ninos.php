<?php // Abre PHP
    session_start(); // Retoma la sesión para saber si hay alguien logueado
    if (!isset($_SESSION['id_profesional'])) { // ¿NO hay un profesional logueado? Esta pantalla es solo para el profesional
        header("Location: login.php"); // Si no hay profesional logueado, manda al login
        exit; // Corta acá: no se muestra ni se procesa nada más
    } // Fin del guardia de entrada

    require __DIR__ . "/conexion.php"; // Trae la conexión a la base, definida una sola vez en conexion.php: deja lista la variable $conexion

    function marcar($actual, $valor) { // Función auxiliar: devuelve " selected" si el valor actual coincide con la opción
        return ($actual === $valor) ? " selected" : ""; // Así la lista desplegable arranca con lo que ya tenía guardado
    } // Fin de la función

    $param = $_GET['n'] ?? $_POST['n'] ?? ""; // Qué se pidió: "nuevo" para agregar, un número para editar a ese niño, o nada para ver la lista
    $modo = "lista"; // Pantalla a mostrar: "lista", "nuevo" o "editar"
    $idNino = 0; // Id del niño que se edita; 0 si no hay
    if ($param === "nuevo") { // ¿Se pidió agregar un niño nuevo?
        $modo = "nuevo"; // Muestra el formulario vacío
    } elseif ((int)$param > 0) { // ¿Se pidió un niño concreto?
        $idNino = (int)$param; // Guarda su id como número entero
        $modo = (($_GET['a'] ?? "") === "ver") ? "ver" : "editar"; // Si la dirección trae a=ver, muestra el perfil para mirar; si no, el formulario para editarlo
    } // Fin del if

    $mensajeError = ""; // Mensaje de error, vacío al principio
    $mensajeOk = isset($_GET['ok']) ? "Cambios guardados." : (isset($_GET['creado']) ? "Perfil agregado." : (isset($_GET['reset']) ? "Acceso de la familia restablecido: al ingresar con su email, va a elegir una contraseña nueva." : "")); // Aviso de éxito según cómo se llegó a la pantalla (ok=1 al editar, creado=1 al agregar)
    $noEncontrado = false; // true si se pidió un niño que no existe

    $v = ["nombre" => "", "apellido" => "", "fecha_nacimiento" => "", "nivel_autonomia" => "", "apoyo_necesario" => "independiente", "tipo_imagen" => "foto", "cantidad_pasos" => 3, "audio_activo" => 0, "subtitulo_activo" => 0, "seleccion_activa" => 0, "email_familia" => ""]; // Valores que muestra el formulario; empiezan como los de un niño nuevo
    $nino = false; // Datos del niño que se edita; false si no hay
    $sinActivar = false; // true si la familia del niño todavía no activó su cuenta

    if ($modo === "editar" || $modo === "ver") { // Si se edita o se mira a un niño, se busca
        $q = $conexion->prepare("SELECT i.*, f.email AS email_familia, (f.contrasena IS NULL) AS sin_activar FROM infantes i LEFT JOIN familia f ON f.id_familia = i.id_familia WHERE i.id_infante = ?"); // Busca al niño junto con el email de su familia y si esa familia todavía no activó su cuenta
        $q->execute([$idNino]); // Ejecuta con el id
        $nino = $q->fetch(PDO::FETCH_ASSOC); // Trae la fila, o false si no existe
        if ($nino) { // Si existe
            $v = $nino; // Los valores del formulario pasan a ser los guardados
            $sinActivar = ($nino['sin_activar'] == 1); // true si se puede corregir el email de la familia
        } else { // Si no existe
            $modo = "lista"; // Vuelve a la lista
            $noEncontrado = true; // Y avisa
        } // Fin del if
    } // Fin del if

    if ($_SERVER["REQUEST_METHOD"] == "POST" && ($_POST['accion'] ?? "") === "restablecer_familia" && $modo === "editar" && $nino) { // ¿Se apretó "Restablecer acceso de la familia"?
        $consulta = $conexion->prepare("UPDATE familia SET contrasena = NULL WHERE id_familia = ?"); // Deja la cuenta de la familia sin contraseña: vuelve a quedar "sin activar"
        $consulta->execute([$nino['id_familia']]); // Ejecuta con la familia de este niño o niña
        header("Location: ninos.php?n=" . $idNino . "&a=ver&reset=1"); // Vuelve al perfil con el aviso
        exit; // Corta acá, después de redirigir
    } // Fin del if

    if ($_SERVER["REQUEST_METHOD"] == "POST" && ($modo === "nuevo" || $modo === "editar")) { // ¿Se envió el formulario?
        $v['nombre'] = trim($_POST['nombre'] ?? ""); // Lee el nombre; trim() saca los espacios de los costados
        $v['apellido'] = trim($_POST['apellido'] ?? ""); // Lee el apellido
        $v['fecha_nacimiento'] = $_POST['fecha_nacimiento'] ?? ""; // Lee la fecha (llega como AAAA-MM-DD)
        $v['nivel_autonomia'] = $_POST['nivel_autonomia'] ?? ""; // Lee el nivel de autonomía
        $v['apoyo_necesario'] = $_POST['apoyo_necesario'] ?? ""; // Lee el apoyo elegido
        $v['tipo_imagen'] = $_POST['tipo_imagen'] ?? ""; // Lee el tipo de imagen elegido
        $v['cantidad_pasos'] = (int)($_POST['cantidad_pasos'] ?? 1); // Lee la cantidad de pasos, como número entero
        $v['audio_activo'] = isset($_POST['audio_activo']) ? 1 : 0; // Casilla de audio: 1 si está tildada, 0 si no
        $v['subtitulo_activo'] = isset($_POST['subtitulo_activo']) ? 1 : 0; // Casilla de subtítulo
        // La selección de opciones ya no se elige en el perfil: se activa dentro de cada historia, con el botón de la barra de iconos
        $emailForm = trim($_POST['email_familia'] ?? ""); // Lee el email de la familia, si el formulario lo trae
        $editaEmail = ($modo === "nuevo" || $sinActivar); // true si en este caso el email se escribe o se puede corregir
        if ($modo === "nuevo") { // Si es un niño nuevo
            $v['email_familia'] = $emailForm; // El email del formulario se conserva por si hay que volver a mostrarlo
        } // Fin del if

        if ($v['nombre'] === "" || $v['apellido'] === "" || $v['fecha_nacimiento'] === "") { // ¿Falta algún dato obligatorio?
            $mensajeError = "Nombre, apellido y fecha de nacimiento son obligatorios."; // Avisa y no guarda nada
        } elseif ($v['cantidad_pasos'] < 1) { // ¿La cantidad de pasos es menor que 1?
            $mensajeError = "La cantidad de pasos tiene que ser al menos 1."; // Avisa y no guarda nada
        } elseif ($editaEmail && !filter_var($emailForm, FILTER_VALIDATE_EMAIL)) { // ¿Hay que revisar el email y no tiene un formato válido?
            $mensajeError = "El email de la familia no es válido."; // Avisa y no guarda nada
        } elseif ($modo === "nuevo") { // Todo en orden y es un niño nuevo: se agrega
            try { // Intenta guardar todo; si algo falla, salta al catch
                $conexion->beginTransaction(); // Abre una transacción: o se guarda todo o nada
                $q = $conexion->prepare("SELECT id_familia FROM familia WHERE email = ?"); // Busca si ya existe una familia con ese email
                $q->execute([$emailForm]); // Ejecuta con el email
                $familia = $q->fetch(PDO::FETCH_ASSOC); // Trae la fila, o false si no hay
                if ($familia) { // Ya existe una familia con ese email
                    $idFamilia = $familia['id_familia']; // El niño se suma a esa misma cuenta
                } else { // No existe: hay que crearla
                    $consulta = $conexion->prepare("INSERT INTO familia (nombre, email, contrasena) VALUES (?, ?, NULL)"); // Crea la familia sin contraseña: la va a elegir ella al activar la cuenta
                    $consulta->execute(["Familia " . $v['apellido'], $emailForm]); // Nombre provisorio "Familia Apellido" y el email
                    $idFamilia = $conexion->lastInsertId(); // Id que la base le asignó a la familia nueva
                } // Fin del if
                $consulta = $conexion->prepare("INSERT INTO infantes (id_familia, nombre, apellido, fecha_nacimiento, nivel_autonomia, apoyo_necesario, tipo_imagen, cantidad_pasos, audio_activo, subtitulo_activo, seleccion_activa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"); // Prepara el alta del niño: once columnas, once huecos
                $consulta->execute([$idFamilia, $v['nombre'], $v['apellido'], $v['fecha_nacimiento'], $v['nivel_autonomia'], $v['apoyo_necesario'], $v['tipo_imagen'], $v['cantidad_pasos'], $v['audio_activo'], $v['subtitulo_activo'], $v['seleccion_activa']]); // Ejecuta poniendo cada valor en su hueco, en el mismo orden
                $idNuevo = $conexion->lastInsertId(); // Id que la base le asignó al niño recién agregado
                $conexion->commit(); // Confirma: recién acá quedan guardados los cambios
                header("Location: ninos.php?n=" . $idNuevo . "&a=ver&creado=1"); // Muestra el perfil recién agregado con el aviso; así recargar la página no vuelve a agregar
                exit; // Corta acá, después de redirigir
            } catch (PDOException $error) { // Si algo falló
                $conexion->rollBack(); // Deshace todo lo hecho dentro de la transacción
                $mensajeError = "No se pudo guardar: " . $error->getMessage(); // Avisa el motivo
            } // Fin del try/catch
        } else { // Todo en orden y es un niño que ya existe: se actualiza
            $emailRepetido = false; // Bandera: pasa a true si el email nuevo ya lo usa otra familia
            if ($sinActivar && $emailForm !== $nino['email_familia']) { // ¿Se cambió el email de una familia sin activar?
                $q = $conexion->prepare("SELECT COUNT(*) FROM familia WHERE email = ? AND id_familia <> ?"); // Prepara: ¿otra familia ya tiene ese email?
                $q->execute([$emailForm, $nino['id_familia']]); // Ejecuta con el email nuevo y la familia de este niño
                $emailRepetido = $q->fetchColumn() > 0; // true si ya existe en otra familia
            } // Fin del if
            if ($emailRepetido) { // Si el email ya lo usa otra familia
                $mensajeError = "Ese email ya pertenece a otra familia."; // Avisa y no guarda nada
            } else { // Todo en orden: se guarda
                try { // Intenta guardar todo; si algo falla, salta al catch
                    $conexion->beginTransaction(); // Abre una transacción
                    $consulta = $conexion->prepare("UPDATE infantes SET nombre = ?, apellido = ?, fecha_nacimiento = ?, nivel_autonomia = ?, apoyo_necesario = ?, tipo_imagen = ?, cantidad_pasos = ?, audio_activo = ?, subtitulo_activo = ?, seleccion_activa = ? WHERE id_infante = ?"); // Prepara la actualización del niño: diez datos y el id
                    $consulta->execute([$v['nombre'], $v['apellido'], $v['fecha_nacimiento'], $v['nivel_autonomia'], $v['apoyo_necesario'], $v['tipo_imagen'], $v['cantidad_pasos'], $v['audio_activo'], $v['subtitulo_activo'], $v['seleccion_activa'], $idNino]); // Ejecuta con cada valor en su hueco, en el mismo orden
                    if ($sinActivar && $emailForm !== $nino['email_familia']) { // Si se corrigió el email de una familia sin activar
                        $consulta = $conexion->prepare("UPDATE familia SET email = ? WHERE id_familia = ? AND contrasena IS NULL"); // Cambia el email solo si la familia sigue sin contraseña
                        $consulta->execute([$emailForm, $nino['id_familia']]); // Ejecuta con el email nuevo y la familia
                    } // Fin del if
                    $conexion->commit(); // Confirma: recién acá quedan guardados los cambios
                    header("Location: ninos.php?n=" . $idNino . "&a=ver&ok=1"); // Redirige al perfil con ok=1: se ve cómo quedó y recargar la página no vuelve a guardar
                    exit; // Corta acá, después de redirigir
                } catch (PDOException $error) { // Si algo falló
                    $conexion->rollBack(); // Deshace todo lo hecho dentro de la transacción
                    $mensajeError = "No se pudo guardar: " . $error->getMessage(); // Avisa el motivo
                } // Fin del try/catch
            } // Fin del if de email repetido
        } // Fin del if de validaciones
    } // Fin del if de POST

    $historiasAsignadas = []; // Títulos de las historias asignadas al niño; se llena solo al mirar un perfil
    $edad = null; // Edad en años; se calcula solo al mirar un perfil
    if ($modo === "ver") { // Si se mira un perfil
        $q = $conexion->prepare("SELECT titulo FROM historias WHERE id_infante = ? ORDER BY id_historia_origen"); // Prepara: ¿qué historias tiene asignadas este niño?
        $q->execute([$idNino]); // Ejecuta con el id
        $historiasAsignadas = $q->fetchAll(PDO::FETCH_COLUMN); // Trae solo la columna de títulos
        $edad = (new DateTime($nino['fecha_nacimiento']))->diff(new DateTime('today'))->y; // Calcula la edad: diferencia en años entre la fecha de nacimiento y hoy
    } // Fin del if

    if ($modo === "lista") { // Si hay que mostrar la lista
        $lista = $conexion->query("SELECT id_infante, nombre, apellido FROM infantes ORDER BY apellido, nombre")->fetchAll(PDO::FETCH_ASSOC); // Lista de niños ya cargados
    } // Fin del if
?>
<!DOCTYPE html> <!-- Avisa al navegador que es HTML5 -->
<html lang="es"> <!-- Abre el documento en español -->
<head> <!-- Abre el encabezado -->
    <meta charset="UTF-8"> <!-- Para que tildes y ñ se vean bien -->
    <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- Hace que el celular use su ancho real: sin esta línea, dibuja la página como en una compu y la achica -->
    <title>Perfiles - S.I.M.O.N.</title> <!-- Texto de la pestaña del navegador -->
    <link rel="stylesheet" href="estilos.css?v=<?php echo filemtime(__DIR__ . "/estilos.css"); // Fecha de la última modificación: si el archivo cambia, el navegador baja la versión nueva ?>"> <!-- Carga la hoja de estilos compartida -->
</head> <!-- Cierra el encabezado -->
<body> <!-- Abre el cuerpo -->

<?php if ($modo === "lista"): // PANTALLA 1: la lista de niños ?>
    <h1>Perfiles de niños y niñas</h1> <!-- Título -->
    <p><a href="menu.php">Volver al menú</a></p> <!-- Link para volver al menú -->

    <?php if ($mensajeOk): // Si hay aviso de éxito, se muestra hasta el endif ?>
        <p class="success"><?php echo htmlspecialchars($mensajeOk); ?></p> <!-- class="success" le da el estilo verde -->
    <?php endif; // Fin del if ?>
    <?php if ($noEncontrado): // Si se pidió un niño que no existe ?>
        <p class="error">No se encontró ese perfil.</p> <!-- Aviso -->
    <?php endif; // Fin del if ?>

    <div style="display: flex; flex-wrap: wrap; gap: 16px; align-items: stretch; margin-bottom: 16px;"> <!-- Fila con dos tarjetas lado a lado, separadas: agregar un niño y abrir una ficha; en un celular angosto se apilan -->
        <form method="GET" action="ninos.php" style="flex: 1; min-width: 240px; margin: 0;"> <!-- Tarjeta de agregar: se envía por GET y abre el formulario vacío -->
            <input type="hidden" name="n" value="nuevo"> <!-- Campo oculto: le dice a la pantalla que se quiere agregar un niño nuevo -->
            <button type="submit">+ Agregar niño/a</button> <!-- Botón de agregar -->
        </form> <!-- Cierra el formulario -->

        <form method="GET" action="ficha.php" style="flex: 1; min-width: 240px; margin: 0;"> <!-- Tarjeta de la ficha: se envía por GET con el niño elegido y abre su ficha -->
            <label>Ficha de:</label> <!-- Etiqueta de la lista -->
            <?php if (count($lista) === 0): // Si todavía no hay ningún niño cargado ?>
                <p>Todavía no hay perfiles cargados.</p> <!-- Aviso -->
            <?php else: // Si hay niños, se muestra la lista desplegable, ya ordenada por apellido y nombre ?>
                <select name="n" required> <!-- Lista desplegable; lo elegido llega en $_GET['n'] -->
                    <option value="">Elegir...</option> <!-- Opción vacía inicial: required obliga a elegir otra -->
                    <?php foreach ($lista as $n): // Repite el bloque siguiente por cada niño ?>
                        <option value="<?php echo (int)$n['id_infante']; // Pone el id del niño como valor de la opción ?>"><?php echo htmlspecialchars($n['apellido'] . ", " . $n['nombre']); // Muestra "Apellido, Nombre" de forma segura ?></option> <!-- Una opción por niño -->
                    <?php endforeach; // Fin del foreach ?>
                </select> <!-- Cierra la lista -->
                <div class="par-botones"> <!-- Los dos botones, uno al lado del otro -->
                    <button type="submit">Abrir ficha</button> <!-- Botón que abre la ficha del niño elegido, para cargar una nota -->
                    <button type="submit" formaction="ver_ficha.php" class="boton-sec">Ver ficha</button> <!-- Mismo formulario, pero formaction cambia la pantalla de destino: abre la hoja de la ficha, de solo lectura -->
                </div> <!-- Cierra los botones -->
            <?php endif; // Fin del if ?>
        </form> <!-- Cierra el formulario -->
    </div> <!-- Cierra la fila de tarjetas -->

    <form method="GET" action="ninos.php"> <!-- Formulario de modificar: se envía por GET con el niño elegido -->
        <label>Perfil:</label> <!-- Etiqueta de la lista -->
        <?php if (count($lista) === 0): // Si todavía no hay ningún niño cargado ?>
            <p>Todavía no hay perfiles cargados.</p> <!-- Aviso -->
        <?php else: // Si hay niños, se muestra la lista desplegable ?>
            <select name="n" required> <!-- Lista desplegable; lo elegido llega en $_GET['n'] -->
                <option value="">Elegir...</option> <!-- Opción vacía inicial: required obliga a elegir otra -->
                <?php foreach ($lista as $n): // Repite el bloque siguiente por cada niño ?>
                    <option value="<?php echo (int)$n['id_infante']; // Pone el id del niño como valor de la opción ?>"><?php echo htmlspecialchars($n['apellido'] . ", " . $n['nombre']); // Muestra "Apellido, Nombre" de forma segura ?></option> <!-- Una opción por niño -->
                <?php endforeach; // Fin del foreach ?>
            </select> <!-- Cierra la lista -->
            <button type="submit" name="a" value="ver">Ver</button> <!-- Botón que muestra el perfil del niño elegido, solo para mirar -->
            <button type="submit" name="a" value="editar">Editar</button> <!-- Botón que abre el formulario para corregir el perfil -->
        <?php endif; // Fin del if ?>
    </form> <!-- Cierra el formulario -->

<?php elseif ($modo === "ver"): // PANTALLA: el perfil de un niño, solo para mirar ?>
    <?php
        $th = "text-align: left; padding: 10px; width: 45%; border-bottom: 1px solid #d5dae3; font-size: 15px; color: #5a6270; font-weight: bold;"; // Estilo de la celda de la izquierda (el nombre del dato)
        $td = "padding: 10px; border-bottom: 1px solid #d5dae3; font-size: 15px;"; // Estilo de la celda de la derecha (el valor)
        $etiquetaTipo = ["foto" => "Foto real", "pictograma" => "Pictograma", "ambas" => "Ambas"]; // Cómo se muestra cada tipo de imagen
        $siNo = ["0" => "No", "1" => "Sí"]; // Cómo se muestran las casillas
    ?>
    <h1>Perfil</h1> <!-- Título -->
    <div class="controles"> <!-- Botones para volver -->
        <a class="boton-sec" href="ninos.php">Volver a perfiles</a> <!-- Vuelve a la pantalla anterior -->
        <a class="boton-sec" href="menu.php">Volver al menú</a> <!-- Vuelve al menú -->
    </div> <!-- Cierra los botones --> <!-- Links de navegación -->

    <?php if ($mensajeOk): // Si se acaba de guardar o agregar, se muestra el aviso ?>
        <p class="success"><?php echo htmlspecialchars($mensajeOk); ?></p> <!-- class="success" le da el estilo verde -->
    <?php endif; // Fin del if ?>

    <h2><?php echo htmlspecialchars($nino['apellido'] . ", " . $nino['nombre']); // Muestra "Apellido, Nombre" de forma segura ?></h2> <!-- Nombre del niño -->
    <table style="width: 100%; border-collapse: collapse; background: #ffffff; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.1);"> <!-- Tabla del perfil: una fila por dato -->
        <tr><th style="<?php echo $th; ?>">Fecha de nacimiento</th><td style="<?php echo $td; ?>"><?php echo date("d/m/Y", strtotime($nino['fecha_nacimiento'])); // Fecha en formato día/mes/año ?> (<?php echo (int)$edad; ?> años)</td></tr> <!-- Fecha y edad -->
        <tr><th style="<?php echo $th; ?>">Observaciones</th><td style="<?php echo $td; ?>"><?php echo htmlspecialchars(($nino['nivel_autonomia'] ?? "") !== "" ? $nino['nivel_autonomia'] : "—"); // Si no cargó nada, muestra una raya ?></td></tr> <!-- Observaciones libres de la profesional (la columna se llama nivel_autonomia por la historia del proyecto) -->
        <tr><th style="<?php echo $th; ?>">Apoyo necesario</th><td style="<?php echo $td; ?>"><?php echo htmlspecialchars(ucfirst($nino['apoyo_necesario'] ?? "")); // Primera letra en mayúscula ?></td></tr> <!-- Apoyo necesario -->
        <tr><th style="<?php echo $th; ?>">Tipo de imagen</th><td style="<?php echo $td; ?>"><?php echo htmlspecialchars($etiquetaTipo[$nino['tipo_imagen']] ?? $nino['tipo_imagen']); ?></td></tr> <!-- Tipo de imagen -->
        <tr><th style="<?php echo $th; ?>">Cantidad de pasos por pantalla</th><td style="<?php echo $td; ?>"><?php echo (int)$nino['cantidad_pasos']; ?></td></tr> <!-- Cantidad de pasos -->
        <tr><th style="<?php echo $th; ?>">Email de la familia</th><td style="<?php echo $td; ?>"><?php echo htmlspecialchars($nino['email_familia'] ?? "—"); ?></td></tr> <!-- Email de la familia -->
        <tr><th style="<?php echo $th; ?>">Cuenta de la familia</th><td style="<?php echo $td; ?>"><?php echo $sinActivar ? "Sin activar" : "Activada"; // Si la familia ya eligió su contraseña o todavía no ?></td></tr> <!-- Estado de la cuenta -->
        <tr><th style="<?php echo $th; ?>">Historias asignadas</th><td style="<?php echo $td; ?>"><?php echo count($historiasAsignadas) === 0 ? "Ninguna" : htmlspecialchars(implode(", ", $historiasAsignadas)); // Los títulos separados por coma ?></td></tr> <!-- Historias asignadas -->
    </table> <!-- Cierra la tabla -->

    <?php if (!$sinActivar): // Si la familia ya activó su cuenta, se ofrece restablecer su acceso ?>
        <form method="POST" action="ninos.php" onsubmit="return confirm('La familia va a poder elegir una contraseña nueva la próxima vez que ingrese con su email. ¿Restablecer su acceso?');"> <!-- Formulario de restablecer: pregunta antes de enviar -->
            <input type="hidden" name="n" value="<?php echo (int)$idNino; ?>"> <!-- Campo oculto: el niño o la niña cuya familia se restablece -->
            <input type="hidden" name="accion" value="restablecer_familia"> <!-- Campo oculto: dice qué se quiere hacer -->
            <p>Si la familia olvidó su contraseña, restablecé su acceso: va a elegir una nueva al ingresar con su email.</p> <!-- Explicación -->
            <button type="submit">Restablecer acceso de la familia</button> <!-- Botón de restablecer -->
        </form> <!-- Cierra el formulario -->
    <?php endif; // Fin del if ?>

    <form method="GET" action="ninos.php"> <!-- Formulario del botón Editar: se envía por GET -->
        <input type="hidden" name="n" value="<?php echo (int)$idNino; ?>"> <!-- Campo oculto: el niño que se edita -->
        <input type="hidden" name="a" value="editar"> <!-- Campo oculto: pide el formulario de edición -->
        <button type="submit">Editar este perfil</button> <!-- Botón que abre el formulario de edición -->
    </form> <!-- Cierra el formulario -->

<?php else: // PANTALLA 2: el formulario, para un niño nuevo o uno existente ?>
    <h1><?php echo ($modo === "nuevo") ? "Agregar niño/a" : "Editar perfil"; // El título cambia según el caso ?></h1> <!-- Título -->
    <div class="controles"> <!-- Botones para volver -->
        <a class="boton-sec" href="ninos.php">Volver a la lista</a> <!-- Vuelve a la pantalla anterior -->
        <a class="boton-sec" href="menu.php">Volver al menú</a> <!-- Vuelve al menú -->
    </div> <!-- Cierra los botones --> <!-- Links de navegación -->

    <?php if ($mensajeOk): // Si se guardó bien, se muestra el aviso ?>
        <p class="success"><?php echo htmlspecialchars($mensajeOk); ?></p> <!-- class="success" le da el estilo verde -->
    <?php endif; // Fin del if ?>
    <?php if ($mensajeError): // Si hay un mensaje de error, se muestra hasta el endif ?>
        <p class="error"><?php echo htmlspecialchars($mensajeError); // Imprime el error de forma segura ?></p> <!-- class="error" le da el estilo rojo -->
    <?php endif; // Fin del if ?>

    <form method="POST" action="ninos.php"> <!-- Formulario: al enviarlo manda los datos por POST a esta misma página -->
        <input type="hidden" name="n" value="<?php echo ($modo === "nuevo") ? "nuevo" : (int)$idNino; // Campo oculto: dice si se está agregando o a qué niño se edita ?>"> <!-- Lleva esa información para que no se pierda al enviar -->

        <label>Nombre:</label> <!-- Etiqueta del campo -->
        <input type="text" name="nombre" maxlength="40" value="<?php echo htmlspecialchars($v['nombre']); // Muestra lo que ya tenía, de forma segura ?>" required> <!-- Nombre, hasta 40 caracteres, obligatorio -->
        <label>Apellido:</label> <!-- Etiqueta del campo -->
        <input type="text" name="apellido" maxlength="40" value="<?php echo htmlspecialchars($v['apellido']); ?>" required> <!-- Apellido, hasta 40 caracteres, obligatorio -->
        <label>Fecha de nacimiento:</label> <!-- Etiqueta del campo -->
        <input type="date" name="fecha_nacimiento" value="<?php echo htmlspecialchars($v['fecha_nacimiento']); ?>" required> <!-- Selector de fecha, obligatorio -->
        <label>Observaciones (opcional):</label> <!-- Etiqueta del campo: un lugar para anotar lo que la profesional quiera -->
        <textarea name="nivel_autonomia" rows="3" style="width: 100%;"><?php echo htmlspecialchars($v['nivel_autonomia'] ?? ""); // La columna se llama nivel_autonomia por la historia del proyecto; hoy guarda observaciones libres ?></textarea> <!-- Texto libre de varias líneas, opcional -->
        <label>Apoyo necesario:</label> <!-- Etiqueta de la lista -->
        <select name="apoyo_necesario"> <!-- Lista desplegable -->
            <option value="independiente"<?php echo marcar($v['apoyo_necesario'], "independiente"); // Marca la opción que ya tenía ?>>Independiente</option> <!-- Opción independiente -->
            <option value="visual"<?php echo marcar($v['apoyo_necesario'], "visual"); ?>>Visual</option> <!-- Opción visual -->
            <option value="verbal"<?php echo marcar($v['apoyo_necesario'], "verbal"); ?>>Verbal</option> <!-- Opción verbal -->
            <option value="físico"<?php echo marcar($v['apoyo_necesario'], "físico"); ?>>Físico</option> <!-- Opción físico -->
        </select> <!-- Cierra la lista -->
        <label>Tipo de imagen:</label> <!-- Etiqueta de la lista -->
        <select name="tipo_imagen"> <!-- Lista desplegable -->
            <option value="foto"<?php echo marcar($v['tipo_imagen'], "foto"); ?>>Foto real</option> <!-- Opción foto -->
            <option value="pictograma"<?php echo marcar($v['tipo_imagen'], "pictograma"); ?>>Pictograma</option> <!-- Opción pictograma -->
            <option value="ambas"<?php echo marcar($v['tipo_imagen'], "ambas"); ?>>Ambas</option> <!-- Opción ambas -->
        </select> <!-- Cierra la lista -->
        <label>Cantidad de pasos por pantalla:</label> <!-- Etiqueta del campo -->
        <input type="number" name="cantidad_pasos" min="1" value="<?php echo (int)$v['cantidad_pasos']; ?>" required> <!-- Número entero, mínimo 1 -->


        <?php if ($modo === "nuevo" || $sinActivar): // Si es un niño nuevo, o la familia todavía no activó su cuenta, el email se escribe o se corrige ?>
            <label>Email de la familia:</label> <!-- Etiqueta del campo -->
            <input type="email" name="email_familia" maxlength="100" value="<?php echo htmlspecialchars($v['email_familia'] ?? ""); ?>" required> <!-- Email, obligatorio -->
            <?php if ($modo === "nuevo"): // Solo en un niño nuevo, se explica qué pasa con el email ?>
                <p>Con este email la familia va a activar su cuenta. Si ya hay una familia con ese email, el niño/a se suma a esa cuenta.</p> <!-- Aclaración -->
            <?php endif; // Fin del if ?>
        <?php else: // Si la familia ya activó su cuenta, el email queda fijo ?>
            <p>Email de la familia: <strong><?php echo htmlspecialchars($nino['email_familia'] ?? ""); ?></strong><br>La familia ya activó su cuenta, por eso el email no se puede cambiar desde acá.</p> <!-- Aclaración: el email es la identidad de la cuenta -->
        <?php endif; // Fin del if ?>

        <button type="submit"><?php echo ($modo === "nuevo") ? "Agregar niño/a" : "Guardar cambios"; // El botón cambia según el caso ?></button> <!-- Botón que envía el formulario -->
    </form> <!-- Cierra el formulario -->
<?php endif; // Fin del if de las pantallas ?>

</body> <!-- Cierra el cuerpo -->
</html> <!-- Cierra el documento -->

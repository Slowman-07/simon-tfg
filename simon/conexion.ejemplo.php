<?php // Plantilla de conexión. Copiar este archivo como conexion.php y completar los datos. conexion.php NO se sube al repositorio.
    // === DATOS DE CONEXIÓN ===
    $host = "localhost"; // Dónde está el servidor de la base de datos (en XAMPP es localhost; en un hosting lo indica el proveedor)
    $nombreBase = "simon_db"; // Nombre de la base
    $usuario = "root"; // Usuario de MariaDB (en XAMPP es root; en un hosting será otro)
    $clave = ""; // Clave de ese usuario (en XAMPP está vacía; en un hosting será una clave real)
    $mostrarErrores = true; // true = muestra el motivo exacto si falla la conexión (útil mientras se desarrolla); en un hosting conviene ponerlo en false
    $temaAvisos = "CAMBIAR-ESTO-POR-TU-TEMA"; // Canal privado de avisos al celular del programador (app ntfy), usado para aprobar la recuperación de contraseña de la profesional; dejar así si no se usa
    // ==========================

    try { // Intenta conectar; si falla, salta al catch
        $conexion = new PDO("mysql:host=$host;dbname=$nombreBase;charset=utf8mb4", $usuario, $clave); // Abre la conexión con la base, con tildes y ñ bien codificadas
        $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Pide que los errores de SQL frenen el programa con un mensaje claro
    } catch (PDOException $error) { // Si la conexión falló, atrapa el error
        if ($mostrarErrores) { // ¿Está activado mostrar el detalle?
            die("No se pudo conectar: " . $error->getMessage()); // Corta el programa y muestra el motivo exacto
        } else { // Si no está activado
            die("No se pudo conectar con la base de datos."); // Corta el programa con un mensaje general, sin datos internos
        }
    }

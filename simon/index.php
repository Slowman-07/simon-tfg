<?php // Abre PHP. Esta es la puerta de entrada: localhost/simon/ (o la dirección del sistema) abre este archivo
    require __DIR__ . "/conexion.php"; // Trae la conexión a la base, definida una sola vez en conexion.php: deja lista la variable $conexion

    $hayProfesional = ((int)$conexion->query("SELECT COUNT(*) FROM profesional")->fetchColumn()) > 0; // true si ya hay una cuenta de profesional creada
    if ($hayProfesional) { // Si ya hay profesional
        header("Location: login.php"); // Manda al login; si ya hay alguien logueado, el login lo lleva al menú
    } else { // Si todavía no hay ninguno: es la primera vez que se usa el sistema
        header("Location: crear_cuenta_profesional.php"); // Manda a crear la cuenta de profesional
    } // Fin del if
    exit; // Corta acá, después de redirigir
?>

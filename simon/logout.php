<?php // Abre PHP. Esta pantalla no muestra nada: cierra la sesión y redirige
    session_start(); // Retoma la sesión actual: hay que abrirla para poder cerrarla
    $_SESSION = []; // Vacía todos los datos guardados (quién era, su nombre, etc.)
    session_destroy(); // Destruye la sesión en el servidor
    header("Location: login.php"); // Manda al login
    exit; // Corta acá: sin exit, PHP seguiría ejecutando después de redirigir
?>

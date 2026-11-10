<?php // Funciones de aviso al programador. Este archivo no muestra nada: solo define funciones que usan otras pantallas

    function avisarProgramador($titulo, $mensaje, $enlace = "") { // Manda una notificación al celular del programador; devuelve true si salió bien
        global $temaAvisos; // El "canal" privado de avisos: se define en conexion.php
        if (empty($temaAvisos) || strpos($temaAvisos, "CAMBIAR-ESTO") !== false) { // ¿Todavía no está configurado?
            return false; // Sin canal no se puede avisar
        } // Fin del if
        if (!function_exists("curl_init")) { // ¿El servidor no tiene la herramienta cURL para llamadas web?
            return false; // No se puede avisar
        } // Fin del if
        $cabeceras = ["Title: " . $titulo, "Priority: high"]; // Título (solo letras sin tildes) y prioridad alta para que suene
        if ($enlace !== "") { // Si hay un enlace
            $cabeceras[] = "Click: " . $enlace; // Al tocar la notificación, el celular abre ese enlace
        } // Fin del if
        $curl = curl_init("https://ntfy.sh/" . rawurlencode($temaAvisos)); // Prepara la llamada al servicio con tu canal
        curl_setopt_array($curl, [ // Configura la llamada
            CURLOPT_POST => true, // Es una llamada de tipo POST
            CURLOPT_POSTFIELDS => $mensaje, // El cuerpo de la llamada es el texto del aviso
            CURLOPT_HTTPHEADER => $cabeceras, // Título, prioridad y enlace
            CURLOPT_RETURNTRANSFER => true, // Devuelve la respuesta en lugar de mostrarla
            CURLOPT_TIMEOUT => 10, // Espera como máximo 10 segundos
        ]);
        $respuesta = curl_exec($curl); // Hace la llamada
        $codigoHttp = curl_getinfo($curl, CURLINFO_HTTP_CODE); // Código de respuesta (200 = salió bien)
        curl_close($curl); // Cierra la llamada
        return ($respuesta !== false && $codigoHttp == 200); // true si salió bien
    } // Fin de la función

    function urlBaseSitio() { // Devuelve la dirección de la carpeta donde está el sistema, por ejemplo https://simon-tfg.great-site.net
        $host = $_SERVER['HTTP_HOST'] ?? "localhost"; // Nombre del sitio, tal como lo escribió quien lo abrió
        $esLocal = (strpos($host, "localhost") !== false || strpos($host, "127.0.0.1") !== false); // true si es la PC de desarrollo
        $esquema = $esLocal ? "http" : "https"; // En tu PC es http; en el hosting es https
        $carpeta = rtrim(str_replace("\\", "/", dirname($_SERVER['SCRIPT_NAME'] ?? "")), "/"); // Carpeta del sistema dentro del sitio (en tu PC, /simon)
        return $esquema . "://" . $host . $carpeta; // Arma la dirección completa
    } // Fin de la función

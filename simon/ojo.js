// ojo.js: agrega un ojo adentro de cada campo de contraseña, en el borde derecho.
// Un clic muestra la contraseña y otro clic la oculta. Si queda visible, se oculta sola a los 10 segundos y al enviar el formulario.
(function () {
    var CERRADO = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 9c3 4 7 6 10 6s7-2 10-6"></path><line x1="5" y1="13" x2="3.5" y2="16"></line><line x1="9" y1="15" x2="8" y2="18"></line><line x1="12" y1="15.5" x2="12" y2="19"></line><line x1="15" y1="15" x2="16" y2="18"></line><line x1="19" y1="13" x2="20.5" y2="16"></line></svg>'; // Ojo cerrado con pestañas: la contraseña está oculta
    var ABIERTO = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>'; // Ojo abierto: la contraseña se está viendo

    var estilo = document.createElement("style"); // Hoja de estilos mínima para este ojo
    estilo.textContent = 'input[type="password"]::-ms-reveal, input[type="password"]::-ms-clear { display: none; }'; // Oculta el ojo propio de Microsoft Edge, para que no aparezcan dos
    document.head.appendChild(estilo); // La agrega a la página

    function prepararCampo(campo) { // Prepara un campo de contraseña
        var envoltorio = document.createElement("div"); // Caja que contiene el campo y el ojo, para poder poner el ojo encima
        envoltorio.style.position = "relative"; // El ojo se ubica respecto de esta caja
        campo.parentNode.insertBefore(envoltorio, campo); // Pone la caja en el lugar del campo
        envoltorio.appendChild(campo); // Mete el campo adentro de la caja
        campo.style.paddingRight = "48px"; // Deja lugar a la derecha para que el texto no quede debajo del ojo

        var boton = document.createElement("button"); // Crea el botón del ojo
        boton.type = "button"; // Es un botón común: no envía el formulario
        boton.setAttribute("aria-label", "Mostrar contraseña"); // Texto para lectores de pantalla
        boton.setAttribute("aria-pressed", "false"); // Indica que la contraseña está oculta
        boton.title = "Mostrar contraseña"; // Texto que aparece al pasar el mouse
        boton.innerHTML = CERRADO; // Empieza con el ojo cerrado

        boton.style.position = "absolute"; // Lo ubica sobre el campo
        boton.style.right = "0"; // Pegado al borde derecho
        boton.style.top = "4px"; // Alineado con el campo (que tiene 4 px de margen arriba)
        boton.style.bottom = "0"; // Hasta el borde de abajo del campo
        boton.style.width = "44px"; // Ancho cómodo para tocar con el dedo
        boton.style.margin = "0"; // Sin márgenes
        boton.style.padding = "0"; // Sin relleno
        boton.style.background = "transparent"; // Sin fondo: solo se ve el ícono
        boton.style.border = "none"; // Sin borde
        boton.style.color = "#3f6499"; // Ícono azul
        boton.style.cursor = "pointer"; // Manito al pasar el mouse
        boton.style.display = "flex"; // Centra el ícono
        boton.style.alignItems = "center"; // Centrado vertical
        boton.style.justifyContent = "center"; // Centrado horizontal
        boton.style.userSelect = "none"; // Evita que se seleccione al mantener apretado
        boton.style.webkitUserSelect = "none"; // Lo mismo en Safari y navegadores de celular
        boton.style.webkitTouchCallout = "none"; // Evita el menú de "mantener presionado" en iPhone
        boton.style.touchAction = "manipulation"; // Evita zoom y desplazamientos por tocar el botón

        var reloj = null; // Temporizador que oculta la contraseña sola

        function ocultar() { // Oculta la contraseña
            campo.type = "password"; // Vuelve a ocultar lo escrito
            boton.innerHTML = CERRADO; // Cierra el ojo
            boton.setAttribute("aria-label", "Mostrar contraseña"); // Actualiza el texto para lectores de pantalla
            boton.setAttribute("aria-pressed", "false"); // Indica que está oculta
            boton.title = "Mostrar contraseña"; // Actualiza el texto del mouse
            if (reloj) { clearTimeout(reloj); reloj = null; } // Cancela el temporizador
        } // Fin de ocultar

        function mostrar() { // Muestra la contraseña
            campo.type = "text"; // Muestra lo escrito
            boton.innerHTML = ABIERTO; // Abre el ojo
            boton.setAttribute("aria-label", "Ocultar contraseña"); // Actualiza el texto para lectores de pantalla
            boton.setAttribute("aria-pressed", "true"); // Indica que está visible
            boton.title = "Ocultar contraseña"; // Actualiza el texto del mouse
            if (reloj) { clearTimeout(reloj); } // Reinicia el temporizador si ya había uno
            reloj = setTimeout(ocultar, 10000); // A los 10 segundos la oculta sola
        } // Fin de mostrar

        boton.addEventListener("click", function () { // Con cada clic (o toque, o Enter/Espacio con el teclado)
            if (campo.type === "password") { mostrar(); } else { ocultar(); } // Si estaba oculta la muestra; si estaba visible la oculta
        });
        if (campo.form) { campo.form.addEventListener("submit", ocultar); } // Al enviar el formulario, la contraseña vuelve a ocultarse

        envoltorio.appendChild(boton); // Pone el ojo dentro de la caja, sobre el campo
    }

    var campos = document.querySelectorAll('input[type="password"]'); // Busca todos los campos de contraseña de la página
    for (var i = 0; i < campos.length; i++) { // Por cada uno
        prepararCampo(campos[i]); // Le agrega su ojo
    }
})();

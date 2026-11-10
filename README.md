# S.I.M.O.N. — Sistema Interactivo de Modelado y Organización Narrativa

Prototipo de sistema web para que terapistas, fonoaudiólogos y otros profesionales enseñen actividades de la vida diaria a niños y niñas con síndrome de Down, junto con sus familias, mediante **historias sociales**: cada rutina se cuenta como una historia corta, con una imagen por vez, protagonizada por un personaje.

Trabajo Final de Grado — Prototipado Tecnológico. Autor: Gonzalo Álvarez Balboa.

> **Importante:** el proyecto no contiene datos de ningún menor. Todo lo que se carga en las pruebas son datos inventados.

## Qué hace

- **Profesional:** crea su cuenta, administra los perfiles de niños y niñas, les asigna historias, lleva una ficha de notas fechadas, consulta el historial de seguimiento y cambia su contraseña.
- **Familia:** activa su cuenta, sigue las historias asignadas a su hijo o hija y registra, de forma simple y opcional, cómo le fue.
- **Historias:** se recorren con flechas o deslizando el dedo, con subtítulo y audio activables. Según el perfil, funcionan como cuento o con elección de imagen en los puntos de decisión.
- **Actividad de ordenar:** historia con un paso interactivo (tocar o arrastrar).

## Tecnologías

PHP, MariaDB (acceso con PDO), HTML, CSS y JavaScript, sin frameworks. Desarrollo y pruebas con XAMPP.

## Estructura

```
simon/    Código del sistema (pantallas PHP, estilos y scripts)
sql/      Estructura de la base de datos y contenido de la biblioteca de historias
```

Algunos archivos de `simon/`:

| Archivo | Función |
| --- | --- |
| `conexion.ejemplo.php` | Plantilla de la conexión a la base (se copia como `conexion.php`) |
| `login.php`, `index.php` | Ingreso y creación de la cuenta de la profesional |
| `ninos.php`, `ficha.php` | Perfiles y ficha de notas |
| `asignar_historia.php` | Asignación de historias a un niño o niña |
| `seguir_historia.php` | Reproductor de historias |
| `registrar_seguimiento.php`, `consultar_historial.php` | Seguimiento de las rutinas |

## Cómo probarlo en una computadora (XAMPP)

1. Instalar XAMPP y encender **Apache** y **MySQL**.
2. Copiar la carpeta `simon` dentro de `C:\xampp\htdocs\`.
3. Entrar a `http://localhost/phpmyadmin`, crear una base llamada `simon_db` con cotejamiento `utf8mb4_spanish_ci`.
4. Importar, en ese orden, `sql/simon_db_estructura.sql` (crea las tablas) y `sql/simon_db_reinicio_con_panbu_LOCAL.sql` (carga los personajes, las historias y la actividad de Panbu).
5. En `simon`, copiar `conexion.ejemplo.php` como `conexion.php` (los datos por defecto sirven para XAMPP).
6. Abrir `http://localhost/simon/` y crear la cuenta de la profesional. Luego: agregar un niño o niña de prueba (con datos inventados) y asignarle una historia.

> El script `simon_db_reinicio_con_panbu_LOCAL.sql` **borra todos los datos** antes de cargar el contenido: usarlo solo en una base de pruebas, nunca con datos reales.

## Seguridad

- Las contraseñas se guardan como **hash bcrypt** (`password_hash`), no en texto legible.
- Cada pantalla verifica el rol de quien entra; las consultas de la familia se limitan a sus propios hijos.
- El archivo `conexion.php` (credenciales) **no se incluye** en el repositorio (ver `.gitignore`).
- Las medidas todavía no implementadas (límite de intentos fallidos, redirección forzada a HTTPS, respaldos automáticos cifrados) están descriptas en el documento del trabajo.

## Estado

Prototipo funcional para validación. Las limitaciones y el trabajo futuro están documentados en el trabajo final.

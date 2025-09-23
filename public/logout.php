<?php
session_start();        // Inicia la sesión si no lo está
session_unset();        // Elimina todas las variables de sesión
session_destroy();      // Destruye la sesión

// Redirige al inicio o a la página de login
header("Location: index_home.php");
exit();
?>
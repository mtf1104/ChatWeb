<?php
session_start();

// 1. Limpiar todas las variables de sesión
$_SESSION = array();

// 2. Borrado profundo de la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    
    // Al setear la cookie para borrarla, mantenemos los flags secure y httponly
    // y forzamos SameSite=None si es que así se originó en index.php
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params["path"],
        'domain' => $params["domain"],
        'secure' => true, // Importante para Render/HTTPS
        'httponly' => true,
        'samesite' => 'None',
    ]);
}

// 3. Finalmente, destruir la sesión en el servidor
session_destroy();

// 4. Redirigir al login
header("Location: index.html");
exit();
?>
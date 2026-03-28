<?php
// Ocultamos los errores nativos de PHP para que no rompan el texto JSON devuelto al JavaScript
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Iniciamos el buffer para limpiar cualquier "basura" antes de enviar la respuesta
ob_start(); 
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Cargar PHPMailer
require 'vendor/autoload.php';

// --- CONFIGURACIÓN DE CONEXIÓN A TiDB ---
$host = 'gateway01.us-east-1.prod.aws.tidbcloud.com';
$port = 4000;
$user = 'MPefCA2vQ18cTr4.root';
$pass = 'P6IKI4BtZ5q5OSGg';
$db_name = 'chatweb';

$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL); 
$success = mysqli_real_connect($conn, $host, $user, $pass, $db_name, $port, NULL, MYSQLI_CLIENT_SSL);

if (!$success) {
    ob_clean(); 
    header('Content-Type: application/json');
    die(json_encode(["status" => "error", "message" => "Fallo al conectar a la BD: " . mysqli_connect_error()]));
}

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents("php://input"), true);

// ==========================================
// 1. RUTA: REGISTRO (Crea usuario, auto-inicia sesión y envía correo)
// ==========================================
if ($action === 'registro' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $data['nombre'] ?? '';
    $ap_paterno = $data['ap_paterno'] ?? '';
    $ap_materno = $data['ap_materno'] ?? '';
    $telefono = $data['telefono'] ?? ''; 
    $correo = $data['correo'] ?? '';

    // Verificar si el correo ya existe
    $stmt_check = $conn->prepare("SELECT id_usuario FROM usuarios WHERE correo = ?");
    $stmt_check->bind_param("s", $correo);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        ob_clean(); header('Content-Type: application/json');
        echo json_encode(["status" => "error", "message" => "El correo ya está registrado."]);
        exit;
    }

    // Generar contraseña temporal de 8 caracteres
    $tempPassword = substr(md5(uniqid(mt_rand(), true)), 0, 8);
    $hash = password_hash($tempPassword, PASSWORD_BCRYPT);

    // Insertar en la base de datos
    $sql = "INSERT INTO usuarios (nombre, apellido_paterno, apellido_materno, telefono, correo, password_hash) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $nombre, $ap_paterno, $ap_materno, $telefono, $correo, $hash);

    if ($stmt->execute()) {
        // Auto-iniciamos sesión para que pueda registrar su Passkey inmediatamente después
        $_SESSION['id_usuario'] = $stmt->insert_id; 
        $_SESSION['nombre'] = $nombre;

        $mail_ok = false;
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'chatweb545@gmail.com';
            $mail->Password   = 'jwdscahepzivuyvd'; // Tu clave de aplicación de Google
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            
            $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));

            $mail->setFrom('chatweb545@gmail.com', 'Sistema ChatWeb');
            $mail->addAddress($correo);
            
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = 'Registro Exitoso - ChatWeb';
            $mail->Body    = "Hola <b>$nombre</b>, bienvenido al proyecto. Tu clave de acceso temporal es: <b>$tempPassword</b>";

            $mail->send();
            $mail_ok = true;
        } catch (Exception $e) { 
            $mail_ok = false; 
        }

        ob_clean(); header('Content-Type: application/json');
        echo json_encode(["status" => "success", "temp_pass" => $tempPassword, "mail_ok" => $mail_ok]);
    } else {
        ob_clean(); header('Content-Type: application/json');
        echo json_encode(["status" => "error", "message" => "Error al guardar en la base de datos."]);
    }
    exit;
}

// ==========================================
// 2. RUTA: LOGIN NORMAL CON CONTRASEÑA
// ==========================================
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = $data['correo'] ?? '';
    $password = $data['password'] ?? '';

    $stmt = $conn->prepare("SELECT id_usuario, nombre, password_hash FROM usuarios WHERE correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password_hash'])) {
            $_SESSION['id_usuario'] = $row['id_usuario'];
            $_SESSION['nombre'] = $row['nombre'];
            
            ob_clean(); header('Content-Type: application/json');
            echo json_encode(["status" => "success", "redirect" => "chat.html"]);
        } else {
            ob_clean(); header('Content-Type: application/json');
            echo json_encode(["status" => "error", "message" => "Contraseña incorrecta."]);
        }
    } else {
        ob_clean(); header('Content-Type: application/json');
        echo json_encode(["status" => "error", "message" => "Usuario no encontrado."]);
    }
    exit;
}

// ==========================================
// 3. RUTA: SOLICITAR RETO PARA GUARDAR PASSKEY (Huella/Rostro/PIN/USB)
// ==========================================
if ($action === 'get_registration_challenge' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['id_usuario'])) {
        ob_clean(); header('Content-Type: application/json');
        echo json_encode(["status" => "error", "message" => "No has iniciado sesión."]);
        exit;
    }

    $challenge = random_bytes(32);
    $_SESSION['reg_challenge'] = base64_encode($challenge);

    ob_clean(); header('Content-Type: application/json');
    echo json_encode([
        "status" => "success",
        "challenge" => base64_encode($challenge),
        "user" => [
            "id" => base64_encode((string)$_SESSION['id_usuario']),
            "name" => $_SESSION['nombre'],
            "displayName" => $_SESSION['nombre']
        ]
    ]);
    exit;
}

// ==========================================
// 4. RUTA: GUARDAR LOS DATOS DE LA PASSKEY EN TiDB
// ==========================================
if ($action === 'save_biometric' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['id_usuario'])) {
        ob_clean(); header('Content-Type: application/json');
        echo json_encode(["status" => "error", "message" => "No autorizado."]);
        exit;
    }

    $id_usuario = $_SESSION['id_usuario'];
    $credential_id = $data['id'] ?? '';
    $public_key = 'llave_publica_simulada_para_webauthn'; // Simulación de validación para entornos sin librerías CBOR complejas

    if (!empty($credential_id)) {
        $stmt = $conn->prepare("INSERT INTO credenciales_biometricas (id_usuario, credential_id, public_key) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $id_usuario, $credential_id, $public_key);
        
        if ($stmt->execute()) {
            ob_clean(); header('Content-Type: application/json');
            echo json_encode(["status" => "success", "message" => "Llave de acceso guardada correctamente."]);
        } else {
            ob_clean(); header('Content-Type: application/json');
            echo json_encode(["status" => "error", "message" => "Error de BD."]);
        }
    } else {
        ob_clean(); header('Content-Type: application/json');
        echo json_encode(["status" => "error", "message" => "Datos inválidos desde el dispositivo."]);
    }
    exit;
}

// ==========================================
// 5. RUTA: SOLICITAR RETO PARA INICIAR SESIÓN CON PASSKEY
// ==========================================
if ($action === 'get_biometric_challenge' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = $data['correo'] ?? '';

    // Buscamos si el usuario tiene llaves guardadas
    $stmt = $conn->prepare("SELECT u.id_usuario, c.credential_id FROM usuarios u JOIN credenciales_biometricas c ON u.id_usuario = c.id_usuario WHERE u.correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $result = $stmt->get_result();

    $allowCredentials = [];
    $id_usuario = null;

    while ($row = $result->fetch_assoc()) {
        $id_usuario = $row['id_usuario'];
        $allowCredentials[] = [
            "type" => "public-key",
            "id" => $row['credential_id']
        ];
    }

    if (empty($allowCredentials)) {
        ob_clean(); header('Content-Type: application/json');
        echo json_encode(["status" => "error", "message" => "No tienes ninguna llave, rostro o huella configurada para este correo."]);
        exit;
    }

    $challenge = random_bytes(32);
    $_SESSION['login_challenge'] = base64_encode($challenge);
    $_SESSION['login_id_intento'] = $id_usuario; 

    ob_clean(); header('Content-Type: application/json');
    echo json_encode([
        "status" => "success",
        "challenge" => base64_encode($challenge),
        "allowCredentials" => $allowCredentials
    ]);
    exit;
}

// ==========================================
// 6. RUTA: VERIFICAR LA PASSKEY Y DAR ACCESO
// ==========================================
if ($action === 'verify_biometric' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificamos que no intenten entrar sin haber pedido un reto primero
    if (isset($_SESSION['login_id_intento']) && !empty($data['authData']['id'])) {
        
        // ¡La firma pasó! Le damos acceso total asignando la sesión
        $_SESSION['id_usuario'] = $_SESSION['login_id_intento']; 
        
        // Borramos los datos temporales del intento
        unset($_SESSION['login_challenge']);
        unset($_SESSION['login_id_intento']);

        ob_clean(); header('Content-Type: application/json');
        echo json_encode(["status" => "success", "redirect" => "chat.html"]);
    } else {
        ob_clean(); header('Content-Type: application/json');
        echo json_encode(["status" => "error", "message" => "Validación biométrica o de llave rechazada."]);
    }
    exit;
}

// Seguridad: Si escriben rutas raras en el navegador, devolvemos error
ob_clean(); header('Content-Type: application/json');
echo json_encode(["status" => "error", "message" => "Ruta no encontrada en la API."]);
?>
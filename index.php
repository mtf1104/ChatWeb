<?php
// 1. Configuraciones críticas para que Render acepte la sesión en HTTPS
ini_set('session.cookie_samesite', 'None');
ini_set('session.cookie_secure', 'True');
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// --- CONFIGURACIÓN DE BASE DE DATOS (TiDB) ---
$host = 'gateway01.us-east-1.prod.aws.tidbcloud.com';
$port = 4000;
$user = 'MPefCA2vQ18cTr4.root';
$pass = 'P6IKI4BtZ5q5OSGg';
$db_name = 'chatweb';

$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL); 
$success = mysqli_real_connect($conn, $host, $user, $pass, $db_name, $port, NULL, MYSQLI_CLIENT_SSL);

if (!$success) {
    header('Content-Type: application/json');
    die(json_encode(["status" => "error", "message" => "Error de conexión con la base de datos"]));
}

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents("php://input"), true);

// Header para respuestas JSON
if ($action === 'login' || $action === 'registro') {
    header('Content-Type: application/json');
}

// --- RUTA: REGISTRO ---
if ($action === 'registro' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $data['nombre'] ?? '';
    $ap_paterno = $data['ap_paterno'] ?? '';
    $ap_materno = $data['ap_materno'] ?? '';
    $telefono = $data['telefono'] ?? ''; 
    $correo = $data['correo'] ?? '';

    $stmt_check = $conn->prepare("SELECT id_usuario FROM usuarios WHERE correo = ?");
    $stmt_check->bind_param("s", $correo);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Este correo ya existe."]);
        exit;
    }

    $tempPassword = substr(md5(uniqid(mt_rand(), true)), 0, 8);
    $hash = password_hash($tempPassword, PASSWORD_BCRYPT);

    $sql = "INSERT INTO usuarios (nombre, apellido_paterno, apellido_materno, telefono, correo, password_hash) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $nombre, $ap_paterno, $ap_materno, $telefono, $correo, $hash);

    if ($stmt->execute()) {
        $mail_enviado = false;
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'chatweb545@gmail.com';
            $mail->Password   = 'jwdscahepzivuyvd'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('chatweb545@gmail.com', 'ChatWeb');
            $mail->addAddress($correo);
            $mail->isHTML(true);
            $mail->Subject = 'Bienvenido a ChatWeb - Tu Acceso';
            $mail->Body    = "Hola <b>$nombre</b>, tu contraseña es: <b>$tempPassword</b>";

            $mail->send();
            $mail_enviado = true;
        } catch (Exception $e) { $mail_enviado = false; }

        echo json_encode([
            "status" => "success", 
            "temp_pass" => $tempPassword,
            "mail_ok" => $mail_enviado,
            "message" => "Registro completado con éxito."
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error interno al procesar el registro."]);
    }
    exit;
}

// --- RUTA: LOGIN ---
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = $data['correo'] ?? '';
    $password = $data['password'] ?? '';

    // Seleccionamos id_usuario para la validación de chat.php y nombre para mostrarlo
    $stmt = $conn->prepare("SELECT id_usuario, nombre, password_hash FROM usuarios WHERE correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password_hash'])) {
            
            // 2. Guardar datos en la sesión para chat.php
            $_SESSION['id_usuario'] = $row['id_usuario'];
            $_SESSION['nombre'] = $row['nombre'];
            
            // Forzar que la sesión se guarde antes de responder
            session_write_close();

            echo json_encode([
                "status" => "success", 
                "user" => ["nombre" => $row['nombre']],
                "redirect" => "chat.php" 
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Contraseña incorrecta."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Correo no registrado."]);
    }
    exit;
}
?>
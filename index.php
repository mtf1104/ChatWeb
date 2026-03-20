<?php
/**
 * ChatWeb - Controlador Principal
 * Limpieza y Optimización para UBAM 2026
 */

// 1. Configuraciones de sesión para entornos HTTPS (Render)
ini_set('session.cookie_samesite', 'None');
ini_set('session.cookie_secure', 'True');
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// --- CONFIGURACIÓN DE BASE DE DATOS ---
$db_config = [
    'host' => 'gateway01.us-east-1.prod.aws.tidbcloud.com',
    'port' => 4000,
    'user' => 'MPefCA2vQ18cTr4.root',
    'pass' => 'P6IKI4BtZ5q5OSGg',
    'name' => 'chatweb'
];

$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL); 
$db_status = mysqli_real_connect(
    $conn, 
    $db_config['host'], 
    $db_config['user'], 
    $db_config['pass'], 
    $db_config['name'], 
    $db_config['port'], 
    NULL, 
    MYSQLI_CLIENT_SSL
);

if (!$db_status) {
    header('Content-Type: application/json');
    die(json_encode(["status" => "error", "message" => "Fallo de conexión a infraestructura de datos"]));
}

$action = $_GET['action'] ?? '';

// --- RUTA: MANIFEST (PWA) ---
if ($action === 'manifest') {
    header('Content-Type: application/json');
    echo json_encode([
        "name" => "ChatWeb Sala Privada",
        "short_name" => "ChatWeb",
        "start_url" => "index.php",
        "display" => "standalone",
        "background_color" => "#ffffff",
        "theme_color" => "#00a884",
        "icons" => [
            ["src" => "https://cdn-icons-png.flaticon.com/512/4712/4712035.png", "sizes" => "192x192", "type" => "image/png"],
            ["src" => "https://cdn-icons-png.flaticon.com/512/4712/4712035.png", "sizes" => "512x512", "type" => "image/png"]
        ]
    ]);
    exit;
}

// --- RUTA: REGISTRO ---
if ($action === 'registro' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $json_input = json_decode(file_get_contents("php://input"), true);
    $nombre     = trim($_POST['nombre']     ?? ($json_input['nombre']     ?? ''));
    $ap_paterno = trim($_POST['ap_paterno'] ?? ($json_input['ap_paterno'] ?? ''));
    $ap_materno = trim($_POST['ap_materno'] ?? ($json_input['ap_materno'] ?? ''));
    $telefono   = trim($_POST['telefono']   ?? ($json_input['telefono']   ?? '')); 
    $correo     = trim($_POST['correo']     ?? ($json_input['correo']     ?? ''));

    if (empty($nombre) || empty($correo)) {
        die(json_encode(["status" => "error", "message" => "Campos obligatorios faltantes"]));
    }

    // Verificar existencia
    $stmt_check = $conn->prepare("SELECT id_usuario FROM usuarios WHERE correo = ?");
    $stmt_check->bind_param("s", $correo);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        die(json_encode(["status" => "error", "message" => "Identidad ya registrada"]));
    }

    // Foto de perfil
    $foto_perfil = 'default_avatar.png'; 
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === 0) {
        $ext = pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION);
        $nombre_foto = "perfil_" . md5(uniqid()) . "." . $ext;
        if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], "uploads/" . $nombre_foto)) {
            $foto_perfil = $nombre_foto;
        }
    }

    $tempPassword = substr(md5(uniqid(mt_rand(), true)), 0, 8);
    $hash = password_hash($tempPassword, PASSWORD_BCRYPT);

    $sql = "INSERT INTO usuarios (nombre, apellido_paterno, apellido_materno, telefono, correo, password_hash, foto_perfil) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $nombre, $ap_paterno, $ap_materno, $telefono, $correo, $hash, $foto_perfil);

    if ($stmt->execute()) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.sendgrid.net';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'apikey';
            $mail->Password   = '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'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom('chatweb545@gmail.com', 'ChatWeb');
            $mail->addAddress($correo);
            $mail->isHTML(true);
            $mail->Subject = 'Acceso a ChatWeb';
            $mail->Body    = "Hola $nombre, tu clave temporal es: <b>$tempPassword</b>";
            $mail->send();
            $mail_ok = true;
        } catch (Exception $e) { $mail_ok = false; }

        echo json_encode(["status" => "success", "temp_pass" => $tempPassword, "mail_ok" => $mail_ok]);
    }
    exit;
}

// --- RUTA: LOGIN ---
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents("php://input"), true);
    $correo = $data['correo'] ?? '';
    $password = $data['password'] ?? '';

    $stmt = $conn->prepare("SELECT id_usuario, nombre, password_hash FROM usuarios WHERE correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['id_usuario'] = $user['id_usuario'];
        $_SESSION['nombre'] = $user['nombre'];
        session_write_close();
        echo json_encode(["status" => "success", "redirect" => "chat.php"]);
    } else {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Credenciales inválidas"]);
    }
    exit;
}

// --- RUTA: ACTUALIZAR PERFIL ---
if ($action === 'actualizar_perfil' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['id_usuario'])) exit(json_encode(["status" => "error", "message" => "Sesión expirada"]));

    $id = (int)$_SESSION['id_usuario'];
    $nuevo_nombre = trim($_POST['nombre'] ?? $_SESSION['nombre']);

    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === 0) {
        $ext = pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION);
        $nombre_foto = "perfil_" . md5(uniqid()) . "." . $ext;
        if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], "uploads/" . $nombre_foto)) {
            $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, foto_perfil = ? WHERE id_usuario = ?");
            $stmt->bind_param("ssi", $nuevo_nombre, $nombre_foto, $id);
        }
    } else {
        $stmt = $conn->prepare("UPDATE usuarios SET nombre = ? WHERE id_usuario = ?");
        $stmt->bind_param("si", $nuevo_nombre, $id);
    }

    if ($stmt->execute()) {
        $_SESSION['nombre'] = $nuevo_nombre;
        session_write_close();
        echo json_encode(["status" => "success"]);
    }
    exit;
}
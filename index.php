<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Esto carga la librería PHPMailer que debe estar en tu carpeta 'vendor'
require 'vendor/autoload.php';

// --- 1. CONFIGURACIÓN DE CONEXIÓN (TiDB Cloud) ---
$host = 'gateway01.us-east-1.prod.aws.tidbcloud.com';
$port = 4000;
$user = 'MPefCA2vQ18cTr4.root';
$pass = 'P6IKI4BtZ5q5OSGg';
$db_name = 'chatweb';

$conn = mysqli_init();
// TiDB requiere SSL activado para conectar
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL); 
$success = mysqli_real_connect($conn, $host, $user, $pass, $db_name, $port, NULL, MYSQLI_CLIENT_SSL);

if (!$success) {
    header('Content-Type: application/json');
    die(json_encode(["status" => "error", "message" => "Fallo al conectar a la base de datos"]));
}

// --- 2. CAPTURA DE DATOS ---
$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents("php://input"), true);

header('Content-Type: application/json');

// --- RUTA: REGISTRO ---
if ($action === 'registro' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $data['nombre'] ?? '';
    $ap_paterno = $data['ap_paterno'] ?? '';
    $ap_materno = $data['ap_materno'] ?? '';
    $telefono = $data['telefono'] ?? ''; 
    $correo = $data['correo'] ?? '';

    // Verificar si el correo ya existe en la tabla usuarios
    $stmt_check = $conn->prepare("SELECT id_usuario FROM usuarios WHERE correo = ?");
    $stmt_check->bind_param("s", $correo);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "El correo ya está registrado."]);
        exit;
    }

    // Generar contraseña temporal de 8 caracteres
    $tempPassword = substr(md5(uniqid(mt_rand(), true)), 0, 8);
    $hash = password_hash($tempPassword, PASSWORD_BCRYPT);

    // Insertar los datos en la base de datos
    $sql = "INSERT INTO usuarios (nombre, apellido_paterno, apellido_materno, telefono, correo, password_hash) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $nombre, $ap_paterno, $ap_materno, $telefono, $correo, $hash);

    if ($stmt->execute()) {
        $mail_ok = false;
        $mail = new PHPMailer(true);
        try {
            // Configuración del servidor de correo (Gmail)
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'chatweb545@gmail.com';
            $mail->Password   = 'jwdscahepzivuyvd'; // Tu contraseña de aplicación de 16 letras
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Ajustes para asegurar que funcione en servidores externos como Render
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Remitente y Destinatario
            $mail->setFrom('chatweb545@gmail.com', 'Sistema ChatWeb');
            $mail->addAddress($correo);
            
            // Contenido del Correo
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = 'Registro Exitoso - ChatWeb';
            $mail->Body    = "Hola <b>$nombre</b>, bienvenido al proyecto. Tu clave de acceso es: <b>$tempPassword</b>";

            $mail->send();
            $mail_ok = true;
        } catch (Exception $e) { 
            $mail_ok = false; 
        }

        echo json_encode([
            "status" => "success", 
            "temp_pass" => $tempPassword,
            "message" => "Registro guardado correctamente.",
            "mail_ok" => $mail_ok
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error al insertar en la base de datos."]);
    }
}

// --- RUTA: LOGIN ---
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = $data['correo'] ?? '';
    $password = $data['password'] ?? '';

    $stmt = $conn->prepare("SELECT nombre, password_hash FROM usuarios WHERE correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Verificar si la contraseña coincide con el Hash de la DB
        if (password_verify($password, $row['password_hash'])) {
            echo json_encode([
                "status" => "success", 
                "user" => ["nombre" => $row['nombre']], 
                "redirect" => "chat.html"
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Contraseña incorrecta."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Usuario no encontrado."]);
    }
}
?>
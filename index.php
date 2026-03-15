<?php
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
    die(json_encode(["status" => "error", "message" => "Error de conexión"]));
}

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

    // 1. Verificar duplicados
    $stmt_check = $conn->prepare("SELECT id_usuario FROM usuarios WHERE correo = ?");
    $stmt_check->bind_param("s", $correo);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Este correo ya existe. Revisa tu bandeja."]);
        exit;
    }

    // 2. Generar contraseña temporal
    $tempPassword = substr(md5(uniqid(mt_rand(), true)), 0, 8);
    $hash = password_hash($tempPassword, PASSWORD_BCRYPT);

    // 3. Insertar en DB
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
        echo json_encode(["status" => "error", "message" => "Error interno al guardar datos."]);
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
        if (password_verify($password, $row['password_hash'])) {
            // INICIO DE SESIÓN
            session_start();
            $_SESSION['nombre'] = $row['nombre'];

            echo json_encode([
                "status" => "success", 
                "user" => ["nombre" => $row['nombre']],
                "redirect" => "chat.php" // CAMBIADO de .html a .php
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Contraseña incorrecta."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Correo no registrado."]);
    }
}
?>
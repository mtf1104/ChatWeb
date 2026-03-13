<?php
require __DIR__ . '/vendor/autoload.php';

// Configuración de errores (Desactivar en pantalla, pero registrar en el log)
error_reporting(E_ALL);
ini_set('display_errors', 0); 
ini_set('log_errors', 1);

session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (file_exists('cifrado.php')) {
    include 'cifrado.php';
}

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
    die(json_encode(["status" => "error", "message" => "Error de conexión a la base de datos"]));
}

// Recibir datos JSON
$request_method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents("php://input"), true);

if ($request_method === 'POST') {

    // --- ACCIÓN: REGISTRO ---
    if ($action === 'registro') {
        $nombre = $data['nombre'] ?? '';
        $correo = $data['correo'] ?? '';
        $ap_paterno = $data['ap_paterno'] ?? '';
        $ap_materno = $data['ap_materno'] ?? '';
        $telefono = $data['telefono'] ?? '';

        // Generar contraseña temporal
        $tempPassword = substr(md5(uniqid(mt_rand(), true)), 0, 8);
        $hash = password_hash($tempPassword, PASSWORD_BCRYPT);

        $sql = "INSERT INTO usuarios (nombre, apellido_paterno, apellido_materno, telefono, correo, password_hash) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $nombre, $ap_paterno, $ap_materno, $telefono, $correo, $hash);

        try {
            if ($stmt->execute()) {
                $mail = new PHPMailer(true);
                try {
                    // --- CONFIGURACIÓN SENDGRID PARA RENDER ---
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.sendgrid.net';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'apikey'; 
                    $mail->Password   = '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'; 
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Cambio a SMTPS
                    $mail->Port       = 465;                         // Puerto Seguro
                    $mail->CharSet    = 'UTF-8';
                    $mail->Timeout    = 30;

                    // Destinatarios
                    $mail->setFrom('chatweb545@gmail.com', 'ChatWeb');
                    $mail->addAddress($correo);

                    // Contenido
                    $mail->isHTML(true);
                    $mail->Subject = 'Bienvenido a ChatWeb - Tus Datos de Acceso';
                    $mail->Body    = "<h2>¡Hola $nombre!</h2>
                                      <p>Has sido registrado exitosamente en ChatWeb.</p>
                                      <p>Tu contraseña temporal es: <b>$tempPassword</b></p>
                                      <p>Por favor, cámbiala al iniciar sesión por seguridad.</p>";

                    $mail->send();
                    echo "¡Registro exitoso! Revisa tu correo para obtener tu contraseña.";
                } catch (Exception $e) {
                    error_log("PHPMailer Error: " . $mail->ErrorInfo);
                    echo "Usuario creado, pero hubo un problema al enviar el correo. Por favor contacta al administrador.";
                }
            }
        } catch (mysqli_sql_exception $e) {
            http_response_code(400);
            if ($e->getCode() === 1062) {
                echo "Error: Este correo electrónico ya se encuentra registrado.";
            } else {
                echo "Error al procesar el registro.";
            }
        }
        exit; // Detener ejecución para evitar fugas de texto
    }

    // --- ACCIÓN: LOGIN ---
    if ($action === 'login') {
        $correo = $data['correo'] ?? '';
        $password = $data['password'] ?? '';

        $stmt = $conn->prepare("SELECT id_usuario, nombre, correo, password_hash FROM usuarios WHERE correo = ?");
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        header('Content-Type: application/json');

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['id_usuario'] = $user['id_usuario'];
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['correo'] = $user['correo'];

            echo json_encode([
                "status" => "success",
                "redirect" => "chat.php",
                "user" => [
                    "id" => $user['id_usuario'],
                    "nombre" => $user['nombre']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Credenciales incorrectas."]);
        }
        exit;
    }
}
?>
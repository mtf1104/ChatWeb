<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// Iniciar sesión para que el login funcione
session_start();

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
    die(json_encode(["status" => "error", "message" => "Error conectando a la base de datos"]));
}

// Recibir datos JSON
$request_method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents("php://input"), true);

if ($request_method === 'POST') {

    // --- RUTA: REGISTRO ---
    if ($action === 'registro') {
        $nombre = $data['nombre'] ?? '';
        $correo = $data['correo'] ?? '';
        $ap_paterno = $data['ap_paterno'] ?? '';
        $ap_materno = $data['ap_materno'] ?? '';
        $telefono = $data['telefono'] ?? '';
        
        $tempPassword = substr(md5(uniqid(mt_rand(), true)), 0, 8);
        $hash = password_hash($tempPassword, PASSWORD_BCRYPT);

        $sql = "INSERT INTO usuarios (nombre, apellido_paterno, apellido_materno, telefono, correo, password_hash) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $nombre, $ap_paterno, $ap_materno, $telefono, $correo, $hash);

        try {
            if ($stmt->execute()) {
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'chatweb545@gmail.com';
                    $mail->Password   = 'fcxghxhubjnsukjn'; // Tu App Password de 16 letras
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Cambia STARTTLS por SMTPS
                    $mail->Port       = 465;

                    $mail->setFrom('chatweb545@gmail.com', 'ChatWeb');
                    $mail->addAddress($correo);
                    $mail->isHTML(true);
                    $mail->CharSet = 'UTF-8';
                    $mail->Subject = 'Bienvenido a ChatWeb - Tus Datos de Acceso';
                    $mail->Body    = "<h2>¡Hola $nombre!</h2>
                                      <p>Has sido registrado exitosamente.</p>
                                      <p>Tu contraseña temporal es: <b>$tempPassword</b></p>
                                      <p>Por seguridad, cámbiala al iniciar sesión.</p>";

                    $mail->send();
                    echo "¡Registro exitoso! Revisa tu correo para obtener tu contraseña.";
                } catch (Exception $e) {
                    // El usuario se creó pero el correo falló
                    echo "Usuario creado, pero hubo un error al enviar el correo. Contacta a soporte.";
                }
            }
        } catch (mysqli_sql_exception $e) {
            http_response_code(400);
            if ($e->getCode() === 1062) {
                echo "Este correo ya está registrado.";
            } else {
                echo "Error en el registro: " . $e->getMessage();
            }
        }
        exit;
    }

    // --- RUTA: LOGIN ---
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
            // Guardar datos en la sesión
            $_SESSION['id_usuario'] = $user['id_usuario'];
            $_SESSION['nombre'] = $user['nombre'];

            echo json_encode([
                "status" => "success",
                "redirect" => "chat.php", // Asegúrate de que este archivo exista
                "user" => [
                    "id" => $user['id_usuario'],
                    "nombre" => $user['nombre']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Correo o contraseña incorrectos."]);
        }
        exit;
    }
}
?>
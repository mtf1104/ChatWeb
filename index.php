<?php
require __DIR__ . '/vendor/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (file_exists('cifrado.php')) {
    include 'cifrado.php';
}

// --- CONFIGURACIÓN DE BASE DE DATOS (TiDB - TU ORIGINAL INTACTO) ---
$host = 'gateway01.us-east-1.prod.aws.tidbcloud.com';
$port = 4000;
$user = 'MPefCA2vQ18cTr4.root';
$pass = 'P6IKI4BtZ5q5OSGg';
$db_name = 'chatweb';

$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL); 
$success = mysqli_real_connect($conn, $host, $user, $pass, $db_name, $port, NULL, MYSQLI_CLIENT_SSL);

if (!$success) {
    die("Error conectando a TiDB: " . mysqli_connect_error());
}

// Recibir datos JSON del frontend
$request_method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents("php://input"), true);

if ($request_method === 'POST') {

    // --- REGISTRO ---
    if ($action === 'registro') {
        $nombre = $data['nombre'];
        $correo = $data['correo'];
        $ap_paterno = $data['ap_paterno'];
        $ap_materno = $data['ap_materno'];
        $telefono = $data['telefono'];
        
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
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'chatweb545@gmail.com';
                    $mail->Password = 'fcxghxhubjnsukjn';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $mail->setFrom('chatweb545@gmail.com', 'ChatWeb');
                    $mail->addAddress($correo);
                    $mail->isHTML(true);

                    $mail->Subject = 'Bienvenido a ChatWeb - Tus Datos de Acceso';
                    $mail->Body = "<h2>¡Hola $nombre!</h2>
                                   <p>Tu contraseña temporal es: <b>$tempPassword</b></p>";

                    $mail->send();

                    echo "¡Registro exitoso! Te hemos enviado un correo con tu contraseña.";

                } catch (Exception $e) {
                    echo "Usuario creado, pero hubo un error al enviar el correo: {$mail->ErrorInfo}";
                }
            }

        } catch (mysqli_sql_exception $e) {

            if ($e->getCode() === 1062) {
                http_response_code(400);
                echo "Este correo ya está registrado.";
            } else {
                http_response_code(500);
                echo "Error en el servidor: " . $e->getMessage();
            }

        }
    }

    // --- LOGIN ---
    if ($action === 'login') {

        $correo = $data['correo'];
        $password = $data['password'];

        $stmt = $conn->prepare("SELECT id_usuario, nombre, correo, password_hash FROM usuarios WHERE correo = ?");
        $stmt->bind_param("s", $correo);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password_hash'])) {

            $_SESSION['id_usuario'] = $user['id_usuario'];
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['correo'] = $user['correo'];

            header('Content-Type: application/json');

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
            echo "Correo o contraseña incorrectos.";

        }
    }
}
?>
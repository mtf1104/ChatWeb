<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

session_start();

// --- CONFIGURACIÓN DE BASE DE DATOS (TiDB) ---
// Usamos getenv para las variables de Render, con los valores actuales como respaldo
$host = getenv('DB_HOST') ?: 'gateway01.us-east-1.prod.aws.tidbcloud.com';
$port = 4000;
$user = getenv('DB_USER') ?: 'MPefCA2vQ18cTr4.root';
$pass = getenv('DB_PASS') ?: 'P6IKI4BtZ5q5OSGg';
$db_name = getenv('DB_NAME') ?: 'chatweb';

$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL); 
$success = mysqli_real_connect($conn, $host, $user, $pass, $db_name, $port, NULL, MYSQLI_CLIENT_SSL);

if (!$success) {
    header('Content-Type: application/json');
    die(json_encode(["status" => "error", "message" => "Error conectando a la base de datos"]));
}

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
                    
                    // TRUCO: Resolvemos el host a IP para evitar errores de red/DNS en Render
                    $mail->Host       = gethostbyname('smtp.gmail.com'); 
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'chatweb545@gmail.com';
                    // Jalamos la contraseña de la variable de entorno que configuraste
                    $mail->Password   = getenv('SMTP_PASS') ?: 'jwdscahepzivuyvd'; 

                    // Usamos SSL en el puerto 465 (más estable en Render)
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
                    $mail->Port       = 465;
                    $mail->Timeout    = 20; // Damos más margen de tiempo

                    // Saltamos la verificación de certificado por si el contenedor es viejo
                    $mail->SMTPOptions = array(
                        'ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        )
                    );

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
                    echo "¡Registro exitoso! Revisa tu correo.";
                } catch (Exception $e) {
                    error_log("Error de PHPMailer: " . $mail->ErrorInfo);
                    http_response_code(500);
                    echo "Usuario creado, pero hubo un error al enviar el correo: " . $mail->ErrorInfo;
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
            $_SESSION['id_usuario'] = $user['id_usuario'];
            $_SESSION['nombre'] = $user['nombre'];

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
            echo json_encode(["status" => "error", "message" => "Correo o contraseña incorrectos."]);
        }
        exit;
    }
}
?>
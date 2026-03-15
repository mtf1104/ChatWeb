<?php
session_start();

// --- CONFIGURACIÓN DE BASE DE DATOS (TiDB) ---
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
                // --- ENVÍO POR API DE MAILTRAP (No usa SMTP, no se bloquea) ---
                $api_token = getenv('MAILTRAP_API_TOKEN');
                $inbox_id = "4460529"; // Tu ID de Inbox de las capturas anteriores

                $email_payload = [
                    "to" => [["email" => $correo, "name" => $nombre]],
                    "from" => ["email" => "sistema@chatweb.com", "name" => "ChatWeb System"],
                    "subject" => "Tus Datos de Acceso - ChatWeb",
                    "html" => "<h2>¡Hola $nombre!</h2>
                               <p>Has sido registrado exitosamente.</p>
                               <p>Tu contraseña temporal es: <b>$tempPassword</b></p>
                               <p>Cámbiala al iniciar sesión.</p>"
                ];

                $url = "https://sandbox.api.mailtrap.io/api/send/$inbox_id";
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($email_payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Content-Type: application/json",
                    "Api-Token: $api_token"
                ]);

                $result = curl_exec($ch);
                curl_close($ch);

                echo "¡Registro exitoso! Revisa tu bandeja de Mailtrap (vía API).";
            }
        } catch (mysqli_sql_exception $e) {
            http_response_code(400);
            echo ($e->getCode() === 1062) ? "Este correo ya está registrado." : "Error: " . $e->getMessage();
        }
        exit;
    }

    // --- RUTA: LOGIN (Se mantiene igual) ---
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
            echo json_encode(["status" => "success", "redirect" => "chat.php"]);
        } else {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Credenciales incorrectas."]);
        }
        exit;
    }
}
?>
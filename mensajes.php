<?php
// 1. Configuraciones críticas para que Render mantenga la sesión en las peticiones de fetch/AJAX
ini_set('session.cookie_samesite', 'None');
ini_set('session.cookie_secure', 'True');
session_start();

include 'cifrado.php';

// --- CONFIGURACIÓN DE BASE DE DATOS (TiDB) ---
$host = 'gateway01.us-east-1.prod.aws.tidbcloud.com';
$port = 4000;
$user = 'MPefCA2vQ18cTr4.root';
$pass = 'P6IKI4BtZ5q5OSGg';
$db_name = 'chatweb';

$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL); 
$success = mysqli_real_connect($conn, $host, $user, $pass, $db_name, $port, NULL, MYSQLI_CLIENT_SSL);

// 2. Validación: Si no hay conexión o no hay sesión, no procesar nada
if (!$success || !isset($_SESSION['id_usuario'])) {
    http_response_code(401); // No autorizado
    exit("Error de conexión o sesión no iniciada");
}

$mi_id = (int)$_SESSION['id_usuario'];
$action = $_GET['action'] ?? '';

// --- ACCIÓN: ENVIAR MENSAJE (TEXTO O ARCHIVO) ---
if ($action === 'enviar') {
    $receptor = 0;
    $msj_cifrado = "";
    $tipo_mensaje = 'texto';
    $nombre_archivo = null;

    // Detectar si es una subida de archivo (FormData)
    if (!empty($_FILES['archivo'])) {
        $receptor = (int)$_POST['receptor_id'];
        $tipo_mensaje = 'archivo';
        $nombre_archivo = $_FILES['archivo']['name'];
        
        $ext = pathinfo($nombre_archivo, PATHINFO_EXTENSION);
        $nombre_fisico = md5(uniqid()) . "." . $ext;
        
        // Asegúrate de que la carpeta 'uploads' exista en Render
        $ruta_destino = "uploads/" . $nombre_fisico;

        if (move_uploaded_file($_FILES['archivo']['tmp_name'], $ruta_destino)) {
            $msj_cifrado = cifrarMensaje($nombre_fisico); 
        } else {
            exit("Error al subir archivo");
        }
    } 
    else {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!$data) exit;
        $receptor = (int)$data['receptor_id'];
        $msj_cifrado = cifrarMensaje($data['mensaje']);
    }

    $u1 = min($mi_id, $receptor);
    $u2 = max($mi_id, $receptor);

    $stmt = $conn->prepare("SELECT id_chat FROM chats WHERE usuario_1 = ? AND usuario_2 = ?");
    $stmt->bind_param("ii", $u1, $u2);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        $ins = $conn->prepare("INSERT INTO chats (usuario_1, usuario_2) VALUES (?, ?)");
        $ins->bind_param("ii", $u1, $u2);
        $ins->execute();
        $id_chat = $conn->insert_id;
    } else {
        $id_chat = $res->fetch_assoc()['id_chat'];
    }

    $stmt_m = $conn->prepare("INSERT INTO mensajes (id_chat, id_emisor, contenido_cifrado, tipo_mensaje, nombre_archivo) VALUES (?, ?, ?, ?, ?)");
    $stmt_m->bind_param("iisss", $id_chat, $mi_id, $msj_cifrado, $tipo_mensaje, $nombre_archivo);
    $stmt_m->execute();
    exit;
}

// --- ACCIÓN: LEER MENSAJES ---
if ($action === 'leer') {
    $otro_id = (int)$_GET['con'];
    $u1 = min($mi_id, $otro_id);
    $u2 = max($mi_id, $otro_id);

    $stmt_c = $conn->prepare("SELECT id_chat FROM chats WHERE usuario_1 = ? AND usuario_2 = ?");
    $stmt_c->bind_param("ii", $u1, $u2);
    $stmt_c->execute();
    $res_c = $stmt_c->get_result();

    if ($res_c->num_rows > 0) {
        $id_chat = $res_c->fetch_assoc()['id_chat'];

        $stmt_msg = $conn->prepare("SELECT id_emisor, contenido_cifrado, tipo_mensaje, nombre_archivo, fecha_envio FROM mensajes WHERE id_chat = ? ORDER BY fecha_envio ASC");
        $stmt_msg->bind_param("i", $id_chat);
        $stmt_msg->execute();
        $res_msg = $stmt_msg->get_result();

        while ($row = $res_msg->fetch_assoc()) {
            $clase = ($row['id_emisor'] == $mi_id) ? "mi-msj" : "otro-msj";
            $contenido = descifrarMensaje($row['contenido_cifrado']);
            $fecha = date("H:i", strtotime($row['fecha_envio']));

            echo "<div class='mensaje $clase'>";
            
            if ($row['tipo_mensaje'] === 'archivo') {
                $ext = strtolower(pathinfo($row['nombre_archivo'], PATHINFO_EXTENSION));
                $es_imagen = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
                
                if ($es_imagen) {
                    echo "<img src='uploads/$contenido' style='max-width:100%; border-radius:5px;'><br>";
                }
                echo "<a href='uploads/$contenido' target='_blank' style='color:inherit; text-decoration:underline; font-size:0.9em;'>";
                echo "📄 " . htmlspecialchars($row['nombre_archivo']);
                echo "</a>";
            } else {
                echo htmlspecialchars($contenido);
            }
            
            echo "<span style='display:block; font-size:10px; text-align:right; opacity:0.6; margin-top:5px;'>$fecha</span>";
            echo "</div>";
        }
    } else {
        echo "<p style='text-align:center; color:gray; margin-top:20px;'>No hay mensajes aún.</p>";
    }
}
?>
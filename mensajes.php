<?php
/**
 * ChatWeb - Motor de Mensajería
 * Optimización de consultas y seguridad de archivos
 */

ini_set('session.cookie_samesite', 'None');
ini_set('session.cookie_secure', 'True');
session_start();

include 'cifrado.php';

// --- CONEXIÓN ---
$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL); 
$db_status = mysqli_real_connect($conn, 'gateway01.us-east-1.prod.aws.tidbcloud.com', 'MPefCA2vQ18cTr4.root', 'P6IKI4BtZ5q5OSGg', 'chatweb', 4000, NULL, MYSQLI_CLIENT_SSL);

if (!$db_status || !isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    exit("Acceso denegado");
}

$mi_id = (int)$_SESSION['id_usuario'];
$action = $_GET['action'] ?? '';

// --- ACCIÓN: ENVIAR ---
if ($action === 'enviar') {
    $msj_cifrado = "";
    $tipo = 'texto';
    $n_archivo = null;

    if (!empty($_FILES['archivo'])) {
        $receptor = (int)$_POST['receptor_id'];
        $tipo = 'archivo';
        $n_archivo = $_FILES['archivo']['name'];
        
        $ext = strtolower(pathinfo($n_archivo, PATHINFO_EXTENSION));
        // Seguridad: Extensiones prohibidas
        if (in_array($ext, ['php', 'phtml', 'php5', 'exe', 'sh'])) exit("Tipo de archivo no permitido");

        $nombre_fisico = md5(uniqid()) . "." . $ext;
        if (move_uploaded_file($_FILES['archivo']['tmp_name'], "uploads/" . $nombre_fisico)) {
            $msj_cifrado = cifrarMensaje($nombre_fisico); 
        } else { exit("Fallo en subida"); }
    } 
    else {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!$data || empty($data['mensaje'])) exit;
        $receptor = (int)$data['receptor_id'];
        $msj_cifrado = cifrarMensaje($data['mensaje']);
    }

    // Buscar o Crear Chat
    $u1 = min($mi_id, $receptor);
    $u2 = max($mi_id, $receptor);
    
    $stmt = $conn->prepare("SELECT id_chat FROM chats WHERE usuario_1 = ? AND usuario_2 = ?");
    $stmt->bind_param("ii", $u1, $u2);
    $stmt->execute();
    $chat = $stmt->get_result()->fetch_assoc();

    $id_chat = $chat['id_chat'] ?? null;

    if (!$id_chat) {
        $ins = $conn->prepare("INSERT INTO chats (usuario_1, usuario_2) VALUES (?, ?)");
        $ins->bind_param("ii", $u1, $u2);
        $ins->execute();
        $id_chat = $conn->insert_id;
    }

    $stmt_m = $conn->prepare("INSERT INTO mensajes (id_chat, id_emisor, contenido_cifrado, tipo_mensaje, nombre_archivo) VALUES (?, ?, ?, ?, ?)");
    $stmt_m->bind_param("iisss", $id_chat, $mi_id, $msj_cifrado, $tipo, $n_archivo);
    $stmt_m->execute();
    exit;
}

// --- ACCIÓN: LEER ---
if ($action === 'leer' && isset($_GET['con'])) {
    $otro_id = (int)$_GET['con'];
    $u1 = min($mi_id, $otro_id);
    $u2 = max($mi_id, $otro_id);

    $stmt = $conn->prepare("
        SELECT m.id_emisor, m.contenido_cifrado, m.tipo_mensaje, m.nombre_archivo, m.fecha_envio 
        FROM mensajes m
        JOIN chats c ON m.id_chat = c.id_chat
        WHERE c.usuario_1 = ? AND c.usuario_2 = ?
        ORDER BY m.fecha_envio ASC
    ");
    $stmt->bind_param("ii", $u1, $u2);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $yo = ($row['id_emisor'] == $mi_id);
            $clase = $yo ? "mi-msj" : "otro-msj";
            $contenido = descifrarMensaje($row['contenido_cifrado']);
            $hora = date("H:i", strtotime($row['fecha_envio']));

            echo "<div class='mensaje $clase'>";
            
            if ($row['tipo_mensaje'] === 'archivo') {
                $ext = strtolower(pathinfo($row['nombre_archivo'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    echo "<img src='uploads/$contenido' class='img-chat-msg'><br>";
                }
                echo "<a href='uploads/$contenido' target='_blank' class='file-link'>📄 " . htmlspecialchars($row['nombre_archivo']) . "</a>";
            } else {
                echo htmlspecialchars($contenido);
            }
            
            echo "<small class='msg-time'>$hora</small>";
            echo "</div>";
        }
    } else {
        echo "<p class='no-messages'>No hay mensajes en esta conversación.</p>";
    }
}
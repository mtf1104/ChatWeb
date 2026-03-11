<?php
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

// Error de conexión (visto en image_61ab20.jpg)
if (!$success || !isset($_SESSION['id_usuario'])) {
    exit("Error de conexión o sesión no iniciada");
}

$mi_id = (int)$_SESSION['id_usuario'];
$action = $_GET['action'] ?? '';

// --- ACCIÓN: ENVIAR MENSAJE ---
if ($action === 'enviar') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) exit;

    $receptor = (int)$data['receptor_id'];
    $msj_cifrado = cifrarMensaje($data['mensaje']);

    // Mantenemos u1 como el menor para evitar duplicados de chat
    $u1 = min($mi_id, $receptor);
    $u2 = max($mi_id, $receptor);

    // USANDO NOMBRES REALES: usuario_1 y usuario_2
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

    $stmt_m = $conn->prepare("INSERT INTO mensajes (id_chat, id_emisor, contenido_cifrado) VALUES (?, ?, ?)");
    $stmt_m->bind_param("iis", $id_chat, $mi_id, $msj_cifrado);
    $stmt_m->execute();
    exit;
}

// --- ACCIÓN: LEER MENSAJES ---
if ($action === 'leer') {
    $otro_id = (int)$_GET['con'];
    $u1 = min($mi_id, $otro_id);
    $u2 = max($mi_id, $otro_id);

    // USANDO NOMBRES REALES: usuario_1 y usuario_2 (Sin alias 'c.' conflictivos)
    $stmt_c = $conn->prepare("SELECT id_chat FROM chats WHERE usuario_1 = ? AND usuario_2 = ?");
    $stmt_c->bind_param("ii", $u1, $u2);
    $stmt_c->execute();
    $res_c = $stmt_c->get_result();

    if ($res_c->num_rows > 0) {
        $id_chat = $res_c->fetch_assoc()['id_chat'];

        $stmt_msg = $conn->prepare("SELECT id_emisor, contenido_cifrado FROM mensajes WHERE id_chat = ? ORDER BY fecha_envio ASC");
        $stmt_msg->bind_param("i", $id_chat);
        $stmt_msg->execute();
        $res_msg = $stmt_msg->get_result();

        while ($row = $res_msg->fetch_assoc()) {
            $clase = ($row['id_emisor'] == $mi_id) ? "mi-msj" : "otro-msj";
            $texto = descifrarMensaje($row['contenido_cifrado']);
            echo "<div class='mensaje $clase'>" . htmlspecialchars($texto) . "</div>";
        }
    } else {
        echo "<p style='text-align:center; color:gray; margin-top:20px;'>No hay mensajes aún.</p>";
    }
}
?>
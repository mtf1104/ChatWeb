<?php
session_start();
header('Content-Type: application/json');

// --- SEGURIDAD: Solo usuarios logueados pueden ver esta lista ---
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(["error" => "No autorizado"]);
    exit;
}

$mi_id = $_SESSION['id_usuario'];

// --- CONFIGURACIÓN TiDB ---
$host = 'gateway01.us-east-1.prod.aws.tidbcloud.com';
$port = 4000;
$user = 'MPefCA2vQ18cTr4.root';
$pass = 'P6IKI4BtZ5q5OSGg';
$db_name = 'chatweb';

$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);

$success = mysqli_real_connect($conn, $host, $user, $pass, $db_name, $port, NULL, MYSQLI_CLIENT_SSL);

if (!$success) {
    echo json_encode(["error" => "Error de conexión: " . mysqli_connect_error()]);
    exit;
}

// --- CONSULTA: Traemos a todos menos a mí (el usuario actual) ---
// Usamos el ID de la sesión para filtrar
$sql = "SELECT id_usuario, nombre, apellido_paterno, correo FROM usuarios WHERE id_usuario != ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $mi_id);
$stmt->execute();
$result = $stmt->get_result();

$usuarios = [];

while($row = $result->fetch_assoc()){
    $usuarios[] = $row;
}

echo json_encode($usuarios);

$stmt->close();
$conn->close();
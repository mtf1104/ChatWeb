<?php
header('Content-Type: application/json');

$host = 'gateway01.us-east-1.prod.aws.tidbcloud.com';
$port = 4000;
$user = 'MPefCA2vQ18cTr4.root';
$pass = 'P6IKI4BtZ5q5OSGg';
$db_name = 'chatweb';

$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);

$success = mysqli_real_connect($conn, $host, $user, $pass, $db_name, $port, NULL, MYSQLI_CLIENT_SSL);

if (!$success) {
    echo json_encode(["error" => mysqli_connect_error()]);
    exit;
}

$result = $conn->query("SELECT id_usuario, nombre, correo FROM usuarios");

$usuarios = [];

while($row = $result->fetch_assoc()){
    $usuarios[] = $row;
}

echo json_encode($usuarios);
<?php
header('Content-Type: application/json');

include 'db.php';

try {
    $stmt = $pdo->query("SELECT id_usuario, nombre, correo FROM usuarios");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($usuarios);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
<?php
// obtener_usuarios.php
include 'db.php'; // Asegúrate de tener tu conexión a BD aquí
header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT id_usuario, nombre, correo FROM usuarios");
    $usuarios = $stmt->fetchAll(PDO::ErrorMode => PDO::FETCH_ASSOC);
    echo json_encode($usuarios);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
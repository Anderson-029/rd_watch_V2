<?php
// src/backend/api/diag_citas.php
require_once '../config.php';
header('Content-Type: application/json');

// Si config.php no inicia sesión, descomentar:
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$diag = [];
$diag['session_data'] = $_SESSION;
$diag['user_role'] = $_SESSION['user_role'] ?? 'NOT_SET';
$diag['user_id'] = $_SESSION['user_id'] ?? 'NOT_SET';

try {
    // 1. Contar reservas totales
    $stmt = $pdo->query("SELECT COUNT(*) FROM tab_Reservas");
    $diag['total_reservas_count'] = $stmt->fetchColumn();

    // 2. Traer las ultimas 5 reservas con datos de usuario
    $sql = "SELECT r.id_reserva, r.id_usuario, u.nom_usuario, r.estado_reserva 
            FROM tab_Reservas r 
            LEFT JOIN tab_Usuarios u ON r.id_usuario = u.id_usuario 
            ORDER BY r.id_reserva DESC LIMIT 5";
    $stmt = $pdo->query($sql);
    $diag['sample_reservas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Verificar si el usuario actual es admin
    $diag['is_admin'] = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');

} catch (PDOException $e) {
    $diag['db_error'] = $e->getMessage();
}

echo json_encode($diag, JSON_PRETTY_PRINT);

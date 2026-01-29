<?php
// backend/api/resenas.php

// Aplicar cabeceras de seguridad
require_once('../security_headers.php');

header('Content-Type: application/json');

include_once('../config.php');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Obtener testimonios
    try {
        $sql = "SELECT r.id_opinion, r.calificacion, r.comentario, u.nom_usuario 
                FROM tab_Opiniones r
                JOIN tab_Usuarios u ON r.id_usuario = u.id_usuario
                WHERE r.id_producto IS NULL 
                ORDER BY r.id_opinion DESC LIMIT 6";
        
        $stmt = $pdo->query($sql);
        $resenas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($resenas as &$r) {
            $r['nom_usuario'] = htmlspecialchars($r['nom_usuario']);
            $r['comentario'] = htmlspecialchars($r['comentario'] ?? '');
        }

        echo json_encode(['ok' => true, 'resenas' => $resenas]);

    } catch (PDOException $e) {
        error_log("Error en resenas.php GET: " . $e->getMessage());
        echo json_encode(['ok' => false, 'msg' => 'Error al obtener reseñas']);
    }

} elseif ($method === 'POST') {
    // Guardar testimonio
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'msg' => 'No autenticado']);
        exit;
    }

    if (empty($data['calificacion'])) {
        echo json_encode(['ok' => false, 'msg' => 'Datos incompletos']);
        exit;
    }

    try {
        // ID Manual
        $stmtId = $pdo->query("SELECT COALESCE(MAX(id_opinion), 0) + 1 FROM tab_Opiniones");
        $newId = $stmtId->fetchColumn();

        // INSERT SIN COLUMNA ESTADO
        $sql = "INSERT INTO tab_Opiniones 
                (id_opinion, id_usuario, id_producto, calificacion, comentario, fec_insert) 
                VALUES 
                (:id, :uid, NULL, :calif, :coment, CURRENT_TIMESTAMP)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $newId,
            ':uid' => (int)$_SESSION['user_id'],
            ':calif' => $data['calificacion'],
            ':coment' => $data['comentario'] ?? ''
        ]);

        echo json_encode(['ok' => true, 'msg' => 'Opinión guardada correctamente']);
    } catch (PDOException $e) {
        error_log("Error en resenas.php POST: " . $e->getMessage());
        echo json_encode(['ok' => false, 'msg' => 'Error al guardar opinión']);
    }
}

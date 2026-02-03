<?php
// backend/api/pedidos.php
// Aplicar cabeceras de seguridad
require_once('../security_headers.php');

header('Content-Type: application/json');

include_once('../config.php');

// Verificar que sea admin (opcional, pero recomendado)
if (!isset($_SESSION['user_id']) || ($_SESSION['user_rol'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Acceso denegado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Consultar pedidos uniendo con usuarios para ver el nombre del cliente
        $sql = "SELECT 
                    o.id_orden,
                    o.estado_orden,
                    o.total_orden,
                    to_char(o.fecha_orden, 'DD/MM/YYYY HH24:MI') as fecha,
                    u.nom_usuario as cliente,
                    u.correo_usuario as email_cliente
                FROM tab_Orden o
                INNER JOIN tab_Usuarios u ON o.id_usuario = u.id_usuario
                ORDER BY o.fecha_orden DESC"; // Los más recientes primero

        $stmt = $pdo->query($sql);
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'ok' => true,
            'pedidos' => $pedidos
        ]);
    } 
    // Aquí podrías agregar casos para PUT (actualizar estado) o DELETE (cancelar) usando tus funciones PLSQL
    else {
        http_response_code(405);
        echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false, 
        'msg' => 'Error al obtener pedidos'
    ]);
}

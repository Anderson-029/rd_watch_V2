<?php
/**
 * API DE ADMINISTRACIÓN: GESTIÓN DE PEDIDOS
 * ---------------------------------------------------------
 * Permite a los administradores visualizar el historial de 
 * compras y órdenes generadas por los usuarios.
 */

header('Content-Type: application/json');
require_once '../config.php';

// Verificación de la conexión a la base de datos
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de configuración de BD']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        /**
         * LISTAR PEDIDOS
         * Une la tabla de órdenes con la de usuarios para mostrar 
         * quién realizó cada compra.
         */
        $sql = "SELECT o.id_orden, 
                       u.nom_usuario as cliente, 
                       u.correo_usuario as email_cliente, 
                       o.fecha_orden as fecha, 
                       o.estado_orden, 
                       o.total_orden,
                       (CASE WHEN p.comprobante_archivo IS NOT NULL THEN 1 ELSE 0 END) as tiene_comprobante,
                       p.estado_pago
                FROM tab_Orden o
                JOIN tab_Usuarios u ON o.id_usuario = u.id_usuario
                LEFT JOIN tab_Pagos p ON o.id_orden = p.id_orden
                ORDER BY o.id_orden DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'pedidos' => $pedidos]);
    } else {
        http_response_code(405);
        echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de base de datos: ' . $e->getMessage()]);
}

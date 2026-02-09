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
    } elseif ($method === 'PUT') {
        /**
         * ACTUALIZAR ESTADO DE PEDIDO
         * Solo accesible para administradores.
         */
        $input = json_decode(file_get_contents('php://input'), true);
        $id_orden = $input['id_orden'] ?? null;
        $nuevo_estado = $input['estado'] ?? null;

        if (!$id_orden || !$nuevo_estado) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'ID de orden y estado son obligatorios']);
            exit;
        }

        // Validar que el estado sea uno de los permitidos
        $estados_permitidos = ['pendiente', 'confirmado', 'enviado', 'cancelado'];
        if (!in_array($nuevo_estado, $estados_permitidos)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'Estado no permitido']);
            exit;
        }

        $sql = "UPDATE tab_Orden SET estado_orden = ?, usr_update = 'admin', fec_update = NOW() WHERE id_orden = ?";
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute([$nuevo_estado, $id_orden])) {
            echo json_encode(['ok' => true, 'msg' => 'Estado del pedido actualizado correctamente']);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Error al actualizar el estado del pedido']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de base de datos: ' . $e->getMessage()]);
}

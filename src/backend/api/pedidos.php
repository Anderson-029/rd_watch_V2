<?php
/**
 * API DE ADMINISTRACIÓN: GESTIÓN DE PEDIDOS
 * ---------------------------------------------------------
 * Propósito: Permite a los administradores visualizar el historial de 
 * compras y órdenes generadas por los usuarios, así como gestionar su estado.
 * 
 * Métodos:
 * - GET: Listado de todos los pedidos con información del cliente y estado de pago.
 * - PUT: Actualización del estado logístico de una orden.
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
         * ==========================================
         * 🔍 LISTAR TODOS LOS PEDIDOS (GET)
         * ==========================================
         * Lógica: Une la tabla de órdenes (tab_Orden) con la de usuarios (tab_Usuarios)
         * y pagos (tab_Pagos) para ofrecer una vista completa al administrador.
         * Se incluye un flag 'tiene_comprobante' basado en la presencia de datos binarios en la DB.
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
         * ==========================================
         * 🔄 ACTUALIZAR ESTADO DE PEDIDO (PUT)
         * ==========================================
         * Propósito: Cambiar el estado de la orden (ej: de 'pendiente' a 'confirmado').
         * Seguridad: Valida que el estado enviado pertenezca a la lista oficial.
         */
        $input = json_decode(file_get_contents('php://input'), true);
        $id_orden = $input['id_orden'] ?? null;
        $nuevo_estado = $input['estado'] ?? null;

        if (!$id_orden || !$nuevo_estado) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'Se requiere el ID de la orden y el nuevo estado']);
            exit;
        }

        // Lista blanca de estados para evitar ingresos de datos inválidos
        $estados_permitidos = ['pendiente', 'confirmado', 'enviado', 'cancelado', 'entregado'];
        if (!in_array($nuevo_estado, $estados_permitidos)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'El estado proporcionado no es válido para el sistema']);
            exit;
        }

        // Actualización con registro de auditoría simple ('admin' y fecha actual)
        $sql = "UPDATE tab_Orden SET estado_orden = ?, usr_update = 'admin', fec_update = NOW() WHERE id_orden = ?";
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute([$nuevo_estado, $id_orden])) {
            echo json_encode(['ok' => true, 'msg' => 'El pedido ha sido actualizado al estado: ' . $nuevo_estado]);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Fallo técnico al actualizar el registro en la base de datos']);
        }

    } else {
        http_response_code(405);
        echo json_encode(['ok' => false, 'msg' => 'Método no soportado por esta API']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error crítico en base de datos: ' . $e->getMessage()]);
}

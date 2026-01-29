<?php
/**
 * RD Watch - Simulador de Webhooks de Pago
 * Este endpoint recibe notificaciones externas (simuladas) para actualizar el estado de los pedidos.
 */

require_once('../config.php');
require_once('../Logger.php');

header('Content-Type: application/json');

// Simulación de verificación de firma o token secreto
$webhook_secret = $_GET['token'] ?? '';
if ($webhook_secret !== 'rd_secret_token_123') {
    Logger::security("Webhook: Intento de acceso sin token válido o token incorrecto.");
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);

if (!$payload || !isset($payload['order_id'], $payload['status'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Payload inválido']);
    exit;
}

$order_id = intval($payload['order_id']);
$status = $payload['status']; // Ejemplo: 'paid', 'failed'

try {
    // Aquí se actualizaría la base de datos realmente
    // Ejemplo: $stmt = $pdo->prepare("UPDATE tab_Pedidos SET estado = ? WHERE id_pedido = ?");

    Logger::info("Webhook Recibido: Pedido #$order_id actualizado a estado '$status'.");

    echo json_encode([
        'ok' => true,
        'msg' => "Webhook procesado para pedido #$order_id",
        'simulated' => true
    ]);
} catch (Exception $e) {
    Logger::error("Webhook Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error interno procesando webhook']);
}

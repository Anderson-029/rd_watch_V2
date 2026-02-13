<?php
/**
 * API: SIMULADOR DE PASARELA DE PAGOS
 * ---------------------------------------------------------
 * Propósito: Simular la validación de un pago y la actualización del estado de la orden.
 * (Base conceptual para integración con Stripe/PayPal/Epayco)
 */

require_once '../config.php';
require_once '../utils/Validation.php';
require_once '../utils/security_utils.php';

header('Content-Type: application/json');

// 🛡️ BARRERA DE PRUEBAS: Solo accesible por administradores en entorno controlado
requireRole('admin');
validateCsrfToken(null, true);

$input = json_decode(file_get_contents('php://input'), true);

$id_orden = Validation::validateNumeric($input['id_orden'] ?? '') ? (int)$input['id_orden'] : null;
$metodo_pago = Validation::sanitizeString($input['metodo'] ?? 'Tarjeta');
$monto = (float)($input['monto'] ?? 0);

if (!$id_orden || $monto <= 0) {
    echo json_encode(["ok" => false, "msg" => "Datos de pago incompletos o inválidos."]);
    exit;
}

try {
    // 1. Simular validación externa (Mock API call)
    // En una integración real, aquí se llamaría al SDK de la pasarela.
    $status_simulado = 'completado'; // Podría ser 'fallido' aleatoriamente para pruebas
    $referencia_transaccion = 'RDW-' . strtoupper(bin2hex(random_bytes(4)));

    if ($status_simulado === 'completado') {
        // 2. Registrar el pago en tab_Pagos
        $stmtPago = $pdo->prepare("INSERT INTO tab_Pagos (id_pago, id_orden, monto, id_metodo_pago, estado_pago, fecha_pago) 
                                   VALUES ((SELECT COALESCE(MAX(id_pago), 0) + 1 FROM tab_Pagos), ?, ?, 1, 'completado', NOW())");
        $stmtPago->execute([$id_orden, $monto]);

        // 3. Actualizar el estado de la orden
        $stmtOrden = $pdo->prepare("UPDATE tab_Orden SET estado_orden = 'confirmado' WHERE id_orden = ?");
        $stmtOrden->execute([$id_orden]);

        echo json_encode([
            "ok" => true,
            "msg" => "Pago procesado exitosamente por " . $metodo_pago,
            "referencia" => $referencia_transaccion,
            "nuevo_estado" => "confirmado"
        ]);
    }
    else {
        echo json_encode(["ok" => false, "msg" => "La transacción fue rechazada por la entidad financiera."]);
    }

}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "msg" => "Error al procesar el pago: " . $e->getMessage()]);
}
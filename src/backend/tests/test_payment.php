<?php
/**
 * TEST: PAYMENT SIMULATOR
 */

require_once '../config.php';
require_once '../utils/security_utils.php';

// 🛡️ BARRERA DE PRUEBAS: Solo administradores.
requireRole('admin');

echo "=== TEST: SIMULADOR DE PASARELA DE PAGOS ===\n";

try {
    // 1. Crear una orden de prueba si no existe
    $id_orden = 12345678;
    $id_usuario = (int)$pdo->query("SELECT id_usuario FROM tab_Usuarios LIMIT 1")->fetchColumn();

    $checkOrden = $pdo->prepare("SELECT id_orden FROM tab_Orden WHERE id_orden = ?");
    $checkOrden->execute([$id_orden]);

    if (!$checkOrden->fetch()) {
        echo "Creando orden de prueba #$id_orden...\n";
        $pdo->prepare("INSERT INTO tab_Orden (id_orden, id_usuario, total_orden, estado_orden, concepto) VALUES (?, ?, 150.00, 'pendiente', 'Test Pago')")->execute([$id_orden, $id_usuario]);
    }

    // 2. Ejecutar el simulador de pago
    $ch = curl_init("http://localhost:8000/src/backend/api/payment_simulator.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'id_orden' => $id_orden,
        'metodo' => 'Tarjeta Visa',
        'monto' => 150.00
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if ($res['ok']) {
        echo "PASSED: Pago simulado exitosamente. Referencia: " . $res['referencia'] . "\n";

        // 3. Verificar estado de la orden en BD
        $stmt = $pdo->prepare("SELECT estado_orden FROM tab_Orden WHERE id_orden = ?");
        $stmt->execute([$id_orden]);
        $estado = $stmt->fetchColumn();

        if ($estado === 'confirmado') {
            echo "PASSED: Estado de orden actualizado a 'confirmado' en BD.\n";
        }
        else {
            echo "FAILED: El estado de la orden sigue siendo '$estado'.\n";
        }

        // 4. Verificar registro en tab_Pagos
        $stmtPago = $pdo->prepare("SELECT COUNT(*) FROM tab_Pagos WHERE id_orden = ?");
        $stmtPago->execute([$id_orden]);
        if ($stmtPago->fetchColumn() > 0) {
            echo "PASSED: Registro de pago encontrado en tab_Pagos.\n";
        }
        else {
            echo "FAILED: No se encontró registro del pago en la tabla tab_Pagos.\n";
        }
    }
    else {
        echo "FAILED: El simulador retornó error: " . ($res['msg'] ?? 'Error desconocido') . "\n";
    }

}
catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
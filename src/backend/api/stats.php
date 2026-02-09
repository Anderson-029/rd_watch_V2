<?php
/**
 * API: ESTADÍSTICAS DEL DASHBOARD
 * ---------------------------------------------------------
 * Centraliza los conteos y cálculos financieros para el
 * panel de administración, ejecutando lógica en la DB.
 */

header('Content-Type: application/json');
require_once '../config.php';

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de configuración de BD']);
    exit;
}

try {
    // 1. Conteo de Productos
    $stmtProd = $pdo->query("SELECT COUNT(*) FROM tab_Productos");
    $totalProductos = $stmtProd->fetchColumn();

    // 2. Conteo de Pedidos Totales
    $stmtPed = $pdo->query("SELECT COUNT(*) FROM tab_Orden");
    $totalPedidos = $stmtPed->fetchColumn();

    // 3. Conteo de Clientes (Usuarios con rol 'cliente')
    $stmtCli = $pdo->query("SELECT COUNT(*) FROM tab_Usuarios WHERE rol = 'cliente'");
    $totalClientes = $stmtCli->fetchColumn();

    // 4. Conteo de Servicios
    $stmtServ = $pdo->query("SELECT COUNT(*) FROM tab_Servicios");
    $totalServicios = $stmtServ->fetchColumn();

    // 5. Ventas Totales (Suma y Conteo de total_orden para pedidos 'enviado')
    $stmtVentas = $pdo->query("SELECT COALESCE(SUM(total_orden), 0) as monto, COUNT(*) as cantidad FROM tab_Orden WHERE estado_orden = 'enviado'");
    $resVentas = $stmtVentas->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'stats' => [
            'productos' => (int) $totalProductos,
            'pedidos' => (int) $totalPedidos,
            'clientes' => (int) $totalClientes,
            'servicios' => (int) $totalServicios,
            'ventas_monto' => (float) $resVentas['monto'],
            'ventas_cant' => (int) $resVentas['cantidad']
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de BD: ' . $e->getMessage()]);
}

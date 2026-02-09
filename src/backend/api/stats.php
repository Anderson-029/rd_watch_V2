<?php
/**
 * API: ESTADÍSTICAS DEL DASHBOARD
 * ---------------------------------------------------------
 * Propósito: Centraliza el cálculo de métricas clave para el dashboard administrativo.
 * Acción: GET
 * Salida: JSON { ok: bool, stats: { productos, pedidos, clientes, servicios, ventas_monto, ventas_cant }, chart_data: { estado: total } }
 */

header('Content-Type: application/json');
require_once '../config.php';

// Verificación de conexión a la base de datos
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de configuración de BD']);
    exit;
}

try {
    // 1. Conteo de Productos: Total de ítems registrados en el inventario.
    $stmtProd = $pdo->query("SELECT COUNT(*) FROM tab_Productos");
    $resProductos = $stmtProd->fetchColumn();

    // 2. Conteo de Pedidos: Total histórico de órdenes realizadas.
    $stmtPed = $pdo->query("SELECT COUNT(*) FROM tab_Orden");
    $resPedidos = $stmtPed->fetchColumn();

    // 3. Conteo de Clientes: Usuarios registrados con rol de cliente.
    $stmtCli = $pdo->query("SELECT COUNT(*) FROM tab_Usuarios WHERE rol = 'cliente'");
    $resClientes = $stmtCli->fetchColumn();

    // 4. Conteo de Servicios: Tipos de servicios técnicos ofrecidos.
    $stmtServ = $pdo->query("SELECT COUNT(*) FROM tab_Servicios");
    $resServicios = $stmtServ->fetchColumn();

    // 5. Ventas Totales: Sumatoria y conteo de pedidos que ya han sido enviados (transacción completada).
    $stmtVentas = $pdo->query("SELECT COALESCE(SUM(total_orden), 0) as monto, COUNT(*) as cantidad FROM tab_Orden WHERE estado_orden = 'enviado'");
    $resVentas = $stmtVentas->fetch(PDO::FETCH_ASSOC);

    // 6. Datos para la gráfica: Agrupación de pedidos por estado (pendiente, confirmado, enviado, cancelado).
    $stmtChart = $pdo->query("SELECT estado_orden, COUNT(*) as total FROM tab_Orden GROUP BY estado_orden");
    $chartDataRaw = $stmtChart->fetchAll(PDO::FETCH_ASSOC);

    // Inicialización de estructura de gráfica con valores en cero para asegurar consistencia
    $chartData = [
        'pendiente' => 0,
        'confirmado' => 0,
        'enviado' => 0,
        'cancelado' => 0
    ];

    // Mapeo de resultados de DB a la estructura de la gráfica
    foreach ($chartDataRaw as $row) {
        if (isset($chartData[$row['estado_orden']])) {
            $chartData[$row['estado_orden']] = (int) $row['total'];
        }
    }

    // Respuesta final consolidada
    echo json_encode([
        'ok' => true,
        'stats' => [
            'productos' => (int) $resProductos,
            'pedidos' => (int) $resPedidos,
            'clientes' => (int) $resClientes,
            'servicios' => (int) $resServicios,
            'ventas_monto' => (float) $resVentas['monto'],
            'ventas_cant' => (int) $resVentas['cantidad']
        ],
        'chart_data' => $chartData
    ]);

} catch (PDOException $e) {
    // Captura de errores de SQL para depuración segura
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error SQL: ' . $e->getMessage()]);
}

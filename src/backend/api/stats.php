<?php
/**
 * API: ESTADÍSTICAS DEL DASHBOARD
 * ---------------------------------------------------------
 * Propósito: Centraliza el cálculo de métricas clave para el dashboard administrativo.
 * Acción: GET
 * Salida: JSON { ok: bool, stats: { productos, pedidos, clientes, servicios, ventas_monto, ventas_cant }, chart_data: { estado: total } }
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once '../config.php';
require_once '../utils/security_utils.php';

// 🛡️ PROTECCIÓN DE MÉTRICAS: Solo accesible por especialistas administrativos
requireRole('admin');


// Verificación de conexión a la base de datos
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de configuración de BD']);
    exit;
}

try {
    // 🔍 OPTIMIZACIÓN MAESTRA: Lectura de métricas persistentes (O(1))
    $stmtMetrics = $pdo->query("SELECT metric_key, metric_value FROM tab_sistema_metricas");
    $metrics = $stmtMetrics->fetchAll(PDO::FETCH_KEY_PAIR);

    $resProductos = $metrics['total_productos'] ?? 0;
    $resPedidos = $metrics['total_pedidos'] ?? 0;
    $resClientes = $metrics['total_clientes'] ?? 0;
    $resServicios = $metrics['total_servicios'] ?? 0;

    // 5. Ventas Totales: Lectura optimizada del contador de enviados
    $resVentasMonto = $pdo->query("SELECT COALESCE(SUM(total_orden), 0) FROM tab_Orden WHERE estado_orden = 'enviado'")->fetchColumn();
    $resVentasCant = $metrics['pedidos_enviado'] ?? 0;

    // 6. Datos para la gráfica: Estructura de carga cero
    $chartData = [
        'pendiente' => (int)($metrics['pedidos_pendiente'] ?? 0),
        'confirmado' => (int)($metrics['pedidos_confirmado'] ?? 0),
        'enviado' => (int)($metrics['pedidos_enviado'] ?? 0),
        'cancelado' => (int)($metrics['pedidos_cancelado'] ?? 0)
    ];

        ]
    ]);

    // 7. Satisfacción del Cliente: Carga cero
    $totalOpiniones = (int)($metrics['total_opiniones'] ?? 0);
    $satisfechas = (int)($metrics['total_opiniones_satisfechas'] ?? 0);
    $resSatisfaccion = ($totalOpiniones > 0) ? round(($satisfechas / $totalOpiniones) * 100) : 100;

    echo json_encode([
        'ok' => true,
        'stats' => [
            'productos' => (int)$resProductos,
            'pedidos' => (int)$resPedidos,
            'clientes' => (int)$resClientes,
            'servicios' => (int)$resServicios,
            'ventas_monto' => (float)$resVentasMonto,
            'ventas_cant' => (int)$resVentasCant,
            'satisfaccion' => $resSatisfaccion
        ],
        'chart_data' => $chartData,
        'public_stats' => [
            'total_products' => (int)$resProductos,
            'orders_completed' => (int)($metrics['pedidos_entregado'] ?? 0),
            'repaired' => (int)($metrics['total_servicios_realizados'] ?? 0),
            'active_clients' => (int)$resClientes,
            'satisfaction' => $resSatisfaccion
        ]
    ]);

}
catch (PDOException $e) {
    // Captura de errores de SQL para depuración segura
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error SQL: ' . $e->getMessage()]);
}
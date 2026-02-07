<?php
/**
 * API: OBTENER DATOS DE FACTURA/ORDEN
 * ---------------------------------------------------------
 * Retorna la cabecera de la factura, datos del cliente 
 * y la lista de productos comprados.
 */

header('Content-Type: application/json');
require_once '../config.php';

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de configuración de BD']);
    exit;
}

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Sesión no iniciada']);
    exit;
}

$idOrden = $_GET['id_orden'] ?? null;
if (!$idOrden) {
    echo json_encode(['ok' => false, 'msg' => 'ID de orden requerido']);
    exit;
}

try {
    // 1. Obtener cabecera (Factura + Orden + Usuario)
    $sqlFactura = "
        SELECT f.id_factura, f.fecha_emision, f.total_factura,
               o.id_orden, o.estado_orden, o.fecha_orden, o.concepto,
               u.nom_usuario, u.correo_usuario, u.num_telefono_usuario, u.direccion_principal
        FROM tab_Facturas f
        JOIN tab_Orden o ON f.id_orden = o.id_orden
        JOIN tab_Usuarios u ON f.id_usuario = u.id_usuario
        WHERE o.id_orden = ? AND f.id_usuario = ?
    ";

    $stmt = $pdo->prepare($sqlFactura);
    $stmt->execute([$idOrden, $_SESSION['user_id']]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$factura) {
        echo json_encode(['ok' => false, 'msg' => 'Factura no encontrada o no pertenece al usuario']);
        exit;
    }

    // 2. Obtener productos de la orden
    $sqlProd = "
        SELECT p.nom_producto, d.cantidad, d.precio_unitario, (d.cantidad * d.precio_unitario) as subtotal_linea
        FROM tab_Detalle_Orden d
        JOIN tab_Productos p ON d.id_producto = p.id_producto
        WHERE d.id_orden = ?
    ";
    $stmtProd = $pdo->prepare($sqlProd);
    $stmtProd->execute([$idOrden]);
    $productos = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'factura' => $factura,
        'productos' => $productos
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de BD: ' . $e->getMessage()]);
}

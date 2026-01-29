<?php
// backend/api/get_factura.php
ob_start();
include_once('../config.php');
ob_clean();

require_once('../security_headers.php');
header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Sesión expirada']);
    exit;
}

try {
    // Obtener ID de orden
    $id_orden = isset($_GET['id_orden']) ? intval($_GET['id_orden']) : 0;
    
    if ($id_orden <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'ID de orden inválido']);
        exit;
    }

    // Verificar que la orden pertenezca al usuario
    $stmt = $pdo->prepare("
        SELECT id_orden, id_usuario 
        FROM tab_Orden 
        WHERE id_orden = :id_orden
    ");
    $stmt->execute([':id_orden' => $id_orden]);
    $orden = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orden || $orden['id_usuario'] != $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'msg' => 'No tienes permiso para ver esta factura']);
        exit;
    }

    // Generar factura si no existe
    $stmt = $pdo->prepare("SELECT fun_generar_factura(:id_orden)");
    $stmt->execute([':id_orden' => $id_orden]);
    $resultado = $stmt->fetchColumn();

    if (strpos($resultado, 'ERROR') !== false) {
        throw new Exception($resultado);
    }

    // Obtener datos completos de la factura
    $stmt = $pdo->prepare("
        SELECT 
            f.id_factura,
            f.fecha_emision,
            f.total_factura,
            f.estado_factura,
            o.fecha_orden,
            o.estado_orden,
            o.concepto,
            u.nom_usuario,
            u.correo_usuario,
            u.num_telefono_usuario,
            u.direccion_principal
        FROM tab_Facturas f
        JOIN tab_Orden o ON f.id_orden = o.id_orden
        JOIN tab_Usuarios u ON f.id_usuario = u.id_usuario
        WHERE f.id_orden = :id_orden
    ");
    $stmt->execute([':id_orden' => $id_orden]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);

    // Obtener detalles de productos
    $stmt = $pdo->prepare("
        SELECT 
            df.cantidad,
            df.precio_unitario,
            df.subtotal_linea,
            p.nom_producto,
            p.url_imagen
        FROM tab_Detalle_Factura df
        JOIN tab_Productos p ON df.id_producto = p.id_producto
        WHERE df.id_factura = :id_factura
    ");
    $stmt->execute([':id_factura' => $factura['id_factura']]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'factura' => $factura,
        'productos' => $productos
    ]);

} catch (Exception $e) {
    error_log("Error en get_factura.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error al obtener factura']);
}
ob_end_flush();


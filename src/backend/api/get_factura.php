<?php
/**
 * API: CONSULTA DE FACTURACIÓN DETALLADA
 * ---------------------------------------------------------
 * Propósito: Recupera toda la información legal y comercial de una orden específica 
 * para su visualización en formato de factura electrónica.
 * 
 * Lógica de Seguridad:
 * 1. Exige sesión activa.
 * 2. Valida estrictamente que la orden solicitada pertenezca al ID_USUARIO en sesión 
 *    para prevenir ataques de Insecure Direct Object Reference (IDOR).
 * 
 * Datos Consolidados:
 * - Cabecera: Datos de la Factura, Orden y Perfil del Cliente.
 * - Detalle: Lista de items comprados con precios unitarios y subtotales.
 */

header('Content-Type: application/json');
require_once '../config.php';

// Integridad de la conexión
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error técnico: El motor de facturación no responde']);
    exit;
}

// Persistencia de sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Barrera de Autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Acceso denegado: Inicie sesión para consultar sus comprobantes']);
    exit;
}

$idOrden = $_GET['id_orden'] ?? null;
if (!$idOrden) {
    echo json_encode(['ok' => false, 'msg' => 'Solicitud incompleta: ID de orden no especificado']);
    exit;
}

try {
    /**
     * ==========================================
     * 📄 1. CABECERA DE FACTURACIÓN
     * ==========================================
     * Se consolidan 3 fuentes (Facturas, Ordenes, Usuarios) en una sola consulta.
     */
    $sqlFactura = "
        SELECT f.id_factura, f.fecha_emision, f.total_factura,
               o.id_orden, o.estado_orden, o.fecha_orden, o.concepto,
               u.nom_usuario, u.correo_usuario, u.num_telefono_usuario, u.direccion_principal
        FROM tab_Facturas f
        JOIN tab_Orden o ON f.id_orden = o.id_orden
        JOIN tab_Usuarios u ON f.id_usuario = u.id_usuario
        WHERE o.id_orden = ? AND f.id_usuario = ?
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sqlFactura);
    $stmt->execute([$idOrden, $_SESSION['user_id']]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);

    // Validación de Existencia/Permisos
    if (!$factura) {
        echo json_encode(['ok' => false, 'msg' => 'Información restringida o factura inexistente']);
        exit;
    }

    /**
     * ==========================================
     * 🛒 2. DETALLE DE ARTÍCULOS
     * ==========================================
     * Recupera los productos vinculados a la orden mediante JOIN a la tabla maestra de Productos.
     */
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
        'productos' => $productos,
        'msg' => 'Datos de facturación recuperados correctamente'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Inconsistencia interna al generar el reporte: ' . $e->getMessage()]);
}

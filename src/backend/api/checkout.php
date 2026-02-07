<?php
/**
 * API: PROCESO DE PAGO (CHECKOUT)
 * ---------------------------------------------------------
 * Convierte el carrito activo en una orden de compra,
 * descuenta el stock de los productos y limpia el carrito.
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

$userId = $_SESSION['user_id'];
$input = $_POST;
$file = $_FILES['payment_proof'] ?? null;

if (!isset($input['direccion'], $input['ciudad']) || !$file) {
    echo json_encode(['ok' => false, 'msg' => 'Faltan datos de envío o el comprobante de pago']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Obtener el carrito activo
    $stmt = $pdo->prepare("SELECT id_carrito FROM tab_Carrito WHERE id_usuario = ? AND estado_carrito = 'activo' LIMIT 1");
    $stmt->execute([$userId]);
    $carrito = $stmt->fetch();

    if (!$carrito) {
        throw new Exception("No tienes un carrito activo para procesar");
    }

    $carritoId = $carrito['id_carrito'];

    // 2. Obtener detalles del carrito
    $stmt = $pdo->prepare("
        SELECT d.id_producto, d.cantidad, p.precio, p.stock, p.nom_producto
        FROM tab_Carrito_Detalle d
        JOIN tab_Productos p ON d.id_producto = p.id_producto
        WHERE d.id_carrito = ?
    ");
    $stmt->execute([$carritoId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        throw new Exception("Tu carrito está vacío");
    }

    // 3. Validar stock y calcular total
    $totalOrden = 0;
    foreach ($items as $item) {
        if ($item['stock'] < $item['cantidad']) {
            throw new Exception("Stock insuficiente para: " . $item['nom_producto']);
        }
        $totalOrden += ($item['precio'] * $item['cantidad']);
    }

    // 4. Crear la Orden (tab_Orden)
    $idOrden = time();
    $sqlOrden = "INSERT INTO tab_Orden (id_orden, id_usuario, fecha_orden, estado_orden, total_orden, concepto, fec_insert, usr_insert) 
                 VALUES (?, ?, NOW(), 'pendiente', ?, ?, NOW(), 'system')";
    $stmtOrden = $pdo->prepare($sqlOrden);

    $metodoDesc = isset($input['metodo']) ? $input['metodo'] : 'Consignación Bancaria';
    $concepto = "Envío a: " . $input['direccion'] . " (" . $metodoDesc . ")";
    if (strlen($concepto) > 100)
        $concepto = substr($concepto, 0, 97) . "...";

    $stmtOrden->execute([$idOrden, $userId, $totalOrden, $concepto]);

    // 5. Crear detalles de la Orden
    $sqlDetalleO = "INSERT INTO tab_Detalle_Orden (id_detalle_orden, id_orden, id_producto, cantidad, precio_unitario, fec_insert, usr_insert) 
                    VALUES (?, ?, ?, ?, ?, NOW(), 'system')";
    $stmtDetalleO = $pdo->prepare($sqlDetalleO);

    $idFactura = $idOrden + 500;
    $sqlFactura = "INSERT INTO tab_Facturas (id_factura, id_orden, id_usuario, fecha_emision, total_factura, estado_factura, fec_insert, usr_insert) 
                   VALUES (?, ?, ?, NOW(), ?, 'Emitida', NOW(), 'system')";
    $pdo->prepare($sqlFactura)->execute([$idFactura, $idOrden, $userId, $totalOrden]);

    $sqlDetalleF = "INSERT INTO tab_Detalle_Factura (id_detalle_factura, id_factura, id_producto, cantidad, precio_unitario, subtotal_linea, fec_insert, usr_insert) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), 'system')";
    $stmtDetalleF = $pdo->prepare($sqlDetalleF);

    $sqlUpdateStock = "UPDATE tab_Productos SET stock = stock - ? WHERE id_producto = ?";
    $stmtStock = $pdo->prepare($sqlUpdateStock);

    foreach ($items as $idx => $item) {
        $idDetalle = ($idOrden * 100) + ($idx + 1);
        $stmtDetalleO->execute([$idDetalle, $idOrden, $item['id_producto'], $item['cantidad'], $item['precio']]);

        $idDetalleF = ($idFactura * 100) + ($idx + 1);
        $subtotal = $item['cantidad'] * $item['precio'];
        $stmtDetalleF->execute([$idDetalleF, $idFactura, $item['id_producto'], $item['cantidad'], $item['precio'], $subtotal]);

        $stmtStock->execute([$item['cantidad'], $item['id_producto']]);
    }

    // 6. Gestionar Dirección (tab_Direcciones_Envio)
    $stmtAddr = $pdo->prepare("SELECT id_direccion FROM tab_Direcciones_Envio WHERE id_usuario = ? AND direccion_completa = ? LIMIT 1");
    $stmtAddr->execute([$userId, $input['direccion']]);
    $existingAddr = $stmtAddr->fetch();

    if ($existingAddr) {
        $direccionId = $existingAddr['id_direccion'];
    } else {
        $direccionId = time() + rand(1, 1000);
        $stmtCity = $pdo->prepare("SELECT id_ciudad FROM tab_Ciudades WHERE nombre_ciudad ILIKE ? LIMIT 1");
        $stmtCity->execute(["%" . $input['ciudad'] . "%"]);
        $cityRow = $stmtCity->fetch();
        $idCiudad = $cityRow ? $cityRow['id_ciudad'] : 1;

        $sqlNewAddr = "INSERT INTO tab_Direcciones_Envio (id_direccion, id_usuario, direccion_completa, id_ciudad, codigo_postal, es_predeterminada, fec_insert, usr_insert) 
                       VALUES (?, ?, ?, ?, '000000', FALSE, NOW(), 'system')";
        $pdo->prepare($sqlNewAddr)->execute([$direccionId, $userId, $input['direccion'], $idCiudad]);
    }

    // 7. Registrar Envío (tab_Envios)
    $idEnvio = $idOrden + 1000;
    $sqlEnvio = "INSERT INTO tab_Envios (id_envio, id_orden, id_direccion_envio, metodo_envio, estado_envio, fecha_envio, fecha_entrega_estimada, costo_envio, fec_insert, usr_insert) 
                 VALUES (?, ?, ?, ?, 'pendiente', NOW(), NOW() + INTERVAL '3 days', 15000, NOW(), 'system')";
    $pdo->prepare($sqlEnvio)->execute([$idEnvio, $idOrden, $direccionId, 'Estándar']);

    // 8. Gestionar Archivo de Pago (Migrado a BYTEA en BD por seguridad)
    $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
    $binaryData = file_get_contents($file['tmp_name']);

    if (!$binaryData) {
        throw new Exception("No se pudo leer el archivo del comprobante");
    }

    // 9. Registrar Pago (tab_Pagos)
    $idPago = $idOrden + 2000;
    $sqlPago = "INSERT INTO tab_Pagos (
                    id_pago, id_orden, monto, id_metodo_pago, estado_pago, 
                    fecha_pago, comprobante_archivo, comprobante_extension, 
                    fec_insert, usr_insert
                ) VALUES (?, ?, ?, 1, 'pendiente', NOW(), ?, ?, NOW(), 'system')";

    $stmtPago = $pdo->prepare($sqlPago);
    $stmtPago->bindValue(1, $idPago);
    $stmtPago->bindValue(2, $idOrden);
    $stmtPago->bindValue(3, $totalOrden + 15000); // Total + Envío
    $stmtPago->bindValue(4, $binaryData, PDO::PARAM_LOB); // Binario para BYTEA
    $stmtPago->bindValue(5, $fileExt);
    $stmtPago->execute();

    // 10. Limpiar carrito
    $pdo->prepare("DELETE FROM tab_Carrito_Detalle WHERE id_carrito = ?")->execute([$carritoId]);
    $pdo->prepare("UPDATE tab_Carrito SET estado_carrito = 'convertido_a_orden', fec_update = NOW() WHERE id_carrito = ?")->execute([$carritoId]);

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'msg' => "¡Orden #$idOrden creada exitosamente!",
        'order_id' => $idOrden
    ]);

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}

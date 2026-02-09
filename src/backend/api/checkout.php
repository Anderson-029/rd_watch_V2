<?php
/**
 * API: PROCESO DE PAGO (CHECKOUT)
 * ---------------------------------------------------------
 * Propósito: Es el núcleo transaccional del sistema. Convierte un carrito de compras 
 * activo en una orden formal, gestionando pagos, inventarios y logística en un solo proceso.
 * 
 * Flujo Técnico:
 * 1. Valida autenticación y datos de entrada.
 * 2. Inicia una transacción SQL (Atomicidad).
 * 3. Valida disponibilidad de stock.
 * 4. Genera Orden, Factura, Envío y Registro de Pago.
 * 5. Almacena el comprobante bancario como binario (BYTEA).
 * 6. Limpia el carrito y cierra la transacción.
 * 
 * Requisito: Datos POST (dirección, ciudad, método) y Archivo (payment_proof).
 */

header('Content-Type: application/json');
require_once '../config.php';

// Verificación de integridad de la base de datos
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de conexión con el motor de base de datos']);
    exit;
}

// Iniciar sesión para identificar al comprador
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * 1. SEGURIDAD: VERIFICACIÓN DE SESIÓN
 */
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Acceso denegado: Debe estar autenticado']);
    exit;
}

$userId = $_SESSION['user_id'];
$input = $_POST;
$file = $_FILES['payment_proof'] ?? null;

// Validación de campos mínimos obligatorios
if (!isset($input['direccion'], $input['ciudad']) || !$file) {
    echo json_encode(['ok' => false, 'msg' => 'Faltan datos requeridos: Dirección, Ciudad o Comprobante de Pago']);
    exit;
}

try {
    /**
     * 2. INICIO DE TRANSACCIÓN
     * Garantiza que si falla algún insert o update, no se guarden datos parciales (corrupción de datos).
     */
    $pdo->beginTransaction();

    // PASO 1: Obtener el carrito activo del usuario
    $stmt = $pdo->prepare("SELECT id_carrito FROM tab_Carrito WHERE id_usuario = ? AND estado_carrito = 'activo' LIMIT 1");
    $stmt->execute([$userId]);
    $carrito = $stmt->fetch();

    if (!$carrito) {
        throw new Exception("Error: No se encontró un carrito de compras activo para este usuario.");
    }

    $carritoId = $carrito['id_carrito'];

    // PASO 2: Recuperar todos los productos del carrito con sus precios y stocks actuales
    $stmt = $pdo->prepare("
        SELECT d.id_producto, d.cantidad, p.precio, p.stock, p.nom_producto
        FROM tab_Carrito_Detalle d
        JOIN tab_Productos p ON d.id_producto = p.id_producto
        WHERE d.id_carrito = ?
    ");
    $stmt->execute([$carritoId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        throw new Exception("El carrito se encuentra vacío.");
    }

    // PASO 3: Validación Crítica de Stock y Cálculo del Total
    $totalOrden = 0;
    foreach ($items as $item) {
        if ($item['stock'] < $item['cantidad']) {
            throw new Exception("Lo sentimos, no hay stock suficiente para: " . $item['nom_producto']);
        }
        $totalOrden += ($item['precio'] * $item['cantidad']);
    }

    // PASO 4: Creación de la Orden Maestra (Cabecera)
    $idOrden = time(); // Generador de ID basado en timestamp
    $sqlOrden = "INSERT INTO tab_Orden (id_orden, id_usuario, fecha_orden, estado_orden, total_orden, concepto, fec_insert, usr_insert) 
                 VALUES (?, ?, NOW(), 'pendiente', ?, ?, NOW(), 'checkout_system')";
    $stmtOrden = $pdo->prepare($sqlOrden);

    $metodoDesc = isset($input['metodo']) ? $input['metodo'] : 'Consignación Bancaria';
    $concepto = "Compra RD-Watch: " . $input['direccion'] . " (" . $metodoDesc . ")";
    if (strlen($concepto) > 100)
        $concepto = substr($concepto, 0, 97) . "...";

    $stmtOrden->execute([$idOrden, $userId, $totalOrden, $concepto]);

    // PASO 5: Generación de Factura, Detalles de Orden y Actualización de Stock
    // Se crean los vínculos entre los productos, la orden y la factura legal.

    $idFactura = $idOrden + 500;
    $sqlFactura = "INSERT INTO tab_Facturas (id_factura, id_orden, id_usuario, fecha_emision, total_factura, estado_factura, fec_insert, usr_insert) 
                   VALUES (?, ?, ?, NOW(), ?, 'Emitida', NOW(), 'checkout_system')";
    $pdo->prepare($sqlFactura)->execute([$idFactura, $idOrden, $userId, $totalOrden]);

    // Preparación de sentencias para ejecución masiva en el bucle
    $stmtDetalleO = $pdo->prepare("INSERT INTO tab_Detalle_Orden (id_detalle_orden, id_orden, id_producto, cantidad, precio_unitario, fec_insert, usr_insert) 
                                   VALUES (?, ?, ?, ?, ?, NOW(), 'checkout_system')");
    $stmtDetalleF = $pdo->prepare("INSERT INTO tab_Detalle_Factura (id_detalle_factura, id_factura, id_producto, cantidad, precio_unitario, subtotal_linea, fec_insert, usr_insert) 
                                   VALUES (?, ?, ?, ?, ?, ?, NOW(), 'checkout_system')");
    $stmtStock = $pdo->prepare("UPDATE tab_Productos SET stock = stock - ?, fec_update = NOW(), usr_update = 'checkout' WHERE id_producto = ?");

    foreach ($items as $idx => $item) {
        $idDetalle = ($idOrden * 10) + ($idx + 1);
        $stmtDetalleO->execute([$idDetalle, $idOrden, $item['id_producto'], $item['cantidad'], $item['precio']]);

        $idDetalleF = ($idFactura * 10) + ($idx + 1);
        $subtotal = $item['cantidad'] * $item['precio'];
        $stmtDetalleF->execute([$idDetalleF, $idFactura, $item['id_producto'], $item['cantidad'], $item['precio'], $subtotal]);

        // Descuento efectivo del inventario
        $stmtStock->execute([$item['cantidad'], $item['id_producto']]);
    }

    // PASO 6: Gestión Logística de la Dirección
    $stmtAddr = $pdo->prepare("SELECT id_direccion FROM tab_Direcciones_Envio WHERE id_usuario = ? AND direccion_completa = ? LIMIT 1");
    $stmtAddr->execute([$userId, $input['direccion']]);
    $existingAddr = $stmtAddr->fetch();

    if ($existingAddr) {
        $direccionId = $existingAddr['id_direccion'];
    } else {
        // Si es una dirección nueva, se registra automáticamente
        $direccionId = time() + rand(1, 999);
        $stmtCity = $pdo->prepare("SELECT id_ciudad FROM tab_Ciudades WHERE nombre_ciudad ILIKE ? LIMIT 1");
        $stmtCity->execute(["%" . $input['ciudad'] . "%"]);
        $cityRow = $stmtCity->fetch();
        $idCiudad = $cityRow ? $cityRow['id_ciudad'] : 1;

        $sqlNewAddr = "INSERT INTO tab_Direcciones_Envio (id_direccion, id_usuario, direccion_completa, id_ciudad, codigo_postal, es_predeterminada, fec_insert, usr_insert) 
                       VALUES (?, ?, ?, ?, '000000', FALSE, NOW(), 'checkout_process')";
        $pdo->prepare($sqlNewAddr)->execute([$direccionId, $userId, $input['direccion'], $idCiudad]);
    }

    // PASO 7: Registro del Envío (Logística)
    $idEnvio = $idOrden + 1000;
    $sqlEnvio = "INSERT INTO tab_Envios (id_envio, id_orden, id_direccion_envio, metodo_envio, estado_envio, fecha_envio, fecha_entrega_estimada, costo_envio, fec_insert, usr_insert) 
                 VALUES (?, ?, ?, ?, 'pendiente', NOW(), NOW() + INTERVAL '3 days', 15000, NOW(), 'logistics_system')";
    $pdo->prepare($sqlEnvio)->execute([$idEnvio, $idOrden, $direccionId, 'Estándar Premium']);

    // PASO 8: Procesamiento de Comprobante de Pago (Seguridad Binaria)
    $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
    $binaryData = file_get_contents($file['tmp_name']);

    if (!$binaryData) {
        throw new Exception("Error Crítico: El archivo del comprobante no pudo ser procesado.");
    }

    // PASO 9: Registro del Pago
    $idPago = $idOrden + 2000;
    $sqlPago = "INSERT INTO tab_Pagos (
                    id_pago, id_orden, monto, id_metodo_pago, estado_pago, 
                    fecha_pago, comprobante_archivo, comprobante_extension, 
                    fec_insert, usr_insert
                ) VALUES (?, ?, ?, 1, 'pendiente', NOW(), ?, ?, NOW(), 'finance_system')";

    $stmtPago = $pdo->prepare($sqlPago);
    $stmtPago->bindValue(1, $idPago);
    $stmtPago->bindValue(2, $idOrden);
    $stmtPago->bindValue(3, $totalOrden + 15000); // Monto Total + Costo de Envío
    $stmtPago->bindValue(4, $binaryData, PDO::PARAM_LOB); // Inserción como Objeto Binario Grande (LOB/BYTEA)
    $stmtPago->bindValue(5, $fileExt);
    $stmtPago->execute();

    // PASO 10: Limpieza y Cierre del Carrito
    $pdo->prepare("DELETE FROM tab_Carrito_Detalle WHERE id_carrito = ?")->execute([$carritoId]);
    $pdo->prepare("UPDATE tab_Carrito SET estado_carrito = 'convertido_a_orden', fec_update = NOW(), usr_update = 'checkout' WHERE id_carrito = ?")->execute([$carritoId]);

    // COMMIT: Confirmación de todos los cambios en la base de datos
    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'msg' => "¡Excelente! Su orden #$idOrden ha sido generada. Validaremos su pago a la brevedad.",
        'order_id' => $idOrden
    ]);

} catch (Exception $e) {
    /**
     * GESTIÓN DE FALLOS: ROLLBACK
     * Si cualquier paso falla, se deshacen todos los INSERTs y UPDATEs realizados desde el beginTransaction.
     */
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Proceso de Checkout interrumpido: ' . $e->getMessage()]);
}

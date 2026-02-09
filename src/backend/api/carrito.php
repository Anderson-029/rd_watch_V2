<?php
/**
 * API: GESTIÓN DE CARRITO DE COMPRAS
 * ---------------------------------------------------------
 * Propósito: Administra la persistencia de productos que un cliente ha seleccionado
 * para su compra. El carrito se guarda en base de datos para preservar los ítems
 * entre sesiones o dispositivos del mismo usuario.
 * 
 * Requisito: Sesión de usuario activa ($_SESSION['user_id']).
 * 
 * Métodos:
 * - GET: Obtener lista de productos en el carrito activo.
 * - POST: Agregar un nuevo producto o incrementar cantidad existente.
 * - PUT: Actualizar cantidad específica de un producto.
 * - DELETE: Eliminar un producto o vaciar el carrito por completo.
 */

header('Content-Type: application/json');
require_once '../config.php';

// Verificación de integridad de la conexión
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error técnico: Conexión a BD no disponible']);
    exit;
}

// Asegurar que la sesión PHP esté iniciada para acceder a $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * 1. SEGURIDAD: CONTROL DE ACCESO
 * El carrito es una funcionalidad privada del usuario autenticado.
 */
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Sesión no válida o expirada. Inicie sesión de nuevo.']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Función para decodificar el cuerpo de peticiones (POST/PUT/DELETE).
 */
function getJsonInput()
{
    return json_decode(file_get_contents('php://input'), true);
}

try {
    /**
     * 2. INICIALIZACIÓN DEL CARRITO
     * Antes de cualquier operación, se verifica si el usuario tiene un carrito con estado 'activo'.
     * Si no existe, se crea uno automáticamente para las operaciones de escritura.
     */
    $stmt = $pdo->prepare("SELECT id_carrito FROM tab_Carrito WHERE id_usuario = ? AND estado_carrito = 'activo'");
    $stmt->execute([$userId]);
    $carrito = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$carrito && in_array($method, ['POST', 'PUT', 'GET'])) {
        // Crear carrito nuevo (Usamos time() como ID único secuencial simple)
        $id_carrito = time();
        $sql = "INSERT INTO tab_Carrito (id_carrito, id_usuario, estado_carrito, fec_insert, usr_insert) 
                VALUES (?, ?, 'activo', NOW(), 'system_cart')";
        $pdo->prepare($sql)->execute([$id_carrito, $userId]);
        $carritoId = $id_carrito;
    } else {
        $carritoId = $carrito ? $carrito['id_carrito'] : null;
    }

    // Procesamiento según el verbo HTTP
    switch ($method) {
        case 'GET':
            /**
             * ACCIÓN: Listar Contenido
             * Une la tabla de detalles con la de productos para obtener nombres, precios e imágenes.
             */
            if (!$carritoId) {
                echo json_encode(['ok' => true, 'items' => []]);
                exit;
            }

            $sql = "SELECT d.id_producto, p.nom_producto, p.precio, p.url_imagen, p.stock, d.cantidad 
                    FROM tab_Carrito_Detalle d
                    JOIN tab_Productos p ON d.id_producto = p.id_producto
                    WHERE d.id_carrito = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$carritoId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'items' => $items]);
            break;

        case 'POST':
            /**
             * ACCIÓN: Agregar Ítem
             * Si el producto ya está en el carrito, se suma la cantidad nueva a la anterior.
             */
            $data = getJsonInput();
            if (!isset($data['id_producto'], $data['cantidad'])) {
                echo json_encode(['ok' => false, 'msg' => 'Parámetros id_producto y cantidad son obligatorios']);
                exit;
            }

            $id_prod = $data['id_producto'];
            $qty = (int) $data['cantidad'];

            // Comprobar existencia previa en el detalle del carrito
            $check = $pdo->prepare("SELECT cantidad FROM tab_Carrito_Detalle WHERE id_carrito = ? AND id_producto = ?");
            $check->execute([$carritoId, $id_prod]);
            $existing = $check->fetch();

            if ($existing) {
                // Actualización (Incremento acumulativo)
                $newQty = $existing['cantidad'] + $qty;
                $stmt = $pdo->prepare("UPDATE tab_Carrito_Detalle SET cantidad = ?, fec_update = NOW() WHERE id_carrito = ? AND id_producto = ?");
                $stmt->execute([$newQty, $carritoId, $id_prod]);
            } else {
                // Inserción de nuevo registro de detalle
                $id_detalle = $carritoId . rand(100, 999); // Generador de ID basado en el carrito + aleatorio
                $sql = "INSERT INTO tab_Carrito_Detalle (id_carrito_detalle, id_carrito, id_producto, cantidad, fec_insert, usr_insert) 
                        VALUES (?, ?, ?, ?, NOW(), 'user_add')";
                $pdo->prepare($sql)->execute([$id_detalle, $carritoId, $id_prod, $qty]);
            }

            echo json_encode(['ok' => true, 'msg' => 'Producto añadido exitosamente al carrito']);
            break;

        case 'PUT':
            /**
             * ACCIÓN: Actualizar Cantidad
             * Reemplaza la cantidad actual por la enviada (usado en el selector de cantidad del UI).
             */
            $data = getJsonInput();
            if (!isset($data['id_producto'], $data['cantidad'])) {
                echo json_encode(['ok' => false, 'msg' => 'Datos insuficientes para actualizar']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE tab_Carrito_Detalle SET cantidad = ?, fec_update = NOW() WHERE id_carrito = ? AND id_producto = ?");
            $stmt->execute([$data['cantidad'], $carritoId, $data['id_producto']]);

            echo json_encode(['ok' => true, 'msg' => 'Cantidad modificada correctamente']);
            break;

        case 'DELETE':
            /**
             * ACCIÓN: Eliminar / Vaciar
             * Si se envía id_producto, solo elimina ese ítem. Si no, limpia todo el carrito.
             */
            $data = getJsonInput();
            $id_prod = $data['id_producto'] ?? null;

            if ($id_prod) {
                // Eliminación quirúrgica
                $stmt = $pdo->prepare("DELETE FROM tab_Carrito_Detalle WHERE id_carrito = ? AND id_producto = ?");
                $stmt->execute([$carritoId, $id_prod]);
                echo json_encode(['ok' => true, 'msg' => 'Producto retirado del carrito']);
            } else {
                // Limpieza total
                $stmt = $pdo->prepare("DELETE FROM tab_Carrito_Detalle WHERE id_carrito = ?");
                $stmt->execute([$carritoId]);
                echo json_encode(['ok' => true, 'msg' => 'El carrito ha sido vaciado por completo']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['ok' => false, 'msg' => 'Esta API no soporta el método solicitado']);
            break;
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Falla técnica de base de datos: ' . $e->getMessage()]);
}

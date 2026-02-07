<?php
/**
 * API: GESTIÓN DE CARRITO DE COMPRAS
 * ---------------------------------------------------------
 * Maneja la persistencia de los productos que el usuario
 * desea comprar. Requiere sesión activa.
 */

header('Content-Type: application/json');
require_once '../config.php';

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de configuración de BD']);
    exit;
}

// Iniciar sesión si no existe
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
$method = $_SERVER['REQUEST_METHOD'];

function getJsonInput()
{
    return json_decode(file_get_contents('php://input'), true);
}

try {
    // 1. Asegurar que el usuario tenga un carrito activo
    $stmt = $pdo->prepare("SELECT id_carrito FROM tab_Carrito WHERE id_usuario = ? AND estado_carrito = 'activo'");
    $stmt->execute([$userId]);
    $carrito = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$carrito && in_array($method, ['POST', 'PUT', 'GET'])) {
        // Crear carrito nuevo si no existe
        $id_carrito = time(); // Generador simple de ID (idealmente SERIAL en DB)
        $sql = "INSERT INTO tab_Carrito (id_carrito, id_usuario, estado_carrito, fec_insert, usr_insert) 
                VALUES (?, ?, 'activo', NOW(), 'system')";
        $pdo->prepare($sql)->execute([$id_carrito, $userId]);
        $carritoId = $id_carrito;
    } else {
        $carritoId = $carrito ? $carrito['id_carrito'] : null;
    }

    switch ($method) {
        case 'GET':
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
            $data = getJsonInput();
            if (!isset($data['id_producto'], $data['cantidad'])) {
                echo json_encode(['ok' => false, 'msg' => 'Datos incompletos']);
                exit;
            }

            $id_prod = $data['id_producto'];
            $qty = (int) $data['cantidad'];

            // Verificar si ya existe el producto en el carrito
            $check = $pdo->prepare("SELECT cantidad FROM tab_Carrito_Detalle WHERE id_carrito = ? AND id_producto = ?");
            $check->execute([$carritoId, $id_prod]);
            $existing = $check->fetch();

            if ($existing) {
                // Actualizar cantidad (incrementar)
                $newQty = $existing['cantidad'] + $qty;
                $stmt = $pdo->prepare("UPDATE tab_Carrito_Detalle SET cantidad = ? WHERE id_carrito = ? AND id_producto = ?");
                $stmt->execute([$newQty, $carritoId, $id_prod]);
            } else {
                // Insertar nuevo detalle
                $id_detalle = time() + rand(1, 1000); // Evitar colisiones simples
                $sql = "INSERT INTO tab_Carrito_Detalle (id_carrito_detalle, id_carrito, id_producto, cantidad, fec_insert, usr_insert) 
                        VALUES (?, ?, ?, ?, NOW(), 'system')";
                $pdo->prepare($sql)->execute([$id_detalle, $carritoId, $id_prod, $qty]);
            }

            echo json_encode(['ok' => true, 'msg' => 'Producto agregado al carrito']);
            break;

        case 'PUT':
            $data = getJsonInput();
            if (!isset($data['id_producto'], $data['cantidad'])) {
                echo json_encode(['ok' => false, 'msg' => 'Datos incompletos']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE tab_Carrito_Detalle SET cantidad = ? WHERE id_carrito = ? AND id_producto = ?");
            $stmt->execute([$data['cantidad'], $carritoId, $data['id_producto']]);

            echo json_encode(['ok' => true, 'msg' => 'Cantidad actualizada']);
            break;

        case 'DELETE':
            $data = getJsonInput();
            $id_prod = $data['id_producto'] ?? null;

            if ($id_prod) {
                // Eliminar producto específico
                $stmt = $pdo->prepare("DELETE FROM tab_Carrito_Detalle WHERE id_carrito = ? AND id_producto = ?");
                $stmt->execute([$carritoId, $id_prod]);
                echo json_encode(['ok' => true, 'msg' => 'Producto eliminado']);
            } else {
                // Vaciar todo el carrito
                $stmt = $pdo->prepare("DELETE FROM tab_Carrito_Detalle WHERE id_carrito = ?");
                $stmt->execute([$carritoId]);
                echo json_encode(['ok' => true, 'msg' => 'Carrito vaciado']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
            break;
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de BD: ' . $e->getMessage()]);
}

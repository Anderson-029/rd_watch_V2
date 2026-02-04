<?php
header('Content-Type: application/json');
require_once '../config.php';

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de configuración de BD']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Helper para leer body JSON
function getJsonInput() {
    return json_decode(file_get_contents('php://input'), true);
}

try {
    switch ($method) {
        case 'GET':
            // Listar productos con nombres de marcas y categorías
            // Hacemos JOINs para mostrar nombres en lugar de IDs en la tabla principal
            $sql = "SELECT p.id_producto, p.nom_producto, p.precio, p.stock, p.url_imagen, p.descripcion, p.estado,
                           m.nom_marca, m.id_marca,
                           c.nom_categoria, c.id_categoria,
                           s.nom_subcategoria, s.id_subcategoria
                    FROM tab_Productos p
                    LEFT JOIN tab_Marcas m ON p.id_marca = m.id_marca
                    LEFT JOIN tab_Categorias c ON p.id_categoria = c.id_categoria
                    LEFT JOIN tab_Subcategorias s ON (p.id_categoria = s.id_categoria AND p.id_subcategoria = s.id_subcategoria)
                    ORDER BY p.id_producto DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['ok' => true, 'productos' => $productos]);
            break;

        case 'POST':
            // Crear producto
            $data = getJsonInput();
            
            // Validaciones básicas
            if (!isset($data['id_producto'], $data['nom_producto'], $data['precio'], $data['id_marca'], $data['id_categoria'], $data['id_subcategoria'])) {
                echo json_encode(['ok' => false, 'msg' => 'Faltan datos obligatorios']);
                exit;
            }

            // Validar existencia ID
            $check = $pdo->prepare("SELECT id_producto FROM tab_Productos WHERE id_producto = ?");
            $check->execute([$data['id_producto']]);
            if ($check->fetch()) {
                echo json_encode(['ok' => false, 'msg' => 'Ya existe un producto con ese ID']);
                exit;
            }
            
            // Validar que subcategoría pertenezca a categoría
             $checkSub = $pdo->prepare("SELECT id_subcategoria FROM tab_Subcategorias WHERE id_categoria = ? AND id_subcategoria = ?");
             $checkSub->execute([$data['id_categoria'], $data['id_subcategoria']]);
             if (!$checkSub->fetch()) {
                 echo json_encode(['ok' => false, 'msg' => 'La subcategoría no coincide con la categoría seleccionada']);
                 exit;
             }

            $sql = "INSERT INTO tab_Productos (
                        id_producto, nom_producto, descripcion, precio, stock, url_imagen,
                        id_marca, id_categoria, id_subcategoria, estado,
                        fec_insert, usr_insert
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        NOW(), 'admin'
                    )";

            $stmt = $pdo->prepare($sql);
            
            $estado = true; // Por defecto activo al crear
            $img = $data['url_imagen'] ?? null;
            $desc = $data['descripcion'] ?? '';

            if ($stmt->execute([
                $data['id_producto'],
                $data['nom_producto'],
                $desc,
                $data['precio'],
                $data['stock'],
                $img,
                $data['id_marca'],
                $data['id_categoria'],
                $data['id_subcategoria'],
                $estado ? 'true' : 'false'
            ])) {
                echo json_encode(['ok' => true, 'msg' => 'Producto creado correctamente']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error al crear producto']);
            }
            break;

        case 'PUT':
            // Actualizar producto
            $data = getJsonInput();
            
            if (!isset($data['id_producto'])) {
                echo json_encode(['ok' => false, 'msg' => 'ID de producto requerido']);
                exit;
            }

             // Validar que subcategoría pertenezca a categoría si se envían
             if (isset($data['id_categoria'], $data['id_subcategoria'])) {
                 $checkSub = $pdo->prepare("SELECT id_subcategoria FROM tab_Subcategorias WHERE id_categoria = ? AND id_subcategoria = ?");
                 $checkSub->execute([$data['id_categoria'], $data['id_subcategoria']]);
                 if (!$checkSub->fetch()) {
                     echo json_encode(['ok' => false, 'msg' => 'La subcategoría no coincide con la categoría seleccionada']);
                     exit;
                 }
             }

            $sql = "UPDATE tab_Productos SET
                        nom_producto = ?,
                        descripcion = ?,
                        precio = ?,
                        stock = ?,
                        url_imagen = ?,
                        id_marca = ?,
                        id_categoria = ?,
                        id_subcategoria = ?,
                        fec_update = NOW(),
                        usr_update = 'admin'
                    WHERE id_producto = ?";

            $stmt = $pdo->prepare($sql);
            
            $img = $data['url_imagen'] ?? null;
            $desc = $data['descripcion'] ?? '';

            if ($stmt->execute([
                $data['nom_producto'],
                $desc,
                $data['precio'],
                $data['stock'],
                $img,
                $data['id_marca'],
                $data['id_categoria'],
                $data['id_subcategoria'],
                $data['id_producto']
            ])) {
                echo json_encode(['ok' => true, 'msg' => 'Producto actualizado']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error al actualizar producto']);
            }
            break;

        case 'DELETE':
            // Eliminar producto
            $data = getJsonInput();
            if (!isset($data['id_producto'])) {
                echo json_encode(['ok' => false, 'msg' => 'ID requerido']);
                exit;
            }

            // Verificar si hay órdenes/detalles asociados
            $check = $pdo->prepare("SELECT COUNT(*) FROM tab_Detalle_Orden WHERE id_producto = ?");
            $check->execute([$data['id_producto']]);
            if ($check->fetchColumn() > 0) {
                 echo json_encode(['ok' => false, 'msg' => 'No se puede eliminar: El producto está en órdenes de compra']);
                 exit;
            }
            
            // Verificar carrito
            $checkCart = $pdo->prepare("SELECT COUNT(*) FROM tab_Carrito_Detalle WHERE id_producto = ?");
            $checkCart->execute([$data['id_producto']]);
            if ($checkCart->fetchColumn() > 0) {
                 // Opción: Borrar del carrito o impedir. Por integridad, impedimos.
                 echo json_encode(['ok' => false, 'msg' => 'No se puede eliminar: El producto está en carritos activos']);
                 exit;
            }

            $stmt = $pdo->prepare("DELETE FROM tab_Productos WHERE id_producto = ?");
            if ($stmt->execute([$data['id_producto']])) {
                echo json_encode(['ok' => true, 'msg' => 'Producto eliminado']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error al eliminar producto']);
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

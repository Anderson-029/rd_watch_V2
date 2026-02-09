<?php
/**
 * API: GESTIÓN DE PRODUCTOS (CATÁLOGO DE RELOJES)
 * ---------------------------------------------------------
 * Propósito: Centraliza el ciclo de vida de los productos (relojes). Permite la 
 * visualización enriquecida para el cliente y la gestión administrativa total.
 * 
 * Capas de Integridad:
 * - Validación de jerarquía Categoría -> Subcategoría.
 * - Restricciones de borrado lógico vs fisico (Protección de historial).
 * - Sincronización de catálogos via JOINs.
 * 
 * Métodos:
 * - GET: Listado completo con nombres de marcas y categorías.
 * - POST: Creación de nuevos productos con validación de ID único.
 * - PUT: Actualización de atributos y stock.
 * - DELETE: Eliminación segura (valida que no tenga historial de ventas).
 */

header('Content-Type: application/json');
require_once '../config.php';

// Verificación de disponibilidad de la base de datos
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error técnico: Conector de datos no inicializado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

/**
 * Captura datos de cuerpo JSON.
 */
function getJsonInput()
{
    return json_decode(file_get_contents('php://input'), true);
}

try {
    switch ($method) {
        case 'GET':
            /**
             * ==========================================
             * 🔍 LISTAR PRODUCTOS (GET)
             * ==========================================
             * Lógica: Realiza JOINs con tab_Marcas, tab_Categorias y tab_Subcategorias.
             * Esto evita que el frontend deba hacer múltiples peticiones para resolver IDs.
             */
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
            /**
             * ==========================================
             * ➕ CREAR PRODUCTO (POST)
             * ==========================================
             * Seguridad: Valida duplicidad de ID y pertenencia correcta de subcategoría.
             */
            $data = getJsonInput();

            if (!isset($data['id_producto'], $data['nom_producto'], $data['precio'], $data['id_marca'], $data['id_categoria'], $data['id_subcategoria'])) {
                echo json_encode(['ok' => false, 'msg' => 'Error: Todos los campos marcados como obligatorios deben ser completados']);
                exit;
            }

            // 1. Validar que el código de producto sea único
            $check = $pdo->prepare("SELECT id_producto FROM tab_Productos WHERE id_producto = ?");
            $check->execute([$data['id_producto']]);
            if ($check->fetch()) {
                echo json_encode(['ok' => false, 'msg' => 'El código de producto (ID) ya está registrado en el sistema']);
                exit;
            }

            // 2. Validar jerarquía: La subcategoría debe pertenecer a la categoría padre elegida
            $checkSub = $pdo->prepare("SELECT id_subcategoria FROM tab_Subcategorias WHERE id_categoria = ? AND id_subcategoria = ?");
            $checkSub->execute([$data['id_categoria'], $data['id_subcategoria']]);
            if (!$checkSub->fetch()) {
                echo json_encode(['ok' => false, 'msg' => 'Inconsistencia: La subcategoría seleccionada no pertenece a la categoría padre']);
                exit;
            }

            $sql = "INSERT INTO tab_Productos (
                        id_producto, nom_producto, descripcion, precio, stock, url_imagen,
                        id_marca, id_categoria, id_subcategoria, estado,
                        fec_insert, usr_insert
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'admin_inventario')";

            $stmt = $pdo->prepare($sql);
            $img = $data['url_imagen'] ?? null;
            $desc = $data['descripcion'] ?? '';

            if (
                $stmt->execute([
                    $data['id_producto'],
                    $data['nom_producto'],
                    $desc,
                    $data['precio'],
                    $data['stock'],
                    $img,
                    $data['id_marca'],
                    $data['id_categoria'],
                    $data['id_subcategoria'],
                    'true'
                ])
            ) {
                echo json_encode(['ok' => true, 'msg' => 'Nuevo reloj añadido al catálogo exitosamente']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Fallo al insertar el registro de producto']);
            }
            break;

        case 'PUT':
            /**
             * ==========================================
             * 🔄 ACTUALIZAR PRODUCTO (PUT)
             * ==========================================
             */
            $data = getJsonInput();

            if (!isset($data['id_producto'])) {
                echo json_encode(['ok' => false, 'msg' => 'Se requiere el ID del producto para realizar la actualización']);
                exit;
            }

            // Re-validación de jerarquía si se cambiaron categorías
            if (isset($data['id_categoria'], $data['id_subcategoria'])) {
                $checkSub = $pdo->prepare("SELECT id_subcategoria FROM tab_Subcategorias WHERE id_categoria = ? AND id_subcategoria = ?");
                $checkSub->execute([$data['id_categoria'], $data['id_subcategoria']]);
                if (!$checkSub->fetch()) {
                    echo json_encode(['ok' => false, 'msg' => 'Categoría y Subcategoría no coinciden']);
                    exit;
                }
            }

            $sql = "UPDATE tab_Productos SET
                        nom_producto = ?, descripcion = ?, precio = ?, stock = ?, 
                        url_imagen = ?, id_marca = ?, id_categoria = ?, id_subcategoria = ?,
                        fec_update = NOW(), usr_update = 'admin_editor'
                    WHERE id_producto = ?";

            $stmt = $pdo->prepare($sql);
            $img = $data['url_imagen'] ?? null;
            $desc = $data['descripcion'] ?? '';

            if (
                $stmt->execute([
                    $data['nom_producto'],
                    $desc,
                    $data['precio'],
                    $data['stock'],
                    $img,
                    $data['id_marca'],
                    $data['id_categoria'],
                    $data['id_subcategoria'],
                    $data['id_producto']
                ])
            ) {
                echo json_encode(['ok' => true, 'msg' => 'Información del producto actualizada correctamente']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error técnico al intentar actualizar el producto']);
            }
            break;

        case 'DELETE':
            /**
             * ==========================================
             * 🗑️ ELIMINACIÓN SEGURA (DELETE)
             * ==========================================
             * Bloqueo de Borrado: Un producto no puede borrarse físicamente si:
             * 1. Ya ha sido vendido (Integridad de facturación/pedidos).
             * 2. Está en un carrito de compras (Experiencia de usuario).
             */
            $data = getJsonInput();
            $pid = $data['id_producto'] ?? null;

            if (!$pid) {
                echo json_encode(['ok' => false, 'msg' => 'ID de producto no proporcionado']);
                exit;
            }

            // 1. Verificar registros en historial de órdenes
            $check = $pdo->prepare("SELECT COUNT(*) FROM tab_Detalle_Orden WHERE id_producto = ?");
            $check->execute([$pid]);
            if ($check->fetchColumn() > 0) {
                echo json_encode(['ok' => false, 'msg' => 'Imposible borrar: Este reloj posee historial de ventas vinculado']);
                exit;
            }

            // 2. Verificar si hay usuarios con este producto en su carrito
            $checkCart = $pdo->prepare("SELECT COUNT(*) FROM tab_Carrito_Detalle WHERE id_producto = ?");
            $checkCart->execute([$pid]);
            if ($checkCart->fetchColumn() > 0) {
                echo json_encode(['ok' => false, 'msg' => 'Imposible borrar: El producto está siendo procesado en carritos activos']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM tab_Productos WHERE id_producto = ?");
            if ($stmt->execute([$pid])) {
                echo json_encode(['ok' => true, 'msg' => 'Producto eliminado definitivamente del catálogo']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Falla en la base de datos al ejecutar el borrado']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['ok' => false, 'msg' => 'Método HTTP denegado para esta API']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error crítico de base de datos: ' . $e->getMessage()]);
}

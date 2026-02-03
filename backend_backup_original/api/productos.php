<?php
/**
 * RD Watch - Sistema de Gestión de Relojería
 * Endpoints para Gestión de Productos
 * 
 * Permite listar productos (público) y realizar operaciones CRUD (admin).
 */

// Aplicar configuración global primero (manejo de errores, DB, sesión)
require_once('../config.php');
require_once('../security_headers.php');
require_once('../validator.php');
require_once('../encoder.php');
require_once('../csrf.php');

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // =================================================================
            // LISTAR PRODUCTOS (Acceso Público)
            // =================================================================
            $stmt = $pdo->query("
                SELECT p.*, m.nom_marca, c.nom_categoria, s.nom_subcategoria
                FROM tab_Productos p
                LEFT JOIN tab_Marcas m ON p.id_marca = m.id_marca
                LEFT JOIN tab_Categorias c ON p.id_categoria = c.id_categoria
                LEFT JOIN tab_Subcategorias s 
                    ON p.id_categoria = s.id_categoria 
                    AND p.id_subcategoria = s.id_subcategoria
                WHERE p.estado = TRUE
                ORDER BY p.id_producto DESC
            ");
            $productos = $stmt->fetchAll();

            // Sanitización de salida para prevenir XSS
            foreach ($productos as &$prod) {
                $prod['nom_producto'] = Encoder::html($prod['nom_producto']);
                $prod['descripcion'] = Encoder::html($prod['descripcion']);
                $prod['url_imagen'] = Encoder::url($prod['url_imagen'] ?? '');
            }

            ErrorHandler::sendSuccess("Lista de productos obtenida", ['productos' => $productos]);
            break;

        case 'POST':
        case 'PUT':
        case 'DELETE':
            // =================================================================
            // OPERACIONES ADMINISTRATIVAS (Requiere Rol Admin)
            // =================================================================
            if (!isset($_SESSION['user_id']) || ($_SESSION['user_rol'] ?? '') !== 'admin') {
                ErrorHandler::stopError("Acceso denegado: se requieren privilegios de administrador", 403);
            }

            if ($method === 'POST' || $method === 'PUT' || $method === 'DELETE') {
                require_csrf_token();
            }

            $data = json_decode(file_get_contents("php://input"), true);
            if (!$data) {
                ErrorHandler::stopError("Datos de solicitud inválidos", 400);
            }

            if ($method === 'POST') {
                // Validación y creación
                $id = Validator::validateId($data['id_producto'] ?? 0);
                $marca = Validator::validateId($data['id_marca'] ?? 0);
                $nombre = Validator::sanitizeString($data['nom_producto'] ?? '', 255);
                $desc = Validator::sanitizeString($data['descripcion'] ?? '', 1000);
                $precio = floatval($data['precio'] ?? 0);
                $cat = Validator::validateId($data['id_categoria'] ?? 0);
                $subcat = Validator::validateId($data['id_subcategoria'] ?? 0);
                $stock = intval($data['stock'] ?? 0);
                $img = Validator::sanitizeString($data['url_imagen'] ?? '', 500);

                $stmt = $pdo->prepare("SELECT fun_insert_productos(:id::bigint, :marca::bigint, :nombre::varchar, :desc::text, :precio::numeric, :cat::integer, :subcat::integer, :stock::smallint, :img::varchar)");
                $stmt->execute([
                    ':id' => $id,
                    ':marca' => $marca,
                    ':nombre' => $nombre,
                    ':desc' => $desc,
                    ':precio' => $precio,
                    ':cat' => $cat,
                    ':subcat' => $subcat,
                    ':stock' => $stock,
                    ':img' => $img
                ]);

                $response = $stmt->fetchColumn();
                if (strpos($response, 'SUCCESS') !== false) {
                    ErrorHandler::sendSuccess("Producto creado correctamente", ['details' => $response]);
                } else {
                    ErrorHandler::stopError($response, 400);
                }

            } elseif ($method === 'PUT') {
                // Validación y actualización
                $id = Validator::validateId($data['id_producto'] ?? 0);
                $marca = Validator::validateId($data['id_marca'] ?? 0);
                $nombre = Validator::sanitizeString($data['nom_producto'] ?? '', 255);
                $desc = Validator::sanitizeString($data['descripcion'] ?? '', 1000);
                $precio = floatval($data['precio'] ?? 0);
                $cat = Validator::validateId($data['id_categoria'] ?? 0);
                $subcat = Validator::validateId($data['id_subcategoria'] ?? 0);
                $stock = intval($data['stock'] ?? 0);
                $img = Validator::sanitizeString($data['url_imagen'] ?? '', 500);
                $estado = isset($data['estado']) ? ($data['estado'] === true || $data['estado'] === 'true') : true;

                $stmt = $pdo->prepare("SELECT fun_update_productos(:id, :marca, :nombre, :desc, :precio, :cat, :subcat, :stock, :img, :estado)");
                $stmt->execute([
                    ':id' => $id,
                    ':marca' => $marca,
                    ':nombre' => $nombre,
                    ':desc' => $desc,
                    ':precio' => $precio,
                    ':cat' => $cat,
                    ':subcat' => $subcat,
                    ':stock' => $stock,
                    ':img' => $img,
                    ':estado' => $estado ? 'true' : 'false'
                ]);

                $response = $stmt->fetchColumn();
                if (strpos($response, 'SUCCESS') !== false) {
                    ErrorHandler::sendSuccess("Producto actualizado correctamente", ['details' => $response]);
                } else {
                    ErrorHandler::stopError($response, 400);
                }

            } elseif ($method === 'DELETE') {
                // Eliminación lógica
                $id = Validator::validateId($data['id_producto'] ?? 0);

                $stmt = $pdo->prepare("SELECT fun_delete_productos(:id)");
                $stmt->execute([':id' => $id]);

                $response = $stmt->fetchColumn();
                if (strpos($response, 'SUCCESS') !== false) {
                    ErrorHandler::sendSuccess("Producto desactivado correctamente");
                } else {
                    ErrorHandler::stopError($response, 400);
                }
            }
            break;

        default:
            ErrorHandler::stopError("Método HTTP no permitido", 405);
    }
} catch (Throwable $e) {
    ErrorHandler::handleException($e);
}

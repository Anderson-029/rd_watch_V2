<?php
/**
 * RD Watch - Sistema de Gestión de Relojería
 * Endpoints para Gestión de Categorías y Subcategorías
 * 
 * Permite listar categorías/subcategorías y realizar operaciones CRUD (admin).
 */

// Aplicar cabeceras de seguridad y dependencias
require_once('../security_headers.php');
require_once('../validator.php');
require_once('../config.php');

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'categoria'; // 'categoria' o 'subcategoria'

try {
    // 1. Verificación de permisos para operaciones de escritura
    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        if (!isset($_SESSION['user_id']) || ($_SESSION['user_rol'] ?? '') !== 'admin') {
            ErrorHandler::stopError("Acceso denegado: privilegios insuficientes", 403);
        }
    }

    // 2. Procesamiento según el tipo de entidad (Categoría o Subcategoría)
    if ($action === 'categoria') {
        // =====================================================================
        // CRUD CATEGORÍAS
        // =====================================================================
        switch ($method) {
            case 'GET':
                $stmt = $pdo->query("SELECT * FROM tab_Categorias ORDER BY nom_categoria ASC");
                $categorias = $stmt->fetchAll();
                ErrorHandler::sendSuccess("Lista de categorías obtenida", ['categorias' => $categorias]);
                break;

            case 'POST':
                $data = Validator::validateJson(file_get_contents("php://input"));
                $id = Validator::validateId($data['id_categoria'] ?? 0);
                $nom = Validator::sanitizeString($data['nom_categoria'] ?? '', 100);
                $desc = Validator::sanitizeString($data['descripcion_categoria'] ?? '', 500);

                $stmt = $pdo->prepare("SELECT fun_insert_categorias(:id, :nombre, :desc)");
                $stmt->execute([':id' => $id, ':nombre' => $nom, ':desc' => $desc]);

                $response = $stmt->fetchColumn();
                if (strpos($response, 'SUCCESS') !== false) {
                    http_response_code(201);
                    ErrorHandler::sendSuccess("Categoría creada correctamente");
                } else {
                    ErrorHandler::stopError($response, 400);
                }
                break;

            case 'PUT':
                $data = Validator::validateJson(file_get_contents("php://input"));
                $id = Validator::validateId($data['id_categoria'] ?? 0);
                $nom = Validator::sanitizeString($data['nom_categoria'] ?? '', 100);
                $desc = Validator::sanitizeString($data['descripcion_categoria'] ?? '', 500);
                $est = isset($data['estado']) ? ($data['estado'] === true || $data['estado'] === 'true') : true;

                $stmt = $pdo->prepare("SELECT fun_update_categorias(:id, :nombre, :desc, :estado)");
                $stmt->execute([':id' => $id, ':nombre' => $nom, ':desc' => $desc, ':estado' => $est ? 'true' : 'false']);

                $response = $stmt->fetchColumn();
                if (strpos($response, 'SUCCESS') !== false) {
                    ErrorHandler::sendSuccess("Categoría actualizada correctamente");
                } else {
                    ErrorHandler::stopError($response, 400);
                }
                break;

            case 'DELETE':
                $data = Validator::validateJson(file_get_contents("php://input"));
                $id = Validator::validateId($data['id_categoria'] ?? 0);

                $stmt = $pdo->prepare("SELECT fun_delete_categorias(:id)");
                $stmt->execute([':id' => $id]);

                $response = $stmt->fetchColumn();
                if (strpos($response, 'SUCCESS') !== false) {
                    ErrorHandler::sendSuccess("Categoría desactivada correctamente");
                } else {
                    ErrorHandler::stopError($response, 400);
                }
                break;

            default:
                ErrorHandler::stopError("Método no permitido para categorías", 405);
        }

    } elseif ($action === 'subcategoria') {
        // =====================================================================
        // CRUD SUBCATEGORÍAS
        // =====================================================================
        switch ($method) {
            case 'GET':
                $id_cat = isset($_GET['id_categoria']) ? (int) $_GET['id_categoria'] : 0;

                if ($id_cat > 0) {
                    $stmt = $pdo->prepare("SELECT * FROM tab_Subcategorias WHERE id_categoria = :id AND estado = TRUE ORDER BY nom_subcategoria ASC");
                    $stmt->execute([':id' => $id_cat]);
                } else {
                    $stmt = $pdo->query("
                        SELECT s.*, c.nom_categoria 
                        FROM tab_Subcategorias s 
                        INNER JOIN tab_Categorias c ON s.id_categoria = c.id_categoria 
                        ORDER BY c.nom_categoria ASC, s.nom_subcategoria ASC
                    ");
                }
                $subcategorias = $stmt->fetchAll();
                ErrorHandler::sendSuccess("Lista de subcategorías obtenida", ['subcategorias' => $subcategorias]);
                break;

            case 'POST':
                $data = Validator::validateJson(file_get_contents("php://input"));
                $id_cat = Validator::validateId($data['id_categoria'] ?? 0);
                $id_sub = Validator::validateId($data['id_subcategoria'] ?? 0);
                $nom = Validator::sanitizeString($data['nom_subcategoria'] ?? '', 100);

                $stmt = $pdo->prepare("SELECT fun_insert_subcategorias(:id_cat, :id_sub, :nom)");
                $stmt->execute([':id_cat' => $id_cat, ':id_sub' => $id_sub, ':nom' => $nom]);

                $response = $stmt->fetchColumn();
                if (strpos($response, 'SUCCESS') !== false) {
                    http_response_code(201);
                    ErrorHandler::sendSuccess("Subcategoría creada correctamente");
                } else {
                    ErrorHandler::stopError($response, 400);
                }
                break;

            case 'PUT':
                $data = Validator::validateJson(file_get_contents("php://input"));
                $id_cat = Validator::validateId($data['id_categoria'] ?? 0);
                $id_sub = Validator::validateId($data['id_subcategoria'] ?? 0);
                $nom = Validator::sanitizeString($data['nom_subcategoria'] ?? '', 100);
                $est = isset($data['activo']) ? ($data['activo'] === true || $data['activo'] === 'true') : true;

                $stmt = $pdo->prepare("SELECT fun_update_subcategorias(:id_cat, :id_sub, :nom, :estado)");
                $stmt->execute([':id_cat' => $id_cat, ':id_sub' => $id_sub, ':nom' => $nom, ':estado' => $est ? 'true' : 'false']);

                $response = $stmt->fetchColumn();
                if (strpos($response, 'SUCCESS') !== false) {
                    ErrorHandler::sendSuccess("Subcategoría actualizada correctamente");
                } else {
                    ErrorHandler::stopError($response, 400);
                }
                break;

            case 'DELETE':
                $data = Validator::validateJson(file_get_contents("php://input"));
                $id_cat = Validator::validateId($data['id_categoria'] ?? 0);
                $id_sub = Validator::validateId($data['id_subcategoria'] ?? 0);

                $stmt = $pdo->prepare("SELECT fun_delete_subcategorias(:id_cat, :id_sub)");
                $stmt->execute([':id_cat' => $id_cat, ':id_sub' => $id_sub]);

                $response = $stmt->fetchColumn();
                if (strpos($response, 'SUCCESS') !== false) {
                    ErrorHandler::sendSuccess("Subcategoría eliminada correctamente");
                } else {
                    ErrorHandler::stopError($response, 400);
                }
                break;

            default:
                ErrorHandler::stopError("Método no permitido para subcategorías", 405);
        }
    } else {
        ErrorHandler::stopError("Acción desconocida", 400);
    }

} catch (Throwable $e) {
    ErrorHandler::handleException($e);
}

<?php
header('Content-Type: application/json');
require_once '../config.php';

// config.php debe exponer $pdo
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de configuración de BD']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Helper para leer body JSON
function getJsonInput() {
    return json_decode(file_get_contents('php://input'), true);
}

try {
    if ($action === 'subcategoria') {
        // ==========================================
        // GESTIÓN DE SUBCATEGORÍAS
        // ==========================================
        switch ($method) {
            case 'GET':
                // Listar todas las subcategorías (con JOIN para nombre de categoría)
                $sql = "SELECT s.id_categoria, s.id_subcategoria, s.nom_subcategoria, s.estado, c.nom_categoria 
                        FROM tab_Subcategorias s
                        JOIN tab_Categorias c ON s.id_categoria = c.id_categoria
                        ORDER BY s.id_categoria, s.id_subcategoria ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Normalizar booleanos para frontend
                foreach ($subs as &$sub) {
                    $sub['estado'] = $sub['estado'] ? true : false;
                }
                
                echo json_encode(['ok' => true, 'subcategorias' => $subs]);
                break;

            case 'POST':
                // Crear subcategoría
                $data = getJsonInput();
                if (!isset($data['id_categoria'], $data['id_subcategoria'], $data['nom_subcategoria'])) {
                    echo json_encode(['ok' => false, 'msg' => 'Datos incompletos']);
                    exit;
                }

                // Validar existencia (clave compuesta)
                $check = $pdo->prepare("SELECT id_subcategoria FROM tab_Subcategorias WHERE id_categoria = ? AND id_subcategoria = ?");
                $check->execute([$data['id_categoria'], $data['id_subcategoria']]);
                if ($check->fetch()) {
                    echo json_encode(['ok' => false, 'msg' => 'Ya existe esa subcategoría en esta categoría']);
                    exit;
                }

                $sql = "INSERT INTO tab_Subcategorias (id_categoria, id_subcategoria, nom_subcategoria, estado, fec_insert, usr_insert) 
                        VALUES (?, ?, ?, true, NOW(), 'admin')";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([
                    $data['id_categoria'],
                    $data['id_subcategoria'],
                    $data['nom_subcategoria']
                ])) {
                    echo json_encode(['ok' => true, 'msg' => 'Subcategoría creada']);
                } else {
                    echo json_encode(['ok' => false, 'msg' => 'Error al crear subcategoría']);
                }
                break;

            case 'PUT':
                // Actualizar subcategoría
                $data = getJsonInput();
                if (!isset($data['id_categoria'], $data['id_subcategoria'], $data['nom_subcategoria'])) {
                    echo json_encode(['ok' => false, 'msg' => 'Datos incompletos']);
                    exit;
                }

                $sql = "UPDATE tab_Subcategorias 
                        SET nom_subcategoria = ?, fec_update = NOW(), usr_update = 'admin'
                        WHERE id_categoria = ? AND id_subcategoria = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([
                    $data['nom_subcategoria'],
                    $data['id_categoria'],
                    $data['id_subcategoria']
                ])) {
                    echo json_encode(['ok' => true, 'msg' => 'Subcategoría actualizada']);
                } else {
                    echo json_encode(['ok' => false, 'msg' => 'Error al actualizar subcategoría']);
                }
                break;

            case 'DELETE':
                // Eliminar subcategoría
                $data = getJsonInput();
                if (!isset($data['id_categoria'], $data['id_subcategoria'])) {
                    echo json_encode(['ok' => false, 'msg' => 'IDs requeridos']);
                    exit;
                }

                // Verificar productos asociados
                $check = $pdo->prepare("SELECT COUNT(*) FROM tab_Productos WHERE id_categoria = ? AND id_subcategoria = ?");
                $check->execute([$data['id_categoria'], $data['id_subcategoria']]);
                if ($check->fetchColumn() > 0) {
                    echo json_encode(['ok' => false, 'msg' => 'No se puede eliminar: Hay productos asociados']);
                    exit;
                }

                $stmt = $pdo->prepare("DELETE FROM tab_Subcategorias WHERE id_categoria = ? AND id_subcategoria = ?");
                if ($stmt->execute([$data['id_categoria'], $data['id_subcategoria']])) {
                    echo json_encode(['ok' => true, 'msg' => 'Subcategoría eliminada']);
                } else {
                    echo json_encode(['ok' => false, 'msg' => 'Error al eliminar subcategoría']);
                }
                break;
        }
    } else {
        // ==========================================
        // GESTIÓN DE CATEGORÍAS (PRINCIPAL)
        // ==========================================
        switch ($method) {
            case 'GET':
                // Listar categorías
                $stmt = $pdo->prepare("SELECT id_categoria, nom_categoria, descripcion_categoria, estado FROM tab_Categorias ORDER BY id_categoria ASC");
                $stmt->execute();
                $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Normalizar booleanos
                foreach ($cats as &$c) {
                    $c['estado'] = $c['estado'] ? true : false;
                }

                echo json_encode(['ok' => true, 'categorias' => $cats]);
                break;

            case 'POST':
                // Crear categoría
                $data = getJsonInput();
                if (!isset($data['id_categoria'], $data['nom_categoria'])) {
                    echo json_encode(['ok' => false, 'msg' => 'Datos incompletos']);
                    exit;
                }

                $check = $pdo->prepare("SELECT id_categoria FROM tab_Categorias WHERE id_categoria = ?");
                $check->execute([$data['id_categoria']]);
                if ($check->fetch()) {
                    echo json_encode(['ok' => false, 'msg' => 'Ya existe una categoría con ese ID']);
                    exit;
                }

                $sql = "INSERT INTO tab_Categorias (id_categoria, nom_categoria, descripcion_categoria, estado, fec_insert, usr_insert) 
                        VALUES (?, ?, ?, ?, NOW(), 'admin')";
                
                $estado = isset($data['estado']) ? ($data['estado'] ? 'true' : 'false') : 'true';
                $desc = $data['descripcion_categoria'] ?? '';

                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([
                    $data['id_categoria'],
                    $data['nom_categoria'],
                    $desc,
                    $estado
                ])) {
                    echo json_encode(['ok' => true, 'msg' => 'Categoría creada']);
                } else {
                    echo json_encode(['ok' => false, 'msg' => 'Error al crear categoría']);
                }
                break;

            case 'PUT':
                // Actualizar categoría
                $data = getJsonInput();
                if (!isset($data['id_categoria'], $data['nom_categoria'])) {
                    echo json_encode(['ok' => false, 'msg' => 'Datos incompletos']);
                    exit;
                }

                $sql = "UPDATE tab_Categorias 
                        SET nom_categoria = ?, descripcion_categoria = ?, estado = ?, fec_update = NOW(), usr_update = 'admin'
                        WHERE id_categoria = ?";
                $stmt = $pdo->prepare($sql);
                
                $estado = isset($data['estado']) ? ($data['estado'] ? 'true' : 'false') : 'true';
                $desc = $data['descripcion_categoria'] ?? '';

                if ($stmt->execute([
                    $data['nom_categoria'],
                    $desc,
                    $estado,
                    $data['id_categoria']
                ])) {
                    echo json_encode(['ok' => true, 'msg' => 'Categoría actualizada']);
                } else {
                    echo json_encode(['ok' => false, 'msg' => 'Error al actualizar categoría']);
                }
                break;

            case 'DELETE':
                // Eliminar categoría
                $data = getJsonInput();
                if (!isset($data['id_categoria'])) {
                    echo json_encode(['ok' => false, 'msg' => 'ID requerido']);
                    exit;
                }

                // Verificar subcategorías dependientes
                $check = $pdo->prepare("SELECT COUNT(*) FROM tab_Subcategorias WHERE id_categoria = ?");
                $check->execute([$data['id_categoria']]);
                if ($check->fetchColumn() > 0) {
                    echo json_encode(['ok' => false, 'msg' => 'No se puede eliminar: Tiene subcategorías asociadas']);
                    exit;
                }
                
                // Verificar productos
                $checkProd = $pdo->prepare("SELECT COUNT(*) FROM tab_Productos WHERE id_categoria = ?");
                $checkProd->execute([$data['id_categoria']]);
                if ($checkProd->fetchColumn() > 0) {
                    echo json_encode(['ok' => false, 'msg' => 'No se puede eliminar: Tiene productos asociados']);
                    exit;
                }

                $stmt = $pdo->prepare("DELETE FROM tab_Categorias WHERE id_categoria = ?");
                if ($stmt->execute([$data['id_categoria']])) {
                    echo json_encode(['ok' => true, 'msg' => 'Categoría eliminada']);
                } else {
                    echo json_encode(['ok' => false, 'msg' => 'Error al eliminar categoría']);
                }
                break;

            default:
                http_response_code(405);
                echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
                break;
        }
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de BD: ' . $e->getMessage()]);
}

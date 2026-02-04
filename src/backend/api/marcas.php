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

// Helper para leer body JSON
function getJsonInput() {
    return json_decode(file_get_contents('php://input'), true);
}

try {
    switch ($method) {
        case 'GET':
            // Listar todas las marcas
            $stmt = $pdo->prepare("SELECT id_marca, nom_marca, estado_marca FROM tab_Marcas ORDER BY id_marca ASC");
            $stmt->execute();
            $marcas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'marcas' => $marcas]);
            break;

        case 'POST':
            // Crear marca
            $data = getJsonInput();
            if (!isset($data['id_marca'], $data['nom_marca'])) {
                echo json_encode(['ok' => false, 'msg' => 'Datos incompletos']);
                exit;
            }

            // Validar existencia por nombre
            $check = $pdo->prepare("SELECT id_marca FROM tab_Marcas WHERE nom_marca = ?");
            $check->execute([$data['nom_marca']]);
            if ($check->fetch()) {
                echo json_encode(['ok' => false, 'msg' => 'Ya existe una marca con ese nombre']);
                exit;
            }

            // Validar existencia por ID
            $checkId = $pdo->prepare("SELECT id_marca FROM tab_Marcas WHERE id_marca = ?");
            $checkId->execute([$data['id_marca']]);
            if ($checkId->fetch()) {
                echo json_encode(['ok' => false, 'msg' => 'Ya existe una marca con ese ID']);
                exit;
            }

            $sql = "INSERT INTO tab_Marcas (id_marca, nom_marca, estado_marca, fec_insert, usr_insert) 
                    VALUES (?, ?, ?, NOW(), 'admin')";
            $stmt = $pdo->prepare($sql);
            
            // Estado por defecto true si no viene
            $estado = isset($data['estado_marca']) ? ($data['estado_marca'] ? 'true' : 'false') : 'true';

            if ($stmt->execute([
                $data['id_marca'],
                $data['nom_marca'],
                $estado
            ])) {
                echo json_encode(['ok' => true, 'msg' => 'Marca creada correctamente']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error al crear marca']);
            }
            break;

        case 'PUT':
            // Actualizar marca
            $data = getJsonInput();
            if (!isset($data['id_marca'], $data['nom_marca'])) {
                echo json_encode(['ok' => false, 'msg' => 'Datos incompletos']);
                exit;
            }

            $sql = "UPDATE tab_Marcas 
                    SET nom_marca = ?, estado_marca = ?, fec_update = NOW(), usr_update = 'admin'
                    WHERE id_marca = ?";
            $stmt = $pdo->prepare($sql);
            
            $estado = isset($data['estado_marca']) ? ($data['estado_marca'] ? 'true' : 'false') : 'true';

            if ($stmt->execute([
                $data['nom_marca'],
                $estado,
                $data['id_marca']
            ])) {
                echo json_encode(['ok' => true, 'msg' => 'Marca actualizada']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error al actualizar marca']);
            }
            break;

        case 'DELETE':
            // Eliminar marca
            $data = getJsonInput();
            if (!isset($data['id_marca'])) {
                echo json_encode(['ok' => false, 'msg' => 'ID requerido']);
                exit;
            }

            // Verificar si hay productos asociados antes de eliminar
            $check = $pdo->prepare("SELECT COUNT(*) FROM tab_Productos WHERE id_marca = ?");
            $check->execute([$data['id_marca']]);
            if ($check->fetchColumn() > 0) {
                echo json_encode(['ok' => false, 'msg' => 'No se puede eliminar: Hay productos asociados a esta marca']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM tab_Marcas WHERE id_marca = ?");
            if ($stmt->execute([$data['id_marca']])) {
                echo json_encode(['ok' => true, 'msg' => 'Marca eliminada']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error al eliminar marca']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de base de datos: ' . $e->getMessage()]);
}

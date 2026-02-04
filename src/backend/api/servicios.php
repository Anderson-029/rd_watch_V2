<?php
header('Content-Type: application/json');
require_once '../config.php';
// require_once 'cors.php'; // config.php ya puede manejar cors o no, nos ajustamos.

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
            // Listar todos los servicios
            $stmt = $pdo->prepare("SELECT id_servicio, nom_servicio, descripcion, precio_servicio, duracion_estimada FROM tab_Servicios ORDER BY id_servicio DESC");
            $stmt->execute();
            $servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'servicios' => $servicios]);
            break;

        case 'POST':
            // Crear servicio
            $data = getJsonInput();
            if (!isset($data['id_servicio'], $data['nom_servicio'], $data['precio_servicio'])) {
                echo json_encode(['ok' => false, 'msg' => 'Datos incompletos']);
                exit;
            }

            // Validar existencia
            $check = $pdo->prepare("SELECT id_servicio FROM tab_Servicios WHERE nom_servicio = ?");
            $check->execute([$data['nom_servicio']]);
            if ($check->fetch()) {
                echo json_encode(['ok' => false, 'msg' => 'Ya existe un servicio con ese nombre']);
                exit;
            }

            $sql = "INSERT INTO tab_Servicios (id_servicio, nom_servicio, descripcion, precio_servicio, duracion_estimada, fec_insert, usr_insert) 
                    VALUES (?, ?, ?, ?, ?, NOW(), 'admin')";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([
                $data['id_servicio'],
                $data['nom_servicio'],
                $data['descripcion'] ?? '',
                $data['precio_servicio'],
                $data['duracion_estimada'] ?? 'N/A'
            ])) {
                echo json_encode(['ok' => true, 'msg' => 'Servicio creado correctamente']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error al crear servicio']);
            }
            break;

        case 'PUT':
            // Actualizar servicio
            $data = getJsonInput();
            if (!isset($data['id_servicio'])) {
                echo json_encode(['ok' => false, 'msg' => 'ID de servicio requerido']);
                exit;
            }

            $sql = "UPDATE tab_Servicios 
                    SET nom_servicio = ?, descripcion = ?, precio_servicio = ?, duracion_estimada = ?, fec_update = NOW(), usr_update = 'admin'
                    WHERE id_servicio = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([
                $data['nom_servicio'],
                $data['descripcion'],
                $data['precio_servicio'],
                $data['duracion_estimada'],
                $data['id_servicio']
            ])) {
                echo json_encode(['ok' => true, 'msg' => 'Servicio actualizado']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error al actualizar servicio']);
            }
            break;

        case 'DELETE':
            // Eliminar servicio
            $data = getJsonInput();
            if (!isset($data['id_servicio'])) {
                echo json_encode(['ok' => false, 'msg' => 'ID requerido']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM tab_Servicios WHERE id_servicio = ?");
            if ($stmt->execute([$data['id_servicio']])) {
                echo json_encode(['ok' => true, 'msg' => 'Servicio eliminado']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error al eliminar servicio']);
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

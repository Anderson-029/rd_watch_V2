<?php
/**
 * API: GESTIÓN DE MARCAS
 * ---------------------------------------------------------
 * Este archivo permite listar, crear, editar y eliminar las
 * marcas de los productos (ej. Rolex, Omega, etc.).
 */

header('Content-Type: application/json');
require_once '../config.php';

// Verificación de la conexión a la base de datos
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de configuración de BD']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Función auxiliar para capturar datos enviados en formato JSON
function getJsonInput()
{
    return json_decode(file_get_contents('php://input'), true);
}

try {
    switch ($method) {
        case 'GET':
            /**
             * LISTAR MARCAS
             * Devuelve todas las marcas ordenadas por su ID.
             */
            $stmt = $pdo->prepare("SELECT id_marca, nom_marca, estado_marca FROM tab_Marcas ORDER BY id_marca ASC");
            $stmt->execute();
            $marcas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'marcas' => $marcas]);
            break;

        case 'POST':
            /**
             * CREAR MARCA
             * Recibe id_marca y nom_marca. Valida duplicados por nombre e ID.
             */
            $data = getJsonInput();
            if (!isset($data['id_marca'], $data['nom_marca'])) {
                echo json_encode(['ok' => false, 'msg' => 'Datos incompletos']);
                exit;
            }

            // Validar que el nombre no esté repetido
            $check = $pdo->prepare("SELECT id_marca FROM tab_Marcas WHERE nom_marca = ?");
            $check->execute([$data['nom_marca']]);
            if ($check->fetch()) {
                echo json_encode(['ok' => false, 'msg' => 'Ya existe una marca con ese nombre']);
                exit;
            }

            // Validar que el ID no esté repetido
            $checkId = $pdo->prepare("SELECT id_marca FROM tab_Marcas WHERE id_marca = ?");
            $checkId->execute([$data['id_marca']]);
            if ($checkId->fetch()) {
                echo json_encode(['ok' => false, 'msg' => 'Ya existe una marca con ese ID']);
                exit;
            }

            $sql = "INSERT INTO tab_Marcas (id_marca, nom_marca, estado_marca, fec_insert, usr_insert) 
                    VALUES (?, ?, ?, NOW(), 'admin')";
            $stmt = $pdo->prepare($sql);

            // Si no se especifica el estado, se asume 'true' (activa)
            $estado = isset($data['estado_marca']) ? ($data['estado_marca'] ? 'true' : 'false') : 'true';

            if (
                $stmt->execute([
                    $data['id_marca'],
                    $data['nom_marca'],
                    $estado
                ])
            ) {
                echo json_encode(['ok' => true, 'msg' => 'Marca creada correctamente']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error al crear marca']);
            }
            break;

        case 'PUT':
            /**
             * ACTUALIZAR MARCA
             * Actualiza el nombre y/o estado de una marca existente.
             */
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

            if (
                $stmt->execute([
                    $data['nom_marca'],
                    $estado,
                    $data['id_marca']
                ])
            ) {
                echo json_encode(['ok' => true, 'msg' => 'Marca actualizada']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error al actualizar marca']);
            }
            break;

        case 'DELETE':
            /**
             * ELIMINAR MARCA
             * Solo permite eliminar si no hay productos que dependan de esta marca (Integridad Referencial).
             */
            $data = getJsonInput();
            if (!isset($data['id_marca'])) {
                echo json_encode(['ok' => false, 'msg' => 'ID requerido']);
                exit;
            }

            // Verificar productos asociados
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
            // Respuesta para métodos HTTP no soportados (ej. PATCH)
            http_response_code(405);
            echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de base de datos: ' . $e->getMessage()]);
}

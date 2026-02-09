<?php
/**
 * API: GESTIÓN DE MARCAS
 * ---------------------------------------------------------
 * Propósito: Administrar el catálogo de marcas comerciales (ej: Rolex, Omega, Casio).
 * Proporciona los cimientos para la clasificación de productos.
 * 
 * Funcionalidades:
 * - GET: Recuperar listado de marcas activas e inactivas.
 * - POST: Registrar nueva marca con validaciones de unicidad.
 * - PUT: Modificar marcas existentes.
 * - DELETE: Eliminación condicionada a la ausencia de productos vinculados.
 */

header('Content-Type: application/json');
require_once '../config.php';

// Verificación de salud de la conexión a la base de datos
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de infraestructura: No hay conexión a BD']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

/**
 * Captura datos de entrada JSON del flujo de entrada de PHP.
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
             * 🔍 LISTAR MARCAS (GET)
             * ==========================================
             */
            $stmt = $pdo->prepare("SELECT id_marca, nom_marca, estado_marca FROM tab_Marcas ORDER BY id_marca ASC");
            $stmt->execute();
            $marcas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'marcas' => $marcas]);
            break;

        case 'POST':
            /**
             * ==========================================
             * ➕ CREAR MARCA (POST)
             * ==========================================
             * Lógica: Se asegura de que no existan colisiones de ID ni de Nombre.
             */
            $data = getJsonInput();
            if (!isset($data['id_marca'], $data['nom_marca'])) {
                echo json_encode(['ok' => false, 'msg' => 'Error: ID y Nombre son obligatorios para crear una marca']);
                exit;
            }

            // 1. Validar que el nombre sea único para evitar redundancia
            $check = $pdo->prepare("SELECT id_marca FROM tab_Marcas WHERE nom_marca = ?");
            $check->execute([$data['nom_marca']]);
            if ($check->fetch()) {
                echo json_encode(['ok' => false, 'msg' => 'Conflicto: Ya existe una marca registrada con ese nombre']);
                exit;
            }

            // 2. Validar que el ID numérico no esté en uso
            $checkId = $pdo->prepare("SELECT id_marca FROM tab_Marcas WHERE id_marca = ?");
            $checkId->execute([$data['id_marca']]);
            if ($checkId->fetch()) {
                echo json_encode(['ok' => false, 'msg' => 'Conflicto: El ID proporcionado ya está ocupado']);
                exit;
            }

            $sql = "INSERT INTO tab_Marcas (id_marca, nom_marca, estado_marca, fec_insert, usr_insert) 
                    VALUES (?, ?, ?, NOW(), 'admin_catálogo')";
            $stmt = $pdo->prepare($sql);

            // Manejo del estado lógico (por defecto activo)
            $estado = isset($data['estado_marca']) ? ($data['estado_marca'] ? 'true' : 'false') : 'true';

            if ($stmt->execute([$data['id_marca'], $data['nom_marca'], $estado])) {
                echo json_encode(['ok' => true, 'msg' => 'Nueva marca registrada exitosamente']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Fallo técnico al insertar la marca']);
            }
            break;

        case 'PUT':
            /**
             * ==========================================
             * 🔄 ACTUALIZAR MARCA (PUT)
             * ==========================================
             */
            $data = getJsonInput();
            if (!isset($data['id_marca'], $data['nom_marca'])) {
                echo json_encode(['ok' => false, 'msg' => 'Error: Faltan datos críticos para actualizar']);
                exit;
            }

            $sql = "UPDATE tab_Marcas 
                    SET nom_marca = ?, estado_marca = ?, fec_update = NOW(), usr_update = 'admin_editor'
                    WHERE id_marca = ?";
            $stmt = $pdo->prepare($sql);

            $estado = isset($data['estado_marca']) ? ($data['estado_marca'] ? 'true' : 'false') : 'true';

            if ($stmt->execute([$data['nom_marca'], $estado, $data['id_marca']])) {
                echo json_encode(['ok' => true, 'msg' => 'Marca actualizada correctamente']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Fallo al actualizar el registro']);
            }
            break;

        case 'DELETE':
            /**
             * ==========================================
             * 🗑️ ELIMINAR MARCA (DELETE)
             * ==========================================
             * Seguridad: Se prohíbe el borrado si existen relojes cargados de esta marca.
             */
            $data = getJsonInput();
            if (!isset($data['id_marca'])) {
                echo json_encode(['ok' => false, 'msg' => 'Se requiere el ID de la marca']);
                exit;
            }

            // Verificar integridad referencial manual (FK Check)
            $check = $pdo->prepare("SELECT COUNT(*) FROM tab_Productos WHERE id_marca = ?");
            $check->execute([$data['id_marca']]);
            if ($check->fetchColumn() > 0) {
                echo json_encode(['ok' => false, 'msg' => 'No es posible eliminar: Esta marca tiene productos vinculados en el catálogo']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM tab_Marcas WHERE id_marca = ?");
            if ($stmt->execute([$data['id_marca']])) {
                echo json_encode(['ok' => true, 'msg' => 'Marca eliminada permanentemente']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Fallo en la base de datos al ejecutar el borrado']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['ok' => false, 'msg' => 'Método HTTP inválido para esta operación']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error crítico de base de datos: ' . $e->getMessage()]);
}

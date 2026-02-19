<?php
/**
 * API: GESTIÓN DE MARCAS
 * ---------------------------------------------------------
 * Propósito: Administrar el catálogo de marcas comerciales.
 * Protege la integridad referencial impidiendo el borrado de marcas con productos activos.
 */

header('Content-Type: application/json');
require_once '../config.php';
require_once '../utils/security_utils.php';
require_once '../utils/Validation.php';

// Verificación de infraestructura
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de Infraestructura: Motor de datos no disponible']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            /**
             * 🔍 LISTAR MARCAS (GET)
             */
            $stmt = $pdo->prepare("SELECT id_marca, nom_marca, estado_marca FROM tab_Marcas ORDER BY id_marca ASC");
            $stmt->execute();
            $marcas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Normalización para JS
            foreach ($marcas as &$m) {
                $m['estado_marca'] = $m['estado_marca'] ? true : false;
            }

            echo json_encode(['ok' => true, 'marcas' => $marcas]);
            break;

        case 'POST':
            /**
             * ➕ CREAR MARCA (POST)
             */
            requireRole('admin');
            validateCsrfToken(null, true);

            $data = getCachedJsonInput();

            // 🛡️ VALIDACIÓN ESTRICTA
            Validation::validateOrReject($data, [
                'id_marca' => 'id',
                'nom_marca' => 'name'
            ]);

            // Unicidad
            $check = $pdo->prepare("SELECT id_marca FROM tab_Marcas WHERE nom_marca = ? OR id_marca = ?");
            $check->execute([$data['nom_marca'], $data['id_marca']]);
            if ($check->fetch()) {
                echo json_encode(['ok' => false, 'msg' => 'Conflicto: La marca o el ID ya existen en el registro']);
                exit;
            }

            $sql = "INSERT INTO tab_Marcas (id_marca, nom_marca, estado_marca, fec_insert, usr_insert) 
                    VALUES (?, ?, ?, NOW(), 'admin_root')";
            $stmt = $pdo->prepare($sql);

            $estado = isset($data['estado_marca']) ? ($data['estado_marca'] ? 'true' : 'false') : 'true';

            if ($stmt->execute([$data['id_marca'], $data['nom_marca'], $estado])) {
                echo json_encode(['ok' => true, 'msg' => 'Marca registrada exitosamente']);
            }
            else {
                echo json_encode(['ok' => false, 'msg' => 'Error técnico al guardar la marca']);
            }
            break;

        case 'PUT':
            /**
             * 🔄 ACTUALIZAR MARCA (PUT)
             */
            requireRole('admin');
            validateCsrfToken(null, true);

            $data = getCachedJsonInput();
            if (!isset($data['id_marca'], $data['nom_marca'])) {
                echo json_encode(['ok' => false, 'msg' => 'Datos insuficientes para la actualización']);
                exit;
            }

            $sql = "UPDATE tab_Marcas SET nom_marca = ?, estado_marca = ?, fec_update = NOW(), usr_update = 'admin_editor'
                    WHERE id_marca = ?";
            $stmt = $pdo->prepare($sql);

            $estado = isset($data['estado_marca']) ? ($data['estado_marca'] ? 'true' : 'false') : 'true';

            if ($stmt->execute([$data['nom_marca'], $estado, $data['id_marca']])) {
                echo json_encode(['ok' => true, 'msg' => 'Marca actualizada correctamente']);
            }
            else {
                echo json_encode(['ok' => false, 'msg' => 'Fallo al actualizar el registro']);
            }
            break;

        case 'DELETE':
            /**
             * 🗑️ ELIMINAR MARCA (DELETE)
             * Integridad: Bloqueo si hay relojes vinculados.
             */
            requireRole('admin');
            validateCsrfToken(null, true);

            $data = getCachedJsonInput();
            $idMarca = $data['id_marca'] ?? null;

            if (!$idMarca) {
                echo json_encode(['ok' => false, 'msg' => 'Se requiere el ID de la marca']);
                exit;
            }

            // 🛡️ BARRERA DE INTEGRIDAD: Validar productos vinculados
            $check = $pdo->prepare("SELECT COUNT(id_producto) FROM tab_Productos WHERE id_marca = ?");
            $check->execute([$idMarca]);
            $count = $check->fetchColumn();

            if ($count > 0) {
                echo json_encode([
                    'ok' => false,
                    'msg' => "ACCIÓN BLOQUEADA: Existen $count productos vinculados a esta marca en el catálogo. Debe eliminar o reasignar los productos antes de borrar la marca."
                ]);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM tab_Marcas WHERE id_marca = ?");
            if ($stmt->execute([$idMarca])) {
                echo json_encode(['ok' => true, 'msg' => 'Marca eliminada permanentemente']);
            }
            else {
                $error = $stmt->errorInfo();
                echo json_encode(['ok' => false, 'msg' => 'Error de integridad en BD: ' . ($error[2] ?? 'Fallo desconocido')]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
            break;
    }
}
catch (Throwable $e) {
    http_response_code(500);
    $raw = file_get_contents('php://input');
    echo json_encode([
        'ok' => false,
        'msg' => 'Error crítico en servicio de marcas: ' . $e->getMessage(),
        'debug_info' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'raw_input' => $raw
        ]
    ]);
}
<?php
// backend/api/servicios.php
// Aplicar cabeceras de seguridad y dependencias
require_once('../security_headers.php');
require_once('../csrf.php');
require_once('../validator.php');
require_once('../encoder.php');
require_once('../config.php');

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Listar todos los servicios
            $stmt = $pdo->query("
                SELECT 
                    id_servicio, nom_servicio, descripcion,
                    precio_servicio, duracion_estimada
                FROM tab_Servicios 
                ORDER BY id_servicio DESC
            ");
            $servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Sanitización de salida para prevenir XSS
            foreach ($servicios as &$serv) {
                $serv['nom_servicio'] = Encoder::html($serv['nom_servicio']);
                $serv['descripcion'] = Encoder::html($serv['descripcion']);
            }

            ErrorHandler::sendSuccess("Lista de servicios obtenida", [
                'servicios' => $servicios,
                'total' => count($servicios)
            ]);
            break;

        case 'POST':
        case 'PUT':
        case 'DELETE':
            // Validar rol administrativo
            if (!isset($_SESSION['user_id']) || ($_SESSION['user_rol'] ?? '') !== 'admin') {
                ErrorHandler::stopError("Acceso denegado: se requieren privilegios de administrador", 403);
            }

            // Requerir validación CSRF
            require_csrf_token();

            $data = json_decode(file_get_contents("php://input"), true);
            if (!$data) {
                ErrorHandler::stopError("Datos de solicitud inválidos", 400);
            }

            if ($method === 'POST') {
                $id = Validator::validateId($data['id_servicio'] ?? 0);
                $nombre = Validator::sanitizeString($data['nom_servicio'] ?? '', 255);
                $desc = Validator::sanitizeString($data['descripcion'] ?? '', 1000);
                $precio = floatval($data['precio_servicio'] ?? 0);
                $duracion = intval($data['duracion_estimada'] ?? 0);

                if (empty($nombre) || empty($desc)) {
                    ErrorHandler::stopError("Nombre y descripción son requeridos", 400);
                }

                $stmt = $pdo->prepare("SELECT fun_insert_servicios(:id, :nombre, :desc, :precio, :duracion)");
                $stmt->execute([
                    ':id' => $id,
                    ':nombre' => $nombre,
                    ':desc' => $desc,
                    ':precio' => $precio,
                    ':duracion' => $duracion
                ]);

                $response = $stmt->fetchColumn();
                if (strpos($response, 'SUCCESS') !== false) {
                    ErrorHandler::sendSuccess("Servicio creado correctamente", ['details' => $response]);
                } else {
                    ErrorHandler::stopError($response, 400);
                }

            } elseif ($method === 'PUT') {
                $id = Validator::validateId($data['id_servicio'] ?? 0);
                $nombre = Validator::sanitizeString($data['nom_servicio'] ?? '', 255);
                $desc = Validator::sanitizeString($data['descripcion'] ?? '', 1000);
                $precio = floatval($data['precio_servicio'] ?? 0);
                $duracion = intval($data['duracion_estimada'] ?? 0);

                $stmt = $pdo->prepare("SELECT fun_update_servicios(:id, :nombre, :desc, :precio, :duracion)");
                $stmt->execute([
                    ':id' => $id,
                    ':nombre' => $nombre,
                    ':desc' => $desc,
                    ':precio' => $precio,
                    ':duracion' => $duracion
                ]);

                $response = $stmt->fetchColumn();
                if (strpos($response, 'SUCCESS') !== false) {
                    ErrorHandler::sendSuccess("Servicio actualizado correctamente");
                } else {
                    ErrorHandler::stopError($response, 400);
                }

            } elseif ($method === 'DELETE') {
                $id = Validator::validateId($data['id_servicio'] ?? 0);

                $stmt = $pdo->prepare("SELECT fun_delete_servicios(:id)");
                $stmt->execute([':id' => $id]);

                $response = $stmt->fetchColumn();
                if (strpos($response, 'SUCCESS') !== false) {
                    ErrorHandler::sendSuccess("Servicio eliminado correctamente");
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

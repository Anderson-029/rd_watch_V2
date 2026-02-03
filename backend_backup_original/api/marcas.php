<?php
/**
 * RD Watch - Sistema de Gestión de Relojería
 * Endpoints para Gestión de Marcas
 * 
 * Permite listar marcas y gestionar el catálogo (solo admin).
 */

// Aplicar cabeceras de seguridad y dependencias
require_once('../security_headers.php');
require_once('../validator.php');
require_once('../config.php');

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // =================================================================
            // LISTAR MARCAS (Acceso Público)
            // =================================================================
            $stmt = $pdo->query("
                SELECT id_marca, nom_marca, estado_marca, usr_insert, fec_insert
                FROM tab_Marcas 
                ORDER BY nom_marca ASC
            ");
            $marcas = $stmt->fetchAll();
            ErrorHandler::sendSuccess("Lista de marcas obtenida", ['marcas' => $marcas]);
            break;

        case 'POST':
        case 'PUT':
        case 'DELETE':
            // =================================================================
            // OPERACIONES ADMINISTRATIVAS (Solo Admin)
            // =================================================================
            if (!isset($_SESSION['user_id']) || ($_SESSION['user_rol'] ?? '') !== 'admin') {
                ErrorHandler::stopError("Acceso denegado", 403);
            }

            $data = json_decode(file_get_contents("php://input"), true);
            if (!$data) {
                ErrorHandler::stopError("Datos inválidos", 400);
            }

            if ($method === 'POST') {
                $id = Validator::validateId($data['id_marca'] ?? 0);
                $nombre = Validator::sanitizeString($data['nom_marca'] ?? '', 100);

                $stmt = $pdo->prepare("SELECT fun_insert_marcas(:id, :nombre)");
                $stmt->execute([':id' => $id, ':nombre' => $nombre]);

                $response = $stmt->fetchColumn();
                if (strpos($response, 'SUCCESS') !== false) {
                    http_response_code(201);
                    ErrorHandler::sendSuccess("Marca creada correctamente");
                } else {
                    ErrorHandler::stopError($response, 400);
                }

            } elseif ($method === 'PUT') {
                $id = Validator::validateId($data['id_marca'] ?? 0);
                $nombre = Validator::sanitizeString($data['nom_marca'] ?? '', 100);
                $estado = isset($data['estado_marca']) ?
                    (($data['estado_marca'] === true || $data['estado_marca'] === 'true' || $data['estado_marca'] == 1) ? 'true' : 'false')
                    : 'true';

                $stmt = $pdo->prepare("SELECT fun_update_marcas(:id, :nombre, :estado::boolean)");
                $stmt->execute([':id' => $id, ':nombre' => $nombre, ':estado' => $estado]);

                $response = $stmt->fetchColumn();
                if (strpos($response, 'SUCCESS') !== false) {
                    ErrorHandler::sendSuccess("Marca actualizada correctamente");
                } else {
                    ErrorHandler::stopError($response, 400);
                }

            } elseif ($method === 'DELETE') {
                $id = Validator::validateId($data['id_marca'] ?? 0);

                $stmt = $pdo->prepare("SELECT fun_delete_marcas(:id)");
                $stmt->execute([':id' => $id]);

                $response = $stmt->fetchColumn();
                if (strpos($response, 'SUCCESS') !== false) {
                    ErrorHandler::sendSuccess("Marca desactivada correctamente");
                } else {
                    ErrorHandler::stopError($response, 400);
                }
            }
            break;

        default:
            ErrorHandler::stopError("Método no permitido", 405);
    }
} catch (Throwable $e) {
    ErrorHandler::handleException($e);
}

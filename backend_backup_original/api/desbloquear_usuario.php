<?php
/**
 * RD Watch - Sistema de Gestión de Relojería
 * Endpoint para desbloquear usuarios (solo Admin)
 */

require_once('../security_headers.php');
require_once('../csrf.php');
require_once('../validator.php');
require_once('../session_manager.php');
require_once('../config.php');

header('Content-Type: application/json');

// Requerir sesión válida y rol de administrador
require_valid_session();
if (($_SESSION['user_rol'] ?? '') !== 'admin') {
    ErrorHandler::stopError("Acceso denegado", 403);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method !== 'POST') {
        ErrorHandler::stopError("Método no permitido", 405);
    }

    require_csrf_token();

    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data || empty($data['id_usuario'])) {
        ErrorHandler::stopError("ID de usuario requerido", 400);
    }

    $idUsuario = Validator::validateId($data['id_usuario']);

    // Ejecutar actualización
    $stmt = $pdo->prepare("
        UPDATE tab_Usuarios 
        SET bloqueado = FALSE,
            intentos_fallidos = 0,
            fecha_bloqueo = NULL
        WHERE id_usuario = :id
    ");

    $stmt->execute([':id' => $idUsuario]);

    if ($stmt->rowCount() > 0) {
        ErrorHandler::sendSuccess("Usuario desbloqueado correctamente");
    } else {
        ErrorHandler::stopError("Usuario no encontrado", 404);
    }

} catch (PDOException $e) {
    ErrorHandler::handleException($e);
} catch (Throwable $e) {
    ErrorHandler::handleException($e);
}

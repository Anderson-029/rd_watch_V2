<?php
/**
 * RD Watch - Sistema de Gestión de Relojería
 * Endpoints para Solicitud de Citas y Servicios Técnicos
 */

require_once('../security_headers.php');
require_once('../csrf.php');
require_once('../validator.php');
require_once('../session_manager.php');
require_once('../rate_limiter.php');
require_once('../config.php');

header('Content-Type: application/json');

require_valid_session();

$sessionUid = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        require_csrf_token();

        if (!check_rate_limit('citas_post', (string) $sessionUid, 5, 300)) {
            rate_limit_fail_response(300);
        }

        $data = json_decode(file_get_contents("php://input"), true);
        if (!$data) {
            ErrorHandler::stopError("Datos inválidos", 400);
        }

        // Mapeo y validación (usando p_ para compatibilidad con el JS/DB)
        $id_servicio = Validator::validateId($data['p_id_servicio'] ?? 0);
        $fecha = Validator::sanitizeString($data['p_fecha_pref'] ?? date('Y-m-d'), 20);
        $notas = Validator::sanitizeString($data['p_notas'] ?? '', 500);
        $prioridad = Validator::validateAction($data['p_prioridad'] ?? 'normal', ['urgente', 'normal', 'baja']);

        // Llamada a la función almacenada fun_registrar_peticion_servicio
        $stmt = $pdo->prepare("SELECT fun_registrar_peticion_servicio(:uid, :sid, :fecha, :notas, :prio)");
        $stmt->execute([
            ':uid' => $sessionUid,
            ':sid' => $id_servicio,
            ':fecha' => $fecha,
            ':notas' => $notas,
            ':prio' => $prioridad
        ]);

        $id_reserva = $stmt->fetchColumn();

        if ($id_reserva > 0) {
            ErrorHandler::sendSuccess("Solicitud registrada con éxito. Ticket #" . $id_reserva, [
                'id_reserva' => $id_reserva
            ]);
        } else {
            ErrorHandler::stopError("No se pudo registrar la solicitud en la base de datos.");
        }

    } elseif ($method === 'GET') {
        $rol = $_SESSION['user_rol'] ?? 'cliente';

        if ($rol === 'admin') {
            // Admin: Listar TODAS
            $sql = "
                SELECT 
                    r.id_reserva,
                    r.fecha_preferida,
                    r.prioridad,
                    r.estado_reserva,
                    r.notas_cliente,
                    u.nom_usuario as nombre_cliente,
                    u.correo_usuario as email_cliente,
                    s.nom_servicio
                FROM tab_Reservas r
                LEFT JOIN tab_Usuarios u ON r.id_usuario = u.id_usuario
                LEFT JOIN tab_Servicios s ON r.id_servicio = s.id_servicio
                ORDER BY r.id_reserva DESC
            ";
            $stmt = $pdo->query($sql);
        } else {
            // Usuario: Listar PROPIAS
            $sql = "
                SELECT 
                    r.id_reserva,
                    r.fecha_preferida,
                    r.prioridad,
                    r.estado_reserva,
                    r.notas_cliente,
                    s.nom_servicio
                FROM tab_Reservas r
                LEFT JOIN tab_Servicios s ON r.id_servicio = s.id_servicio
                WHERE r.id_usuario = :uid
                ORDER BY r.id_reserva DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':uid' => $sessionUid]);
        }

        $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ErrorHandler::sendSuccess("Lista de reservas obtenida", ['reservas' => $reservas]);

    } else {
        ErrorHandler::stopError("Método no permitido", 405);
    }

} catch (PDOException $e) {
    ErrorHandler::handleException($e);
} catch (Throwable $e) {
    ErrorHandler::handleException($e);
}

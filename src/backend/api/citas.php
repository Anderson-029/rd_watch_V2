<?php
/**
 * API: GESTIÓN DE CITAS Y RESERVAS
 * ---------------------------------------------------------
 * Gestiona la programación de servicios técnicos por parte 
 * de los clientes, permitiendo ver su historial y crear solicitudes.
 */

header('Content-Type: application/json');
require_once '../config.php';

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de configuración de BD']);
    exit;
}

/**
 * 1. SEGURIDAD: VERIFICACIÓN DE SESIÓN
 * Solo usuarios autenticados pueden agendar o ver citas.
 */
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Función auxiliar para leer cuerpo JSON.
 */
function getJsonInput()
{
    return json_decode(file_get_contents('php://input'), true);
}

try {
    if ($method === 'GET') {
        /**
         * LISTAR MIS CITAS (GET)
         * Obtiene todas las solicitudes del usuario actual con el nombre del servicio.
         */
        $sql = "SELECT r.id_reserva, s.nom_servicio as nombre_servicio, r.fecha_preferida, 
                       r.prioridad, r.estado_reserva as estado, r.notas_cliente as notas
                FROM tab_Reservas r
                JOIN tab_Servicios s ON r.id_servicio = s.id_servicio
                WHERE r.id_usuario = ?
                ORDER BY r.fecha_reserva DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'citas' => $citas]);

    } elseif ($method === 'POST') {
        /**
         * SOLICITAR CITA (POST)
         * Registra una nueva petición de servicio técnico.
         */
        $data = getJsonInput();
        $id_servicio = $data['p_id_servicio'] ?? null;
        $fecha_pref = $data['p_fecha_pref'] ?? null;
        $prioridad = $data['p_prioridad'] ?? 'normal';
        $notas = $data['p_notas'] ?? '';

        if (!$id_servicio || !$fecha_pref) {
            echo json_encode(['ok' => false, 'msg' => 'Servicio y fecha son obligatorios']);
            exit;
        }

        // Generar el siguiente ID secuencial para la reserva
        $maxId = $pdo->query("SELECT COALESCE(MAX(id_reserva), 0) + 1 FROM tab_Reservas")->fetchColumn();

        $sql = "INSERT INTO tab_Reservas (id_reserva, id_usuario, id_servicio, fecha_preferida, notas_cliente, prioridad, estado_reserva, fecha_reserva, usr_insert, fec_insert) 
                VALUES (?, ?, ?, ?, ?, ?, 'pendiente', NOW(), ?, NOW())";
        $stmt = $pdo->prepare($sql);

        // Registramos quién hizo la inserción (user_ID)
        if ($stmt->execute([$maxId, $user_id, $id_servicio, $fecha_pref, $notas, $prioridad, 'user_' . $user_id])) {
            echo json_encode(['ok' => true, 'msg' => 'Cita solicitada correctamente']);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Error al crear la reserva']);
        }

    } else {
        http_response_code(405);
        echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de base de datos: ' . $e->getMessage()]);
}

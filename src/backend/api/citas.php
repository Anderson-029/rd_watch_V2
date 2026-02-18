<?php
/**
 * API: GESTIÓN DE CITAS Y RESERVAS
 * ---------------------------------------------------------
 * Propósito: Gestiona la programación de servicios técnicos por parte 
 * de los clientes y la administración de dichas solicitudes por el staff técnico.
 * 
 * Acciones:
 * - GET: Listar citas (contexto admin o cliente).
 * - POST: Crear nueva solicitud de cita o actualizar estado (vía action=update_status).
 * - PUT: Actualizar estado de cita (exclusivo Admin).
 */

header('Content-Type: application/json');
require_once '../config.php';
require_once '../utils/Validation.php';

// Verificación de integridad de la base de datos
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de configuración de BD']);
    exit;
}

/**
 * 1. SEGURIDAD: VERIFICACIÓN DE SESIÓN
 * Bloqueo de acceso si no existe un usuario logueado en la sesión de PHP.
 */
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'No autorizado: Inicie sesión para continuar']);
    exit;
}

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Captura datos de entrada en formato JSON (Cuerpo de la petición).
 */
// La función getJsonInput() ahora se provee globalmente por security_utils.php de forma segura (con caché).


// Determinación del nivel de acceso según el rol guardado en la sesión
$rol = $_SESSION['user_role'] ?? 'cliente';

try {
    if ($method === 'GET') {
        /**
         * ==========================================
         * 🔍 LISTAR CITAS (GET)
         * ==========================================
         * La consulta varía significativamente según el rol.
         */
        if ($rol === 'admin') {
            /**
             * VISTA ADMIN: RequiereJOIN con la tabla de usuarios para ver quién solicitó la cita.
             */
            $sql = "SELECT r.id_reserva, r.id_usuario, u.nom_usuario as cliente, s.nom_servicio as nombre_servicio, 
                           r.fecha_preferida, r.prioridad, r.estado_reserva as estado, r.notas_cliente as notas
                    FROM tab_Reservas r
                    LEFT JOIN tab_Servicios s ON r.id_servicio = s.id_servicio
                    LEFT JOIN tab_Usuarios u ON r.id_usuario = u.id_usuario
                    ORDER BY r.fecha_reserva DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }
        else {
            /**
             * VISTA CLIENTE: Filtra estrictamente las citas que le pertenecen al usuario logueado.
             */
            $sql = "SELECT r.id_reserva, s.nom_servicio as nombre_servicio, r.fecha_preferida, 
                           r.prioridad, r.estado_reserva as estado, r.notas_cliente as notas
                    FROM tab_Reservas r
                    JOIN tab_Servicios s ON r.id_servicio = s.id_servicio
                    WHERE r.id_usuario = ?
                    ORDER BY r.fecha_reserva DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
        }

        $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'citas' => $citas]);

    }
    elseif ($method === 'POST') {
        /**
         * ==========================================
         * ➕ SOLICITAR O ACTUALIZAR CITA (POST)
         * ==========================================
         */
        validateCsrfToken(null, true);
        $data = getJsonInput();


        /**
         * SUB-ACCIÓN: update_status
         * Permite actualizar el estado de una cita desde un formulario POST (compatibilidad).
         */
        if (isset($data['action']) && $data['action'] === 'update_status') {
            if ($rol !== 'admin') {
                http_response_code(403);
                echo json_encode(['ok' => false, 'msg' => 'Acción denegada: Solo administradores pueden cambiar estados']);
                exit;
            }

            // 🛡️ VALIDACIÓN ESTRICTA (ISO 830)
            Validation::validateOrReject($data, [
                'id_reserva' => 'id',
                'estado' => 'name'
            ]);

            $id_reserva = $data['id_reserva'];
            $nuevo_estado = $data['estado'];

            $sql = "UPDATE tab_Reservas SET estado_reserva = ?, usr_update = ?, fec_update = NOW() WHERE id_reserva = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$nuevo_estado, 'admin_' . $user_id, $id_reserva])) {
                echo json_encode(['ok' => true, 'msg' => 'Estado de cita actualizado exitosamente']);
            }
            else {
                echo json_encode(['ok' => false, 'msg' => 'Error técnico al intentar actualizar el estado']);
            }
            exit;
        }


        /**
         * ACCIÓN POR DEFECTO: Crear Cita
         * Captura parámetros específicos para nueva reserva técnica.
         */
        // 🛡️ VALIDACIÓN ESTRICTA (ISO 830)
        Validation::validateOrReject($data, [
            'p_id_servicio' => 'id',
            'p_fecha_pref' => 'name', // Validamos que sea un string con formato esperado
            'p_prioridad' => 'name'
        ]);

        $id_servicio = (int)$data['p_id_servicio'];
        $fecha_pref = Validation::sanitizeString($data['p_fecha_pref']);
        $prioridad = Validation::sanitizeString($data['p_prioridad'] ?? 'normal');
        $notas = Validation::sanitizeString($data['p_notas'] ?? '');

        // 🛡️ CONTROL DE REDUNDANCIA / ANTI-SPAM: No permitir 2 citas para el mismo servicio en el mismo día
        $stmtCheck = $pdo->prepare("SELECT id_reserva FROM tab_Reservas WHERE id_usuario = ? AND id_servicio = ? AND fecha_preferida = ? AND estado_reserva = 'pendiente'");
        $stmtCheck->execute([$user_id, $id_servicio, $fecha_pref]);
        if ($stmtCheck->fetch()) {
            echo json_encode(['ok' => false, 'msg' => 'Inconsistencia: Ya tiene una solicitud pendiente para este servicio en la fecha seleccionada.']);
            exit;
        }


        // Generación manual de ID autoincremental para entornos sin SERIAL
        $maxId = $pdo->query("SELECT COALESCE(MAX(id_reserva), 0) + 1 FROM tab_Reservas")->fetchColumn();

        $sql = "INSERT INTO tab_Reservas (id_reserva, id_usuario, id_servicio, fecha_preferida, notas_cliente, prioridad, estado_reserva, fecha_reserva, usr_insert, fec_insert) 
                VALUES (?, ?, ?, ?, ?, ?, 'pendiente', NOW(), ?, NOW())";
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute([$maxId, $user_id, $id_servicio, $fecha_pref, $notas, $prioridad, 'user_' . $user_id])) {
            echo json_encode(['ok' => true, 'msg' => 'Su solicitud de cita ha sido registrada']);
        }
        else {
            echo json_encode(['ok' => false, 'msg' => 'Hubo un problema al crear su solicitud en la base de datos']);
        }

    }
    elseif ($method === 'PUT') {
        /**
         * ==========================================
         * 🛠️ ACTUALIZAR ESTADO (PUT) - Estándar REST
         * ==========================================
         */
        if ($rol !== 'admin') {
            http_response_code(403);
            echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
            exit;
        }

        $data = getJsonInput();
        $id_reserva = Validation::validateNumeric($data['id_reserva'] ?? '') ? (int)$data['id_reserva'] : null;
        $nuevo_estado = Validation::sanitizeString($data['estado'] ?? '');

        if (!$id_reserva || empty($nuevo_estado)) {
            echo json_encode(['ok' => false, 'msg' => 'Faltan parámetros críticos (ID o Estado)']);
            exit;
        }

        $sql = "UPDATE tab_Reservas SET estado_reserva = ?, usr_update = ?, fec_update = NOW() WHERE id_reserva = ?";
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute([$nuevo_estado, 'admin_' . $user_id, $id_reserva])) {
            echo json_encode(['ok' => true, 'msg' => 'El estado de la cita fue actualizado correctamente']);
        }
        else {
            echo json_encode(['ok' => false, 'msg' => 'Error al intentar actualizar el registro']);
        }

    }
    else {
        http_response_code(405);
        echo json_encode(['ok' => false, 'msg' => 'Método HTTP diseñado solo para GET, POST y PUT']);
    }
}
catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Falla en la base de datos: ' . $e->getMessage()]);
}
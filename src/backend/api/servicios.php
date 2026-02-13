<?php
/**
 * API: GESTIÓN DE SERVICIOS TÉCNICOS (TALLER)
 * ---------------------------------------------------------
 * Propósito: Administra la oferta de servicios del taller profesional (Mantenimiento, 
 * Pulido, Cambio de Batería, etc.). Estos servicios son la base para las citas técnicas.
 * 
 * Funcionalidades:
 * - GET: Listado cronológico de servicios disponibles.
 * - POST: Registro de nuevos tipos de servicio con validación de nombre.
 * - PUT: Actualización de precios y tiempos estimados.
 * - DELETE: Eliminación física del registro de servicio.
 */

header('Content-Type: application/json');
require_once '../config.php';
require_once '../utils/security_utils.php';
require_once '../utils/Validation.php';

// Verificación de salud de la conexión a la base de datos
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de conexión: El motor de base de datos no responde']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

/**
 * Captura datos de cuerpo JSON de la petición.
 */
function getJsonInput()
{
    // Usamos la versión cacheada en security_utils.php para evitar conflictos con validateCsrfToken
    if (function_exists('getCachedJsonInput')) {
        return getCachedJsonInput();
    }
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

try {
    switch ($method) {
        case 'GET':
            /**
             * ==========================================
             * 🔍 LISTAR SERVICIOS (GET)
             * ==========================================
             * Lógica: Recupera todos los servicios del catálogo ordenados por ID de forma descendente.
             */
            $stmt = $pdo->prepare("SELECT id_servicio, nom_servicio, descripcion, precio_servicio, duracion_estimada FROM tab_Servicios ORDER BY id_servicio DESC");
            $stmt->execute();
            $servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'servicios' => $servicios]);
            break;

        case 'POST':
            /**
             * ==========================================
             * ➕ CREAR SERVICIO (POST)
             * ==========================================
             * Seguridad: Solo accesible por administradores y protegido por CSRF.
             */
            requireRole('admin');
            validateCsrfToken(null, true);

            $data = getJsonInput();

            // 🛡️ VALIDACIÓN FLEXIBILIZADA (Rescate funcional)
            Validation::validateOrReject($data, [
                'id_servicio' => 'id',
                'nom_servicio' => 'name',
                'precio_servicio' => 'price'
            ]);

            // 1. Validar unicidad del nombre del servicio para evitar redundancia
            $check = $pdo->prepare("SELECT id_servicio FROM tab_Servicios WHERE nom_servicio = ?");
            $check->execute([$data['nom_servicio']]);
            if ($check->fetch()) {
                echo json_encode(['ok' => false, 'msg' => 'Inconsistencia: Ya existe un servicio técnico registrado con ese nombre']);
                exit;
            }

            $sql = "INSERT INTO tab_Servicios (id_servicio, nom_servicio, descripcion, precio_servicio, duracion_estimada, fec_insert, usr_insert) 
                    VALUES (?, ?, ?, ?, ?, NOW(), ?)";
            $stmt = $pdo->prepare($sql);

            $desc = Validation::sanitizeString($data['descripcion'] ?? '');
            $duracion = Validation::sanitizeString($data['duracion_estimada'] ?? 'Consultar');

            $user_id = $_SESSION['user_id'] ?? 'admin_manual';

            if ($stmt->execute([$data['id_servicio'], $data['nom_servicio'], $desc, $data['precio_servicio'], $duracion, $user_id])) {
                echo json_encode(['ok' => true, 'msg' => 'Nuevo servicio técnico añadido exitosamente']);
            }
            else {
                $error = $stmt->errorInfo();
                echo json_encode(['ok' => false, 'msg' => 'Fallo en BD: ' . ($error[2] ?? 'Error desconocido')]);
            }
            break;

        case 'PUT':
            /**
             * ==========================================
             * 🔄 ACTUALIZAR SERVICIO (PUT)
             * ==========================================
             */
            requireRole('admin');
            validateCsrfToken(null, true);

            $data = getJsonInput();
            if (!isset($data['id_servicio'])) {
                echo json_encode(['ok' => false, 'msg' => 'Error: Se requiere el ID del servicio para actualizar']);
                exit;
            }

            $sql = "UPDATE tab_Servicios 
                    SET nom_servicio = ?, descripcion = ?, precio_servicio = ?, duracion_estimada = ?, fec_update = NOW(), usr_update = 'admin_editor'
                    WHERE id_servicio = ?";
            $stmt = $pdo->prepare($sql);

            if ($stmt->execute([
            $data['nom_servicio'],
            $data['descripcion'],
            $data['precio_servicio'],
            $data['duracion_estimada'],
            $data['id_servicio']
            ])) {
                echo json_encode(['ok' => true, 'msg' => 'Servicio actualizado correctamente']);
            }
            else {
                $error = $stmt->errorInfo();
                echo json_encode(['ok' => false, 'msg' => 'Fallo técnico en BD: ' . ($error[2] ?? 'Error desconocido')]);
            }
            break;

        case 'DELETE':
            /**
             * ==========================================
             * 🗑️ ELIMINAR SERVICIO (DELETE)
             * ==========================================
             */
            requireRole('admin');
            validateCsrfToken(null, true);

            $data = getJsonInput();
            $sid = $data['id_servicio'] ?? null;

            logDebug("DELETE SERVICE ATTEMPT: ID[" . ($sid ?? 'NULL') . "]");

            if (!$sid) {
                echo json_encode(['ok' => false, 'msg' => 'ID de servicio no proporcionado']);
                exit;
            }

            // 1. Verificar si existen citas (reservas) vinculadas a este servicio
            $checkCitas = $pdo->prepare("SELECT COUNT(*) FROM tab_Reservas WHERE id_servicio = ?");
            $checkCitas->execute([$sid]);
            $count = $checkCitas->fetchColumn();

            if ($count > 0) {
                logDebug("DELETE BLOCKED: Service $sid has $count linked reservations.");
                echo json_encode([
                    'ok' => false,
                    'msg' => 'Imposible borrar: Este servicio tiene ' . $count . ' citas técnicas vinculadas'
                ]);
                exit;
            }

            // 2. Ejecutar borrado físico
            $stmt = $pdo->prepare("DELETE FROM tab_Servicios WHERE id_servicio = ?");
            if ($stmt->execute([$sid])) {
                logDebug("DELETE SUCCESS: Service $sid removed.");
                echo json_encode(['ok' => true, 'msg' => 'Servicio eliminado permanentemente del catálogo']);
            }
            else {
                logDebug("DELETE FAILED: DB Execute returned false for ID $sid.");
                echo json_encode(['ok' => false, 'msg' => 'Falla técnica al procesar la eliminación en la base de datos']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['ok' => false, 'msg' => 'Método HTTP no soportado por esta API']);
            break;
    }
}
catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error crítico en el servidor: ' . $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()]);
}
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
    return json_decode(file_get_contents('php://input'), true);
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
             * Seguridad: Valida que no exista otro servicio con el mismo nombre para evitar confusión en el taller.
             */
            $data = getJsonInput();
            if (!isset($data['id_servicio'], $data['nom_servicio'], $data['precio_servicio'])) {
                echo json_encode(['ok' => false, 'msg' => 'Faltan campos obligatorios: ID, Nombre o Precio']);
                exit;
            }

            // 1. Validar unicidad del nombre del servicio
            $check = $pdo->prepare("SELECT id_servicio FROM tab_Servicios WHERE nom_servicio = ?");
            $check->execute([$data['nom_servicio']]);
            if ($check->fetch()) {
                echo json_encode(['ok' => false, 'msg' => 'Conflicto: Ya existe un servicio técnico registrado con ese nombre']);
                exit;
            }

            $sql = "INSERT INTO tab_Servicios (id_servicio, nom_servicio, descripcion, precio_servicio, duracion_estimada, fec_insert, usr_insert) 
                    VALUES (?, ?, ?, ?, ?, NOW(), 'admin_taller')";
            $stmt = $pdo->prepare($sql);

            $desc = $data['descripcion'] ?? '';
            $duracion = $data['duracion_estimada'] ?? 'Consultar';

            if ($stmt->execute([$data['id_servicio'], $data['nom_servicio'], $desc, $data['precio_servicio'], $duracion])) {
                echo json_encode(['ok' => true, 'msg' => 'Nuevo servicio técnico añadido exitosamente']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Fallo al intentar registrar el servicio en BD']);
            }
            break;

        case 'PUT':
            /**
             * ==========================================
             * 🔄 ACTUALIZAR SERVICIO (PUT)
             * ==========================================
             */
            $data = getJsonInput();
            if (!isset($data['id_servicio'])) {
                echo json_encode(['ok' => false, 'msg' => 'Error: Se requiere el ID del servicio para actualizar']);
                exit;
            }

            $sql = "UPDATE tab_Servicios 
                    SET nom_servicio = ?, descripcion = ?, precio_servicio = ?, duracion_estimada = ?, fec_update = NOW(), usr_update = 'admin_editor'
                    WHERE id_servicio = ?";
            $stmt = $pdo->prepare($sql);

            if (
                $stmt->execute([
                    $data['nom_servicio'],
                    $data['descripcion'],
                    $data['precio_servicio'],
                    $data['duracion_estimada'],
                    $data['id_servicio']
                ])
            ) {
                echo json_encode(['ok' => true, 'msg' => 'Servicio actualizado correctamente']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Fallo técnico al realizar la actualización']);
            }
            break;

        case 'DELETE':
            /**
             * ==========================================
             * 🗑️ ELIMINAR SERVICIO (DELETE)
             * ==========================================
             * Nota: Se borra físicamente. En una versión futura se recomienda borrado lógico
             * si el servicio ya tiene citas históricas vinculadas.
             */
            $data = getJsonInput();
            if (!isset($data['id_servicio'])) {
                echo json_encode(['ok' => false, 'msg' => 'ID de servicio no proporcionado']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM tab_Servicios WHERE id_servicio = ?");
            if ($stmt->execute([$data['id_servicio']])) {
                echo json_encode(['ok' => true, 'msg' => 'Servicio eliminado permanentemente del catálogo']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error de integridad al intentar borrar el registro']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['ok' => false, 'msg' => 'Método HTTP no soportado por esta API']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error crítico de base de datos: ' . $e->getMessage()]);
}

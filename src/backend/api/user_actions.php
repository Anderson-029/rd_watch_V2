<?php
/**
 * API: ACCIONES DEL CLIENTE (Panel de Usuario)
 * ---------------------------------------------------------
 * Centraliza las acciones que un cliente puede realizar sobre su propia 
 * cuenta, como ver su perfil, actualizar datos y gestionar su dirección.
 */

header('Content-Type: application/json');
require_once '../config.php';

// Verificación de la conexión a la base de datos
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de configuración de BD']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

/**
 * Función auxiliar para capturar datos enviados en formato JSON.
 */
function getJsonInput()
{
    return json_decode(file_get_contents('php://input'), true);
}

try {
    if ($method === 'GET') {
        /**
         * ==========================================
         * 🔍 OBTENCIÓN DE DATOS (GET)
         * ==========================================
         */
        $action = $_GET['action'] ?? '';
        $uid = $_GET['uid'] ?? null;

        if ($action === 'perfil' && $uid) {
            /**
             * OBTENER PERFIL
             * Retorna la información personal del usuario.
             */
            $stmt = $pdo->prepare("SELECT id_usuario, nom_usuario, correo_usuario, num_telefono_usuario, direccion_principal, activo, fecha_registro FROM tab_Usuarios WHERE id_usuario = ?");
            $stmt->execute([$uid]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                echo json_encode(['ok' => true, 'data' => $user]);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Usuario no encontrado']);
            }
        } elseif ($action === 'pedidos' && $uid) {
            /**
             * OBTENER PEDIDOS DEL USUARIO
             * Retorna el historial de órdenes del cliente.
             */
            $stmt = $pdo->prepare("
                SELECT id_orden, concepto, fecha_orden as fecha, total_orden, estado_orden 
                FROM tab_Orden 
                WHERE id_usuario = ? 
                ORDER BY fecha_orden DESC
            ");
            $stmt->execute([$uid]);
            $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'data' => $pedidos]);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Acción o ID de usuario no proporcionado']);
        }
    } elseif ($method === 'POST') {
        /**
         * ==========================================
         * 🔄 PROCESAMIENTO DE ACCIONES (POST)
         * ==========================================
         */
        $data = getJsonInput();
        $action = $data['action'] ?? '';
        $uid = $data['uid'] ?? null;

        if ($action === 'update_profile' && $uid) {
            /**
             * ACTUALIZAR PERFIL
             * Actualiza nombre, correo y teléfono. Valida que el correo sea único.
             */
            $nombre = $data['nombre'] ?? '';
            $email = $data['email'] ?? '';
            $telefono = $data['telefono'] ?? '';

            if (empty($nombre) || empty($email)) {
                echo json_encode(['ok' => false, 'msg' => 'El nombre y el correo son obligatorios']);
                exit;
            }

            // Validar que el nuevo correo no esté en uso por otro ID de usuario
            $checkEmail = $pdo->prepare("SELECT id_usuario FROM tab_Usuarios WHERE correo_usuario = ? AND id_usuario <> ?");
            $checkEmail->execute([$email, $uid]);
            if ($checkEmail->fetch()) {
                echo json_encode(['ok' => false, 'msg' => 'El correo electrónico ya está en uso por otro usuario']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE tab_Usuarios SET nom_usuario = ?, correo_usuario = ?, num_telefono_usuario = ?, fec_update = NOW(), usr_update = 'self' WHERE id_usuario = ?");
            if ($stmt->execute([$nombre, $email, $telefono, $uid])) {
                echo json_encode(['ok' => true, 'msg' => 'Perfil actualizado correctamente']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error al actualizar el perfil']);
            }
        } elseif ($action === 'update_address' && $uid) {
            /**
             * ACTUALIZAR DIRECCIÓN
             * Gestiona tanto la tabla tab_Usuarios como tab_Direcciones_Envio usando transacciones.
             */
            $direccion = $data['direccion'] ?? '';
            $ciudad_id = $data['ciudad_id'] ?? null;
            $postal = $data['postal'] ?? '';

            if (empty($direccion) || !$ciudad_id) {
                echo json_encode(['ok' => false, 'msg' => 'Dirección y ciudad son obligatorios']);
                exit;
            }

            // Iniciamos una transacción para asegurar que ambas tablas se actualicen correctamente
            $pdo->beginTransaction();
            try {
                // 1. Actualizar la dirección rápida en la tabla Usuarios
                $stmtUser = $pdo->prepare("UPDATE tab_Usuarios SET direccion_principal = ?, fec_update = NOW(), usr_update = 'self' WHERE id_usuario = ?");
                $stmtUser->execute([$direccion, $uid]);

                // 2. Gestionar el detalle en la tabla de Direcciones de Envío
                // Buscamos si el usuario ya tiene una dirección marcada como predeterminada
                $check = $pdo->prepare("SELECT id_direccion FROM tab_Direcciones_Envio WHERE id_usuario = ? AND es_predeterminada = TRUE");
                $check->execute([$uid]);
                $dirExistente = $check->fetch();

                if ($dirExistente) {
                    // Si existe, la actualizamos
                    $sqlDir = "UPDATE tab_Direcciones_Envio SET direccion_completa = ?, id_ciudad = ?, codigo_postal = ?, fec_update = NOW(), usr_update = 'self' WHERE id_direccion = ?";
                    $stmtDir = $pdo->prepare($sqlDir);
                    $stmtDir->execute([$direccion, $ciudad_id, $postal, $dirExistente['id_direccion']]);
                } else {
                    // Si no existe, creamos una nueva dirección predeterminada
                    $maxId = $pdo->query("SELECT COALESCE(MAX(id_direccion), 0) + 1 FROM tab_Direcciones_Envio")->fetchColumn();
                    $sqlDir = "INSERT INTO tab_Direcciones_Envio (id_direccion, id_usuario, direccion_completa, id_ciudad, codigo_postal, es_predeterminada, fec_insert, usr_insert) 
                               VALUES (?, ?, ?, ?, ?, TRUE, NOW(), 'self')";
                    $stmtDir = $pdo->prepare($sqlDir);
                    $stmtDir->execute([$maxId, $uid, $direccion, $ciudad_id, $postal]);
                }

                $pdo->commit();
                echo json_encode(['ok' => true, 'msg' => 'Dirección actualizada correctamente']);
            } catch (Exception $e) {
                // Si algo falla, deshacemos todos los cambios en la BD
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'msg' => 'Error al procesar dirección: ' . $e->getMessage()]);
            }
        } elseif ($action === 'update_payment') {
            // Placeholder para futura gestión de métodos de pago
            echo json_encode(['ok' => false, 'msg' => 'Acción POST no reconocida o datos incompletos']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de base de datos: ' . $e->getMessage()]);
}

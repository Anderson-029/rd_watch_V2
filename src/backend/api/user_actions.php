<?php
/**
 * API: ACCIONES DEL CLIENTE (Panel de Usuario)
 * ---------------------------------------------------------
 * Propósito: Centraliza las acciones que un cliente puede realizar sobre su propia 
 * cuenta, como ver su perfil, actualizar datos, gestionar su dirección y ver su resumen de actividad.
 * 
 * Métodos Soportados:
 * - GET: Consultar información (perfil, pedidos, resumen).
 * - POST: Modificar información (actualizar perfil, dirección).
 */

header('Content-Type: application/json');
require_once '../config.php';
require_once '../utils/security_utils.php';
require_once '../utils/Validation.php';

// 🛡️ SEGURIDAD: Se requiere inicio de sesión para CUALQUIER acción
requireLogin();

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de configuración de BD']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

/**
 * Función auxiliar para capturar datos enviados en formato JSON (Peticiones POST).
 * @return array|null Datos decodificados del cuerpo de la petición.
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
         * 🛡️ SEGURIDAD IDOR: Se ignora el 'uid' de la URL para usar el de la sesión.
         */
        $uid = $_SESSION['user_id'];
        $action = $_GET['action'] ?? '';

        if ($action === 'perfil') {
            /**
             * ACCIÓN: perfil
             * Propósito: Retorna la información personal básica y de contacto del usuario.
             */
            $stmt = $pdo->prepare("SELECT id_usuario, nom_usuario, correo_usuario, num_telefono_usuario, direccion_principal, activo, fecha_registro FROM tab_Usuarios WHERE id_usuario = ?");
            $stmt->execute([$uid]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                echo json_encode(['ok' => true, 'data' => $user]);
            }
            else {
                echo json_encode(['ok' => false, 'msg' => 'Usuario no encontrado']);
            }

        }
        elseif ($action === 'pedidos') {
            /**
             * ACCIÓN: pedidos
             * Propósito: Retorna el historial de órdenes realizadas por el cliente, ordenadas por fecha reciente.
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

        }
        elseif ($action === 'resumen') {
            /**
             * ACCIÓN: resumen
             * Propósito: Proporciona conteos rápidos para el dashboard del usuario.
             */

            // 1. Conteo de Pedidos Activos
            $stmtActivos = $pdo->prepare("SELECT COUNT(*) FROM tab_Orden WHERE id_usuario = ? AND estado_orden IN ('pendiente', 'confirmado', 'enviado')");
            $stmtActivos->execute([$uid]);
            $pedidosActivos = $stmtActivos->fetchColumn();

            // 2. Conteo de Pedidos Completados
            $stmtCompletados = $pdo->prepare("SELECT COUNT(*) FROM tab_Orden WHERE id_usuario = ? AND estado_orden = 'entregado' ");
            $stmtCompletados->execute([$uid]);
            $pedidosCompletados = $stmtCompletados->fetchColumn();

            // 3. Conteo de Citas Pendientes
            $stmtCitas = $pdo->prepare("SELECT COUNT(*) FROM tab_Reservas WHERE id_usuario = ? AND estado_reserva = 'pendiente'");
            $stmtCitas->execute([$uid]);
            $citasPendientes = $stmtCitas->fetchColumn();

            echo json_encode([
                'ok' => true,
                'data' => [
                    'pedidosActivos' => (int)$pedidosActivos,
                    'pedidosCompletados' => (int)$pedidosCompletados,
                    'citasPendientes' => (int)$citasPendientes
                ]
            ]);

        }
        else {
            echo json_encode(['ok' => false, 'msg' => 'Acción no especificada o inválida']);
        }

    }
    elseif ($method === 'POST') {
        /**
         * ==========================================
         * 🔄 PROCESAMIENTO DE ACCIONES (POST)
         * ==========================================
         * Espera JSON con { action, uid, ...datos }
         */
        $data = getJsonInput();
        validateCsrfToken(null, true);
        $action = $data['action'] ?? '';

        // 🛡️ SEGURIDAD IDOR: El ID de usuario NUNCA debe venir del cliente para operaciones de escritura.
        $uid = $_SESSION['user_id'];


        if ($action === 'update_profile' && $uid) {
            /**
             * ACCIÓN: update_profile
             * Propósito: Actualiza los datos de contacto.
             * Seguridad: Valida duplicidad de correo electrónico antes de guardar.
             */
            // 🛡️ VALIDACIÓN ESTRICTA (ISO 830)
            Validation::validateOrReject($data, [
                'nombre' => 'name',
                'email' => 'email',
                'telefono' => 'phone'
            ]);

            $nombre = Validation::sanitizeString($data['nombre']);
            $email = Validation::sanitizeString($data['email']);
            $telefono = $data['telefono'];


            // Validar que el nuevo correo no lo tenga otra cuenta
            $checkEmail = $pdo->prepare("SELECT id_usuario FROM tab_Usuarios WHERE correo_usuario = ? AND id_usuario <> ?");
            $checkEmail->execute([$email, $uid]);
            if ($checkEmail->fetch()) {
                echo json_encode(['ok' => false, 'msg' => 'El correo electrónico ya está en uso por otro usuario']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE tab_Usuarios SET nom_usuario = ?, correo_usuario = ?, num_telefono_usuario = ?, fec_update = NOW(), usr_update = 'self' WHERE id_usuario = ?");
            if ($stmt->execute([$nombre, $email, $telefono, $uid])) {
                echo json_encode(['ok' => true, 'msg' => 'Perfil actualizado correctamente']);
            }
            else {
                echo json_encode(['ok' => false, 'msg' => 'Error al actualizar el perfil']);
            }

        }
        elseif ($action === 'update_address' && $uid) {
            /**
             * ACCIÓN: update_address
             * Propósito: Sincroniza la dirección principal en Usuarios y el detalle en Direcciones_Envio.
             * Lógica: Usa transacciones SQL para asegurar atomicidad (o se guardan ambos o nada).
             */
            // 🛡️ VALIDACIÓN ESTRICTA (ISO 830)
            Validation::validateOrReject($data, [
                'direccion' => 'address',
                'ciudad_id' => 'id',
                'postal' => 'zip'
            ]);

            $direccion = Validation::sanitizeString($data['direccion']);
            $ciudad_id = $data['ciudad_id'];
            $postal = $data['postal'];

            // 🛡️ CONTROL DE REDUNDANCIA: No permitir registrar la misma dirección dos veces para el mismo usuario
            $stmtRedundancy = $pdo->prepare("SELECT id_direccion FROM tab_Direcciones_Envio WHERE id_usuario = ? AND direccion_completa = ? AND id_ciudad = ?");
            $stmtRedundancy->execute([$uid, $direccion, $ciudad_id]);
            if ($stmtRedundancy->fetch()) {
                echo json_encode(['ok' => false, 'msg' => 'Inconsistencia: Esta dirección ya se encuentra registrada en su agenda.']);
                exit;
            }


            $pdo->beginTransaction();
            try {
                // 1. Actualizar caché de dirección en tabla Usuarios
                $stmtUser = $pdo->prepare("UPDATE tab_Usuarios SET direccion_principal = ?, fec_update = NOW(), usr_update = 'self' WHERE id_usuario = ?");
                $stmtUser->execute([$direccion, $uid]);

                // 2. Gestionar la tabla de Direcciones detallada
                $check = $pdo->prepare("SELECT id_direccion FROM tab_Direcciones_Envio WHERE id_usuario = ? AND es_predeterminada = TRUE");
                $check->execute([$uid]);
                $dirExistente = $check->fetch();

                if ($dirExistente) {
                    // Actualizar si ya existe una predeterminada
                    $sqlDir = "UPDATE tab_Direcciones_Envio SET direccion_completa = ?, id_ciudad = ?, codigo_postal = ?, fec_update = NOW(), usr_update = 'self' WHERE id_direccion = ?";
                    $stmtDir = $pdo->prepare($sqlDir);
                    $stmtDir->execute([$direccion, $ciudad_id, $postal, $dirExistente['id_direccion']]);
                }
                else {
                    // Insertar nueva si no tiene
                    $maxId = $pdo->query("SELECT COALESCE(MAX(id_direccion), 0) + 1 FROM tab_Direcciones_Envio")->fetchColumn();
                    $sqlDir = "INSERT INTO tab_Direcciones_Envio (id_direccion, id_usuario, direccion_completa, id_ciudad, codigo_postal, es_predeterminada, fec_insert, usr_insert) 
                               VALUES (?, ?, ?, ?, ?, TRUE, NOW(), 'self')";
                    $stmtDir = $pdo->prepare($sqlDir);
                    $stmtDir->execute([$maxId, $uid, $direccion, $ciudad_id, $postal]);
                }

                $pdo->commit();
                echo json_encode(['ok' => true, 'msg' => 'Dirección actualizada correctamente']);
            }
            catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'msg' => 'Error al procesar dirección: ' . $e->getMessage()]);
            }
        }
        else {
            echo json_encode(['ok' => false, 'msg' => 'Acción POST no reconocida o datos incompletos']);
        }
    }
    else {
        http_response_code(405);
        echo json_encode(['ok' => false, 'msg' => 'Método HTTP no permitido']);
    }
}
catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error crítico de base de datos: ' . $e->getMessage()]);
}
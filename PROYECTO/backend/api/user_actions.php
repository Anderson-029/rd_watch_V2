<?php
/**
 * RD Watch - Sistema de Gestión de Relojería
 * Endpoints para Acciones de Usuario Autenticado
 * 
 * Maneja el perfil, direcciones, pedidos y métodos de pago del cliente.
 * Requiere sesión válida y protege contra CSRF y ataques de tasa (Rate Limiting).
 */

// Aplicar cabeceras de seguridad y dependencias
require_once('../security_headers.php');
require_once('../csrf.php');
require_once('../validator.php');
require_once('../session_manager.php');
require_once('../rate_limiter.php');
require_once('../encoder.php');
require_once('../config.php');

header('Content-Type: application/json');

// Iniciar sesión y validar autenticidad
require_valid_session();

$sessionUid = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Whitelist de acciones permitidas
$allowedActions = [
    'perfil',
    'direccion',
    'pedidos',
    'metodo_pago',
    'update_profile',
    'update_address',
    'change_password',
    'update_payment'
];

try {
    // Validar acción si se proporciona por URL
    if (!empty($action)) {
        $action = Validator::validateAction($action, $allowedActions);
    }

    // Procesar datos de entrada para peticiones POST
    $data = null;
    if ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!$data) {
            ErrorHandler::stopError("Datos de solicitud inválidos (JSON esperado)", 400);
        }
        // Permitir definir la acción dentro del cuerpo JSON
        if (isset($data['action'])) {
            $action = Validator::validateAction($data['action'], $allowedActions);
        }
    }

    // =========================================================================
    // BLOQUE DE ACCIONES GET (Consultas)
    // =========================================================================
    if ($method === 'GET') {
        switch ($action) {
            case 'perfil':
                $stmt = $pdo->prepare("
                    SELECT nom_usuario, correo_usuario, num_telefono_usuario, direccion_principal
                    FROM tab_Usuarios 
                    WHERE id_usuario = :uid
                ");
                $stmt->execute([':uid' => $sessionUid]);
                $res = $stmt->fetch();

                if ($res) {
                    $res['nom_usuario'] = htmlspecialchars($res['nom_usuario']);
                    $res['correo_usuario'] = htmlspecialchars($res['correo_usuario']);
                    $res['direccion_principal'] = htmlspecialchars($res['direccion_principal'] ?? '');
                    $res['num_telefono_usuario'] = htmlspecialchars($res['num_telefono_usuario'] ?? '');
                }
                ErrorHandler::sendSuccess("Perfil obtenido", $res ?: []);
                break;

            case 'direccion':
                $stmt = $pdo->prepare("SELECT * FROM fun_obtener_direccion_usuario(:uid)");
                $stmt->execute([':uid' => $sessionUid]);
                $res = $stmt->fetch();
                ErrorHandler::sendSuccess("Dirección obtenida", $res ?: null);
                break;

            case 'pedidos':
                $stmt = $pdo->prepare("SELECT * FROM fun_obtener_pedidos_usuario(:uid)");
                $stmt->execute([':uid' => $sessionUid]);
                $pedidos = $stmt->fetchAll();
                ErrorHandler::sendSuccess("Lista de pedidos obtenida", $pedidos);
                break;

            case 'metodo_pago':
                $stmt = $pdo->prepare("SELECT * FROM fun_obtener_metodo_pago_usuario(:uid)");
                $stmt->execute([':uid' => $sessionUid]);
                $res = $stmt->fetch();
                ErrorHandler::sendSuccess("Método de pago obtenido", $res ?: null);
                break;

            default:
                ErrorHandler::stopError("Acción GET no especificada o inválida", 400);
        }
    }

    // =========================================================================
    // BLOQUE DE ACCIONES POST (Modificaciones)
    // =========================================================================
    elseif ($method === 'POST') {
        // Requerir protección CSRF y Rate Limiting
        require_csrf_token();

        if (!check_rate_limit('user_action_post', (string) $sessionUid, 20, 60)) {
            rate_limit_fail_response(60);
        }

        switch ($action) {
            case 'update_profile':
                $nombre = Validator::sanitizeString($data['nombre'] ?? '', 255);
                $telefono = Validator::validatePhone($data['telefono'] ?? '');

                $stmt = $pdo->prepare("SELECT * FROM fun_actualizar_perfil(:uid, :nom, :tel)");
                $stmt->execute([
                    ':uid' => $sessionUid,
                    ':nom' => $nombre,
                    ':tel' => (int) preg_replace('/[^0-9]/', '', $telefono)
                ]);
                $res = $stmt->fetch();

                if ($res['status'] === 'SUCCESS') {
                    ErrorHandler::sendSuccess(Encoder::html($res['message']));
                } else {
                    ErrorHandler::stopError(Encoder::html($res['message']), 400);
                }
                break;

            case 'update_address':
                $direccion = Validator::sanitizeString($data['direccion'] ?? '', 255);
                $ciudad_id = Validator::validateId($data['ciudad_id'] ?? 0);
                $postal = Validator::sanitizeString($data['postal'] ?? '', 20);

                $stmt = $pdo->prepare("SELECT * FROM fun_gestionar_direccion(:uid, :dir, :ciudad, :postal)");
                $stmt->execute([
                    ':uid' => $sessionUid,
                    ':dir' => $direccion,
                    ':ciudad' => $ciudad_id,
                    ':postal' => $postal
                ]);
                $res = $stmt->fetch();

                if ($res['status'] === 'SUCCESS') {
                    ErrorHandler::sendSuccess(Encoder::html($res['message']));
                } else {
                    ErrorHandler::stopError(Encoder::html($res['message']), 400);
                }
                break;

            case 'change_password':
                $oldPass = $data['old_pass'] ?? '';
                $newPass = Validator::validatePassword($data['new_pass'] ?? '');

                $stmt = $pdo->prepare("SELECT * FROM fun_cambiar_password(:uid, :old, :new)");
                $stmt->execute([
                    ':uid' => $sessionUid,
                    ':old' => $oldPass,
                    ':new' => $newPass
                ]);
                $res = $stmt->fetch();

                if ($res['status'] === 'SUCCESS') {
                    secure_session_regenerate();
                    ErrorHandler::sendSuccess(Encoder::html($res['message']));
                } else {
                    ErrorHandler::stopError(Encoder::html($res['message']), 403);
                }
                break;

            case 'update_payment':
                $metodo_id = Validator::validateId($data['id_metodo_pago'] ?? 0);
                $tarjeta = Validator::sanitizeString($data['num_tarjeta'] ?? '', 20);

                // Procesar fecha MM/YYYY
                $fechaVenc = null;
                if (!empty($data['fecha_vencimiento'])) {
                    $partes = explode('/', $data['fecha_vencimiento']);
                    if (count($partes) === 2) {
                        $fechaVenc = "{$partes[1]}-" . str_pad($partes[0], 2, '0', STR_PAD_LEFT) . "-01";
                    }
                }

                $stmt = $pdo->prepare("SELECT * FROM fun_actualizar_metodo_pago(:uid, :metodo, :tarjeta, :fecha)");
                $stmt->execute([
                    ':uid' => $sessionUid,
                    ':metodo' => $metodo_id,
                    ':tarjeta' => $tarjeta,
                    ':fecha' => $fechaVenc
                ]);
                $res = $stmt->fetch();

                if ($res['status'] === 'SUCCESS') {
                    ErrorHandler::sendSuccess($res['message']);
                } else {
                    ErrorHandler::stopError($res['message'], 400);
                }
                break;

            default:
                ErrorHandler::stopError("Acción POST no reconocida", 400);
        }
    } else {
        ErrorHandler::stopError("Método HTTP no permitido", 405);
    }

} catch (PDOException $e) {
    ErrorHandler::handleException($e);
} catch (Throwable $e) {
    ErrorHandler::handleException($e);
}

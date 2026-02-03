<?php
// backend/api/checkout.php
ob_start();
include_once('../config.php');
ob_clean(); // Limpieza de buffer

// Aplicar cabeceras de seguridad
require_once('../security_headers.php');
require_once('../csrf.php');
require_once('../validator.php');
require_once('../session_manager.php');
require_once('../rate_limiter.php');

header('Content-Type: application/json');

// 1. Verificar Sesión con seguridad mejorada
require_valid_session();

// 2. Verificar token CSRF
require_csrf_token();

// 3. Rate limiting - 10 checkouts por minuto por usuario
$userId = (string)$_SESSION['user_id'];
if (!check_rate_limit('checkout', $userId, 10, 60)) {
    rate_limit_fail_response(60);
}

try {
    // 4. Obtener y validar datos del frontend
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data) {
        throw new InvalidArgumentException('Datos inválidos');
    }
    
    // Validar y sanitizar entradas
    $direccion = Validator::sanitizeString($data['direccion'] ?? '', 255);
    $ciudad    = Validator::sanitizeString($data['ciudad'] ?? '', 100);
    $metodo    = Validator::sanitizeString($data['metodo'] ?? 'Transferencia Bancaria', 50);
    
    if (empty($direccion) || empty($ciudad)) {
        echo json_encode(['ok' => false, 'msg' => 'Dirección y ciudad son requeridas']);
        exit;
    }

    $direccion_completa = "$direccion, $ciudad";

    // 5. Llamar a la función SQL de checkout (usa prepared statements)
    $stmt = $pdo->prepare("SELECT fun_checkout(:uid, :metodo, :dir)");
    $stmt->execute([
        ':uid'    => $_SESSION['user_id'],
        ':metodo' => $metodo,
        ':dir'    => $direccion_completa
    ]);
    
    $resultado = $stmt->fetchColumn();

    if (strpos($resultado, 'SUCCESS') !== false) {
        // Regenerar CSRF token después de operación sensible
        regenerate_csrf_token();
        
        echo json_encode([
            'ok' => true, 
            'msg' => 'Orden creada exitosamente',
            'order_id' => filter_var($resultado, FILTER_SANITIZE_NUMBER_INT),
            'csrf_token' => generate_csrf_token()  // Nuevo token para siguiente operación
        ]);
    } else {
        echo json_encode(['ok' => false, 'msg' => $resultado]);
    }

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error en checkout.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error del servidor']);
}
ob_end_flush();

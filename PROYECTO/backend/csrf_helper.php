<?php
/**
 * CSRF Protection Helper
 * Genera y valida tokens CSRF para proteger formularios y APIs
 */

/**
 * Genera un nuevo token CSRF y lo almacena en sesión
 * @return string Token CSRF generado
 */
function csrf_generate_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    $_SESSION['csrf_token_time'] = time();
    
    return $token;
}

/**
 * Obtiene el token CSRF actual o genera uno nuevo si no existe
 * @return string Token CSRF
 */
function csrf_get_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Si no hay token o expiró (1 hora), generar nuevo
    if (!isset($_SESSION['csrf_token']) || 
        !isset($_SESSION['csrf_token_time']) ||
        (time() - $_SESSION['csrf_token_time']) > 3600) {
        return csrf_generate_token();
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Valida un token CSRF recibido contra el almacenado en sesión
 * @param string $token Token a validar
 * @return bool True si es válido, false si no
 */
function csrf_validate_token(?string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($token) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    // Comparación segura contra timing attacks
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Envía respuesta de error CSRF y termina ejecución
 */
function csrf_fail_response(): void {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => false,
        'msg' => 'Token de seguridad inválido. Recarga la página e intenta de nuevo.'
    ]);
    exit;
}


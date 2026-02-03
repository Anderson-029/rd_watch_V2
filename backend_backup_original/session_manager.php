<?php
/**
 * Session Security Manager
 * Prevents session hijacking and fixation attacks
 */

/**
 * Generate a unique fingerprint for the current session
 * @return string The fingerprint hash
 */
function generate_session_fingerprint(): string {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
    
    // Use a secret salt (should be in .env in production)
    $salt = $_ENV['SESSION_SALT'] ?? 'RD_WATCH_SECRET_SALT_2026';
    
    return hash('sha256', $userAgent . $acceptLanguage . $acceptEncoding . $salt);
}

/**
 * Initialize session with security fingerprint
 */
function init_session_security(): void {
    if (!isset($_SESSION['fingerprint'])) {
        $_SESSION['fingerprint'] = generate_session_fingerprint();
        $_SESSION['created_at'] = time();
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Validate session fingerprint and timeout
 * @return bool True if session is valid
 */
function validate_session_security(): bool {
    // Check if session exists
    if (!isset($_SESSION['fingerprint'])) {
        return false;
    }
    
    // Validate fingerprint
    $currentFingerprint = generate_session_fingerprint();
    if (!hash_equals($_SESSION['fingerprint'], $currentFingerprint)) {
        error_log('⚠️ Session hijacking attempt detected');
        session_destroy();
        return false;
    }
    
    // Check session timeout (30 minutes of inactivity)
    $sessionTimeout = 1800; // 30 minutes
    if (isset($_SESSION['last_activity'])) {
        $inactive = time() - $_SESSION['last_activity'];
        if ($inactive > $sessionTimeout) {
            error_log('ℹ️ Session expired due to inactivity');
            session_destroy();
            return false;
        }
    }
    
    // Check absolute session lifetime (24 hours)
    $maxLifetime = 86400; // 24 hours
    if (isset($_SESSION['created_at'])) {
        $lifetime = time() - $_SESSION['created_at'];
        if ($lifetime > $maxLifetime) {
            error_log('ℹ️ Session expired due to max lifetime');
            session_destroy();
            return false;
        }
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    
    return true;
}

/**
 * Require valid session or terminate request
 */
function require_valid_session(): void {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => false,
            'msg' => 'Sesión expirada o inválida'  
        ]);
        exit;
    }
    
    if (!validate_session_security()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => false,
            'msg' => 'Sesión inválida por razones de seguridad'
        ]);
        exit;
    }
}

/**
 * Regenerate session ID (call after login or privilege elevation)
 */
function secure_session_regenerate(): void {
    session_regenerate_id(true);
    $_SESSION['fingerprint'] = generate_session_fingerprint();
    $_SESSION['created_at'] = time();
    $_SESSION['last_activity'] = time();
}


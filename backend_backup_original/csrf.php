<?php
/**
 * CSRF Protection Library
 * Protects against Cross-Site Request Forgery attacks
 */

/**
 * Generate a CSRF token for the current session
 * @return string The CSRF token
 */
function generate_csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a CSRF token
 * @param string $token The token to verify
 * @return bool True if valid, false otherwise
 */
function verify_csrf_token(string $token): bool
{
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }

    // Check token age (max 1 hour)
    if (isset($_SESSION['csrf_token_time'])) {
        $tokenAge = time() - $_SESSION['csrf_token_time'];
        if ($tokenAge > 3600) {
            unset($_SESSION['csrf_token']);
            unset($_SESSION['csrf_token_time']);
            return false;
        }
    }

    // Use timing-safe comparison
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token from request headers or POST data
 * @return string The CSRF token from request
 */
function get_csrf_token_from_request(): string
{
    // Check headers first (for AJAX)
    $headers = function_exists('getallheaders') ? getallheaders() : [];

    // Search case-insensitively for X-CSRF-Token
    foreach ($headers as $name => $value) {
        if (strcasecmp($name, 'X-CSRF-Token') === 0) {
            return $value;
        }
    }

    // Fallback to $_SERVER (standard PHP mapping)
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    // Fallback to POST data (for forms)
    if (empty($token)) {
        $token = $_POST['csrf_token'] ?? '';
    }

    return $token;
}

/**
 * Validate CSRF token from current request
 * Sends 403 response and exits if invalid
 */
function require_csrf_token(): void
{
    $token = get_csrf_token_from_request();

    if (!verify_csrf_token($token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => false,
            'msg' => 'Token de seguridad inválido o expirado'
        ]);
        exit;
    }
}

/**
 * Regenerate CSRF token (call after sensitive operations)
 */
function regenerate_csrf_token(): string
{
    unset($_SESSION['csrf_token']);
    unset($_SESSION['csrf_token_time']);
    return generate_csrf_token();
}


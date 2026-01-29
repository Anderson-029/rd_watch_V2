<?php
/**
 * RD Watch - Sistema de Gestión de Relojería
 * Configuración de Cabeceras de Seguridad y CORS
 */
require_once __DIR__ . '/Logger.php';
header_remove('X-Powered-By');
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['csp_nonce'])) {
    $_SESSION['csp_nonce'] = base64_encode(random_bytes(16));
}
$nonce = $_SESSION['csp_nonce'];
header(
    "Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' 'nonce-$nonce' https://cdnjs.cloudflare.com; " .
    "style-src 'self' 'nonce-$nonce' https://fonts.googleapis.com https://cdnjs.cloudflare.com; " .
    "img-src 'self' data: https://via.placeholder.com; " .
    "connect-src 'self' http://localhost:8001 http://127.0.0.1:8001; " .
    "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; " .
    "object-src 'none'; " .
    "frame-ancestors 'none'; " .
    "base-uri 'self'; " .
    "form-action 'self';"
);
require_once __DIR__ . '/load_env.php';
$allowed_origins = ['http://localhost', 'http://localhost:80', 'http://127.0.0.1', 'http://localhost:8000', 'http://localhost:8002'];
if (isset($_ENV['CORS_ALLOWED_ORIGINS'])) {
    $env_origins = explode(',', $_ENV['CORS_ALLOWED_ORIGINS']);
    foreach ($env_origins as $origin_url) {
        $origin_url = trim($origin_url);
        if (!empty($origin_url) && !in_array($origin_url, $allowed_origins)) {
            $allowed_origins[] = $origin_url;
        }
    }
}
$request_origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($request_origin && in_array($request_origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $request_origin");
} else {
    if ($request_origin) {
        Logger::security("CORS Bloqueado: Intento desde origen no permitido: $request_origin");
    }
    header("Access-Control-Allow-Origin: " . ($allowed_origins[0] ?? 'http://localhost'));
}
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

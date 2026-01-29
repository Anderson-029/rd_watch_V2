<?php
/**
 * RD Watch - Sistema de Gestión de Relojería
 * Configuración Global del Backend
 * 
 * Centraliza la conexión a la base de datos, configuraciones de sesión,
 * manejo de errores y carga de variables de entorno.
 */

// Incluir utilidades centrales
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/ErrorHandler.php';
set_exception_handler(['ErrorHandler', 'handleException']);

// =============================================================================
// 1. Configuraciones de Seguridad de PHP y Sesión
// =============================================================================

// Ocultar versión de PHP en cabeceras
ini_set('expose_php', 'Off');

// Configuración de sesiones seguras
ini_set('session.cookie_httponly', '1');  // Prevenir acceso por JavaScript (XSS)
ini_set('session.cookie_samesite', 'Lax');  // Mayor compatibilidad en desarrollo multi-puerto
ini_set('session.cookie_secure', '0');    // Cambiar a '1' si se usa HTTPS
ini_set('session.use_strict_mode', '1');  // Prevenir Session Fixation
ini_set('session.use_only_cookies', '1'); // Rechazar IDs de sesión en la URL
ini_set('session.gc_maxlifetime', '3600'); // Expira tras 1 hora de inactividad
ini_set('session.entropy_length', '32');  // Fortalecer aleatoriedad de IDs

// Límites de recursos y manejo de errores
ini_set('upload_max_filesize', '5M');
ini_set('post_max_size', '6M');
ini_set('max_execution_time', '30');
ini_set('display_errors', '0');           // No mostrar errores al usuario final
ini_set('log_errors', '1');              // Registrar errores en log interno
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// =============================================================================
// 2. Carga de Variables de Entorno y Conexión a Base de Datos
// =============================================================================

// Cargar variables desde el archivo .env
require_once __DIR__ . '/load_env.php';

// Validar que las variables críticas existan
$required_env = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'];
foreach ($required_env as $var) {
    if (!isset($_ENV[$var])) {
        error_log("CRITICAL: La variable de entorno $var no está definida.");
        http_response_code(500);
        die(json_encode([
            "ok" => false,
            "msg" => "Error de configuración interna del servidor"
        ]));
    }
}

// Intentar conexión a PostgreSQL usando PDO
try {
    $dsn = "pgsql:host={$_ENV['DB_HOST']};port={$_ENV['DB_PORT']};dbname={$_ENV['DB_NAME']};";

    $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false, // Usar prepared statements nativos
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_PERSISTENT => false  // Conexiones no persistentes por seguridad
    ]);
} catch (PDOException $e) {
    error_log("CRITICAL: Error de conexión a la BD: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "msg" => "No se pudo establecer conexión con la base de datos"
    ]);
    exit;
}
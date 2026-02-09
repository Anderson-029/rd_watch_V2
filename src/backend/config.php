<?php
/**
 * 🚀 NÚCLEO DE CONFIGURACIÓN GLOBAL (BOOTSTRAP & ENGINE)
 * ---------------------------------------------------------
 * Propósito: Este archivo es la "columna vertebral" del backend. Actúa como el 
 * primer punto de ejecución para todas las APIs de RD-Watch. Su función es 
 * estandarizar el entorno, asegurar las comunicaciones y proveer el acceso a datos.
 * 
 * Flujo de Inicialización:
 * 1. Monitoreo de Errores: Configura el reporte visual de fallas (Entorno Dev).
 * 2. Gatekeeper CORS: Regula quién puede hablar con este servidor.
 * 3. Gestor de Estado: Inicializa el motor de sesiones nativo de PHP.
 * 4. Capa de Datos (PDO): Inyecta la conexión a PostgreSQL de forma segura.
 */

/* 
 * 1. GESTIÓN DE ERRORES (ERROR HANDLING)
 * Se activa el reporte de errores para agilizar el ciclo de desarrollo y soporte.
 * En PRODUCCIÓN, 'display_errors' debería establecerse en 0 para seguridad.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

/**
 * ==========================================
 * 🛡️ 2. POLÍTICA DE SEGURIDAD CORS
 * ==========================================
 * El Cross-Origin Resource Sharing (CORS) es esencial para que el frontend 
 * (Vite/React/Vanilla JS) pueda enviar peticiones AJAX/Fetch a este backend 
 * si están en diferentes puertos o dominios.
 */
// Detectamos el origen de la petición para permitir navegación con credenciales
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header("Access-Control-Allow-Credentials: true"); // Vital para persistencia de login
} else {
    header("Access-Control-Allow-Origin: *");
}

// Definición de verbos HTTP permitidos y cabeceras de seguridad
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");

// Manejo de peticiones Pre-flight (Las que el navegador envía antes del POST real)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * ==========================================
 * 🎫 🎫 3. MOTOR DE SESIÓN GLOBAL
 * ==========================================
 * Permite que los archivos de la API reconozcan al usuario mediante $_SESSION.
 * config.php garantiza que session_start() se ejecute ANTES de cualquier salida al buffer.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * ==========================================
 * 🐘 4. CONEXIÓN A BASE DE DATOS (PDO postgreSQL)
 * ==========================================
 * Utilizamos PDO (PHP Data Objects) por su robustez y soporte de sentencias preparadas.
 */
// 4.1. Carga Segura de Variables de Entorno (.env)
$envPath = __DIR__ . '/.env';
if (!file_exists($envPath)) {
    http_response_code(500);
    die("⚠️ Error Crítico: Archivo .env no detectado. El backend no puede arrancar sin credenciales.");
}

$env = parse_ini_file($envPath);

try {
    // Definición del Data Source Name (DSN) para Postgres
    $dsn = "pgsql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_NAME']}";

    /**
     * 4.2. Inyección de la variable Global $pdo
     * Configuración de Seguridad y Rendimiento:
     * - ERRMODE_EXCEPTION: Transforma errores SQL en excepciones PHP capturables.
     * - FETCH_ASSOC: Optimiza la memoria devolviendo arreglos clave-valor.
     * - EMULATE_PREPARES => false: OBLIGA al servidor a usar sentencias preparadas reales, 
     *   siendo la defensa #1 contra Inyección SQL (SQLi).
     */
    $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

} catch (PDOException $e) {
    // Ofuscación de errores técnicos en el cliente por seguridad preventiva
    http_response_code(500);
    error_log("Falla en HANDSHAKE de Base de Datos: " . $e->getMessage());
    die("Error de Infraestructura: El motor de datos no respondió. Contacte al administrador.");
}

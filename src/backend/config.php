<?php
/**
 * ARCHIVO DE CONFIGURACIÓN GLOBAL - RD WATCH
 * ---------------------------------------------------------
 * Este archivo es el punto de entrada para todas las APIs.
 * Se encarga de la conexión a la base de datos, seguridad CORS
 * y manejo de sesiones.
 */

// Habilitar reporte de errores para depuración (Útil en desarrollo)
ini_set('display_errors', 1);
error_reporting(E_ALL);

/**
 * 1. CONFIGURACIÓN DE CORS (Cross-Origin Resource Sharing)
 * Permite que el navegador acepte respuestas desde este servidor.
 */
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header("Access-Control-Allow-Credentials: true");
} else {
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");

// Si es una petición de tipo OPTIONS (pre-vuelo), terminamos aquí.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * 2. GESTIÓN DE SESIONES
 * Inicia la sesión si aún no ha sido iniciada.
 * Esto permite usar $_SESSION para identificar al usuario conectado.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * 3. CONEXIÓN A LA BASE DE DATOS
 * Carga las credenciales desde el archivo .env y crea el objeto $pdo.
 */
// parse_ini_file lee archivos tipo configuración (.env) y los convierte en un arreglo
$env = parse_ini_file(__DIR__ . '/.env');

try {
    // Definimos la cadena de conexión (DSN). Actualmente configurado para PostgreSQL.
    $dsn = "pgsql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_NAME']}";

    // Creamos la instancia de conexión usando PDO
    $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,            // Lanza errores como excepciones
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC        // Devuelve los datos como arreglos asociativos
    ]);
} catch (PDOException $e) {
    // Si la conexión falla, se detiene la ejecución y se muestra el error
    die("Error Database: " . $e->getMessage());
}

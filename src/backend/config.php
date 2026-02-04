<?php
// config.php - Ultra Simplificado + CORS + Sesión
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. CORS Total (Permitir peticiones desde cualquier origen)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Si es preflight (OPTIONS), matar la ejecución aquí con éxito
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 2. Iniciar Sesión Automáticamente
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Carga .env nativamente
$env = parse_ini_file(__DIR__ . '/.env');

try {
    $dsn = "pgsql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_NAME']}";
    $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}

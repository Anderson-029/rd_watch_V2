<?php
// config.php - Ultra Simplificado
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Carga .env nativamente (Más rápido y seguro)
$env = parse_ini_file(__DIR__ . '/.env');

try {
    $dsn = "pgsql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_NAME']}";
    $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Error Base de Datos: " . $e->getMessage());
}

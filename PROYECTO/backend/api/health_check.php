<?php
/**
 * RD Watch - Sistema de Gestión de Relojería
 * Endpoint de Verificación de Salud (Health Check)
 * 
 * Este archivo confirma que el servidor PHP está vivo y que
 * existe una conexión exitosa con la base de datos PostgreSQL.
 */

// Cabeceras básicas (Sin incluir security_headers para evitar colisión de CORS en tests simples)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // 1. Cargar configuración y conectar (Esto lanza excepción si falla)
    require_once('../config.php');

    // 2. Ejecutar una consulta simple para validar la BD
    $stmt = $pdo->query("SELECT version()");
    $db_version = $stmt->fetchColumn();

    // 3. Respuesta de éxito
    echo json_encode([
        "ok" => true,
        "status" => "ONLINE",
        "timestamp" => date('Y-m-d H:i:s'),
        "system" => [
            "php_version" => PHP_VERSION,
            "database" => "CONNECTED",
            "db_info" => $db_version
        ],
        "message" => "El ecosistema RD Watch está listo para operar."
    ]);

} catch (Throwable $e) {
    // Respuesta de error si algo falla
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "status" => "CRITICAL_ERROR",
        "error" => $e->getMessage(),
        "hint" => "Asegúrate de que PostgreSQL esté corriendo y el archivo .env sea correcto."
    ]);
}

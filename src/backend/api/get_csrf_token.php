<?php
/**
 * API: OBTENCIÓN DE TOKEN CSRF
 * ---------------------------------------------------------
 * El token CSRF protege contra ataques de falsificación de 
 * peticiones en sitios cruzados.
 * 
 * NOTA: Esta versión es un "dummy" (simulado) para asegurar
 * la compatibilidad con el frontend sin bloqueos de seguridad
 * durante el desarrollo.
 */

require_once '../config.php';
header('Content-Type: application/json');

// Simplemente devuelve un token fijo
echo json_encode([
    "ok" => true,
    "csrf_token" => "dummy_token_no_security"
]);

<?php
/**
 * API: OBTENCIÓN DE TOKEN CSRF
 * ---------------------------------------------------------
 * Propósito: Mitigar ataques de Cross-Site Request Forgery (CSRF).
 * El token CSRF garantiza que las peticiones de modificación de datos (POST, PUT, DELETE) 
 * provengan legítimamente de nuestra propia interfaz de usuario.
 * 
 * Estado de Implementación:
 * Actualmente opera en modo 'DUMMY' (Simulado). Devuelve un token estático para 
 * mantener la compatibilidad con el frontend sin introducir bloqueos durante 
 * el desarrollo de las funcionalidades de negocio.
 * 
 * RECOMENDACIÓN DE SEGURIDAD: 
 * En producción, este archivo debe generar un hash aleatorio criptográficamente 
 * seguro (ej: bin2hex(random_bytes(32))), almacenarlo en $_SESSION y validarlo 
 * en cada petición de escritura.
 */

require_once '../config.php';
header('Content-Type: application/json');

// Respuesta estática para compatibilidad de interfaz
echo json_encode([
    "ok" => true,
    "csrf_token" => "dummy_token_no_security_v1",
    "info" => "Token de desarrollo activo"
]);

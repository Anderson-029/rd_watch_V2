<?php
/**
 * RD Watch - Sistema de Gestión de Relojería
 * Endpoint para obtener el token CSRF del lado del cliente
 */

require_once('../security_headers.php');
require_once('../config.php');
require_once('../csrf.php');

header('Content-Type: application/json');


// El endpoint es público para permitir registro e inicio de sesión
// Generar y devolver el token CSRF vinculado a la sesión actual (iniciada en security_headers.php)
$token = generate_csrf_token();

ErrorHandler::sendSuccess('Token CSRF generado', [
    'csrf_token' => $token
]);

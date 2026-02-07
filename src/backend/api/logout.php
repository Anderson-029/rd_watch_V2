<?php
/**
 * API: CIERRE DE SESIÓN (LOGOUT)
 * ---------------------------------------------------------
 * Finaliza la sesión actual del usuario de forma segura.
 */

require_once '../config.php';
header('Content-Type: application/json');

// 1. Destruir toda la información de la sesión en el servidor
session_destroy();

// 2. Limpiar la cookie de sesión en el navegador para evitar reutilización
setcookie(session_name(), '', time() - 3600, '/');

echo json_encode(["ok" => true, "msg" => "Sesión cerrada correctamente"]);

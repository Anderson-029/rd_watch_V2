<?php
// backend/api/logout.php
require_once('../security_headers.php');
require_once('../config.php'); // Ya incluye ErrorHandler

header('Content-Type: application/json');

// Destruir sesión de forma segura
session_unset();
session_destroy();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

ErrorHandler::sendSuccess('Sesión cerrada correctamente');

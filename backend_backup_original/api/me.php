<?php
// backend/api/me.php
require_once('../config.php'); // Ya incluye ErrorHandler
require_once('../security_headers.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    ErrorHandler::stopError('No hay sesión activa', 401);
}

ErrorHandler::sendSuccess('Sesión activa', [
    'user' => [
        'id' => $_SESSION['user_id'],
        'nombre' => $_SESSION['user_name'] ?? 'Usuario',
        'correo' => $_SESSION['user_mail'] ?? '',
        'rol' => $_SESSION['user_rol'] ?? 'cliente'
    ]
]);

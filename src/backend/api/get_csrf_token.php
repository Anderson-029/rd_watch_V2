<?php
// api/get_csrf_token.php
// Versión "Naked": Devuelve un token fijo para complacer al Frontend
// pero no validamos nada en el backend real.

require_once '../config.php';
header('Content-Type: application/json');

echo json_encode([
    "ok" => true,
    "csrf_token" => "dummy_token_no_security"
]);

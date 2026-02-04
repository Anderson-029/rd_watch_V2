<?php
// api/me.php
require_once '../config.php';

header('Content-Type: application/json');

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    echo json_encode([
        "ok" => true,
        "user" => [
            "id" => $_SESSION['user_id'],
            "nombre" => $_SESSION['user_name'],
            "rol" => $_SESSION['user_role']
        ]
    ]);
} else {
    // No es error 401 para no llenar consola de errores, solo ok: false
    echo json_encode(["ok" => false, "user" => null]);
}

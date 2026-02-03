<?php
// api/logout.php
require_once '../config.php';

header('Content-Type: application/json');

session_destroy();
setcookie(session_name(), '', time() - 3600, '/'); // Borrar cookie del navegador

echo json_encode(["ok" => true, "msg" => "Sesión cerrada"]);

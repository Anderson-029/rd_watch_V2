<?php
// backend/api/signup.php

// Aplicar cabeceras de seguridad
require_once('../security_headers.php');
require_once('../rate_limiter.php');
require_once('../validator.php');
require_once('../encoder.php');
require_once('../csrf.php');

header('Content-Type: application/json');
include_once('../config.php');

// Rate limiting: 10 registros por cada 5 minutos por IP (Ajustado para desarrollo)
$clientIP = $_SERVER['REMOTE_ADDR'];
if (!check_rate_limit('signup', $clientIP, 10, 300)) {
    $remaining = get_rate_limit_remaining('signup', $clientIP, 300);
    rate_limit_fail_response($remaining > 0 ? $remaining : 300);
}

$data = json_decode(file_get_contents("php://input"), true);

// 2. Verificar token CSRF
require_csrf_token();

try {
    $nombre = Validator::sanitizeString($data['nombre'] ?? '', 100);
    $email = Validator::validateEmail($data['email'] ?? '');

    // Limpiar teléfono: quitar todo lo que no sea dígito
    $telefonoRaw = $data['telefono'] ?? '';
    $telefonoDigits = preg_replace('/[^0-9]/', '', $telefonoRaw);
    $telefono = !empty($telefonoDigits) ? (int) $telefonoDigits : null;

    // Validar contraseña
    $passwordRaw = Validator::validatePassword($data['password'] ?? '');
    // Hashear contraseña con Bcrypt (PHP nativo)
    $password = password_hash($passwordRaw, PASSWORD_DEFAULT);

    $direccion = Validator::sanitizeString($data['direccion'] ?? '', 255);

    // Validación de campos obligatorios
    if (empty($nombre) || empty($email) || empty($password)) {
        throw new InvalidArgumentException('Faltan campos obligatorios');
    }

    $stmt = $pdo->prepare("SELECT * FROM fun_registrar_usuario(:n,:e,:t,:p,:d)");
    $stmt->execute([
        ':n' => $nombre,
        ':e' => $email,
        ':t' => $telefono,
        ':p' => $password,
        ':d' => $direccion
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row['status'] === 'SUCCESS') {
        ErrorHandler::sendSuccess(Encoder::html($row['message']), [
            'id' => $row['id_usuario'] ?? null
        ]);
    } else {
        ErrorHandler::stopError(Encoder::html($row['message']), 400);
    }

} catch (InvalidArgumentException $e) {
    ErrorHandler::stopError($e->getMessage(), 400);
} catch (Throwable $e) {
    ErrorHandler::handleException($e);
}

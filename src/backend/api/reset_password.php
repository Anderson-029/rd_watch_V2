<?php
/**
 * API: RESTABLECIMIENTO DE CONTRASEÑA
 * ---------------------------------------------------------
 */

require_once '../config.php';
require_once '../utils/security_utils.php';
require_once '../utils/Validation.php';

header('Content-Type: application/json');

// 🛡️ SEGURIDAD: Validación CSRF obligatoria
validateCsrfToken(null, true);

$input = getJsonInput();
$token = Validation::sanitizeString($input['token'] ?? '');
$newPassword = $input['password'] ?? '';

if (empty($token) || empty($newPassword)) {
    echo json_encode(["ok" => false, "msg" => "Token o contraseña no proporcionados."]);
    exit;
}

if (strlen($newPassword) < 8 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
    echo json_encode(["ok" => false, "msg" => "La contraseña no cumple con los requisitos de seguridad."]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id_usuario FROM tab_Usuarios WHERE token_recuperacion = ? AND token_expiracion > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(["ok" => false, "msg" => "El enlace ha expirado o no es válido. Por favor solicita uno nuevo."]);
        exit;
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT);

    $updateStmt = $pdo->prepare("UPDATE tab_Usuarios SET contra = ?, token_recuperacion = NULL, token_expiracion = NULL WHERE id_usuario = ?");
    if ($updateStmt->execute([$hash, $user['id_usuario']])) {
        echo json_encode(["ok" => true, "msg" => "Tu contraseña ha sido actualizada exitosamente. Ya puedes iniciar sesión."]);
    }
    else {
        throw new Exception("Error al actualizar la contraseña en la base de datos.");
    }

}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "msg" => "Error técnico: " . $e->getMessage()]);
}
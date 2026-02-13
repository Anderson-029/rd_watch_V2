<?php
/**
 * API: RESTABLECIMIENTO DE CONTRASEÑA
 * ---------------------------------------------------------
 * Valida el token y permite al usuario establecer una nueva contraseña.
 */

require_once '../config.php';
require_once '../utils/Validation.php';
require_once '../utils/security_utils.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$token = Validation::sanitizeString($input['token'] ?? '');
$newPassword = $input['password'] ?? '';

if (empty($token) || empty($newPassword)) {
    echo json_encode(["ok" => false, "msg" => "Token o contraseña no proporcionados."]);
    exit;
}

// Validación de complejidad de contraseña (reutilizada de signup.php)
if (strlen($newPassword) < 8 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
    echo json_encode(["ok" => false, "msg" => "La contraseña no cumple con los requisitos de seguridad."]);
    exit;
}

try {
    // 1. Buscar usuario con ese token y que no haya expirado
    $stmt = $pdo->prepare("SELECT id_usuario FROM tab_Usuarios WHERE token_recuperacion = ? AND token_expiracion > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(["ok" => false, "msg" => "El enlace ha expirado o no es válido. Por favor solicita uno nuevo."]);
        exit;
    }

    // 2. Hashear nueva contraseña
    $hash = password_hash($newPassword, PASSWORD_BCRYPT);

    // 3. Actualizar contraseña y limpiar tokens
    $updateStmt = $pdo->prepare("UPDATE tab_Usuarios SET contra = ?, token_recuperacion = NULL, token_expiracion = NULL WHERE id_usuario = ?");
    if ($updateStmt->execute([$hash, $user['id_usuario']])) {
        echo json_encode(["ok" => true, "msg" => "Tu contraseña ha sido actualizada exitosamente. Ya puedes iniciar sesión."]);
    } else {
        throw new Exception("Error al actualizar la contraseña en la base de datos.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "msg" => "Error técnico: " . $e->getMessage()]);
}

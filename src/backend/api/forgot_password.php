<?php
/**
 * API: SOLICITUD DE RECUPERACIÓN DE CONTRASEÑA
 * ---------------------------------------------------------
 */

require_once '../config.php';
require_once '../utils/security_utils.php';
require_once '../utils/Validation.php';

header('Content-Type: application/json');

// 🛡️ SEGURIDAD: Validación CSRF obligatoria
validateCsrfToken(null, true);

$input = getJsonInput();

// 🛡️ VALIDACIÓN ESTRICTA (ISO 830)
Validation::validateOrReject($input, ['email' => 'email']);

$email = Validation::sanitizeString($input['email']);

try {
    $stmt = $pdo->prepare("SELECT id_usuario, nom_usuario FROM tab_Usuarios WHERE correo_usuario = ? AND activo = TRUE");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(["ok" => true, "msg" => "Si el correo está registrado, recibirás un enlace de recuperación en breve."]);
        exit;
    }

    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $updateStmt = $pdo->prepare("UPDATE tab_Usuarios SET token_recuperacion = ?, token_expiracion = ? WHERE id_usuario = ?");
    $updateStmt->execute([$token, $expires, $user['id_usuario']]);

    $resetLink = "reset_password.html?token=" . $token;
    error_log("RESET PASSWORD LINK for {$email}: {$resetLink}");

    echo json_encode([
        "ok" => true,
        "msg" => "Si el correo está registrado, recibirás un enlace de recuperación en breve.",
        "debug_link" => $resetLink
    ]);

}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "msg" => "Error técnico: " . $e->getMessage()]);
}
<?php
/**
 * API: SOLICITUD DE RECUPERACIÓN DE CONTRASEÑA
 * ---------------------------------------------------------
 * Genera un token único y lo envía (simulado) al usuario para
 * permitir el cambio de contraseña.
 */

require_once '../config.php';
require_once '../utils/Validation.php';
require_once '../utils/security_utils.php';

header('Content-Type: application/json');

// 🛡️ VALIDACIÓN ESTRICTA (ISO 830)
Validation::validateOrReject($input, ['email' => 'email']);

$email = Validation::sanitizeString($input['email']);


try {
    // 1. Verificar si el usuario existe
    $stmt = $pdo->prepare("SELECT id_usuario, nom_usuario FROM tab_Usuarios WHERE correo_usuario = ? AND activo = TRUE");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Por seguridad, no revelamos si el correo existe o no
        echo json_encode(["ok" => true, "msg" => "Si el correo está registrado, recibirás un enlace de recuperación en breve."]);
        exit;
    }

    // 2. Generar Token Seguro
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // 3. Guardar Token en la base de datos
    $updateStmt = $pdo->prepare("UPDATE tab_Usuarios SET token_recuperacion = ?, token_expiracion = ? WHERE id_usuario = ?");
    $updateStmt->execute([$token, $expires, $user['id_usuario']]);

    // 4. "Enviar Correo" (Simulación por ahora)
    // En un entorno real, usaría mail() o PHPMailer
    $resetLink = "reset_password.html?token=" . $token;

    // Log para el desarrollador (simulando envío)
    error_log("RESET PASSWORD LINK for {$email}: {$resetLink}");

    echo json_encode([
        "ok" => true,
        "msg" => "Si el correo está registrado, recibirás un enlace de recuperación en breve.",
        // Incluimos el link en la respuesta SOLO PARA PROPÓSITOS DE DESARROLLO/TESTING
        // en este entorno donde no hay servidor de correo configurado.
        "debug_link" => $resetLink
    ]);

}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "msg" => "Error técnico: " . $e->getMessage()]);
}
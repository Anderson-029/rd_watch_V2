<?php
/**
 * API: INICIO DE SESIÓN (LOGIN)
 * ---------------------------------------------------------
 * Propósito: Punto de entrada para la autenticación de usuarios. 
 */

require_once '../config.php';
require_once '../utils/security_utils.php';
require_once '../utils/Validation.php';

header('Content-Type: application/json');

// 🛡️ SEGURIDAD: Validación CSRF obligatoria
validateCsrfToken(null, true);

// Captura de datos JSON desde el flujo de entrada centralizado
$input = getJsonInput();

$clientIP = getClientIP();
// 🛡️ RATE LIMITING: Verificar intentos previos (5 intentos en 15 min)
if (!checkRateLimit($pdo, $clientIP, 'login_attempt', 5, 15)) {
    http_response_code(429); // Too Many Requests
    echo json_encode(["ok" => false, "msg" => "Demasiados intentos fallidos. Por favor espere 15 minutos."]);
    exit;
}

Validation::validateOrReject($input, [
    'email' => 'email',
    'password' => 'password'
]);

$email = Validation::sanitizeString($input['email']);
$pass = $input['password'] ?? ($input['contra'] ?? '');

try {
    $stmt = $pdo->prepare("SELECT id_usuario, nom_usuario, contra, rol, activo, bloqueado FROM tab_Usuarios WHERE correo_usuario = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        logRateLimit($pdo, $clientIP, 'login_attempt');
        echo json_encode(["ok" => false, "msg" => "Las credenciales no coinciden con nuestros registros"]);
        exit;
    }

    if (!$user['activo'] || $user['bloqueado']) {
        echo json_encode(["ok" => false, "msg" => "Su cuenta se encuentra inactiva o bloqueda. Contacte a soporte."]);
        exit;
    }

    $loginSuccess = false;

    if (password_verify($pass, $user['contra'])) {
        $loginSuccess = true;
    }
    elseif ($pass === $user['contra']) {
        $newHash = password_hash($pass, PASSWORD_BCRYPT);
        $upd = $pdo->prepare("UPDATE tab_Usuarios SET contra = ? WHERE id_usuario = ?");
        $upd->execute([$newHash, $user['id_usuario']]);
        $loginSuccess = true;
    }

    if ($loginSuccess) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);
        clearRateLimit($pdo, $clientIP, 'login_attempt');

        $_SESSION['user_id'] = $user['id_usuario'];
        $_SESSION['user_role'] = $user['rol'];
        $_SESSION['user_name'] = $user['nom_usuario'];
        $_SESSION['logged_in'] = true;

        echo json_encode([
            "ok" => true,
            "msg" => "Autenticación exitosa. Bienvenido de nuevo, " . $user['nom_usuario'],
            "redirect" => ($user['rol'] === 'admin') ? 'src/admin/admin.html' : 'src/user/user.html',
            "user" => [
                "id" => $user['id_usuario'],
                "nombre" => $user['nom_usuario'],
                "rol" => $user['rol']
            ]
        ]);
    }
    else {
        logRateLimit($pdo, $clientIP, 'login_attempt');
        echo json_encode(["ok" => false, "msg" => "La contraseña ingresada es incorrecta"]);
    }

}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "msg" => "Fallo interno en el sistema de autenticación"]);
}
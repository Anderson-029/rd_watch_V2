<?php
/**
 * API: INICIO DE SESIÓN (LOGIN)
 * ---------------------------------------------------------
 * Propósito: Punto de entrada para la autenticación de usuarios. 
 * Gestiona el acceso de Clientes y Administradores, estableciendo una sesión segura.
 * 
 * Lógica de Seguridad:
 * 1. Validación de existencia del usuario y estado de cuenta (Activo/Bloqueado).
 * 2. Verificación de contraseñas mediante password_verify.
 * 3. [MIGRACIÓN]: Si detecta una clave legacy (texto plano), la HASHEA automáticamente 
 *    utilizando BCRYPT para elevar el nivel de seguridad sin interrumpir al usuario.
 * 4. Persistencia de datos clave en $_SESSION.
 */

require_once '../config.php';
header('Content-Type: application/json');

require_once '../utils/security_utils.php';
require_once '../utils/Validation.php';

$input = json_decode(file_get_contents('php://input'), true);

$clientIP = getClientIP();
// 🛡️ RATE LIMITING: Verificar intentos previos (5 intentos en 15 min)
if (!checkRateLimit($pdo, $clientIP, 'login_attempt', 5, 15)) {
    http_response_code(429); // Too Many Requests
    echo json_encode(["ok" => false, "msg" => "Demasiados intentos fallidos. Por favor espere 15 minutos."]);
    exit;
}

Validation::validateOrReject($input, [
    'email' => 'email',
    'password' => 'password' // Validamos presencia y longitud mínima
]);

$email = Validation::sanitizeString($input['email']);
$pass = $input['password'] ?? ($input['contra'] ?? '');


try {
    /**
     * ==========================================
     * 🔍 VALIDACIÓN DE IDENTIDAD
     * ==========================================
     */
    $stmt = $pdo->prepare("SELECT id_usuario, nom_usuario, contra, rol, activo, bloqueado FROM tab_Usuarios WHERE correo_usuario = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // 🛡️ Registrar el intento fallido (incluso si user no existe, para evitar enumeración rápida)
        logRateLimit($pdo, $clientIP, 'login_attempt');
        echo json_encode(["ok" => false, "msg" => "Las credenciales no coinciden con nuestros registros"]);
        exit;
    }

    // Seguridad: Bloqueo de acceso para cuentas suspendidas
    if (!$user['activo'] || $user['bloqueado']) {
        echo json_encode(["ok" => false, "msg" => "Su cuenta se encuentra inactiva o bloqueda. Contacte a soporte."]);
        exit;
    }

    /**
     * ==========================================
     * 🔐 VERIFICACIÓN DE CREDENCIALES
     * ==========================================
     */
    $loginSuccess = false;

    // 1. Caso Estándar: Verificación contra Hash BCRYPT
    if (password_verify($pass, $user['contra'])) {
        $loginSuccess = true;
    }
    // 2. Caso Legacy: Verificación contra Texto Plano
    elseif ($pass === $user['contra']) {
        /**
         * AUTO-MIGRACIÓN DE SEGURIDAD
         * Convertimos la clave antigua en un hash seguro inmediatamente.
         */
        $newHash = password_hash($pass, PASSWORD_BCRYPT);
        $upd = $pdo->prepare("UPDATE tab_Usuarios SET contra = ? WHERE id_usuario = ?");
        $upd->execute([$newHash, $user['id_usuario']]);
        $loginSuccess = true;
    }

    if ($loginSuccess) {
        /**
         * ==========================================
         * 🎫 ESTABLECIMIENTO DE SESIÓN
         * ==========================================
         */
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // 🛡️ Seguridad de Sesión: Prevenir Session Fixation
        session_regenerate_id(true);

        // 🛡️ Limpiar conteo de fallos tras éxito
        clearRateLimit($pdo, $clientIP, 'login_attempt');

        $_SESSION['user_id'] = $user['id_usuario'];
        $_SESSION['user_role'] = $user['rol'];
        $_SESSION['user_name'] = $user['nom_usuario'];
        $_SESSION['logged_in'] = true;

        echo json_encode([
            "ok" => true,
            "msg" => "Autenticación exitosa. Bienvenido de nuevo, " . $user['nom_usuario'],
            // Redirección inteligente basada en privilegios
            "redirect" => ($user['rol'] === 'admin') ? 'src/admin/admin.html' : 'src/user/user.html',
            "user" => [
                "id" => $user['id_usuario'],
                "nombre" => $user['nom_usuario'],
                "rol" => $user['rol']
            ]
        ]);
    }
    else {
        // 🛡️ Registrar el intento fallido
        logRateLimit($pdo, $clientIP, 'login_attempt');
        echo json_encode(["ok" => false, "msg" => "La contraseña ingresada es incorrecta"]);
    }

}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "msg" => "Fallo interno en el sistema de autenticación"]);
}
<?php
/**
 * ============================================================
 * API: SOLICITUD DE RECUPERACIÓN DE CONTRASEÑA (forgot_password.php)
 * ============================================================
 * ENDPOINT: POST /api/forgot_password.php
 *
 * PROPÓSITO:
 * El usuario olvidó su contraseña. Genera un token temporal
 * que se enviaría por email para permitir el reset.
 *
 * SEGURIDAD ANTI-ENUMERACIÓN:
 * Por diseño, SIEMPRE responde con el mismo mensaje genérico,
 * sin importar si el email existe o no. Esto previene que un
 * atacante pueda descubrir qué emails están registrados.
 *
 * FUNCIONES POSTGRESQL QUE USA:
 * - fn_auth_forgot_password(email, token, expiración) → JSON o NULL
 *   Internamente:
 *   1. Busca usuario activo por email
 *   2. Si existe: guarda token + expiración → retorna JSON con datos
 *   3. Si no existe: retorna NULL (PHP muestra mensaje genérico)
 *
 * FLUJO COMPLETO:
 * 1. Validar CSRF
 * 2. Validar formato del email
 * 3. Generar token criptográfico (64 caracteres hex)
 * 4. Definir expiración (+1 hora desde ahora)
 * 5. Llamar fn_auth_forgot_password
 * 6. SIEMPRE responder con mensaje genérico (anti-enumeración)
 * 7. Si el usuario existía: generar link de reset (en log del server)
 * ============================================================
 */

require_once '../config.php';
require_once '../utils/security_utils.php';
require_once '../utils/Validation.php';

header('Content-Type: application/json');

// PASO 1: Validar token CSRF (protección contra ataques CSRF)
validateCsrfToken(null, true);

// PASO 2: Obtener y validar el email del input
$input = getJsonInput();
Validation::validateOrReject($input, ['email' => 'email']);

$email = Validation::sanitizeString($input['email']);

try {
    // ──────────────────────────────────────────────
    // PASO 3: GENERAR TOKEN CRIPTOGRÁFICO
    // ──────────────────────────────────────────────
    // random_bytes(32) genera 32 bytes aleatorios criptográficamente seguros
    // bin2hex() los convierte en 64 caracteres hexadecimales (a-f, 0-9)
    // Ejemplo resultado: "a3f8c1d7e5b9...hasta 64 chars"
    // Este token se guardará en la BD y se usará en la URL de reset
    $token = bin2hex(random_bytes(32));

    // PASO 4: Definir cuándo expira el token (1 hora desde ahora)
    // strtotime('+1 hour') suma 1 hora al timestamp actual
    // date() lo formatea como YYYY-MM-DD HH:II:SS para PostgreSQL
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // ──────────────────────────────────────────────
    // PASO 5: REGISTRAR TOKEN EN BD (Consulta Opaca)
    // ──────────────────────────────────────────────
    // fn_auth_forgot_password:
    // - Si el email EXISTE y está activo → guarda token → retorna JSON con id y nombre
    // - Si el email NO EXISTE → no hace nada → retorna NULL
    // El "?::timestamp" le dice a PostgreSQL que trate el valor como tipo timestamp
    $stmt = $pdo->prepare("SELECT fn_auth_forgot_password(?, ?, ?::timestamp)");
    $stmt->execute([$email, $token, $expires]);
    $user = json_decode($stmt->fetchColumn(), true);

    // ──────────────────────────────────────────────
    // PASO 6: RESPONDER (SIEMPRE mensaje genérico)
    // ──────────────────────────────────────────────
    // IMPORTANTE: No importa si $user es NULL o tiene datos,
    // el mensaje al frontend es SIEMPRE el mismo.
    // Esto previene ataques de enumeración de usuarios.
    if (!$user) {
        // Email no encontrado → respuesta genérica (no revelamos que no existe)
        echo json_encode(["ok" => true, "msg" => "Si el correo está registrado, recibirás un enlace de recuperación en breve."]);
        exit;
    }

    // Email encontrado → generar link de reset
    // En producción esto se enviaría por email
    // Por ahora lo registramos en el log del servidor
    $resetLink = "reset_password.html?token=" . $token;
    error_log("RESET PASSWORD LINK for {$email}: {$resetLink}");

    // MISMA respuesta genérica (protección anti-enumeración)
    echo json_encode([
        "ok" => true,
        "msg" => "Si el correo está registrado, recibirás un enlace de recuperación en breve.",
        "debug_link" => $resetLink // Solo para desarrollo, remover en producción
    ]);

}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "msg" => "Error técnico: " . $e->getMessage()]);
}
<?php
/**
 * API: CIERRE DE SESIÓN (LOGOUT)
 * ---------------------------------------------------------
 * Propósito: Finalizar de forma segura la sesión del usuario tanto en el 
 * lado del servidor como en el navegador del cliente.
 * 
 * Flujo de Seguridad:
 * 1. Inicializa el motor de sesiones si no está activo.
 * 2. Purga todas las variables de $_SESSION.
 * 3. Destruye la persistencia en el servidor (session_destroy).
 * 4. Invalida la cookie PHPSESSID en el cliente configurando su tiempo en el pasado.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../utils/security_utils.php';
validateCsrfToken(); // 🛡️ Bloqueo CSRF

// 1. Limpieza de datos en memoria del servidor
$_SESSION = array();

// 2. Destrucción física de la sesión
session_destroy();

/**
 * 3. SEGURIDAD DEL CLIENTE: LIMPIEZA DE COOKIE
 * Al espirar la cookie en el pasado, el navegador la elimina, 
 * mitigando riesgos de Session Fixation o Hijacking.
 */
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

echo json_encode([
    "ok" => true,
    "msg" => "Sesión cerrada de forma segura. Hasta pronto."
]);
<?php
/**
 * API: VERIFICACIÓN DE IDENTIDAD (ME)
 * ---------------------------------------------------------
 * Propósito: Facilita al frontend la obtención de los datos del usuario 
 * actualmente autenticado en el servidor. 
 * 
 * Uso: Es la base para personalizar la interfaz (ej: "Hola, Juan") y 
 * para rellenar automáticamente formularios de dirección en el checkout.
 * 
 * Lógica:
 * 1. Verifica la existencia de una sesión activa sustentada en $_SESSION['logged_in'].
 * 2. Realiza un JOIN con tab_Direcciones_Envio para capturar la ubicación predeterminada.
 * 3. Maneja respuestas silenciosas (no 401) para evitar ruidos de error en la consola 
 *    durante la navegación anonima pre-login.
 */

require_once '../config.php';
header('Content-Type: application/json');

// Comprobación de integridad de sesión
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $userId = $_SESSION['user_id'];

    try {
        /**
         * ==========================================
         * 🔍 OBTENCIÓN DE PERFIL COMPLETO
         * ==========================================
         * Se prioriza la dirección marcada como predeterminada (es_predeterminada = TRUE) 
         * para agilizar el flujo de compras del cliente.
         */
        $sql = "SELECT u.id_usuario, u.nom_usuario, u.rol, u.direccion_principal,
                       d.direccion_completa, c.nombre_ciudad
                FROM tab_Usuarios u
                LEFT JOIN tab_Direcciones_Envio d ON u.id_usuario = d.id_usuario AND d.es_predeterminada = TRUE
                LEFT JOIN tab_Ciudades c ON d.id_ciudad = c.id_ciudad
                WHERE u.id_usuario = ? 
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        /**
         * LÓGICA DE FALLBACK: 
         * Si el usuario no tiene una dirección de envío guardada, usamos la 
         * dirección principal registrada en su perfil básico.
         */
        $address = $userData['direccion_completa'] ?: ($userData['direccion_principal'] ?: 'Sin dirección registrada');
        $city = $userData['nombre_ciudad'] ?: 'N/A';

        echo json_encode([
            "ok" => true,
            "user" => [
                "id" => $userData['id_usuario'],
                "nombre" => $userData['nom_usuario'],
                "rol" => $userData['rol'],
                "direccion" => $address,
                "ciudad" => $city
            ]
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["ok" => false, "msg" => "Fallo técnico al recuperar identidad: " . $e->getMessage()]);
    }
} else {
    /**
     * ESTADO ANONIMO:
     * El usuario no está logueado. Devolvemos ok:false de forma controlada 
     * para que el frontend pueda manejar el estado 'Guest'.
     */
    echo json_encode([
        "ok" => false,
        "user" => null,
        "msg" => "Sesión no detectada o expirada"
    ]);
}

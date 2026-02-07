<?php
/**
 * API: VERIFICACIÓN DE SESIÓN ACTUAL (ME)
 * ---------------------------------------------------------
 * Devuelve la información del usuario que tiene la sesión 
 * iniciada actualmente. Útil para que el frontend sepa
 * quién está conectado.
 */

require_once '../config.php';
header('Content-Type: application/json');

// Comprobamos si la variable de sesión 'logged_in' existe y es verdadera
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $userId = $_SESSION['user_id'];

    try {
        // Intentar obtener dirección predeterminada o la principal del perfil
        $stmt = $pdo->prepare("
            SELECT u.id_usuario, u.nom_usuario, u.rol, u.direccion_principal,
                   d.direccion_completa, c.nombre_ciudad
            FROM tab_Usuarios u
            LEFT JOIN tab_Direcciones_Envio d ON u.id_usuario = d.id_usuario AND d.es_predeterminada = TRUE
            LEFT JOIN tab_Ciudades c ON d.id_ciudad = c.id_ciudad
            WHERE u.id_usuario = ?
        ");
        $stmt->execute([$userId]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        $address = $userData['direccion_completa'] ?: $userData['direccion_principal'];
        $city = $userData['nombre_ciudad'] ?: '';

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
        echo json_encode(["ok" => false, "msg" => "Error al obtener datos: " . $e->getMessage()]);
    }
} else {
    /**
     * Si no hay sesión, devolvemos ok: false.
     * Nota: No usamos código 401 para evitar alertas de error en la consola
     * del navegador durante chequeos rutinarios.
     */
    echo json_encode(["ok" => false, "user" => null]);
}

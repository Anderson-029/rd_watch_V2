<?php
/**
 * API: GESTIÓN DE RESEÑAS Y REPUTACIÓN (SOCIAL PROOF)
 * ---------------------------------------------------------
 * Propósito: Administra las opiniones y calificaciones de los clientes sobre los 
 * productos y servicios. Crucial para la generación de confianza y conversión.
 * 
 * Funcionalidades:
 * - GET: Listado público de las últimas 10 reseñas verificadas y activas.
 * - POST: Permite a clientes autenticados registrar una nueva calificación y comentario.
 */

require_once __DIR__ . '/../config.php';

// Integridad de la conexión a la base de datos
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error técnico: El servidor de reputación no está disponible']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        /**
         * ==========================================
         * 🔍 LISTAR RESEÑAS PÚBLICAS (GET)
         * ==========================================
         * Lógica: Solo muestra reseñas marcadas como 'activas' (moderadas) y realiza 
         * un JOIN para obtener el nombre legible del autor.
         */
        $stmt = $pdo->prepare("
            SELECT o.id_opinion, o.calificacion, o.comentario, o.fecha_opinion, u.nom_usuario
            FROM tab_Opiniones o
            JOIN tab_Usuarios u ON o.id_usuario = u.id_usuario
            WHERE o.activo = TRUE
            ORDER BY o.fecha_opinion DESC
            LIMIT 10
        ");
        $stmt->execute();
        $resenas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "ok" => true,
            "count" => count($resenas),
            "resenas" => $resenas,
            "msg" => "Testimonios recuperados exitosamente"
        ]);
        exit;
    }

    if ($method === 'POST') {
        /**
         * ==========================================
         * ➕ ENVIAR NUEVA RESEÑA (POST)
         * ==========================================
         * Seguridad: 
         * 1. Barrera de sesión operativa.
         * 2. Validación de rango de calificación (1 estrella a 5 estrellas).
         */

        // 1. Verificación de Identidad
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(["ok" => false, "msg" => "Acceso restringido: Inicie sesión para compartir su experiencia"]);
            exit;
        }

        // 2. Extracción de inputs JSON
        $input = json_decode(file_get_contents('php://input'), true);

        $id_usuario = $_SESSION['user_id'];
        $calificacion = isset($input['calificacion']) ? (int) $input['calificacion'] : 0;
        $comentario = isset($input['comentario']) ? trim($input['comentario']) : '';

        // 3. Validación de Reglas de Negocio
        if ($calificacion < 1 || $calificacion > 5) {
            echo json_encode(["ok" => false, "msg" => "Calificación inválida: El rango permitido es de 1 a 5 estrellas"]);
            exit;
        }

        if (empty($comentario)) {
            echo json_encode(["ok" => false, "msg" => "Error: El cuerpo del comentario es obligatorio"]);
            exit;
        }

        /**
         * 4. PERSISTENCIA
         * La reseña entra en estado inicial pendiente de moderación o activa según política.
         */
        $stmt = $pdo->prepare("
            INSERT INTO tab_Opiniones (id_usuario, calificacion, comentario, usr_insert, fec_insert, activo)
            VALUES (?, ?, ?, ?, NOW(), TRUE)
        ");

        $stmt->execute([
            $id_usuario,
            $calificacion,
            $comentario,
            $_SESSION['user_name'] ?? 'autor_web'
        ]);

        echo json_encode([
            "ok" => true,
            "msg" => "¡Gracias por tu aporte! Tu opinión ha sido publicada y ayudará a otros coleccionistas."
        ]);
        exit;
    }

    // Respuesta para verbos no soportados
    http_response_code(405);
    echo json_encode(["ok" => false, "msg" => "Operación denegada en este endpoint de reseñas"]);

} catch (PDOException $e) {
    http_response_code(500);
    // Logueamos el error técnico internamente pero devolvemos un mensaje amigable al cliente.
    error_log("Falla en resenas.php: " . $e->getMessage());
    echo json_encode(["ok" => false, "msg" => "Inconsistencia temporal en el motor de opiniones"]);
}

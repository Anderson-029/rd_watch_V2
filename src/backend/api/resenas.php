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
require_once __DIR__ . '/../utils/security_utils.php';
require_once __DIR__ . '/../utils/Validation.php';
header('Content-Type: application/json');

// Log para debugging
error_log("=== RESENAS.PHP INICIADO ===");
error_log("Método: " . $_SERVER['REQUEST_METHOD']);
error_log("Sesión activa: " . (session_status() === PHP_SESSION_ACTIVE ? 'SI' : 'NO'));
error_log("Session ID: " . session_id());

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
         * Lógica: Muestra todas las reseñas ordenadas por fecha reciente.
         */
        $stmt = $pdo->prepare("
            SELECT o.id_opinion, o.calificacion, o.comentario, o.fecha_opinion, u.nom_usuario
            FROM tab_Opiniones o
            JOIN tab_Usuarios u ON o.id_usuario = u.id_usuario
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

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(["ok" => false, "msg" => "Acceso restringido: Inicie sesión para compartir su experiencia"]);
            exit;
        }

        $id_usuario = $_SESSION['user_id'];
        validateCsrfToken(null, true);

        $input = getJsonInput();

        // 🛡️ VALIDACIÓN ESTRICTA (ISO 830)
        Validation::validateOrReject($input, [
            'calificacion' => 'numeric',
            'comentario' => 'address' // Usamos address para permitir texto con signos básicos
        ]);

        $calificacion = (int)$input['calificacion'];
        $comentario = Validation::sanitizeString($input['comentario']);

        // 3. Validación de Reglas de Negocio (Rango)
        if ($calificacion < 1 || $calificacion > 5) {
            http_response_code(400);
            echo json_encode(["ok" => false, "msg" => "Calificación inválida: El rango permitido es de 1 a 5 estrellas"]);
            exit;
        }

        // 🛡️ CONTROL DE REDUNDANCIA: Evitar que el mismo usuario publique el mismo comentario dos veces
        $stmtRedundancy = $pdo->prepare("SELECT id_opinion FROM tab_Opiniones WHERE id_usuario = ? AND comentario = ?");
        $stmtRedundancy->execute([$id_usuario, $comentario]);
        if ($stmtRedundancy->fetch()) {
            echo json_encode(['ok' => false, 'msg' => 'Inconsistencia: Ya has publicado este comentario anteriormente.']);
            exit;
        }


        /**
         * 4. PERSISTENCIA
         * La reseña se guarda directamente en la base de datos.
         * Nota: id_producto es NULL porque estas son reseñas generales del servicio, no de productos específicos.
         * Como id_opinion es BIGINT NOT NULL (no SERIAL), debemos generar el ID manualmente.
         */
        error_log("💾 Insertando en base de datos...");
        error_log("Datos a insertar - usuario: $id_usuario, calificación: $calificacion, comentario: " . substr($comentario, 0, 30));

        // Generar el próximo ID para id_opinion
        $stmtId = $pdo->query("SELECT COALESCE(MAX(id_opinion), 0) + 1 AS next_id FROM tab_Opiniones");
        $nextId = $stmtId->fetch()['next_id'];
        error_log("📌 Próximo ID a usar: " . $nextId);

        $stmt = $pdo->prepare("
            INSERT INTO tab_Opiniones (id_opinion, id_usuario, id_producto, calificacion, comentario, fecha_opinion)
            VALUES (?, ?, NULL, ?, ?, CURRENT_TIMESTAMP)
        ");

        $result = $stmt->execute([
            $nextId,
            $id_usuario,
            $calificacion,
            $comentario
        ]);

        if ($result) {
            error_log("✅ Reseña insertada exitosamente. ID: " . $nextId);

            echo json_encode([
                "ok" => true,
                "msg" => "¡Gracias por tu aporte! Tu opinión ha sido publicada y ayudará a otros coleccionistas.",
                "id_opinion" => $nextId
            ]);
        }
        else {
            error_log("❌ Error al insertar - execute() retornó false");
            http_response_code(500);
            echo json_encode([
                "ok" => false,
                "msg" => "Error al guardar la reseña",
                "debug" => "execute() retornó false"
            ]);
        }
        exit;
    }

    // Respuesta para verbos no soportados
    http_response_code(405);
    echo json_encode(["ok" => false, "msg" => "Operación denegada en este endpoint de reseñas"]);

}
catch (PDOException $e) {
    http_response_code(500);
    // Logueamos el error técnico internamente
    error_log("❌ PDOException en resenas.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    echo json_encode([
        "ok" => false,
        "msg" => "Error al procesar la reseña. Por favor intenta de nuevo.",
        "debug" => $e->getMessage(),
        "code" => $e->getCode()
    ]);
}
catch (Exception $e) {
    http_response_code(500);
    error_log("❌ Exception en resenas.php: " . $e->getMessage());

    echo json_encode([
        "ok" => false,
        "msg" => "Error inesperado en el servidor",
        "debug" => $e->getMessage()
    ]);
}
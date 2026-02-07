<?php
/**
 * API DE RESEÑAS - RD WATCH
 * ---------------------------------------------------------
 * Este archivo maneja la visualización y envío de reseñas.
 * Soporta GET (listar reseñas públicas) y POST (enviar nueva reseña).
 */

require_once __DIR__ . '/../config.php';

// Obtener el método de la petición
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        /**
         * LISTAR RESEÑAS (GET)
         * Retorna las últimas reseñas activas junto con el nombre del usuario.
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
        $resenas = $stmt->fetchAll();

        echo json_encode([
            "ok" => true,
            "resenas" => $resenas
        ]);
        exit;
    }

    if ($method === 'POST') {
        /**
         * ENVIAR RESEÑA (POST)
         * Permite a un usuario autenticado enviar una calificación y comentario.
         */

        // 1. Verificar sesión
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(["ok" => false, "msg" => "Debes iniciar sesión para dejar una reseña"]);
            exit;
        }

        // 2. Obtener datos del cuerpo de la petición (JSON)
        $input = json_decode(file_get_contents('php://input'), true);

        $id_usuario = $_SESSION['user_id'];
        $calificacion = isset($input['calificacion']) ? (int) $input['calificacion'] : 0;
        $comentario = isset($input['comentario']) ? trim($input['comentario']) : '';

        // 3. Validaciones básicas
        if ($calificacion < 1 || $calificacion > 5) {
            echo json_encode(["ok" => false, "msg" => "La calificación debe estar entre 1 y 5"]);
            exit;
        }

        if (empty($comentario)) {
            echo json_encode(["ok" => false, "msg" => "El comentario no puede estar vacío"]);
            exit;
        }

        // 4. Insertar en la base de datos
        $stmt = $pdo->prepare("
            INSERT INTO tab_Opiniones (id_usuario, calificacion, comentario, usr_insert)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $id_usuario,
            $calificacion,
            $comentario,
            $_SESSION['user_name'] ?? 'sistema'
        ]);

        echo json_encode([
            "ok" => true,
            "msg" => "¡Gracias por tu reseña! Tu opinión es muy valiosa para nosotros."
        ]);
        exit;
    }

    // Si no es GET ni POST
    http_response_code(405);
    echo json_encode(["ok" => false, "msg" => "Método no permitido"]);

} catch (PDOException $e) {
    // Error de base de datos
    http_response_code(500);
    error_log("Error en resenas.php: " . $e->getMessage());
    echo json_encode(["ok" => false, "msg" => "Error interno en el servidor de base de datos"]);
}

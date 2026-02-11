<?php
/**
 * API: CONTACTO / RESERVAS
 * ---------------------------------------------------------
 * Propósito: Maneja las solicitudes de contacto desde el landing page.
 * Utiliza tab_Reservas para almacenar los mensajes de contacto.
 * Acción: POST
 */

header('Content-Type: application/json');
require_once '../config.php';

// Solo permitimos POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    exit;
}

// Verificación de conexión a la base de datos
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de configuración de BD']);
    exit;
}

// 🛡️ RATE LIMITING
require_once '../utils/security_utils.php';
$clientIP = getClientIP();
if (!checkRateLimit($pdo, $clientIP, 'contact_form', 3, 60)) {
    http_response_code(429);
    echo json_encode(["ok" => false, "msg" => "Has excedido el límite de mensajes. Intenta más tarde."]);
    exit;
}
logRateLimit($pdo, $clientIP, 'contact_form');

try {
    // Obtener datos JSON del body
    $input = json_decode(file_get_contents('php://input'), true);

    // 🛡️ SANITIZACIÓN DE ENTRADAS
    $nombre = strip_tags(trim($input['nombre'] ?? ''));
    $email = filter_var(trim($input['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $telefono = preg_replace('/\D/', '', $input['telefono'] ?? '');
    $servicio = strip_tags(trim($input['servicio'] ?? ''));
    $mensaje = strip_tags(trim($input['mensaje'] ?? ''));

    // Campos requeridos
    if (empty($nombre) || empty($email) || empty($servicio) || empty($mensaje)) {
        echo json_encode(['ok' => false, 'msg' => 'Faltan campos obligatorios']);
        exit;
    }

    // Validar formato de email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'msg' => 'Formato de email inválido']);
        exit;
    }

    // Buscar o crear usuario temporal para el contacto
    // Primero verificamos si el email ya existe
    $stmtCheck = $pdo->prepare("SELECT id_usuario FROM tab_Usuarios WHERE correo_usuario = ?");
    $stmtCheck->execute([$email]);
    $usuario = $stmtCheck->fetch();

    if ($usuario) {
        $id_usuario = $usuario['id_usuario'];
    } else {
        // Crear usuario temporal/básico para el contacto
        $stmtMaxId = $pdo->query("SELECT COALESCE(MAX(id_usuario), 0) + 1 as next_id FROM tab_Usuarios");
        $id_usuario = $stmtMaxId->fetchColumn();

        // Número de teléfono: convertir a bigint o usar 0 si está vacío
        $num_telefono = !empty($telefono) ? preg_replace('/\D/', '', $telefono) : 0;

        $stmtUser = $pdo->prepare("
            INSERT INTO tab_Usuarios 
            (id_usuario, nom_usuario, correo_usuario, num_telefono_usuario, contra, rol, usr_insert, fec_insert)
            VALUES (?, ?, ?, ?, ?, 'contacto', 'system', NOW())
        ");
        $stmtUser->execute([
            $id_usuario,
            $nombre,
            $email,
            $num_telefono,
            password_hash('CONTACTO_NO_LOGIN', PASSWORD_BCRYPT) // Password temporal
        ]);
    }

    // Mapear servicio solicitado a id_servicio
    // Primero intentamos buscar el servicio por nombre similar
    $service_map = [
        'repair' => 'reparación',
        'maintenance' => 'mantenimiento',
        'parts' => 'repuesto',
        'appraisal' => 'valuación',
        'other' => 'otro'
    ];

    $search_term = $service_map[$servicio] ?? $servicio;

    $stmtServicio = $pdo->prepare("
        SELECT id_servicio FROM tab_Servicios 
        WHERE LOWER(nom_servicio) LIKE ? 
        LIMIT 1
    ");
    $stmtServicio->execute(['%' . strtolower($search_term) . '%']);
    $servicio_data = $stmtServicio->fetch();

    // Si no encuentra, usar el primer servicio disponible
    if (!$servicio_data) {
        $stmtServicio = $pdo->query("SELECT MIN(id_servicio) as id_servicio FROM tab_Servicios");
        $servicio_data = $stmtServicio->fetch();
    }

    $id_servicio = $servicio_data['id_servicio'] ?? 1;

    // Generar nuevo ID para la reserva
    $stmtMaxReserva = $pdo->query("SELECT COALESCE(MAX(id_reserva), 0) + 1 as next_id FROM tab_Reservas");
    $id_reserva = $stmtMaxReserva->fetchColumn();

    // Insertar en tab_Reservas
    $stmtReserva = $pdo->prepare("
        INSERT INTO tab_Reservas 
        (id_reserva, id_usuario, id_servicio, fecha_reserva, notas_cliente, estado_reserva, usr_insert, fec_insert)
        VALUES (?, ?, ?, NOW(), ?, 'pendiente', 'system', NOW())
    ");

    $stmtReserva->execute([
        $id_reserva,
        $id_usuario,
        $id_servicio,
        "Contacto desde landing page:\n\nNombre: $nombre\nEmail: $email\nTeléfono: $telefono\nServicio: $servicio\n\nMensaje:\n$mensaje"
    ]);

    echo json_encode([
        'ok' => true,
        'msg' => '¡Mensaje enviado exitosamente! Nos pondremos en contacto pronto.',
        'id_reserva' => $id_reserva
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Error en contacto.php: " . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Error al procesar la solicitud: ' . $e->getMessage()]);
}

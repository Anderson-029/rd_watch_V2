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
require_once '../utils/Validation.php';
require_once '../utils/security_utils.php';

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

// 🛡️ SEGURIDAD: CSRF OBLIGATORIO
validateCsrfToken(null, true);

// Captura de datos JSON
$data = getJsonInput();

// 👤 RECONOCIMIENTO INTELIGENTE: ¿Usuario logueado?
$isLoggedIn = isset($_SESSION['user_id']);

if (!$isLoggedIn) {
    // 🛡️ RATE LIMITING: Solo para usuarios anónimos (Protección contra SPAM)
    $clientIP = getClientIP();
    if (!checkRateLimit($pdo, $clientIP, 'contact_form_anonymous', 3, 60)) {
        http_response_code(429);
        echo json_encode(["ok" => false, "msg" => "Has excedido el límite de intentos anónimos. Por favor inicia sesión."]);
        exit;
    }
    logRateLimit($pdo, $clientIP, 'contact_form_anonymous');

    // Bloqueo de seguridad: Exigir sesión para agendar
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Para su seguridad, debe iniciar sesión antes de agendar una cita.']);
    exit;
}

// Si llegamos aquí, el usuario está logueado y no tiene Rate Limit por IP
$id_usuario = $_SESSION['user_id'];

try {
    // 🛡️ VALIDACIÓN ESTRICTA (ISO 830)
    Validation::validateOrReject($data, [
        'nombre' => 'name',
        'email' => 'email',
        'telefono' => 'name',
        'servicio' => 'name',
        'mensaje' => 'name' // Cambiado de 'address' a 'name' para permitir más caracteres básicos en el texto
    ]);

    $nombre = Validation::sanitizeString($data['nombre']);
    $email = Validation::sanitizeString($data['email']);
    $telefonoRaw = Validation::sanitizeString($data['telefono']);
    $telefono = preg_replace('/\D/', '', $telefonoRaw);
    $servicio = Validation::sanitizeString($data['servicio']);
    $mensaje = Validation::sanitizeString($data['mensaje']);

    // Validación lógica de teléfono: exactamente 10 dígitos
    if (strlen($telefono) !== 10) {
        echo json_encode(['ok' => false, 'msg' => 'El teléfono debe tener exactamente 10 dígitos']);
        exit;
    }

    // 🛡️ CONTROL DE REDUNDANCIA: No permitir enviar el mismo mensaje dos veces seguidas para el mismo usuario
    $stmtRedundancy = $pdo->prepare("
        SELECT id_reserva FROM tab_Reservas 
        WHERE id_usuario = ? AND notas_cliente LIKE ? AND fec_insert > (NOW() - INTERVAL '5 minutes')
    ");
    $stmtRedundancy->execute([$id_usuario, '%Mensaje:\n' . $mensaje . '%']);
    if ($stmtRedundancy->fetch()) {
        echo json_encode(['ok' => false, 'msg' => 'Su solicitud ya ha sido recibida.']);
        exit;
    }

    // Mapear servicio solicitado a id_servicio (Mantenemos búsqueda dinámica)
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

    $id_servicio = $servicio_data['id_servicio'] ?? 1;

    // Generar nuevo ID para la reserva
    $stmtMaxReserva = $pdo->query("SELECT COALESCE(MAX(id_reserva), 0) + 1 as next_id FROM tab_Reservas");
    $id_reserva = $stmtMaxReserva->fetchColumn();

    // Insertar en tab_Reservas (Vínculo directo con usuario logueado)
    $stmtReserva = $pdo->prepare("
        INSERT INTO tab_Reservas 
        (id_reserva, id_usuario, id_servicio, fecha_reserva, notas_cliente, estado_reserva, usr_insert, fec_insert)
        VALUES (?, ?, ?, NOW(), ?, 'pendiente', ?, NOW())
    ");

    $notas = "Cita agendada desde el Landing:\n\nServicio: $servicio\n\nMensaje:\n$mensaje";

    $stmtReserva->execute([
        $id_reserva,
        $id_usuario,
        $id_servicio,
        $notas,
        'user_' . $id_usuario
    ]);

    echo json_encode([
        'ok' => true,
        'msg' => '¡Su cita ha sido agendada con éxito! Nos pondremos en contacto pronto.',
        'id_reserva' => $id_reserva
    ]);

}
catch (PDOException $e) {
    http_response_code(500);
    error_log("Error en contacto.php: " . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Error al procesar la solicitud: ' . $e->getMessage()]);
}
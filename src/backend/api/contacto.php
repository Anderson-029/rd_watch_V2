<?php
/**
 * ============================================================
 * API: CONTACTO / AGENDAMIENTO DESDE LANDING (contacto.php)
 * ============================================================
 * ENDPOINT: POST /api/contacto.php
 *
 * PROPÓSITO:
 * Maneja las solicitudes de contacto desde la página principal
 * (landing page). Crea reservas en la BD directamente.
 *
 * SEGURIDAD:
 * - Solo usuarios logueados pueden agendar (anti-spam)
 * - Rate limiting para usuarios anónimos
 * - Anti-duplicado: no permite enviar el mismo mensaje 2 veces
 *   en 5 minutos (fn_contacto_check_dup)
 * - Validación de teléfono: exactamente 10 dígitos
 *
 * FUNCIONES POSTGRESQL QUE USA:
 * - fn_sec_check_rate_limit(ip, acción, límite, ventana) → BOOLEAN
 * - fn_sec_log_attempt(ip, acción) → Registra intento anónimo
 * - fn_contacto_check_dup(user_id, mensaje) → BOOLEAN
 * - fn_contacto_create(user_id, servicio_txt, notas) → JSON {ok, msg}
 *
 * MAPEO DE SERVICIOS:
 * El frontend envía un código de servicio ('repair', 'maintenance'...)
 * que se traduce a un término en español para buscar en la BD.
 * fn_contacto_create busca el servicio por nombre parcial (ILIKE).
 * ============================================================
 */

header('Content-Type: application/json');
require_once '../config.php';
require_once '../utils/Validation.php';
require_once '../utils/security_utils.php';

// Solo POST permitido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    exit;
}

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de configuración de BD']);
    exit;
}

// CSRF obligatorio
validateCsrfToken(null, true);

$data = getJsonInput();

// ──────────────────────────────────────────────
// RECONOCIMIENTO: ¿Logueado o anónimo?
// ──────────────────────────────────────────────
$isLoggedIn = isset($_SESSION['user_id']);

if (!$isLoggedIn) {
    // ──────────────────────────────────────────────
    // BLOQUEO DE ANÓNIMOS CON RATE LIMITING
    // ──────────────────────────────────────────────
    // Los anónimos tienen límite de 3 intentos en 60 minutos
    // Esto previene spam masivo sin cuenta
    $clientIP = getClientIP();

    $rlStmt = $pdo->prepare("SELECT fn_sec_check_rate_limit(?, ?, 3, 60)");
    $rlStmt->execute([$clientIP, 'contact_form_anonymous']);
    if (!$rlStmt->fetchColumn()) {
        http_response_code(429);
        echo json_encode(["ok" => false, "msg" => "Has excedido el límite de intentos anónimos. Por favor inicia sesión."]);
        exit;
    }
    // Registrar intento del anónimo
    $pdo->prepare("SELECT fn_sec_log_attempt(?, ?)")->execute([$clientIP, 'contact_form_anonymous']);

    // Bloqueo: exigir sesión para agendar
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Para su seguridad, debe iniciar sesión antes de agendar una cita.']);
    exit;
}

// Si llegamos aquí → usuario logueado
$id_usuario = $_SESSION['user_id'];

try {
    // ──────────────────────────────────────────────
    // VALIDACIÓN DE INPUTS
    // ──────────────────────────────────────────────
    Validation::validateOrReject($data, [
        'nombre' => 'name',
        'email' => 'email',
        'telefono' => 'name',
        'servicio' => 'name',
        'mensaje' => 'name'
    ]);

    $nombre = Validation::sanitizeString($data['nombre']);
    $email = Validation::sanitizeString($data['email']);
    $telefonoRaw = Validation::sanitizeString($data['telefono']);
    $telefono = preg_replace('/\D/', '', $telefonoRaw); // Solo dígitos
    $servicio = Validation::sanitizeString($data['servicio']);
    $mensaje = Validation::sanitizeString($data['mensaje']);

    // Validar teléfono: exactamente 10 dígitos
    if (strlen($telefono) !== 10) {
        echo json_encode(['ok' => false, 'msg' => 'El teléfono debe tener exactamente 10 dígitos']);
        exit;
    }

    // ──────────────────────────────────────────────
    // ANTI-DUPLICADO (consulta opaca)
    // ──────────────────────────────────────────────
    // fn_contacto_check_dup busca si ya envió este mismo mensaje
    // en los últimos 5 minutos
    $dupStmt = $pdo->prepare("SELECT fn_contacto_check_dup(?, ?)");
    $dupStmt->execute([$id_usuario, $mensaje]);
    if ($dupStmt->fetchColumn()) {
        echo json_encode(['ok' => false, 'msg' => 'Su solicitud ya ha sido recibida.']);
        exit;
    }

    // ──────────────────────────────────────────────
    // MAPEO DE SERVICIOS (frontend → español)
    // ──────────────────────────────────────────────
    // El frontend envía códigos en inglés → los traducimos
    // para buscar en la BD con ILIKE
    $service_map = [
        'repair' => 'reparación',
        'maintenance' => 'mantenimiento',
        'parts' => 'repuesto',
        'appraisal' => 'valuación',
        'other' => 'otro'
    ];
    $search_term = $service_map[$servicio] ?? $servicio;

    // ──────────────────────────────────────────────
    // CREAR RESERVA (consulta opaca)
    // ──────────────────────────────────────────────
    // fn_contacto_create busca el servicio por nombre parcial
    // y crea la reserva automáticamente
    $notas = "Cita agendada desde el Landing:\n\nServicio: $servicio\n\nMensaje:\n$mensaje";

    $stmt = $pdo->prepare("SELECT fn_contacto_create(?, ?, ?)");
    $stmt->execute([$id_usuario, $search_term, $notas]);
    echo json_encode(json_decode($stmt->fetchColumn(), true));

}
catch (PDOException $e) {
    http_response_code(500);
    error_log("Error en contacto.php: " . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Error al procesar la solicitud: ' . $e->getMessage()]);
}
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

// 🛡️ SEGURIDAD: CSRF OBLIGATORIO
validateCsrfToken($_POST['csrf_token'] ?? null, true);

try {
    // 🛡️ VALIDACIÓN ESTRICTA (ISO 830)
    Validation::validateOrReject($_POST, [
        'nombre' => 'name',
        'email' => 'email',
        'servicio' => 'name',
        'mensaje' => 'address' // Usamos address para permitir texto largo con signos básicos
    ]);

    $nombre = Validation::sanitizeString($_POST['nombre']);
    $email = Validation::sanitizeString($_POST['email']);
    $telefono = !empty($_POST['telefono']) ? preg_replace('/\D/', '', $_POST['telefono']) : null;
    $servicio = Validation::sanitizeString($_POST['servicio']);
    $mensaje = Validation::sanitizeString($_POST['mensaje']);

    // 🛡️ CONTROL DE REDUNDANCIA: No permitir enviar el mismo mensaje dos veces seguidas
    $stmtRedundancy = $pdo->prepare("
        SELECT id_reserva FROM tab_Reservas 
        WHERE notas_cliente LIKE ? AND fec_insert > (NOW() - INTERVAL '10 minutes')
    ");
    $stmtRedundancy->execute(['%Mensaje:\n' . $mensaje . '%']);
    if ($stmtRedundancy->fetch()) {
        echo json_encode(['ok' => false, 'msg' => 'Inconsistencia: Su mensaje ya ha sido enviado recientemente. Intente más tarde.']);
        exit;
    }


    // === VALIDACIÓN Y PROCESAMIENTO DE FOTO ADJUNTA ===
    $foto_binario = null;
    $foto_extension = null;

    if (isset($_FILES['contact_file']) && $_FILES['contact_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['contact_file'];

        // Validar tipo MIME
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/svg+xml'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedMimeTypes)) {
            echo json_encode(['ok' => false, 'msg' => 'Solo se permiten imágenes: JPG, PNG, SVG']);
            exit;
        }

        // Validar extensión
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'svg'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($fileExtension, $allowedExtensions)) {
            echo json_encode(['ok' => false, 'msg' => 'Extensión de archivo no permitida']);
            exit;
        }

        // Validar tamaño (máximo 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            echo json_encode(['ok' => false, 'msg' => 'La imagen no debe superar los 5MB']);
            exit;
        }

        // Leer archivo como binario para almacenamiento en BD (BYTEA)
        $foto_binario = file_get_contents($file['tmp_name']);
        $foto_extension = $fileExtension;

        if (!$foto_binario) {
            echo json_encode(['ok' => false, 'msg' => 'Error al procesar la imagen']);
            exit;
        }
    }
    elseif (isset($_FILES['contact_file']) && $_FILES['contact_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Hubo un error en la carga
        echo json_encode(['ok' => false, 'msg' => 'Error al cargar el archivo']);
        exit;
    }


    // Buscar o crear usuario temporal para el contacto
    // Primero verificamos si el email ya existe
    $stmtCheck = $pdo->prepare("SELECT id_usuario FROM tab_Usuarios WHERE correo_usuario = ?");
    $stmtCheck->execute([$email]);
    $usuario = $stmtCheck->fetch();

    if ($usuario) {
        $id_usuario = $usuario['id_usuario'];
    }
    else {
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

    // Insertar en tab_Reservas (con foto adjunta si existe)
    $stmtReserva = $pdo->prepare("
        INSERT INTO tab_Reservas 
        (id_reserva, id_usuario, id_servicio, fecha_reserva, notas_cliente, estado_reserva, foto_adjunto, foto_extension, usr_insert, fec_insert)
        VALUES (?, ?, ?, NOW(), ?, 'pendiente', ?, ?, 'system', NOW())
    ");

    $notas = "Contacto desde landing page:\n\nNombre: $nombre\nEmail: $email\nTeléfono: $telefono\nServicio: $servicio\n\nMensaje:\n$mensaje";

    $stmtReserva->bindValue(1, $id_reserva, PDO::PARAM_INT);
    $stmtReserva->bindValue(2, $id_usuario, PDO::PARAM_INT);
    $stmtReserva->bindValue(3, $id_servicio, PDO::PARAM_INT);
    $stmtReserva->bindValue(4, $notas, PDO::PARAM_STR);
    $stmtReserva->bindValue(5, $foto_binario, PDO::PARAM_LOB); // BYTEA
    $stmtReserva->bindValue(6, $foto_extension, PDO::PARAM_STR);
    $stmtReserva->execute();

    echo json_encode([
        'ok' => true,
        'msg' => '¡Mensaje enviado exitosamente! Nos pondremos en contacto pronto.',
        'id_reserva' => $id_reserva
    ]);

}
catch (PDOException $e) {
    http_response_code(500);
    error_log("Error en contacto.php: " . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Error al procesar la solicitud: ' . $e->getMessage()]);
}
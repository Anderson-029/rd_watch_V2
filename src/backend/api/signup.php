<?php
/**
 * API: REGISTRO DE USUARIOS (SIGNUP)
 * ---------------------------------------------------------
 * Propósito: Permitir a nuevos visitantes crear una cuenta de 'cliente' 
 * en la plataforma de forma autónoma.
 * 
 * Flujo de Seguridad:
 * 1. Sanitización y validación de campos obligatorios.
 * 2. Verificación de identidad única (Email) para evitar cuentas duplicadas.
 * 3. Encriptación de alta seguridad mediante BCRYPT antes de la persistencia.
 * 4. Asignación automática de rol 'cliente' y estado inicial 'activo'.
 */

require_once '../config.php';
header('Content-Type: application/json');

// Captura de datos JSON desde el flujo de entrada
$input = json_decode(file_get_contents('php://input'), true);

// 🛡️ SANITIZACIÓN (PREVENCIÓN XSS)
require_once '../utils/security_utils.php';
$nombre = sanitizeHtml(trim($input['nombre'] ?? ''));
$email = filter_var(trim($input['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$telefono = preg_replace('/\D/', '', $input['telefono'] ?? ''); // Solo números
$pass = $input['password'] ?? ''; // La contraseña no se sanitiza, se hashea

// 1. REGLAS DE NEGOCIO: VALIDACIÓN DE CAMPOS
if (empty($email) || empty($pass) || empty($nombre)) {
    echo json_encode(["ok" => false, "msg" => "Faltan datos obligatorios para completar el registro"]);
    exit;
}

// Validación de nombre (solo letras, espacios y acentos)
if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $nombre)) {
    echo json_encode(["ok" => false, "msg" => "El nombre solo debe contener letras y espacios"]);
    exit;
}

// Validación de formato de email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["ok" => false, "msg" => "El formato del correo electrónico no es válido"]);
    exit;
}

// Validación de formato de teléfono (10 dígitos)
if (strlen((string) $telefono) !== 10 || !ctype_digit((string) $telefono)) {
    echo json_encode(["ok" => false, "msg" => "El número de teléfono debe tener exactamente 10 dígitos numéricos"]);
    exit;
}

// Validación de complejidad de contraseña
$passwordErrors = [];
if (strlen($pass) < 8) {
    $passwordErrors[] = "mínimo 8 caracteres";
}
if (!preg_match('/[A-Z]/', $pass)) {
    $passwordErrors[] = "una letra mayúscula";
}
if (!preg_match('/[0-9]/', $pass)) {
    $passwordErrors[] = "un número";
}
if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $pass)) {
    $passwordErrors[] = "un carácter especial";
}

if (!empty($passwordErrors)) {
    echo json_encode([
        "ok" => false,
        "msg" => "La contraseña debe contener: " . implode(", ", $passwordErrors)
    ]);
    exit;
}

try {
    /**
     * ==========================================
     * 🔍 COMPROBACIÓN DE EXISTENCIA
     * ==========================================
     */
    $stmt = $pdo->prepare("SELECT id_usuario FROM tab_Usuarios WHERE correo_usuario = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(["ok" => false, "msg" => "Inconsistencia: Esta dirección de correo electrónico ya posee una cuenta activa"]);
        exit;
    }

    /**
     * ==========================================
     * 🔐 PROTECCIÓN DE CREDENCIALES
     * ==========================================
     */
    $hash = password_hash($pass, PASSWORD_BCRYPT);

    /**
     * ==========================================
     * 💾 PERSISTENCIA DEL NUEVO REGISTRO
     * ==========================================
     * Nota Técnica: Se utiliza una subconsulta para el ID_USUARIO para garantizar 
     * continuidad numérica en ausencia de disparadores de autoincremento explícitos en el motor.
     */
    $sql = "INSERT INTO tab_Usuarios (
                id_usuario, nom_usuario, correo_usuario, num_telefono_usuario, 
                contra, salt, rol, activo, bloqueado, fecha_registro, intentos_fallidos
            ) VALUES (
                (SELECT COALESCE(MAX(id_usuario), 0) + 1 FROM tab_Usuarios),
                ?, ?, ?, ?, 'legacy_salt', 'cliente', TRUE, FALSE, NOW(), 0
            )";

    $stmtInsert = $pdo->prepare($sql);

    if ($stmtInsert->execute([$nombre, $email, $telefono, $hash])) {
        echo json_encode([
            "ok" => true,
            "msg" => "¡Bienvenido a RD-Watch, " . $nombre . "! Tu cuenta ha sido creada exitosamente. Ya puedes iniciar sesión."
        ]);
    } else {
        throw new Exception("Error interno al ejecutar la sentencia de inserción");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "msg" => "Fallo técnico en el sistema de registro: " . $e->getMessage()]);
}

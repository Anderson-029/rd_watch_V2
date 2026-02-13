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

// 🛡️ SANITIZACIÓN e Integridad (ISO 830)
require_once '../utils/security_utils.php';
require_once '../utils/Validation.php';

// 🛡️ VALIDACIÓN ESTRICTA (ISO 830)
Validation::validateOrReject($input, [
    'nombre' => 'name',
    'email' => 'email',
    'telefono' => 'phone',
    'password' => 'password' // Validación de formato básico ISO 830
]);

$nombre = Validation::sanitizeString($input['nombre']);
$email = Validation::sanitizeString($input['email']);
$telefono = $input['telefono'];
$pass = $input['password'];


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

    // 🛡️ CONTROL DE REDUNDANCIA: Evitar múltiples cuentas con el mismo nombre y teléfono
    $stmtRedundancy = $pdo->prepare("SELECT id_usuario FROM tab_Usuarios WHERE nom_usuario = ? AND num_telefono_usuario = ?");
    $stmtRedundancy->execute([$nombre, $telefono]);
    if ($stmtRedundancy->fetch()) {
        echo json_encode(["ok" => false, "msg" => "Inconsistencia: Ya existe un registro con esta combinación de nombre y teléfono."]);
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
    }
    else {
        throw new Exception("Error interno al ejecutar la sentencia de inserción");
    }

}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "msg" => "Fallo técnico en el sistema de registro: " . $e->getMessage()]);
}
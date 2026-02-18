<?php
/**
 * API: REGISTRO DE USUARIOS (SIGNUP)
 * ---------------------------------------------------------
 * Propósito: Permitir a nuevos visitantes crear una cuenta de 'cliente' 
 * en la plataforma de forma autónoma.
 */

require_once '../config.php';
require_once '../utils/security_utils.php';
require_once '../utils/Validation.php';

header('Content-Type: application/json');

// 🛡️ SEGURIDAD: Validación CSRF obligatoria
validateCsrfToken(null, true);

// Captura de datos JSON desde el flujo de entrada centralizado
$input = getJsonInput();

// 🛡️ VALIDACIÓN ESTRICTA (ISO 830)
Validation::validateOrReject($input, [
    'nombre' => 'name',
    'email' => 'email',
    'telefono' => 'phone',
    'password' => 'password'
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
    $stmt = $pdo->prepare("SELECT id_usuario FROM tab_Usuarios WHERE correo_usuario = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(["ok" => false, "msg" => "Inconsistencia: Esta dirección de correo electrónico ya posee una cuenta activa"]);
        exit;
    }

    $stmtRedundancy = $pdo->prepare("SELECT id_usuario FROM tab_Usuarios WHERE nom_usuario = ? AND num_telefono_usuario = ?");
    $stmtRedundancy->execute([$nombre, $telefono]);
    if ($stmtRedundancy->fetch()) {
        echo json_encode(["ok" => false, "msg" => "Inconsistencia: Ya existe un registro con esta combinación de nombre y teléfono."]);
        exit;
    }

    $hash = password_hash($pass, PASSWORD_BCRYPT);

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
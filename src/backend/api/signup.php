<?php
/**
 * API: REGISTRO DE USUARIOS (SIGNUP)
 * ---------------------------------------------------------
 * Permite la creación de nuevas cuentas para clientes.
 */

require_once '../config.php';
header('Content-Type: application/json');

// 1. OBTENCIÓN DE DATOS
$input = json_decode(file_get_contents('php://input'), true);

$nombre = $input['nombre'] ?? '';
$email = $input['email'] ?? '';
$telefono = $input['telefono'] ?? '';
$pass = $input['password'] ?? '';

// Validaciones básicas de campos obligatorios
if (empty($email) || empty($pass) || empty($nombre)) {
    echo json_encode(["ok" => false, "msg" => "Faltan datos obligatorios"]);
    exit;
}

try {
    // 2. VERIFICACIÓN DE DUPLICADOS
    // Comprobamos si el correo electrónico ya está registrado en la base de datos
    $stmt = $pdo->prepare("SELECT id_usuario FROM tab_Usuarios WHERE correo_usuario = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(["ok" => false, "msg" => "El correo ya está registrado"]);
        exit;
    }

    // 3. SEGURIDAD DE CONTRASEÑA
    // Encriptamos la contraseña usando el algoritmo BCRYPT antes de guardarla
    $hash = password_hash($pass, PASSWORD_BCRYPT);

    /**
     * 4. INSERCIÓN DEL NUEVO USUARIO
     * Calculamos el ID manualmente (MAX + 1) para mayor control sobre el esquema.
     */
    $sql = "INSERT INTO tab_Usuarios (
                id_usuario, nom_usuario, correo_usuario, num_telefono_usuario, 
                contra, salt, rol, activo, bloqueado, fecha_registro, intentos_fallidos
            ) VALUES (
                (SELECT COALESCE(MAX(id_usuario), 0) + 1 FROM tab_Usuarios),
                ?, ?, ?, ?, 'legacy_salt', 'cliente', TRUE, FALSE, NOW(), 0
            )";

    $stmtInsert = $pdo->prepare($sql);
    $stmtInsert->execute([$nombre, $email, $telefono, $hash]);

    echo json_encode(["ok" => true, "msg" => "Usuario registrado correctamente"]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "msg" => "Error de servidor: " . $e->getMessage()]);
}

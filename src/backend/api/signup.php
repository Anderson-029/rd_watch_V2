<?php
// api/signup.php
require_once '../config.php';

header('Content-Type: application/json');

// 1. Obtener JSON
$input = json_decode(file_get_contents('php://input'), true);

$nombre = $input['nombre'] ?? '';
$email = $input['email'] ?? '';
$telefono = $input['telefono'] ?? '';
$pass = $input['password'] ?? '';

// Validaciones básicas
if (empty($email) || empty($pass) || empty($nombre)) {
    echo json_encode(["ok" => false, "msg" => "Faltan datos obligatorios"]);
    exit;
}

try {
    // 2. Verificar si ya existe el correo
    $stmt = $pdo->prepare("SELECT id_usuario FROM tab_Usuarios WHERE correo_usuario = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(["ok" => false, "msg" => "El correo ya está registrado"]);
        exit;
    }

    // 3. Hashear contraseña
    $hash = password_hash($pass, PASSWORD_BCRYPT);

    // 4. Insertar Usuario
    // Calculamos el ID manualmente (MAX + 1) para compatibilidad con el esquema actual
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

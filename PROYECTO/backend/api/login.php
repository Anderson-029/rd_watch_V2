<?php
// api/login.php
require_once '../config.php';

header('Content-Type: application/json');

// 1. Obtener JSON del cliente
$input = json_decode(file_get_contents('php://input'), true);

$email = $input['email'] ?? '';
$pass = $input['password'] ?? ($input['contra'] ?? '');

// 2. Query
try {
    $stmt = $pdo->prepare("SELECT id_usuario, nom_usuario, contra, rol, activo, bloqueado FROM tab_Usuarios WHERE correo_usuario = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(["ok" => false, "msg" => "Usuario no encontrado"]);
        exit;
    }

    if (!$user['activo'] || $user['bloqueado']) {
        echo json_encode(["ok" => false, "msg" => "Usuario bloqueado o inactivo"]);
        exit;
    }

    // 3. Verificar Contraseña hash
    if (password_verify($pass, $user['contra'])) {
        // LOGIN EXITOSO
        $_SESSION['user_id'] = $user['id_usuario'];
        $_SESSION['user_role'] = $user['rol'];
        $_SESSION['user_name'] = $user['nom_usuario'];
        $_SESSION['logged_in'] = true;

        echo json_encode([
            "ok" => true,
            "msg" => "Bienvenido " . $user['nom_usuario'],
            "redirect" => ($user['rol'] === 'admin') ? 'admin/admin.html' : 'user/user.html',
            "user" => [
                "id" => $user['id_usuario'],
                "nombre" => $user['nom_usuario'],
                "rol" => $user['rol']
            ]
        ]);
    } else {
        echo json_encode(["ok" => false, "msg" => "Contraseña incorrecta"]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "msg" => "Error de servidor: " . $e->getMessage()]);
}

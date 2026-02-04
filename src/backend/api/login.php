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

    // 3. Verificar Contraseña hash (o texto plano legado)
    $loginSuccess = false;

    if (password_verify($pass, $user['contra'])) {
        $loginSuccess = true;
    } elseif ($pass === $user['contra']) {
        // Fallback: Soporte para contraseñas antiguas en texto plano
        // Si coincide, actualizamos automáticamente a hash para el futuro
        $newHash = password_hash($pass, PASSWORD_BCRYPT);
        $upd = $pdo->prepare("UPDATE tab_Usuarios SET contra = ? WHERE id_usuario = ?");
        $upd->execute([$newHash, $user['id_usuario']]);
        $loginSuccess = true;
    }

    if ($loginSuccess) {
        // LOGIN EXITOSO
        $_SESSION['user_id'] = $user['id_usuario'];
        $_SESSION['user_role'] = $user['rol'];
        $_SESSION['user_name'] = $user['nom_usuario'];
        $_SESSION['logged_in'] = true;

        echo json_encode([
            "ok" => true,
            "msg" => "Bienvenido " . $user['nom_usuario'],
            "redirect" => ($user['rol'] === 'admin') ? 'src/admin/admin.html' : 'src/user/user.html',
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

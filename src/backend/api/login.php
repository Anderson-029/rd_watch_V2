<?php
/**
 * API: INICIO DE SESIÓN (LOGIN)
 * ---------------------------------------------------------
 * Este archivo procesa las solicitudes de autenticación de
 * usuarios, tanto para clientes como para administradores.
 */

require_once '../config.php';
header('Content-Type: application/json');

// 1. OBTENCIÓN DE DATOS DEL CLIENTE
// Leemos el cuerpo de la petición (format JSON) y lo convertimos a un arreglo PHP
$input = json_decode(file_get_contents('php://input'), true);

$email = $input['email'] ?? '';
$pass = $input['password'] ?? ($input['contra'] ?? '');

// 2. CONSULTA A LA BASE DE DATOS
try {
    // Buscamos al usuario por su correo electrónico único
    $stmt = $pdo->prepare("SELECT id_usuario, nom_usuario, contra, rol, activo, bloqueado FROM tab_Usuarios WHERE correo_usuario = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Verificaciones de existencia y estado del usuario
    if (!$user) {
        echo json_encode(["ok" => false, "msg" => "Usuario no encontrado"]);
        exit;
    }

    if (!$user['activo'] || $user['bloqueado']) {
        echo json_encode(["ok" => false, "msg" => "Usuario bloqueado o inactivo"]);
        exit;
    }

    /**
     * 3. VERIFICACIÓN DE CONTRASEÑA
     * El sistema soporta contraseñas hasheadas (seguro) y texto plano (legado).
     */
    $loginSuccess = false;

    // Comprobamos si la contraseña coincide con el hash almacenado
    if (password_verify($pass, $user['contra'])) {
        $loginSuccess = true;
    } elseif ($pass === $user['contra']) {
        // FALLBACK: Si la clave está en texto plano, la validamos y la actualizamos a hash automáticamente
        $newHash = password_hash($pass, PASSWORD_BCRYPT);
        $upd = $pdo->prepare("UPDATE tab_Usuarios SET contra = ? WHERE id_usuario = ?");
        $upd->execute([$newHash, $user['id_usuario']]);
        $loginSuccess = true;
    }

    if ($loginSuccess) {
        // 4. INICIO DE SESIÓN EXITOSO
        // Guardamos los datos esenciales en la sesión global del servidor
        $_SESSION['user_id'] = $user['id_usuario'];
        $_SESSION['user_role'] = $user['rol'];
        $_SESSION['user_name'] = $user['nom_usuario'];
        $_SESSION['logged_in'] = true;

        echo json_encode([
            "ok" => true,
            "msg" => "Bienvenido " . $user['nom_usuario'],
            // Redireccionamos según el rol del usuario
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
    // Si ocurre un error inesperado, devolvemos un código 500
    http_response_code(500);
    echo json_encode(["ok" => false, "msg" => "Error de servidor: " . $e->getMessage()]);
}

<?php
// backend/api/login.php
require_once('../config.php');
require_once('../security_headers.php');
require_once('../rate_limiter.php');
require_once('../validator.php');
require_once('../encoder.php');
require_once('../session_manager.php');
require_once('../csrf.php');
header('Content-Type: application/json');
$clientIP = $_SERVER['REMOTE_ADDR'];
if (!check_rate_limit('login', $clientIP, 5, 60)) {
    $remaining = get_rate_limit_remaining('login', $clientIP, 60);
    rate_limit_fail_response($remaining > 0 ? $remaining : 60);
}
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Datos inválidos']);
    exit;
}
try {
    $email = Validator::validateEmail(trim($data['email'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    error_log("Attempting login for email: '$email'");
    if (empty($password)) {
        throw new InvalidArgumentException('Contraseña requerida');
    }
    $stmt = $pdo->prepare("SELECT * FROM fun_login_usuario(:email, :pwd)");
    $stmt->execute([':email' => $email, ':pwd' => 'legacy_call']); // El parámetro password se ignora en la nueva versión SQL
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    error_log("DB Return: " . ($row ? json_encode($row) : "NULL"));

    if (!$row || $row['status'] !== 'SUCCESS') {
        ErrorHandler::stopError(Encoder::html($row['message'] ?? 'Credenciales inválidas'), 401);
    }

    // VERIFICACIÓN DE HASH BCRYPT (PHP)
    $storedHash = $row['ret_token'] ?? ''; // La función SQL ahora devuelve el hash aquí
    if (!password_verify($password, $storedHash)) {
        error_log("Password verification failed for $email");
        ErrorHandler::stopError('Credenciales inválidas', 401);
    }
    $stmtRol = $pdo->prepare("SELECT rol FROM tab_Usuarios WHERE id_usuario = :id");
    $stmtRol->execute([':id' => $row['ret_id_usuario']]);
    $rolData = $stmtRol->fetch(PDO::FETCH_ASSOC);
    $rol = $rolData['rol'] ?? 'cliente';
    secure_session_regenerate();
    clear_rate_limit('login', $clientIP);
    $_SESSION['user_id'] = (int) $row['ret_id_usuario'];
    $_SESSION['user_name'] = $row['ret_nombre'];
    $_SESSION['user_mail'] = $row['ret_email'];
    $_SESSION['user_rol'] = $rol;
    init_session_security();
    error_log("Login exitoso - Usuario ID: " . $_SESSION['user_id']);
    ErrorHandler::sendSuccess("Login exitoso", [
        'user' => [
            'id' => $row['ret_id_usuario'],
            'nombre' => Encoder::html($row['ret_nombre']),
            'correo' => Encoder::html($row['ret_email']),
            'telefono' => Encoder::html($row['ret_telefono']),
            'direccion' => Encoder::html($row['ret_direccion']),
            'rol' => $rol
        ],
        'csrf_token' => generate_csrf_token(),
        'redirect' => $rol === 'admin' ? 'admin/admin.html' : 'user/user.html'
    ]);
} catch (InvalidArgumentException $e) {
    ErrorHandler::stopError($e->getMessage(), 400);
} catch (Throwable $e) {
    ErrorHandler::handleException($e);
}

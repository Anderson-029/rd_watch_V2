<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'RD-WATCH-STATIC-TOKEN-2025';

require_once '/home/kali/rdwatch/src/backend/config.php';
$_SESSION['csrf_token'] = 'RD-WATCH-STATIC-TOKEN-2025';

require_once '/home/kali/rdwatch/src/backend/utils/Validation.php';
require_once '/home/kali/rdwatch/src/backend/utils/security_utils.php';

$data = ["nombre"=>"ander", "email"=>"ander@email.com", "telefono"=>"3222222222", "asunto"=>"Cambio de Cristal Zafiro", "mensaje"=>"se me daño"];
$GLOBALS['__CACHED_JSON_INPUT'] = $data;

// Simulate contacto.php locally to trace the PDO error
try {
    Validation::validateOrReject($data, [
        'nombre' => 'name',
        'email' => 'email',
        'telefono' => 'name',
        'asunto' => 'name',
        'mensaje' => 'name'
    ]);
    
    $nombre = Validation::sanitizeString($data['nombre']);
    $email = Validation::sanitizeString($data['email']);
    $telefonoRaw = Validation::sanitizeString($data['telefono']);
    $telefono = preg_replace('/\D/', '', $telefonoRaw);
    $asunto = Validation::sanitizeString($data['asunto']);
    $mensaje = Validation::sanitizeString($data['mensaje']);

    $stmt = $pdo->prepare("SELECT fn_contacto_public_create(?, ?, ?, ?, ?)");
    $stmt->execute([$nombre, $email, $telefono, $asunto, $mensaje]);
    
    echo "JSON BD:\n";
    echo $stmt->fetchColumn() . "\n";
} catch (Exception $e) {
    echo "EXCEPCION:\n";
    echo $e->getMessage() . "\n";
}

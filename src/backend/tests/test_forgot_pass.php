<?php
/**
 * TEST: PASSWORD RECOVERY TOKEN GENERATION
 */

require_once '../config.php';
require_once '../utils/security_utils.php';

// 🛡️ BARRERA DE PRUEBAS: Solo administradores.
requireRole('admin');

echo "=== TEST: GENERACIÓN DE TOKEN PARA RECUPERACIÓN ===\n";

$email = 'agomez@example.com'; // Usamos un email que exista o creamos uno de prueba

try {
    // 1. Verificar si el usuario existe
    $stmt = $pdo->prepare("SELECT id_usuario FROM tab_Usuarios WHERE correo_usuario = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        echo "Creando usuario de prueba: agomez@example.com\n";
        $pdo->prepare("INSERT INTO tab_Usuarios (id_usuario, nom_usuario, correo_usuario, num_telefono_usuario, contra, rol) VALUES (9999, 'Andres Gomez', 'agomez@example.com', 3214567890, 'hash', 'cliente')")->execute();
    }

    // 2. Simular llamada a forgot_password.php
    $ch = curl_init("http://localhost:8000/src/backend/api/forgot_password.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => 'agomez@example.com']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if ($res['ok']) {
        echo "PASSED: Token generado exitosamente.\n";

        // 3. Verificar en BD
        $stmt = $pdo->prepare("SELECT token_recuperacion, token_expiracion FROM tab_Usuarios WHERE correo_usuario = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if (!empty($row['token_recuperacion'])) {
            echo "PASSED: Token persistido en BD: " . substr($row['token_recuperacion'], 0, 10) . "...\n";
            echo "PASSED: Expiración configurada: " . $row['token_expiracion'] . "\n";
        }
        else {
            echo "FAILED: El token no se guardó en la base de datos.\n";
        }
    }
    else {
        echo "FAILED: El endpoint retornó error: " . ($res['msg'] ?? 'Error desconocido') . "\n";
    }

}
catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
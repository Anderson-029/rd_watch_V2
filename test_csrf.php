<?php
/**
 * SCRIPT DE VERIFICACIÓN CSRF (A04)
 */

function test_csrf($path, $token = null) {
    echo "Probando CSRF en: $path\n";
    
    $testScript = '<?php ' . "\n";
    $testScript .= '$_SESSION["csrf_token"] = "valid_secret_123";' . "\n";
    $testScript .= '$_SERVER["REQUEST_METHOD"] = "POST";' . "\n";
    $testScript .= 'function getallheaders() { return ["X-CSRF-Token" => ' . var_export($token, true) . ']; }' . "\n";
    $testScript .= 'ob_start();' . "\n";
    $testScript .= 'try { include "' . $path . '"; } catch(Exception $e) {}' . "\n";
    $testScript .= '$output = ob_get_clean();' . "\n";
    $testScript .= '$code = http_response_code();' . "\n";
    $testScript .= 'echo json_encode(["code" => $code, "output" => json_decode($output, true)]);' . "\n";
    
    file_put_contents("tmp_csrf_runner.php", $testScript);
    $result = shell_exec("php tmp_csrf_runner.php");
    unlink("tmp_csrf_runner.php");
    
    return json_decode($result, true);
}

echo "--- PRUEBAS DE SEGURIDAD CSRF ---\n\n";

// 1. Petición con token INVÁLIDO
$res1 = test_csrf('src/backend/api/admin_settings.php', 'wrong_token');
echo "[RESULTADO 1] Token Inválido:\n";
echo "Código esperado: 403\n";
echo "Código obtenido: " . $res1['code'] . "\n";
echo "Mensaje: " . ($res1['output']['msg'] ?? 'N/A') . "\n\n";

// 2. Petición con token VÁLIDO
$res2 = test_csrf('src/backend/api/admin_settings.php', 'valid_secret_123');
echo "[RESULTADO 2] Token Válido:\n";
echo "Código esperado: 200 (Simulado)\n";
echo "Código obtenido: " . ($res2['code'] ?: '200') . "\n";
echo "Mensaje: " . ($res2['output']['msg'] ?? 'N/A') . "\n\n";

echo "--- FIN DE PRUEBAS ---\n";

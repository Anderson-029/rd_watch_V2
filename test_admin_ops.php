<?php
/**
 * 🛠️ QA TEST: ADMIN DELETION INTEGRITY
 * -----------------------------------------
 * Este script valida que las APIs de borrado funcionen bajo sesión de Admin.
 */

// 1. MOCK ENVIRONMENT
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['user_id'] = 1; // Asumimos ID 1 es Admin
$_SESSION['user_role'] = 'admin';
$_SESSION['logged_in'] = true;
$_SESSION['csrf_token'] = "test_token_123";

// Mock REQUEST_METHOD for the included APIs (some files check this)
$_SERVER['REQUEST_METHOD'] = 'DELETE';
$_SERVER['HTTP_X_CSRF_TOKEN'] = "test_token_123";

require_once 'src/backend/config.php';

function admin_delete_test($endpoint, $payload) {
    echo "--- Testing DELETE on: $endpoint ---\n";
    
    // Simular el contenido de php://input
    // Nota: Dado que no podemos reescribir php://input fácilmente desde PHP mismo 
    // sin cURL, usaremos una aproximación técnica: llamar a las APIs vía cURL local.
    
    $url = "http://192.168.1.52/rd_watch_V2/src/backend/api/" . $endpoint;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $_SESSION['csrf_token'],
        'Cookie: PHPSESSID=' . session_id()
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    
    echo "Respuesta: $result\n\n";
    return json_decode($result, true);
}

// PREPARACIÓN: Insertar datos de prueba para borrar
try {
    // 1. Producto de prueba
    $pdo->exec("INSERT INTO tab_Productos (id_producto, nom_producto, precio, stock, id_marca, id_categoria, id_subcategoria) 
                VALUES (999, 'Test Watch', 100, 10, 1, 1, 1) ON CONFLICT DO NOTHING");
                
    // 2. Servicio de prueba
    $pdo->exec("INSERT INTO tab_Servicios (id_servicio, nom_servicio, precio_servicio) 
                VALUES (888, 'Test Service', 50) ON CONFLICT DO NOTHING");
                
    // 3. Categoría de prueba
    $pdo->exec("INSERT INTO tab_Categorias (id_categoria, nom_categoria) 
                VALUES (777, 'Test Category') ON CONFLICT DO NOTHING");

    echo "✅ Datos de prueba insertados.\n\n";

} catch (Exception $e) {
    echo "⚠️ Error preparando datos: " . $e->getMessage() . "\n";
}

// EJECUCIÓN DE PRUEBAS
admin_delete_test("productos.php", ["id_producto" => 999]);
admin_delete_test("servicios.php", ["id_servicio" => 888]);
admin_delete_test("categorias.php", ["id_categoria" => 777]);

// PRUEBA DE SEGURIDAD: Intentar borrar algo que TIENE dependencias (Categoría 1)
echo "--- Prueba de Robustez: Borrar categoría con hijos ---\n";
admin_delete_test("categorias.php", ["id_categoria" => 1]);

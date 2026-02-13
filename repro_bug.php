<?php
// Script de reproducción del error en servicios.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mock de sesión
session_start();
$_SESSION['user_role'] = 'admin';
$_SESSION['logged_in'] = true;
$_SESSION['csrf_token'] = 'test';
$_SESSION['user_id'] = 'tester';
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'test';
$_SERVER['REQUEST_METHOD'] = 'POST';

// Mock de entrada
$testData = [
    'id_servicio' => 9991, // ID alto para no chocar
    'nom_servicio' => 'Servicio Repro 1',
    'precio_servicio' => 100.50,
    'descripcion' => 'Prueba de depuracion',
    'duracion_estimada' => '1 hora'
];

// Simulamos lo que hace getCachedJsonInput
$GLOBALS['__CACHED_JSON_INPUT'] = $testData;

try {
    // Intentamos cargar la lógica real
    // Nota: Como servicios.php tiene un header('Content-Type: application/json'),
    // y exit; en varios puntos, lo incluiremos pero después de definir la constante para evitar que corra el switch si es posible.
    // Pero servicios.php corre el switch inmediatamente.

    // Mejor copiamos la lógica de POST aquí para ver qué falla.
    require_once 'src/backend/config.php';
    require_once 'src/backend/utils/security_utils.php';
    require_once 'src/backend/utils/Validation.php';

    echo "Iniciando prueba de inserción...\n";

    $data = $testData;

    // Validación
    Validation::validateOrReject($data, [
        'id_servicio' => 'id',
        'nom_servicio' => 'name',
        'precio_servicio' => 'price'
    ]);
    echo "Validación exitosa.\n";

    // Unicidad
    $check = $pdo->prepare("SELECT id_servicio FROM tab_Servicios WHERE nom_servicio = ?");
    $check->execute([$data['nom_servicio']]);
    if ($check->fetch()) {
        die("Error: El nombre ya existe.\n");
    }
    echo "Unicidad comprobada.\n";

    $sql = "INSERT INTO tab_Servicios (id_servicio, nom_servicio, descripcion, precio_servicio, duracion_estimada, fec_insert, usr_insert) 
            VALUES (?, ?, ?, ?, ?, NOW(), ?)";
    $stmt = $pdo->prepare($sql);

    $desc = Validation::sanitizeString($data['descripcion'] ?? '');
    $duracion = Validation::sanitizeString($data['duracion_estimada'] ?? 'Consultar');
    $user_id = $_SESSION['user_id'] ?? 'admin_taller';

    echo "Ejecutando INSERT con:\n";
    var_dump([$data['id_servicio'], $data['nom_servicio'], $desc, $data['precio_servicio'], $duracion, $user_id]);

    if ($stmt->execute([$data['id_servicio'], $data['nom_servicio'], $desc, $data['precio_servicio'], $duracion, $user_id])) {
        echo "¡ÉXITO! Servicio insertado.\n";
        // Limpiamos
        $pdo->exec("DELETE FROM tab_Servicios WHERE id_servicio = 9991");
        echo "Limpieza completada.\n";
    }
    else {
        echo "FALLO en execute.\n";
        var_dump($stmt->errorInfo());
    }

}
catch (Exception $e) {
    echo "EXCEPCIÓN CAPTURADA: " . $e->getMessage() . "\n";
    echo "En archivo: " . $e->getFile() . " línea " . $e->getLine() . "\n";
}
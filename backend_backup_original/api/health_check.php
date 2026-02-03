require_once('../security_headers.php');
require_once('../config.php');

header('Content-Type: application/json');

try {
// 1. Cargar configuración y conectar (Esto lanza excepción si falla)
require_once('../config.php');

// 2. Ejecutar una consulta simple para validar la BD
$stmt = $pdo->query("SELECT version()");
$db_version = $stmt->fetchColumn();

// 3. Respuesta de éxito
echo json_encode([
"ok" => true,
"status" => "ONLINE",
"timestamp" => date('Y-m-d H:i:s'),
"system" => [
"php_version" => PHP_VERSION,
"database" => "CONNECTED",
"db_info" => $db_version
],
"message" => "El ecosistema RD Watch está listo para operar."
]);

} catch (Throwable $e) {
// Respuesta de error si algo falla
http_response_code(500);
echo json_encode([
"ok" => false,
"status" => "CRITICAL_ERROR",
"error" => $e->getMessage(),
"hint" => "Asegúrate de que PostgreSQL esté corriendo y el archivo .env sea correcto."
]);
}
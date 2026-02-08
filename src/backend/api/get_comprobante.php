<?php
/**
 * API: OBTENER COMPROBANTE DE PAGO
 * ---------------------------------------------------------
 * Recupera el archivo binario del comprobante de pago desde la base de datos
 * y lo sirve con el encabezado Content-Type apropiado para su visualización.
 */

require_once '../config.php';

// Verificación de la conexión a la base de datos
if (!isset($pdo)) {
    http_response_code(500);
    die('Error de configuración de BD');
}

// 1. Validar sesión de ADMINISTRADOR
// (Asegúrate de que 'admin' sea el rol correcto en tu sistema)
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die('Acceso denegado');
}

$id_orden = $_GET['id_orden'] ?? null;

if (!$id_orden) {
    http_response_code(400);
    die('ID de orden no proporcionado');
}

try {
    // 2. Consultar el comprobante
    $stmt = $pdo->prepare("SELECT comprobante_archivo, comprobante_extension FROM tab_Pagos WHERE id_orden = ?");
    $stmt->execute([$id_orden]);
    $pago = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pago && !empty($pago['comprobante_archivo'])) {
        // 3. Determinar el tipo MIME basado en la extensión
        $ext = strtolower($pago['comprobante_extension']);
        $mimeType = 'application/octet-stream'; // Por defecto

        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $mimeType = 'image/jpeg';
                break;
            case 'png':
                $mimeType = 'image/png';
                break;
            case 'pdf':
                $mimeType = 'application/pdf';
                break;
            case 'gif':
                $mimeType = 'image/gif';
                break;
            case 'webp':
                $mimeType = 'image/webp';
                break;
        }

        // 4. Servir el archivo
        // El contenido bytea de Postgres puede venir en formato hex (comenzando con \x)
        // o directamente binario, dependiendo de la configuración del driver PDO.
        // stream_get_contents es útil si se devuelve como recurso (stream).

        $contenido = $pago['comprobante_archivo'];

        // Si el contenido es un recurso (stream), lo leemos
        if (is_resource($contenido)) {
            $contenido = stream_get_contents($contenido);
        }

        // Si viene como string hexadecimal de Postgres (comienza con \x), lo convertimos
        // Aunque PDO::FETCH_ASSOC con PDO::ATTR_STRINGIFY_FETCHES false suele manejarlo bien.
        // Pero por seguridad:
        if (strpos($contenido, '\\x') === 0) {
            $contenido = hex2bin(substr($contenido, 2));
        }

        header("Content-Type: $mimeType");
        header("Content-Length: " . strlen($contenido));
        // Opcional: Para forzar descarga
        // header('Content-Disposition: attachment; filename="comprobante_orden_' . $id_orden . '.' . $ext . '"');

        // Limpiar cualquier salida previa (espacios en blanco, notices) que corrompan la imagen
        if (ob_get_length())
            ob_clean();
        flush();

        echo $contenido;
    } else {
        http_response_code(404);
        die('Comprobante no encontrado para esta orden');
    }
} catch (PDOException $e) {
    http_response_code(500);
    die('Error de base de datos: ' . $e->getMessage());
}
?>
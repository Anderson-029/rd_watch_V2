<?php
/**
 * ============================================================
 * API: VISUALIZADOR DE COMPROBANTES BINARIOS (get_comprobante.php)
 * ============================================================
 * ENDPOINT: GET /api/get_comprobante.php?id_orden=X
 *
 * PROPÓSITO:
 * Sirve imágenes de comprobantes de pago almacenadas como BYTEA
 * en PostgreSQL. Los admins lo usan para validar transferencias.
 *
 * ACCESO: SOLO ADMIN (requireRole('admin'))
 *
 * FUNCIÓN POSTGRESQL QUE USA:
 * - fn_receipt_get_binary(order_id) → TABLE(archivo BYTEA, extension VARCHAR)
 *
 * NOTA SOBRE BYTEA:
 * Esta es una de las excepciones justificadas al patrón opaco JSON.
 * Los datos binarios (imágenes) no se pueden retornar como JSON.
 * fn_receipt_get_binary retorna un RECORD con columnas, pero PHP
 * NO ve nombres de tablas, solo el resultado de la función.
 *
 * FLUJO:
 * 1. Validar sesión admin
 * 2. Llamar fn_receipt_get_binary
 * 3. Determinar MIME type por extensión
 * 4. Limpiar buffer y servir binario
 * ============================================================
 */

require_once '../config.php';
require_once '../utils/security_utils.php';

if (!isset($pdo)) {
    http_response_code(500);
    die('Error crítico: El motor de base de datos no está disponible');
}

// 🛡️ BARRERA ADMINISTRATIVA
requireRole('admin');

$id_orden = $_GET['id_orden'] ?? null;
if (!$id_orden) {
    http_response_code(400);
    die('Solicitud Inválida: ID de orden ausente');
}

try {
    // ══════════════════════════════════════
    // 🔍 OBTENER COMPROBANTE BINARIO
    // ══════════════════════════════════════
    // fn_receipt_get_binary retorna TABLE, no JSON
    // Es la excepción justificada para datos BYTEA
    $stmt = $pdo->prepare("SELECT * FROM fn_receipt_get_binary(?)");
    $stmt->execute([$id_orden]);
    $pago = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pago && !empty($pago['comprobante_archivo'])) {

        // Mapeo dinámico de extensión → MIME
        $ext = strtolower($pago['comprobante_extension']);
        $mimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ];
        $mimeType = $mimeMap[$ext] ?? 'application/octet-stream';

        // Normalización BYTEA (stream o hex)
        $contenido = $pago['comprobante_archivo'];
        if (is_resource($contenido)) {
            $contenido = stream_get_contents($contenido);
        }
        if (strpos($contenido, '\\x') === 0) {
            $contenido = hex2bin(substr($contenido, 2));
        }

        // Despacho del archivo
        header("Content-Type: $mimeType");
        header("Content-Length: " . strlen($contenido));
        header('Cache-Control: private, max-age=3600');

        if (ob_get_length()) {
            ob_clean();
        }
        flush();
        echo $contenido;
        exit;

    }
    else {
        http_response_code(404);
        die('Recurso no encontrado: La orden no posee un comprobante de pago cargado');
    }
}
catch (PDOException $e) {
    http_response_code(500);
    die('Falla interna de base de datos: ' . $e->getMessage());
}
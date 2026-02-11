<?php
/**
 * API: VISUALIZADOR DE COMPROBANTES BINARIOS
 * ---------------------------------------------------------
 * Propósito: Sirve archivos de imagen o PDF almacenados como datos binarios (BYTEA) 
 * en la base de datos. Se utiliza para que los administradores validen los pagos de los clientes.
 * 
 * Lógica de Despacho:
 * 1. Validación de Privilegios: Solo accesible por usuarios con rol 'admin'.
 * 2. Mapeo Dinámico de MIME: Transforma extensiones (jpg, png, pdf, etc.) en headers Content-Type.
 * 3. Procesamiento de Flujo: Maneja el contenido ya sea como recurso de stream o cadena hexadecimal (Postgres).
 * 4. Integridad de Salida: Limpia buffers previos para asegurar que el archivo no se corrompa con caracteres extra.
 */

require_once '../config.php';
require_once '../utils/security_utils.php';

// Verificación de integridad de la infraestructura
if (!isset($pdo)) {
    http_response_code(500);
    die('Error crítico: El motor de base de datos no está disponible');
}

/**
 * ==========================================
 * 🛡️ 1. BARRERA DE ACCESO ADMINISTRATIVO
 * ==========================================
 */
requireRole('admin');

$id_orden = $_GET['id_orden'] ?? null;
if (!$id_orden) {
    http_response_code(400);
    die('Solicitud Inválida: ID de orden ausente');
}

try {
    /**
     * ==========================================
     * 🔍 2. EXTRACCIÓN DE DATOS BINARIOS
     * ==========================================
     */
    $stmt = $pdo->prepare("SELECT comprobante_archivo, comprobante_extension FROM tab_Pagos WHERE id_orden = ?");
    $stmt->execute([$id_orden]);
    $pago = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pago && !empty($pago['comprobante_archivo'])) {

        // 3. IDENTIFICACIÓN DEL TIPO DE ARCHIVO
        $ext = strtolower($pago['comprobante_extension']);
        $mimeType = 'application/octet-stream'; // Valor por defecto seguro

        $map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ];

        if (isset($map[$ext])) {
            $mimeType = $map[$ext];
        }

        /**
         * 4. NORMALIZACIÓN DE CONTENIDO (POSTGRES / PDO)
         * El driver PDO puede devolver el campo BYTEA como un stream (resource) 
         * o como un string (a veces con prefijo \x si es hexadecimal).
         */
        $contenido = $pago['comprobante_archivo'];

        // Si es un stream, extraemos todo su contenido a memoria
        if (is_resource($contenido)) {
            $contenido = stream_get_contents($contenido);
        }

        // Si es un string hexadecimal de escape de Postgres, lo convertimos a binario real
        if (strpos($contenido, '\\x') === 0) {
            $contenido = hex2bin(substr($contenido, 2));
        }

        /**
         * ==========================================
         * 🚀 5. DESPACHO DEL ARCHIVO
         * ==========================================
         */
        header("Content-Type: $mimeType");
        header("Content-Length: " . strlen($contenido));
        header('Cache-Control: private, max-age=3600');

        /**
         * CRÍTICO: LIMPIEZA DE BÚFER
         * ob_clean() elimina cualquier salida accidental (espacios, saltos de línea en archivos PHP) 
         * que corrompería el encabezado binario de la imagen o del PDF.
         */
        if (ob_get_length()) {
            ob_clean();
        }
        flush();

        echo $contenido;
        exit;

    } else {
        http_response_code(404);
        die('Recurso no encontrado: La orden no posee un comprobante de pago cargado');
    }
} catch (PDOException $e) {
    http_response_code(500);
    die('Falla interna de base de datos: ' . $e->getMessage());
}
?>
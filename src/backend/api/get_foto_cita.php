<?php
/**
 * API: VISUALIZADOR DE FOTOS DE CITAS/RESERVAS
 * ---------------------------------------------------------
 * Propósito: Sirve archivos de imagen almacenados como datos binarios (BYTEA)
 * en la base de datos desde las reservas del formulario de contacto.
 * Solo accesible por administradores.
 * 
 * Basado en: get_comprobante.php
 */

require_once '../config.php';
require_once '../utils/security_utils.php';

// Verificación de infraestructura
if (!isset($pdo)) {
    http_response_code(500);
    die('Error crítico: El motor de base de datos no está disponible');
}

/**
 * ==========================================
 * \ud83d\udee1\ufe0f 1. BARRERA DE ACCESO ADMINISTRATIVO
 * ==========================================
 */
requireRole('admin');

$id_reserva = $_GET['id_reserva'] ?? null;
if (!$id_reserva) {
    http_response_code(400);
    die('Solicitud Inválida: ID de reserva ausente');
}

try {
    /**
     * ==========================================
     * \ud83d\udd0d 2. EXTRACCIÓN DE DATOS BINARIOS
     * ==========================================
     */
    $stmt = $pdo->prepare("SELECT foto_adjunto, foto_extension FROM tab_Reservas WHERE id_reserva = ?");
    $stmt->execute([$id_reserva]);
    $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($reserva && !empty($reserva['foto_adjunto'])) {

        // 3. IDENTIFICACIÓN DEL TIPO DE ARCHIVO
        $ext = strtolower($reserva['foto_extension']);
        $mimeType = 'application/octet-stream'; // Valor por defecto seguro

        $map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ];

        if (isset($map[$ext])) {
            $mimeType = $map[$ext];
        }

        /**
         * 4. NORMALIZACIÓN DE CONTENIDO (POSTGRES / PDO)
         * El driver PDO puede devolver el campo BYTEA como un stream (resource)
         * o como un string (a veces con prefijo \\x si es hexadecimal).
         */
        $contenido = $reserva['foto_adjunto'];

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
         * \ud83d\ude80 5. DESPACHO DEL ARCHIVO
         * ==========================================
         */
        header("Content-Type: $mimeType");
        header("Content-Length: " . strlen($contenido));
        header('Cache-Control: private, max-age=3600');

        /**
         * CRÍTICO: LIMPIEZA DE BÚFER
         * ob_clean() elimina cualquier salida accidental (espacios, saltos de línea en archivos PHP)
         * que corrompería el encabezado binario de la imagen.
         */
        if (ob_get_length()) {
            ob_clean();
        }
        flush();

        echo $contenido;
        exit;

    } else {
        http_response_code(404);
        die('Recurso no encontrado: La reserva no posee una foto adjunta');
    }
} catch (PDOException $e) {
    http_response_code(500);
    die('Falla interna de base de datos: ' . $e->getMessage());
}
?>
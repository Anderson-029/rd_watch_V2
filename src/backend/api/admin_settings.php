<?php
/**
 * API DE ADMINISTRACIÓN: AJUSTES GLOBALES
 * ---------------------------------------------------------
 * Gestiona configuraciones generales del sitio como el 
 * nombre de la tienda, moneda, y otros parámetros de administración.
 */

header('Content-Type: application/json');
require_once '../config.php';

// Verificación de la conexión a la base de datos
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de configuración de BD']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        /**
         * OBTENER AJUSTES
         * Retorna la configuración actual. Actualmente usa valores 
         * estáticos para compatibilidad con el frontend.
         */
        echo json_encode([
            'ok' => true,
            'store' => ['nombre' => 'RD-Watch', 'moneda' => 'USD'],
            'admin' => ['usuario' => 'admin']
        ]);
    } else {
        /**
         * ACTUALIZAR AJUSTES (POST/PUT)
         * Simulación de éxito para persistencia de configuración.
         */
        echo json_encode(['ok' => true, 'msg' => 'Configuración actualizada correctamente (Simulado)']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
}

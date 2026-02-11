<?php
/**
 * API DE ADMINISTRACIÓN: AJUSTES GLOBALES DEL SITIO
 * ---------------------------------------------------------
 * Propósito: Centraliza la gestión de los parámetros generales de la plataforma 
 * (Nombre de la marca, Moneda de operación, datos de contacto administrativo).
 * 
 * Estado de Implementación:
 * Actualmente utiliza una estructura de 'mock' (respuesta estática) para proveer 
 * consistencia visual al panel de administración mientras se desarrolla el 
 * módulo de persistencia de configuración en base de datos.
 */

header('Content-Type: application/json');
require_once '../config.php';
require_once '../utils/security_utils.php';

// 🛡️ BARRERA DE ACCESO ADMINISTRATIVO
requireRole('admin');

// Verificación de integridad operativa
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error técnico: La conexión a la base de datos no es estable']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            /**
             * ==========================================
             * 🔍 OBTENER AJUSTES (GET)
             * ==========================================
             * Lógica: Retorna la configuración de identidad de la marca.
             */
            echo json_encode([
                'ok' => true,
                'store' => [
                    'nombre' => 'RD-Watch',
                    'moneda' => 'USD',
                    'version' => '2.0.0-backend-doc'
                ],
                'admin' => [
                    'usuario' => 'admin_central',
                    'rol' => 'SuperAdmin'
                ]
            ]);
            break;

        case 'POST':
        case 'PUT':
            /**
             * ==========================================
             * 🔄 ACTUALIZAR AJUSTES (WRITE)
             * ==========================================
             * 🛡️ PROTECCIÓN CSRF ACTIVADA
             */
            validateCsrfToken();
            echo json_encode(['ok' => true, 'msg' => 'Configuración administrativa actualizada correctamente en caché (Simulado)']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['ok' => false, 'msg' => 'Método no soportado para ajustes globales']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error crítico de comunicación: ' . $e->getMessage()]);
}
